<?php
defined('RMS') or die('Direct access not permitted');

// ── Session helpers ──────────────────────────────────────────

function auth_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('rms_sess');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
        session_start();
    }
}

function current_user(): ?array {
    auth_start();
    return $_SESSION['user'] ?? null;
}

function current_user_id(): ?int {
    return current_user()['id'] ?? null;
}

function is_logged_in(): bool {
    $user = current_user();
    return $user !== null && ($user['2fa_ok'] ?? false);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /auth/login');
        exit;
    }
}

function require_role(string ...$roles): void {
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        include views_path('errors/403.php');
        exit;
    }
}

// ── Login flow ───────────────────────────────────────────────

function auth_attempt(string $email, string $password): array {
    $ip = client_ip();

    if (is_locked_out($email, $ip)) {
        audit('login_blocked', null, null, ['email' => $email]);
        return ['status' => 'locked'];
    }

    $user = db_row(
        'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
        [strtolower(trim($email))]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_attempt($email, $ip, false);
        return ['status' => 'invalid'];
    }

    if (!$user['is_active']) {
        return ['status' => 'inactive'];
    }

    record_attempt($email, $ip, true);

    $policy = auth_policy($user['role']);
    // 2FA fires when the user's role requires it, when this individual account
    // has 2FA switched on, or when it's a new/untrusted device on a role that
    // enforces that. Every policy field is coalesced so a role with no
    // security_policies row still behaves sanely (per-user 2FA keeps working).
    $needs_2fa = !empty($policy['require_2fa'])
        || !empty($user['require_2fa'])
        || (!empty($policy['force_2fa_new_device']) && !is_trusted_device($user['id']));

    if ($needs_2fa) {
        $_SESSION['pending_user_id'] = $user['id'];
        $channels = !empty($policy['allowed_2fa_channels'])
            ? order_channels(explode(',', $policy['allowed_2fa_channels']))
            : ['email'];
        $channels = available_2fa_channels($channels);
        return ['status' => '2fa_required', 'channels' => $channels];
    }

    auth_grant_session($user);
    return ['status' => 'ok', 'user' => $user];
}

/**
 * Canonical ordering for notification / 2FA channels: Email, then SMS, then
 * WhatsApp. Used everywhere channels are listed so the order is consistent
 * (the DB SET column returns them in its own definition order otherwise).
 */
function order_channels(array $channels): array {
    $rank = ['email' => 0, 'sms' => 1, 'whatsapp' => 2];
    usort($channels, fn($a, $b) => ($rank[$a] ?? 99) <=> ($rank[$b] ?? 99));
    return $channels;
}

/**
 * Channels switched on app-wide in Administracija → Sistem.
 *
 * The per-role policy says what a role *may* use; this says what is actually
 * wired up on this installation. WhatsApp ships off because it needs a Meta
 * Cloud API account that most installs don't have.
 */
function enabled_2fa_channels(): array {
    $on = [];
    foreach (['email', 'sms', 'whatsapp'] as $c) {
        $default = $c === 'whatsapp' ? '0' : '1';
        if (setting("twofa_{$c}_enabled", $default) === '1') $on[] = $c;
    }
    return $on;
}

/**
 * Narrow a role's allowed channels to those actually switched on.
 *
 * Falls back to email if the intersection is empty: an admin who turns
 * everything off must not lock every 2FA user out of the system.
 */
function available_2fa_channels(array $allowed): array {
    $channels = array_values(array_intersect($allowed, enabled_2fa_channels()));
    return $channels ?: ['email'];
}

// ── 2FA ──────────────────────────────────────────────────────

function otp_send(int $user_id, string $channel): bool {
    $user = db_row('SELECT * FROM users WHERE id = ? LIMIT 1', [$user_id]);
    if (!$user) return false;

    db()->prepare('DELETE FROM otp_codes WHERE user_id = ? AND used = 0')->execute([$user_id]);

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db_insert('otp_codes', [
        'user_id'    => $user_id,
        'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
        'channel'    => $channel,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
    ]);

    return match($channel) {
        'email'     => otp_send_email($user, $code),
        'whatsapp'  => otp_send_whatsapp($user, $code),
        'sms'       => otp_send_sms($user, $code),
        default     => false,
    };
}

