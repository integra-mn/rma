<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Authenticator-app 2FA (TOTP, RFC 6238).
 *
 * Uses spomky-labs/otphp rather than a hand-rolled implementation: the maths is
 * short but the details that matter (base32, clock drift, constant-time
 * comparison) are exactly where a home-made version goes quietly wrong on an
 * authentication path.
 *
 * The QR code is rendered locally by chillerlan/php-qrcode — the secret must
 * never be sent to an external QR service.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use OTPHP\TOTP;

/** Codes are valid for this many steps either side of now (30s each). */
const TOTP_WINDOW = 1;   // ±30s, absorbs ordinary phone/server clock drift

/**
 * Create a fresh secret. Not stored as confirmed until the user proves the app
 * produces matching codes — see totp_confirm().
 */
function totp_create_secret(): string {
    return TOTP::generate()->getSecret();
}

/**
 * Build the otpauth:// URI an authenticator app expects.
 *
 * The label is what the user sees in their app list, so it names the company
 * (Settings → General) rather than the internal app name, plus the account.
 */
function totp_uri(string $secret, string $account): string {
    $totp = TOTP::createFromSecret($secret);
    $totp->setLabel($account);
    $totp->setIssuer(company_name());
    return $totp->getProvisioningUri();
}

/** The enrolment QR as a data: URI, ready for <img src="…">. */
function totp_qr_base64(string $secret, string $account, int $size = 200): string {
    return generate_qr_base64(totp_uri($secret, $account), $size);
}

/**
 * Check a 6-digit code against a secret.
 *
 * Returns false for anything malformed rather than letting the library throw —
 * this runs on the login path, where an exception would surface as a 500 to
 * someone simply mistyping their code.
 */
function totp_verify(string $secret, string $code): bool {
    $code = preg_replace('/\D/', '', $code);
    if ($secret === '' || strlen($code) !== 6) return false;

    try {
        return TOTP::createFromSecret($secret)->verify($code, null, TOTP_WINDOW);
    } catch (Throwable $e) {
        error_log('TOTP verify failed: ' . $e->getMessage());
        return false;
    }
}

/** Has this user finished enrolling an authenticator app? */
function totp_is_enrolled(array $user): bool {
    return !empty($user['totp_secret']) && !empty($user['totp_confirmed_at']);
}

/**
 * Finish enrolment: store the secret only once a generated code checks out.
 * Returns false when the code is wrong, leaving the account untouched.
 */
function totp_confirm(int $user_id, string $secret, string $code): bool {
    if (!totp_verify($secret, $code)) return false;

    db_update('users', [
        'totp_secret'       => $secret,
        'totp_confirmed_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$user_id]);

    audit('totp_enrolled', $user_id);
    return true;
}

/**
 * Remove the authenticator app from an account — used both by the user and by
 * an admin when someone loses their phone. Falls back to the other channels.
 */
function totp_reset(int $user_id): void {
    db_update('users', [
        'totp_secret'       => null,
        'totp_confirmed_at' => null,
    ], 'id = ?', [$user_id]);

    audit('totp_reset', $user_id);
}
