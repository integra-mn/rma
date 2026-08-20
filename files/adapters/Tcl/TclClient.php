<?php
defined('RMS') or die('Direct access not permitted');

/**
 * HTTP client for TCL Smart Care (SCS Dynamic).
 *
 * Built from TCL's own specifications — "SCS Dynamic API – LOGIN" and
 * "SCS Dynamic API - IMEI Query EU" — which live embedded inside
 * API/TCL/Smart Care System Dynamics Launch Booklet_2025_V1.0.docx. That
 * folder is git-ignored: the booklet carries shared TCL logins in plain text.
 *
 * Authentication is two calls, not one:
 *   1. POST /api/login/rsa    with the plain password -> RSA-encrypted password
 *   2. POST /api/Login/Login  with the encrypted one  -> a ticket
 *
 * The ticket lasts 24 hours "or until next login", and TCL asks explicitly
 * that callers do not log in again before it expires. So it is cached in the
 * settings table rather than fetched per lookup — a busy morning at the
 * counter would otherwise log in dozens of times and invalidate its own
 * ticket each time.
 *
 * ── One thing the documents do not say ──────────────────────────────────
 * How the ticket is presented to a business API. The login spec says only
 * that it "is used for authorization of business APIs"; no spec shows a
 * header, and no request sample carries one. Rather than guess in silence,
 * the header name is configurable (`ticket_header`, default "Ticket") and
 * every failed call records the whole exchange in vendor_sync_log, so the
 * first live attempt tells us what TCL actually wants. If it turns out to be
 * a query parameter instead, `ticket_in_query` switches to that.
 * ─────────────────────────────────────────────────────────────────────────
 */
class TclClient {

    /** Where the ticket is parked between calls. */
    private const TICKET_KEY = 'tcl_ticket';

    private string  $base_url;
    private string  $domain;          // xxx@tcl.com
    private string  $password;
    private string  $ticket_header;
    private bool    $ticket_in_query;
    private int     $timeout;
    private ?string $last_error = null;

    public function __construct(array $config) {
        $creds = json_decode($config['credentials'] ?? '{}', true) ?: [];

        // Production per TCL's spec. UAT is https://uatcsm.tcl.com:5560 —
        // set endpoint_url to that while testing so a trial lookup never
        // touches the live system.
        $this->base_url        = rtrim($config['endpoint_url'] ?: 'https://csm.tclcom.com:5560', '/');
        $this->domain          = (string)($creds['domain_name'] ?? '');
        $this->password        = (string)($creds['password'] ?? '');
        $this->ticket_header   = (string)($creds['ticket_header'] ?? 'Ticket');
        $this->ticket_in_query = !empty($creds['ticket_in_query']);
        $this->timeout         = (int)($creds['timeout_seconds'] ?? 20);
    }

    public function lastError(): ?string { return $this->last_error; }

    public function isConfigured(): bool {
        return $this->domain !== '' && $this->password !== '';
    }

    // ── Warranty ─────────────────────────────────────────────────────────

    /**
     * IMEI Query EU. Accepts up to many identifiers per call; the app asks
     * about one device at a time, so one is what it sends.
     *
     * popDate is the customer's proof-of-purchase date. TCL's manual is
     * explicit that sending it produces a more accurate expiry: without it
     * they fall back to the register date, and without that to the delivery
     * date — which is when the device left TCL, not when it was bought.
     */
    public function imeiQuery(string $imei, ?string $pop_date = null): array {
        $ticket = $this->ticket();
        if ($ticket === null) {
            return ['ok' => false, 'status' => 0, 'body' => null,
                    'error' => $this->last_error ?? 'Login failed'];
        }

        $row = ['imei' => $imei];
        if ($pop_date) $row['popDate'] = $pop_date;

        $res = $this->http('POST', '/api/RepairOrder/QueryIMEIEU', ['imeis' => [$row]], $ticket);

        // A stale ticket is the one failure worth retrying on its own: it
        // expires after 24 hours, and "log in again" is the documented cure.
        if (!$res['ok'] && in_array((int)$res['status'], [401, 403], true)) {
            $ticket = $this->ticket(true);
            if ($ticket !== null) {
                $res = $this->http('POST', '/api/RepairOrder/QueryIMEIEU', ['imeis' => [$row]], $ticket);
            }
        }
        return $res;
    }

    // ── Auth ─────────────────────────────────────────────────────────────

    /** A valid ticket, from cache or by logging in. Null if login fails. */
    private function ticket(bool $force = false): ?string {
        if (!$this->isConfigured()) {
            $this->last_error = 'TCL credentials are not set';
            return null;
        }

        if (!$force) {
            $cached = json_decode((string)setting(self::TICKET_KEY, ''), true);
            // A minute of headroom: a ticket that expires mid-flight is the
            // same as no ticket, and the clock here is not TCL's.
            if (!empty($cached['ticket']) && !empty($cached['expires'])
                && strtotime($cached['expires']) > time() + 60) {
                return $cached['ticket'];
            }
        }

        // Step one: the password goes to TCL in the clear and comes back RSA
        // encrypted. Their design, not ours — which is one more reason the
        // endpoint must stay https and the credentials out of the repo.
        $rsa = $this->http('POST', '/api/login/rsa',
            ['DomainName' => $this->domain, 'Password' => $this->password]);
        $encrypted = $rsa['body']['Password'] ?? null;
        if (!$rsa['ok'] || !$encrypted) {
            $this->last_error = $rsa['error'] ?: 'RSA step failed';
            return null;
        }

        $login = $this->http('POST', '/api/Login/Login',
            ['DomainName' => $this->domain, 'Password' => $encrypted]);
        $body  = $login['body'] ?? [];

        if (!$login['ok'] || (int)($body['ResultCode'] ?? -1) !== 0 || empty($body['Ticket'])) {
            $this->last_error = $body['ResultDesc'] ?? ($login['error'] ?: 'Login failed');
            return null;
        }

        setting_set(self::TICKET_KEY, json_encode([
            'ticket'  => $body['Ticket'],
            'expires' => $body['ExpirationDate'] ?? date('c', time() + 23 * 3600),
            'user_id' => $body['UserId'] ?? null,
        ]), 'string');

        return $body['Ticket'];
    }

    // ── Transport ────────────────────────────────────────────────────────

    private function http(string $method, string $path, array $body = [], ?string $ticket = null): array {
        $url     = $this->base_url . $path;
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        if ($ticket !== null) {
            if ($this->ticket_in_query) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'ticket=' . rawurlencode($ticket);
            } else {
                $headers[] = $this->ticket_header . ': ' . $ticket;
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $resp   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        return [
            'ok'     => $err === '' && $status >= 200 && $status < 300,
            'status' => $status,
            'body'   => $resp !== false ? json_decode($resp, true) : null,
            'error'  => $err ?: null,
        ];
    }
}
