<?php
defined('RMS') or die('Direct access not permitted');

class AuthController {

    public function login(): void {
        if (is_logged_in()) {
            header('Location: /');
            exit;
        }
        $error = $_SESSION['auth_error'] ?? null;
        unset($_SESSION['auth_error']);
        include views_path('auth/login.php');
    }

    public function login_post(): void {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $_SESSION['auth_error'] = __('auth.enter_credentials');
            header('Location: /auth/login');
            exit;
        }

        $result = auth_attempt($email, $password);

        switch ($result['status']) {
            case 'ok':
                // Partners land on the portal, everyone else on the staff home.
                $current = current_user();
                if ($current && ($current['role'] ?? '') === 'partner') {
                    header('Location: /portal');
                } else {
                    header('Location: /');
                }
                exit;

            case '2fa_required':
                $_SESSION['2fa_channels'] = $result['channels'];
                // Same view handles both steps (choose channel / enter code);
                // it branches on $_SESSION['2fa_sent'].
                include views_path('auth/2fa.php');
                exit;

            case 'locked':
                $_SESSION['auth_error'] = __('auth.locked');
                break;

            case 'inactive':
                $_SESSION['auth_error'] = __('auth.inactive');
                break;

            case 'wrong_network':
                $_SESSION['auth_error'] = __('auth.wrong_network');
                break;

            default:
                $_SESSION['auth_error'] = __('auth.invalid');
        }

        header('Location: /auth/login');
        exit;
    }

    public function twofa(): void {
        if (is_logged_in()) { header('Location: /'); exit; }
        if (empty($_SESSION['pending_user_id'])) { header('Location: /auth/login'); exit; }
        $error = $_SESSION['auth_error'] ?? null;
        unset($_SESSION['auth_error']);
        include views_path('auth/2fa.php');
    }

    public function twofa_post(): void {
        $user_id = $_SESSION['pending_user_id'] ?? null;
        if (!$user_id) { header('Location: /auth/login'); exit; }

        $action = $_POST['action'] ?? 'verify';

        // Back to the channel chooser. Keeps the pending login alive - only the
        // "a code was sent" state is cleared - so the user can switch from, say,
        // email to SMS without starting the whole login again.
        if ($action === 'change_channel') {
            $_SESSION['2fa_sent'] = false;
            unset($_SESSION['2fa_channel']);
            header('Location: /auth/2fa');
            exit;
        }

        // Send OTP
        if ($action === 'send') {
            $channel = $_POST['channel'] ?? 'email';
            $allowed = $_SESSION['2fa_channels'] ?? ['email'];
            if (!in_array($channel, $allowed, true)) {
                $_SESSION['auth_error'] = __('auth.invalid_channel');
                header('Location: /auth/2fa');
                exit;
            }
            $_SESSION['2fa_channel'] = $channel;
            otp_send((int) $user_id, $channel);
            $_SESSION['2fa_sent'] = true;
            header('Location: /auth/2fa');
            exit;
        }

        // Verify OTP
        $code         = trim($_POST['code'] ?? '');
        $trust_device = !empty($_POST['trust_device']);

        if (!$code) {
            $_SESSION['auth_error'] = __('auth.enter_code');
            header('Location: /auth/2fa');
            exit;
        }

        $result = otp_verify((int) $user_id, $code, $trust_device);

        switch ($result['status']) {
            case 'ok':
                unset($_SESSION['pending_user_id'], $_SESSION['2fa_channel'], $_SESSION['2fa_preferred'],
                      $_SESSION['2fa_sent'], $_SESSION['2fa_channels']);
                header('Location: /');
                exit;

            case 'expired':
                $_SESSION['auth_error'] = __('auth.otp_expired');
                $_SESSION['2fa_sent']   = false;
                break;

            case 'exhausted':
                $_SESSION['auth_error'] = __('auth.otp_exhausted');
                $_SESSION['2fa_sent']   = false;
                break;

            default:
                $left = $result['attempts_left'] ?? 0;
                $_SESSION['auth_error'] = __('auth.otp_invalid', ['count' => $left]);
        }

        header('Location: /auth/2fa');
        exit;
    }

    public function logout(): void {
        auth_logout();
    }
}
