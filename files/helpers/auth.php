<?php
defined('RMS') or die('Direct access not permitted');

// ── Session helpers ──────────────────────────────────────────

/**
 * How long a login lasts, in minutes, from Settings → Sistem.
 *
 * Two clocks, because they answer different questions. Idle is "you walked
 * away"; absolute is "you have been logged in long enough". A counter PC left
 * open all day needs the second one — someone who clicks every ten minutes
 * never trips the first.
 */
function session_idle_minutes(): int {
    return max(5, (int) setting('session_idle_minutes', '120'));
}
function session_absolute_minutes(): int {
    return max(session_idle_minutes(), (int) setting('session_max_minutes', '480'));
}

function auth_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // The app keeps its sessions in its own directory. In the shared
        // /var/lib/php/sessions a system timer (phpsessionclean) deletes files
        // on php.ini's gc_maxlifetime — 24 minutes here — and knows nothing
        // about anything set at runtime. Any timeout configured in Settings
        // would have been quietly overruled by that sweeper.
        $dir = ROOT . '/storage/sessions';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
            // php.ini ships gc_probability=0 because Debian sweeps by cron
            // instead. Nothing sweeps our directory, so PHP has to do it.
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
        ini_set('session.gc_maxlifetime', (string) (session_idle_minutes() * 60));

        session_name('rms_sess');
        // lifetime 0 on purpose: closing the browser ends the session whatever
        // the clocks below say.
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
        session_start();
        session_enforce_timeouts();
    }
}

/**
 * End a session that has gone idle or simply run long.
 *
 * Only touches a session that is actually logged in, so it cannot interfere
 * with a login or a half-finished 2FA.
 */
function session_enforce_timeouts(): void {
    if (empty($_SESSION['user'])) return;

    $now   = time();
    $idle  = session_idle_minutes() * 60;
    $max   = session_absolute_minutes() * 60;
    $seen  = (int) ($_SESSION['last_seen'] ?? $now);
    $start = (int) ($_SESSION['login_at']  ?? $now);

    if (($now - $seen) > $idle || ($now - $start) > $max) {
        $_SESSION = [];
        // Clear the cookie too, or the browser keeps offering a dead id.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', $now - 42000,
                      $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start();                 // a fresh, empty session for the redirect
        $_SESSION['auth_error'] = __('auth.session_expired');
        return;
    }

    $_SESSION['last_seen'] = $now;
    if (!isset($_SESSION['login_at'])) $_SESSION['login_at'] = $now;
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

    // Re-check the network on every request, not just at login: a LAN-only
    // account must not stay usable by carrying the session cookie off-site.
    // Read from the session so this costs nothing; it is refreshed on login,
    // so a change of setting takes effect at the user's next sign-in.
    if ((current_user()['access_scope'] ?? 'any') === 'lan' && !request_is_lan()) {
        audit('session_wrong_network', current_user()['id'] ?? null, null, ['ip' => client_ip_raw()]);
        auth_logout_silent();
        $_SESSION['auth_error'] = __('auth.wrong_network');
        header('Location: /auth/login');
        exit;
    }
}

/**
 * Drop the session without the redirect auth_logout() performs — the caller
 * decides where to send the user.
 */
