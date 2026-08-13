<?php
defined('RMS') or die('Direct access not permitted');

/**
 * SMS dispatcher. Reads the `sms_provider` setting and routes to the
 * configured backend. Returns true on success, false on any failure.
 *
 * Providers:
 *   - mtel       : m:tel Montenegro (local gateway, signed GET)
 *   - twilio     : Twilio REST API (https://api.twilio.com)
 *   - clickatell : Clickatell Platform (https://platform.clickatell.com)
 */
function sms_send(string $to, string $text): bool {
    return sms_send_result($to, $text)['ok'];
}

/**
 * Same as sms_send() but returns ['ok' => bool, 'error' => string] so the
 * settings test button can show the gateway's actual reason for refusing.
 */
function sms_send_result(string $to, string $text): array {
    $provider = setting('sms_provider', '');
    if ($provider === '') return ['ok' => false, 'error' => __('settings.sms_not_configured')];

    // Normalise the phone number to E.164-ish (digits + optional leading +)
    $to = preg_replace('/[^\d+]/', '', $to);
    if ($to === '') return ['ok' => false, 'error' => __('settings.sms_no_number')];

    return match ($provider) {
        'mtel'       => sms_send_mtel($to, $text),
        'twilio'     => ['ok' => sms_send_twilio($to, $text),     'error' => ''],
        'clickatell' => ['ok' => sms_send_clickatell($to, $text), 'error' => ''],
        default      => ['ok' => false, 'error' => __('settings.sms_not_configured')],
    };
}

/**
 * m:tel Montenegro gateway.
 *
 * Signed GET request. The signature is a SHA-256 of the request fields with a
 * shared secret spliced in between the recipient and the message body:
 *     upper(sha256(transid + prema + SECRET + poruka + user))
 *
 * The gateway always answers HTTP 200 — success or failure is the plain-text
 * body, a status number where "0" means the message was accepted. So the body
 * has to be parsed; the HTTP code says nothing.
 *
 * Note: m:tel restricts requests to registered source IPs (status 7), so the
 * server's public IP must be whitelisted with them before this works.
 */
function sms_send_mtel(string $to, string $text): array {
    $url    = setting('sms_mtel_url', '');
    $user   = setting('sms_mtel_user', '');
    $secret = setting('sms_mtel_secret', '');
    if ($url === '' || $user === '' || $secret === '') {
        return ['ok' => false, 'error' => __('settings.sms_not_configured')];
    }

    // m:tel wants bare digits in 3826XXXXXXX form — no plus, no spaces.
    // Montenegrin mobiles are exactly 382 + 8 digits starting with 6. Checking
    // the exact length here catches typos locally; sending a number that is one
    // digit short or long makes the gateway fail to match an operator and
    // answer with a misleading status (e.g. 4 "not a Montenegrin network").
    $prema = ltrim(normalize_phone($to), '+');
    if (!preg_match('/^3826\d{7}$/', $prema)) {
        return ['ok' => false, 'error' => __('settings.sms_bad_number', ['number' => $prema])];
    }

    // Gateway limit is 256 characters.
    $poruka  = mb_substr($text, 0, 256);
    $transid = date('YmdHis') . bin2hex(random_bytes(4));   // unique, <= 100 chars
    $sha     = strtoupper(hash('sha256', $transid . $prema . $secret . $poruka . $user));

    $full = $url . (str_contains($url, '?') ? '&' : '?') . http_build_query([
        'user'    => $user,
        'prema'   => $prema,
        'transid' => $transid,
        'sha'     => $sha,
        'poruka'  => $poruka,
    ]);

    [$ok, $resp] = http_get($full);
    if (!$ok) {
        error_log('m:tel SMS transport error: ' . $resp);
        return ['ok' => false, 'error' => $resp];
    }

    $code = trim($resp);
    if ($code === '0') return ['ok' => true, 'error' => ''];

    $reason = mtel_status_message($code);
    error_log("m:tel SMS refused (status {$code}): {$reason}");
    return ['ok' => false, 'error' => "[{$code}] {$reason}"];
}

/** Human-readable text for an m:tel status code. */
function mtel_status_message(string $code): string {
    $keys = [
        '0'  => 'settings.mtel_0',  '1'  => 'settings.mtel_1',
        '2'  => 'settings.mtel_2',  '3'  => 'settings.mtel_3',
        '4'  => 'settings.mtel_4',  '5'  => 'settings.mtel_5',
        '6'  => 'settings.mtel_6',  '7'  => 'settings.mtel_7',
        '8'  => 'settings.mtel_8',  '9'  => 'settings.mtel_9',
        '10' => 'settings.mtel_10',
    ];
    return isset($keys[$code]) ? __($keys[$code]) : __('settings.mtel_unknown', ['code' => $code]);
}