function otp_verify(int $user_id, string $code, bool $trust_device = false): array {
    $row = db_row(
        'SELECT * FROM otp_codes
         WHERE user_id = ? AND used = 0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1',
        [$user_id]
    );

    if (!$row) return ['status' => 'expired'];

    if ($row['attempts'] >= 3) {
        db_update('otp_codes', ['used' => 1], 'id = ?', [$row['id']]);
        return ['status' => 'exhausted'];
    }

    db_update('otp_codes', ['attempts' => $row['attempts'] + 1], 'id = ?', [$row['id']]);

    if (!password_verify($code, $row['code_hash'])) {
        return ['status' => 'invalid', 'attempts_left' => 3 - $row['attempts'] - 1];
    }

    db_update('otp_codes', ['used' => 1], 'id = ?', [$row['id']]);

    $user = db_row('SELECT * FROM users WHERE id = ? LIMIT 1', [$user_id]);
    auth_grant_session($user);

    if ($trust_device) trust_device($user_id);

    return ['status' => 'ok'];
}

// ── Session grant ────────────────────────────────────────────

function auth_grant_session(array $user): void {
    session_regenerate_id(true);

    $location_ids = null;
    // Admin (non-Super) is scoped to a subset of locations via admin_profiles;
    // Super Admin has access to all locations (handled in permissions helper).
    if (($user['role'] ?? '') === 'admin') {
        $profile = db_row('SELECT location_ids FROM admin_profiles WHERE user_id = ?', [$user['id']]);
        $location_ids = $profile ? json_decode($profile['location_ids'], true) : null;
    }

    $_SESSION['user'] = [
        'id'           => $user['id'],
        'name'         => $user['name'],
        'email'        => $user['email'],
        'role'         => $user['role'],
        'location_id'  => $user['location_id'],
        'location_ids' => $location_ids,
        'lang'         => $user['lang'],
        'theme'        => $user['theme'],
        '2fa_ok'       => true,
    ];

    db_update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

    audit('login_success');
}

function auth_logout(): void {
    // Grab the role BEFORE destroying the session so partners land on the
    // portal login rather than the staff login after signing out.
    $user = current_user();
    $is_partner = $user && ($user['role'] ?? '') === 'partner';

    audit('logout');
    session_destroy();
    header('Location: ' . ($is_partner ? '/portal/login' : '/auth/login'));
    exit;
}

// ── Lockout ──────────────────────────────────────────────────

function is_locked_out(string $identifier, string $ip): bool {
    $policy = auth_policy_by_identifier($identifier);
    $max    = $policy['max_login_attempts'] ?? 5;
    $window = date('Y-m-d H:i:s', strtotime('-' . ($policy['lockout_minutes'] ?? 30) . ' minutes'));

    $fails = (int) db_val(
        'SELECT COUNT(*) FROM auth_attempts
         WHERE (identifier = ? OR ip_address = ?) AND success = 0 AND created_at >= ?',
        [$identifier, $ip, $window]
    );
    return $fails >= $max;
}

function record_attempt(string $identifier, string $ip, bool $success): void {
    db_insert('auth_attempts', [
        'identifier' => $identifier,
        'ip_address' => $ip,
        'success'    => (int) $success,
    ]);
}

// ── Trusted devices ──────────────────────────────────────────
//
// Identity of a "trusted device" is a random secret stored in a signed
// httponly cookie (rms_device). The DB stores only a sha256 hash of that
// secret, so a stolen DB dump doesn't let an attacker mint valid cookies.
// The previous design used hash(UA + Accept-Language) which any attacker
// who could inspect the victim's headers could trivially reproduce.

const DEVICE_COOKIE_NAME = 'rms_device';
const DEVICE_COOKIE_TTL  = 60 * 60 * 24 * 90; // 90 days

function device_cookie_value(): ?string {
    $v = $_COOKIE[DEVICE_COOKIE_NAME] ?? null;
    if (!is_string($v) || !preg_match('/^[a-f0-9]{64}$/', $v)) return null;
    return $v;
}

function device_fingerprint(): ?string {
    $cookie = device_cookie_value();
    return $cookie === null ? null : hash('sha256', $cookie);
}

function is_trusted_device(int $user_id): bool {
    $hash = device_fingerprint();
    if ($hash === null) return false;
    db_update('trusted_devices', ['last_seen' => date('Y-m-d H:i:s')], 'user_id = ? AND device_hash = ?', [$user_id, $hash]);
    return (bool) db_val('SELECT COUNT(*) FROM trusted_devices WHERE user_id = ? AND device_hash = ?', [$user_id, $hash]);
}