function auth_logout_silent(): void {
    $_SESSION = [];
    session_regenerate_id(true);
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

    // Per-user network restriction. Checked after the password so an attacker
    // learns nothing extra, but before any session exists.
    if (($user['access_scope'] ?? 'any') === 'lan' && !request_is_lan()) {
        record_attempt($email, $ip, false);
        audit('login_wrong_network', $user['id'], null, ['ip' => client_ip_raw()]);
        return ['status' => 'wrong_network'];
    }

    record_attempt($email, $ip, true);

    $policy = auth_policy($user['role']);
    // 2FA fires when the role requires it, when the individual account has it
    // switched on, or when the role enforces it for new devices. Every policy
    // field is coalesced so a role with no security_policies row still behaves
    // sanely (per-user 2FA keeps working).
    //
    // A trusted device then skips the code — and only the code. The password is
    // always required; nothing here logs anyone in without one.
    //
    // The trusted check used to sit inside the last clause only, so an account
    // or role with require_2fa set never reached it: the box saved the device
    // and the login ignored it.
    $requires_2fa = !empty($policy['require_2fa'])
        || !empty($user['require_2fa'])
        || !empty($policy['force_2fa_new_device']);

    $needs_2fa = $requires_2fa && !is_trusted_device($user['id']);

    if ($needs_2fa) {
        $_SESSION['pending_user_id'] = $user['id'];
        $channels = !empty($policy['allowed_2fa_channels'])
            ? order_channels(explode(',', $policy['allowed_2fa_channels']))
            : ['email'];
        $channels = available_2fa_channels($channels);

        // The authenticator app is only an option for people who have finished
        // enrolling — offering it otherwise would strand them on a screen
        // asking for a code no app can produce.
        if (!totp_is_enrolled($user)) {
            $channels = array_values(array_diff($channels, ['totp']));
        }
        if (!$channels) $channels = ['email'];

        // Carry the user's choice from Moj profil so the 2FA screen can
        // pre-select it instead of always defaulting to email.
        $_SESSION['2fa_preferred'] = $user['preferred_2fa_channel'] ?? null;
        return ['status' => '2fa_required', 'channels' => $channels];
    }

    auth_grant_session($user);
    return ['status' => 'ok', 'user' => $user];
}

/**
 * Canonical ordering for notification / 2FA channels. Used everywhere channels
 * are listed so the order is consistent (the DB SET column returns them in its
 * own definition order otherwise).
 *
 * Authenticator, SMS, Email, WhatsApp — set by Rajo 2026-08-11, replacing the
 * earlier Authenticator/Email/SMS. Roughly fastest-to-hand first: the app needs
 * no signal at all, an SMS arrives on the phone already in your hand, email
 * means finding another window.
 */
function order_channels(array $channels): array {
    $rank = ['totp' => 0, 'sms' => 1, 'email' => 2, 'whatsapp' => 3];
    usort($channels, fn($a, $b) => ($rank[$a] ?? 99) <=> ($rank[$b] ?? 99));
    return $channels;
}

/**
 * The 2FA channels a role is permitted to use, from security_policies.
 *
 * This is the policy — what the role MAY use. available_2fa_channels() then
 * narrows it to what is actually switched on for this installation.
 */
function role_2fa_channels(string $role): array {
    $raw = db_val('SELECT allowed_2fa_channels FROM security_policies WHERE role = ?', [$role]);
    $list = array_filter(array_map('trim', explode(',', (string) $raw)));
    return $list ? order_channels(array_values($list)) : ['email'];
}

/**
 * Channels switched on app-wide in Administracija → Sistem.
 *
 * The per-role policy says what a role *may* use; this says what is actually
 * wired up on this installation. WhatsApp ships off because it needs a Meta
 * Cloud API account that most installs don't have.
 */