function sms_send_twilio(string $to, string $text): bool {
    $sid   = setting('sms_twilio_sid', '');
    $token = setting('sms_twilio_token', '');
    $from  = setting('sms_twilio_from', '');
    if ($sid === '' || $token === '' || $from === '') return false;

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
    $body = http_build_query(['From' => $from, 'To' => $to, 'Body' => $text]);

    [$ok, $resp] = http_post($url, $body, [
        'Authorization: Basic ' . base64_encode($sid . ':' . $token),
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    if (!$ok) {
        error_log('Twilio SMS failed: ' . $resp);
        return false;
    }
    return true;
}

function sms_send_clickatell(string $to, string $text): bool {
    $apikey = setting('sms_clickatell_apikey', '');
    $from   = setting('sms_clickatell_from', '');
    if ($apikey === '') return false;

    $payload = ['messages' => [[
        'channel' => 'sms',
        'to'      => ltrim($to, '+'),
        'content' => $text,
    ]]];
    if ($from !== '') $payload['messages'][0]['from'] = $from;

    [$ok, $resp] = http_post(
        'https://platform.clickatell.com/v1/message',
        json_encode($payload),
        [
            'Authorization: ' . $apikey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]
    );
    if (!$ok) {
        error_log('Clickatell SMS failed: ' . $resp);
        return false;
    }
    return true;
}

/**
 * Minimal HTTP GET helper, for gateways that take query-string requests.
 * Returns [transport-ok, response-body-or-error]. A `true` here only means the
 * request reached the server — the body still has to be inspected.
 */
function http_get(string $url, int $timeout = 10): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) return [false, $err ?: 'request failed'];
        if ($code < 200 || $code >= 300) return [false, "HTTP {$code}: " . substr((string) $resp, 0, 200)];
        return [true, (string) $resp];
    }

    $ctx  = stream_context_create(['http' => ['method' => 'GET', 'timeout' => $timeout, 'ignore_errors' => true]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return [false, 'stream error'];
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $status = (int) $m[1]; break; }
    }
    if ($status < 200 || $status >= 300) return [false, "HTTP {$status}"];
    return [true, (string) $resp];
}

/**
 * Minimal HTTP POST helper. Uses cURL if available, falls back to streams.
 * Returns [success, response-or-error-string].
 */
function http_post(string $url, string $body, array $headers = [], int $timeout = 10): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) return [false, $err];
        return [$code >= 200 && $code < 300, (string) $resp];
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $body,
        'timeout'       => $timeout,
        'ignore_errors' => true,
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return [false, 'stream error'];
    // $http_response_header is populated on success
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $status = (int)$m[1]; break; }
    }
    return [$status >= 200 && $status < 300, (string) $resp];
}

// ── RMA notifications ────────────────────────────────────────

/**
 * Who gets a text about this RMA.
 *
 * The email counterpart, rma_receipt_recipients(), notifies the partner and
 * — depending on their switch — the end user as well. SMS is deliberately
 * narrower: partners are contacted by email, not text, so this only ever
 * returns the end user.
 *
 * The partner switch still governs. A partner set as the single point of
 * contact with their own customer means that customer hears nothing from us
 * at all, by any channel; the partner has already been emailed.
 *
 * A walk-in has no partner and is simply the customer.
 */
function rma_sms_recipients(array $rma): array {
    if (!empty($rma['partner_id']) && (int) ($rma['partner_notify_customer'] ?? 1) === 0) {
        return [];
    }
    $phone = normalize_phone(trim((string) ($rma['customer_phone'] ?? '')));
    // Six digits is the shortest thing that could be a subscriber number; below
    // that it is a typo or a placeholder, and a gateway charges either way.
    return strlen(preg_replace('/\D/', '', $phone)) >= 6 ? [$phone] : [];
}

/**
 * Text the customer that we have their device, with the tracking link.
 *
 * Off unless Podesavanja turns it on, because every message costs money and
 * this app is still being tested against real phone numbers. Turning it on is
 * a decision with a bill attached, so it is not one a deploy should make.
 */
function send_rma_sms(int $rma_id): bool {
    if (setting('rma_sms_enabled', '0') !== '1') return false;

    $rma = db_row("SELECT r.rma_number, r.partner_id,
                          c.phone as customer_phone, c.lang as customer_lang,
                          pa.notify_customer as partner_notify_customer
                   FROM rma_requests r
                   LEFT JOIN customers c ON c.id = r.customer_id
                   LEFT JOIN partners pa ON pa.id = r.partner_id
                   WHERE r.id = ?", [$rma_id]);
    if (!$rma) return false;

    $to = rma_sms_recipients($rma);
    if (!$to) return false;

    $token = db_val('SELECT token FROM rma_tracking_tokens WHERE rma_id = ?', [$rma_id]);
    if (!$token) return false;

    // The customer's own language, like every other thing they receive.
    $lang = customer_lang($rma['customer_lang'] ?? null);
    $text = __in($lang, 'sms.rma_received', [
        'number' => $rma['rma_number'],
        'url'    => 'https://rma.integra.mn/track/' . $token,
    ]);

    $sent = false;
    foreach ($to as $number) {
        if (sms_send($number, $text)) $sent = true;
    }
    return $sent;
}