function trust_device(int $user_id): void {
    // Reuse an existing cookie if the browser already has one; otherwise
    // mint a fresh secret.
    $cookie = device_cookie_value();
    if ($cookie === null) {
        $cookie = bin2hex(random_bytes(32));
        $secure = !empty($_SERVER['HTTPS']);
        setcookie(DEVICE_COOKIE_NAME, $cookie, [
            'expires'  => time() + DEVICE_COOKIE_TTL,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[DEVICE_COOKIE_NAME] = $cookie; // reflect for this request
    }
    $hash = hash('sha256', $cookie);

    if (!(bool) db_val('SELECT COUNT(*) FROM trusted_devices WHERE user_id = ? AND device_hash = ?', [$user_id, $hash])) {
        db_insert('trusted_devices', [
            'user_id'    => $user_id,
            'device_hash'=> $hash,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => client_ip(),
        ]);
    }
}

// ── Helpers ──────────────────────────────────────────────────

function auth_policy(string $role): array {
    return db_row('SELECT * FROM security_policies WHERE role = ?', [$role]) ?? [];
}

function auth_policy_by_identifier(string $email): array {
    $user = db_row('SELECT role FROM users WHERE email = ? LIMIT 1', [$email]);
    return $user ? auth_policy($user['role']) : [];
}

function client_ip(): string {
    // TCP-level address is the only one we can trust by default.
    // X-Forwarded-For is client-controlled and must only be read when
    // the request comes from a proxy we've explicitly whitelisted via the
    // TRUSTED_PROXIES constant (set in config/db.php or similar).
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $trusted = defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : [];
    if (!empty($trusted) && in_array($remote, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $xff = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        foreach ($xff as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $remote;
}

// ── OTP delivery ─────────────────────────────────────────────

function otp_send_email(array $user, string $code): bool {
    $subject = __('email.otp_subject');
    $name    = htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $safe    = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    // Styled to match the login screen: same logo, same 40px height, centred.
    //
    // The logo is EMBEDDED (cid:), not linked. Mail clients block remote images
    // by default and none of them render SVG, and this app is not reachable
    // from the internet anyway, so a URL would show a broken image.
    //
    // Montserrat is named first with web-safe fallbacks. Mail clients cannot
    // load web fonts, so recipients who don't have it installed fall back
    // gracefully rather than getting a serif default.
    $font = "'Montserrat',-apple-system,'Segoe UI',Arial,sans-serif";
    $logo = dirname(__DIR__) . '/assets/integra-email.png';

    $html = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"></head>"
          . "<body style=\"margin:0;padding:24px;background:#f4f4f0;font-family:{$font};\">"
          . "<div style=\"max-width:480px;margin:0 auto;background:#fff;border:0.5px solid #d3d1c7;"
          . "border-radius:12px;padding:32px;\">"
          . "<div style=\"text-align:center;margin-bottom:28px;\">"
          . "<img src=\"cid:integralogo\" alt=\"Integra\" height=\"40\" "
          . "style=\"height:40px;width:auto;display:inline-block;border:0;\">"
          . "</div>"
          . "<p style=\"font-size:15px;color:#5f5e5a;margin:0 0 16px;font-family:{$font};\">"
          . __('email.otp_greeting', ['name' => $name ?: '']) . "</p>"
          . "<p style=\"font-size:15px;color:#1a1a18;margin:0 0 8px;font-family:{$font};\">"
          . __('email.otp_intro') . "</p>"
          . "<p style=\"font-size:32px;font-weight:700;letter-spacing:6px;color:#1D9E75;"
          . "margin:16px 0;text-align:center;font-family:{$font};\">{$safe}</p>"
          . "<p style=\"font-size:13px;color:#888780;margin:16px 0 0;font-family:{$font};\">"
          . __('email.otp_expiry') . "</p>"
          . "</div></body></html>";

    $attachments = is_readable($logo)
        ? [['path' => $logo, 'cid' => 'integralogo', 'name' => 'integra.png']]
        : [];   // still send the code if the logo is ever missing

    return send_email($user['email'], $user['name'] ?? '', $subject, $html, $attachments);
}

function otp_send_whatsapp(array $user, string $code): bool {
    $phone = $user['phone'] ?? '';
    if ($phone === '') return false;
    return whatsapp_send_otp($phone, $code);
}

function otp_send_sms(array $user, string $code): bool {
    $phone = $user['phone'] ?? '';
    if ($phone === '') return false;
    $text = "Integra RMA: your verification code is {$code}. It expires in 10 minutes.";
    return sms_send($phone, $text);
}