function enabled_2fa_channels(): array {
    // TOTP needs no provider or credit, so it is not part of the on/off
    // switches — enrolment alone decides whether a user sees it.
    $on = ['totp'];
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

/**
 * Which channel a login starts on, so nobody has to be asked.
 *
 * The authenticator wins whenever it is available. It only appears at all for
 * someone who finished enrolling, it is the strongest of the four, and it
 * needs nothing sent — so it is right even for a profile that still names
 * email from before they enrolled.
 *
 * Otherwise the channel chosen in Moj profil, provided the role still allows
 * it and it is switched on app-wide; failing that the first available, rather
 * than assuming email exists.
 *
 * Both the login and the chooser screen call this. They used to work it out
 * separately, which is the kind of duplication that quietly drifts apart.
 */
function default_2fa_channel(array $channels, ?string $preferred): string {
    if (in_array('totp', $channels, true)) return 'totp';
    if ($preferred !== null && in_array($preferred, $channels, true)) return $preferred;
    return $channels[0] ?? 'email';
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
        'access_scope' => $user['access_scope'] ?? 'any',
        '2fa_ok'       => true,
    ];

    // Both clocks start here, not on the first page load — a login that sat on
    // the 2FA screen for a while should not eat into the eight hours.
    $_SESSION['login_at']  = time();
    $_SESSION['last_seen'] = time();

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

const DEVICE_COOKIE_NAME  = 'rms_device';
// The window the "Zapamti ovaj uredjaj 30 dana" checkbox promises. The cookie
// used to last 90 days while the label said 30; one constant now drives both,
// so the two cannot drift apart again.
const TRUSTED_DEVICE_DAYS = 30;
const DEVICE_COOKIE_TTL   = 60 * 60 * 24 * TRUSTED_DEVICE_DAYS;

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

    // The age is checked here rather than left to the cookie's own expiry: a
    // cookie copied to another machine carries no expiry with it, and would
    // otherwise be trusted for ever. Compared in PHP rather than with SQL date
    // arithmetic so it reads the same on Postgres and MySQL.
    $cutoff  = date('Y-m-d H:i:s', time() - TRUSTED_DEVICE_DAYS * 86400);
    $trusted = (bool) db_val(
        'SELECT COUNT(*) FROM trusted_devices
          WHERE user_id = ? AND device_hash = ? AND created_at > ?',
        [$user_id, $hash, $cutoff]
    );

    if ($trusted) {
        db_update('trusted_devices', ['last_seen' => date('Y-m-d H:i:s')],
                  'user_id = ? AND device_hash = ?', [$user_id, $hash]);
    }
    return $trusted;
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

    // An existing row is renewed rather than left alone — otherwise a device
    // trusted 31 days ago could never be trusted again, since the insert would
    // be skipped and the old row stays expired.
    $existing = db_val('SELECT id FROM trusted_devices WHERE user_id = ? AND device_hash = ?', [$user_id, $hash]);
    if ($existing) {
        db_update('trusted_devices', [
            'created_at' => date('Y-m-d H:i:s'),
            'last_seen'  => date('Y-m-d H:i:s'),
            'ip_address' => client_ip(),
        ], 'id = ?', [(int) $existing]);
    } else {
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

/**
 * What a new account of this role should start with.
 *
 * Staff work at the counter and the bench, so they have no reason to sign in
 * from the internet — they start LAN-only. Partners submit RMAs remotely by
 * definition, so they start unrestricted.
 *
 * This only seeds the form; the setting stays editable per user. The point is
 * that the safe choice is what you get by not thinking about it.
 */
function default_access_scope(string $role): string {
    return $role === 'partner' ? 'any' : 'lan';
}

/**
 * The client address as-is, private ranges included.
 *
 * client_ip() below deliberately discards private addresses because it feeds
 * rate-limiting and audit logs, where a LAN address is not a useful identity.
 * Network-scope checks need the opposite: a private address is exactly the
 * signal we are looking for.
 *
 * Takes the LAST entry of X-Forwarded-For. Caddy is configured to overwrite the
 * header (`header_up X-Forwarded-For {remote_host}`), so there is normally only
 * one — but if anything ever appends, the last hop is the one our proxy saw and
 * the only one a client cannot forge.
 */
function client_ip_raw(): string {
    $remote  = $_SERVER['REMOTE_ADDR'] ?? '';
    $trusted = defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : [];

    if (!empty($trusted) && in_array($remote, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $xff = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $ip  = end($xff);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $remote;
}

/**
 * Did this request come from inside the local network?
 *
 * True for RFC1918 / loopback / link-local addresses. Safe to trust because the
 * proxy overwrites X-Forwarded-For with the real TCP peer, so a client on the
 * internet cannot claim a private address.
 *
 * CLI (cron, console) has no address at all and counts as local — otherwise
 * scheduled jobs running as a restricted user would break.
 */
function request_is_lan(): bool {
    if (PHP_SAPI === 'cli') return true;

    $ip = client_ip_raw();
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;

    // No result from the "public addresses only" filter means it is private,
    // reserved or loopback — i.e. local.
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
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

/**
 * Seconds left on the user's current unused code, 0 if there isn't one.
 *
 * Read from the stored expiry rather than assuming ten minutes from page load:
 * the user may have sat on the code screen, refreshed, or come back to it, and
 * a countdown that disagrees with the server is worse than none at all.
 */
function otp_seconds_left(int $user_id): int {
    $exp = db_val(
        'SELECT expires_at FROM otp_codes WHERE user_id = ? AND used = 0 ORDER BY id DESC LIMIT 1',
        [$user_id]
    );
    return $exp ? max(0, strtotime($exp) - time()) : 0;
}

function otp_send_email(array $user, string $code): bool {
    // Subject carries the same name recipients see in the From line, so the two
    // cannot drift apart — change it once, in Administracija → Sistem → Email.
    $sender  = trim((string) setting('smtp_from_name', 'Integra')) ?: 'Integra';
    $subject = __('email.otp_subject', ['sender' => $sender]);
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

    $greeting = __('email.otp_greeting', ['name' => $name ?: '']);
    $intro    = __('email.otp_intro');
    $expiry   = __('email.otp_expiry');
    $ignore   = __('email.otp_ignore');

    // Table layout, not divs. Vertical centring in email only works through a
    // full-height table cell with valign="middle" — flexbox and margin:auto are
    // not supported, and Outlook renders through Word, which ignores most CSS
    // positioning. text-align is repeated on every cell and <p> because Outlook
    // also ignores alignment inherited from a parent block.
    //
    // Where a client sizes the message to its content rather than a full pane
    // (most webmail), this degrades to balanced padding, which is what you want
    // there anyway.
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f0;">
  <table role="presentation" width="100%" height="100%" cellpadding="0" cellspacing="0" border="0"
         style="background:#f4f4f0;height:100%;min-height:100%;">
    <tr>
      <td align="center" valign="middle" style="padding:40px 24px;">

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="480"
               style="width:100%;max-width:480px;background:#ffffff;border:1px solid #d3d1c7;border-radius:12px;">
          <tr>
            <td align="center" style="padding:40px 32px;text-align:center;font-family:{$font};">

              <img src="cid:integralogo" alt="Integra" height="40"
                   style="height:40px;width:auto;display:block;margin:0 auto 40px;border:0;">

              <p style="font-size:15px;color:#5f5e5a;margin:0 0 16px;text-align:center;font-family:{$font};">{$greeting}</p>
              <p style="font-size:15px;color:#1a1a18;margin:0 0 8px;text-align:center;font-family:{$font};">{$intro}</p>
              <p style="font-size:32px;font-weight:700;letter-spacing:6px;color:#1D9E75;margin:16px 0;text-align:center;font-family:{$font};">{$safe}</p>
              <p style="font-size:13px;color:#888780;margin:16px 0 0;text-align:center;font-family:{$font};">{$expiry}</p>
              <p style="font-size:13px;color:#888780;margin:4px 0 0;text-align:center;font-family:{$font};">{$ignore}</p>

            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>
HTML;

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

    // Pinned to Montenegrin rather than the recipient's profile language: the
    // people receiving these are staff in Montenegro, and a login code is not
    // the place for a language surprise. m:tel accepts UTF-8, so the diacritics
    // survive — no transliteration needed (see _sms_server/MTEL-PROFILE.md §7).
    //
    // No "Integra RMA:" prefix any more — m:tel already shows "Integra" as the
    // sender, so it only ate characters and read as a duplicate.
    $text = __in('me', 'auth.sms_otp', ['code' => $code]);
    return sms_send($phone, $text);
}
