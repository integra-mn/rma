<?php
defined('RMS') or die('Direct access not permitted');

/**
 * HTTP client for Apple GSX.
 *
 * ─────────────────────────────────────────────────────────────────────────
 *  NDA NOTE
 *  --------
 *  Apple's GSX API specification is covered by NDA for Apple Authorized
 *  Service Providers. The real endpoint paths, authentication specifics,
 *  and request/response schemas are NOT embedded in this file.
 *
 *  The functions below are stubs that:
 *    (a) have the right signatures for the rest of the app, so the UI
 *        and data flow can be built and tested end-to-end with mocks, and
 *    (b) contain TODO markers showing exactly where to drop in the real
 *        HTTP calls once your AASP contact shares the documentation.
 *
 *  To wire a real call you typically only need to:
 *    1. Know the real endpoint path under the configured base URL.
 *    2. Know the correct auth (client-cert + bearer, per Apple).
 *    3. Map the response JSON to the normalised shape documented below.
 * ─────────────────────────────────────────────────────────────────────────
 */
class GsxClient {

    private string $base_url;
    private string $sold_to;
    private string $ship_to;
    private string $auth_token;
    private ?string $cert_path;
    private ?string $key_path;
    private int    $timeout;

    public function __construct(array $config) {
        $creds = json_decode($config['credentials'] ?? '{}', true) ?: [];
        $this->base_url   = rtrim($config['endpoint_url'] ?? '', '/');
        $this->sold_to    = (string)($creds['sold_to']    ?? '');
        $this->ship_to    = (string)($creds['ship_to']    ?? '');
        $this->auth_token = (string)($creds['auth_token'] ?? '');
        $this->cert_path  = $creds['cert_path'] ?? null;
        $this->key_path   = $creds['key_path']  ?? null;
        $this->timeout    = (int)($creds['timeout_seconds'] ?? 15);
    }

    /**
     * Device warranty / coverage lookup by IMEI or serial.
     *
     * Returns: [
     *   'ok'      => bool,
     *   'message' => string|null,
     *   'data'    => [
     *     'coverage_status'   => 'covered'|'not_covered'|'expired'|'unknown',
     *     'coverage_label'    => string|null,   // "Limited Warranty", "AppleCare+", …
     *     'coverage_expires'  => YYYY-MM-DD|null,
     *     'product_desc'      => string|null,   // "iPhone 15 Pro Max 256GB Natural Titanium"
     *     'purchase_date'     => YYYY-MM-DD|null,
     *     'activation_locked' => bool|null,
     *     'find_my_active'    => bool|null,
     *     'raw'               => mixed,         // untouched vendor payload for audit
     *   ],
     * ]
     */
    public function deviceWarranty(string $identifier): array {
        if (!$this->is_configured()) {
            return $this->mock_warranty($identifier, 'Adapter not configured');
        }
        // TODO: replace with real GSX warranty-status endpoint call.
        //
        //   $http = $this->http('POST', '/api/device/warranty', [
        //       'deviceId' => $identifier,
        //       'soldTo'   => $this->sold_to,
        //   ]);
        //
        // Then map $http['body'] to the normalised shape shown in the
        // docblock above. Store the raw body under data.raw for audit.
        return $this->mock_warranty($identifier, 'Real endpoint not wired yet');
    }

    /** Submit a new repair/RMA to GSX. */
    public function createRepair(array $rma): array {
        if (!$this->is_configured()) {
            return ['ok' => false, 'message' => 'Adapter not configured', 'data' => []];
        }
        // TODO: wire real POST /repairs endpoint here.
        return ['ok' => false, 'message' => 'createRepair not implemented yet', 'data' => []];
    }

    /** Current status of a previously-submitted GSX repair. */
    public function repairStatus(string $vendor_ref): array {
        if (!$this->is_configured()) {
            return ['ok' => false, 'message' => 'Adapter not configured', 'data' => []];
        }
        // TODO: wire real GET /repairs/{vendor_ref} endpoint here.
        return ['ok' => false, 'message' => 'repairStatus not implemented yet', 'data' => []];
    }

    /** Cheap round-trip for the "Test connection" button in admin UI. */
    public function ping(): array {
        if (!$this->is_configured()) {
            return ['ok' => false, 'message' => 'Base URL, auth token, or client cert missing'];
        }
        // TODO: swap for a real lightweight GSX endpoint (e.g. token info).
        return ['ok' => true, 'message' => 'Configured (mock, no live endpoint wired)'];
    }

    // ── Internals ─────────────────────────────────────────────────────────

    private function is_configured(): bool {
        return $this->base_url !== '' && $this->auth_token !== '';
    }

    /**
     * Low-level HTTP wrapper the real endpoint methods will call. Handles:
     *  - client-certificate mTLS (Apple requires this for API access)
     *  - bearer token
     *  - timeout + audit logging (via vendor_sync_log, set by the caller)
     */
    private function http(string $method, string $path, array $body = []): array {
        $ch = curl_init();
        $url = $this->base_url . $path;

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->auth_token,
                'Content-Type: application/json',
                'X-Apple-SoldTo: ' . $this->sold_to,
            ],
        ]);
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        if ($this->cert_path && is_readable($this->cert_path)) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->cert_path);
            if ($this->key_path) curl_setopt($ch, CURLOPT_SSLKEY, $this->key_path);
        }

        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        return [
            'ok'     => $err === '' && $status >= 200 && $status < 300,
            'status' => $status,
            'body'   => $resp !== false ? json_decode($resp, true) : null,
            'error'  => $err ?: null,
        ];
    }

    /**
     * Mock response so the UI flow works without a live GSX connection.
     * Generates deterministic fake data from the identifier so the same
     * IMEI returns the same fake result each time — easier to eyeball.
     */
    private function mock_warranty(string $identifier, string $why): array {
        $hash  = crc32($identifier);
        $buckets = ['covered', 'covered', 'expired', 'not_covered']; // weighted
        $status = $buckets[$hash % count($buckets)];
        $purchase = date('Y-m-d', strtotime('-' . (($hash % 720) + 30) . ' days'));
        $expires  = date('Y-m-d', strtotime($purchase . ' +2 years'));

        return [
            'ok'      => true,
            'message' => 'MOCK: ' . $why,
            'data' => [
                'coverage_status'   => $status,
                'coverage_label'    => match ($status) {
                    'covered'     => 'AppleCare+',
                    'expired'     => 'Limited Warranty (expired)',
                    'not_covered' => 'Out of warranty',
                    default       => null,
                },
                'coverage_expires'  => $status === 'covered' ? $expires : null,
                'product_desc'      => 'iPhone (mock: ' . substr($identifier, -4) . ')',
                'purchase_date'     => $purchase,
                'activation_locked' => ($hash & 1) === 0,
                'find_my_active'    => ($hash & 1) === 0,
                'raw'               => ['mock' => true, 'identifier' => $identifier],
            ],
        ];
    }
}
