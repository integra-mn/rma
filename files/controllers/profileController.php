<?php
defined('RMS') or die('Direct access not permitted');

class ProfileController {

    public function index(): void {
        require_login();

        $page_title = __('profile.my_profile');
        $user       = db_row('SELECT * FROM users WHERE id = ?', [current_user_id()]);
        $policy     = db_row('SELECT * FROM security_policies WHERE role = ?', [$user['role']]);
        // Only offer channels the role allows AND that are switched on app-wide.
        $allowed_channels = available_2fa_channels(
            $policy ? order_channels(explode(',', $policy['allowed_2fa_channels'] ?? 'email')) : ['email']
        );

        // Partner staff can move themselves between their employer's poslovnice.
        // Empty for Integra staff, whose placement is an Integra location set by
        // an administrator.
        $my_branches = $user['role'] === 'partner' ? partner_branches(current_partner_id()) : [];
        $my_branch   = current_partner_branch_id();

        $success = $_SESSION['form_success'] ?? null;
        $error   = $_SESSION['form_error'] ?? null;
        unset($_SESSION['form_success'], $_SESSION['form_error']);

        include views_path('layout/header.php');
        include views_path('profile/index.php');
        include views_path('layout/footer.php');
    }

    public function save(): void {
        require_login();

        $action = $_POST['action'] ?? '';

        match($action) {
            'password'            => $this->save_password(),
            'preferences'         => $this->save_preferences(),
            'vendor_credentials'  => $this->save_vendor_credentials(),
            default               => null,
        };

        header('Location: /profile');
        exit;
    }

    // Per-user credentials for any vendor enabled under Settings → Integrations.
    // Gated by the same permission that shows the section, so partners can't POST
    // their way in. The form posts the vendor slug plus cred_* fields.
    private function save_vendor_credentials(): void {
        if (!can('preferences', 'integrations')) { return; }

        $user_id = current_user_id();
        $slug    = $_POST['vendor'] ?? '';
        if (!in_array($slug, ['apple', 'tcl'], true)) { return; }

        $vendor_id = (int) db_val("SELECT id FROM vendors WHERE slug = ? LIMIT 1", [$slug]);
        if (!$vendor_id) {
            $_SESSION['form_error'] = __('profile.vendor_not_configured');
            return;
        }

        // "Clear" button submits alongside the form — wipe the row and return.
        if (!empty($_POST['clear'])) {
            user_vendor_credentials_clear($user_id, $vendor_id);
            audit('vendor_creds_cleared', 'user', $user_id, ['new' => ['vendor' => $slug]]);
            $_SESSION['form_success'] = __('profile.gsx_creds_cleared');
            return;
        }

        // Collect submitted cred_* fields; blank secrets are kept by the helper.
        $creds = [];
        foreach ($_POST as $k => $v) {
            if (is_string($k) && strncmp($k, 'cred_', 5) === 0) {
                $creds[substr($k, 5)] = trim((string) $v);
            }
        }

        user_vendor_credentials_save($user_id, $vendor_id, $creds);
        audit('vendor_creds_saved', 'user', $user_id, ['new' => ['vendor' => $slug]]);
        $_SESSION['form_success'] = __('profile.gsx_creds_saved');
    }

    private function save_password(): void {
        $user        = db_row('SELECT * FROM users WHERE id = ?', [current_user_id()]);
        $current     = $_POST['current_password'] ?? '';
        $new         = $_POST['new_password'] ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $_SESSION['form_error'] = __('profile.current_pw_incorrect');
            return;
        }

        $policy = db_row('SELECT * FROM security_policies WHERE role = ?', [$user['role']]);
        $min_len = (int)($policy['password_min_length'] ?? 8);

        if (strlen($new) < $min_len) {
            $_SESSION['form_error'] = __('profile.password_min', ['count' => $min_len]);
            return;
        }

        if ($new !== $confirm) {
            $_SESSION['form_error'] = __('profile.passwords_no_match');
            return;
        }

        db_update('users', [
            'password_hash'       => password_hash($new, PASSWORD_DEFAULT),
            'password_changed_at' => date('Y-m-d H:i:s'),
            'must_change_pw'      => 0,
        ], 'id = ?', [current_user_id()]);

        audit('password_changed', 'user', current_user_id());
        $_SESSION['form_success'] = __('profile.password_updated');
    }

    private function save_preferences(): void {
        $user    = db_row('SELECT * FROM users WHERE id = ?', [current_user_id()]);
        $policy  = db_row('SELECT * FROM security_policies WHERE role = ?', [$user['role']]);
        $allowed = available_2fa_channels(
            $policy ? order_channels(explode(',', $policy['allowed_2fa_channels'] ?? 'email')) : ['email']
        );

        $phone   = trim($_POST['phone'] ?? '');
        $lang    = in_array($_POST['lang'] ?? '', ['en','me']) ? $_POST['lang'] : $user['lang'];
        $theme   = in_array($_POST['theme'] ?? '', ['midnight','ocean','focus']) ? $_POST['theme'] : $user['theme'];
        $channel = in_array($_POST['preferred_2fa_channel'] ?? '', $allowed)
                 ? $_POST['preferred_2fa_channel']
                 : ($allowed[0] ?? 'email');

        db_update('users', [
            'phone'                  => $phone,
            'lang'                   => $lang,
            'theme'                  => $theme,
            'preferred_2fa_channel'  => $channel,
        ], 'id = ?', [current_user_id()]);

        // Update session
        $_SESSION['user']['lang']  = $lang;
        $_SESSION['user']['theme'] = $theme;
        $_SESSION['user']['phone'] = $phone;

        // Partner staff may move between their employer's poslovnice. Only their
        // own partner's branches are accepted, and the move is logged separately
        // so "why did this branch's numbers change" has an answer.
        if (($user['role'] ?? '') === 'partner') {
            $partner_id = current_partner_id();
            $old_branch = current_partner_branch_id();
            $new_branch = valid_partner_branch_id($partner_id, $_POST['partner_branch_id'] ?? null);

            if ($partner_id && $new_branch !== $old_branch) {
                db_update('partner_users', ['branch_id' => $new_branch],
                          'user_id = ?', [current_user_id()]);
                audit('branch_changed', 'user', current_user_id(),
                      ['old' => ['branch_id' => $old_branch], 'new' => ['branch_id' => $new_branch]]);
            }
        }

        audit('profile_updated', 'user', current_user_id());
        $_SESSION['form_success'] = __('profile.preferences_saved');
    }

    // ── Authenticator app (TOTP) ──────────────────────────────

    /**
     * Begin enrolment: mint a secret and hold it in the session.
     *
     * Kept out of the database until confirmed — a secret written straight to
     * users.totp_secret would make the app an offered channel before the user
     * has proved their phone produces matching codes, stranding them at login.
     */
    public function totp_start(): void {
        require_login();
        csrf_verify();
        $_SESSION['totp_pending'] = totp_create_secret();
        header('Location: /profile');
        exit;
    }

    /** Verify a code from the app, then store the secret. */
    public function totp_confirm(): void {
        require_login();
        csrf_verify();

        $secret = $_SESSION['totp_pending'] ?? '';
        $code   = trim($_POST['code'] ?? '');

        if ($secret === '') {
            $_SESSION['form_error'] = __('profile.totp_expired_setup');
        } elseif (totp_confirm(current_user_id(), $secret, $code)) {
            unset($_SESSION['totp_pending']);
            $_SESSION['form_success'] = __('profile.totp_enabled');
        } else {
            // Keep the pending secret so they can retry without rescanning.
            $_SESSION['form_error'] = __('profile.totp_bad_code');
        }

        header('Location: /profile');
        exit;
    }

    /** Abandon a half-finished enrolment. */
    public function totp_cancel(): void {
        require_login();
        unset($_SESSION['totp_pending']);
        header('Location: /profile');
        exit;
    }

    /** Turn the authenticator app off; other channels take over. */
    public function totp_disable(): void {
        require_login();
        csrf_verify();
        totp_reset(current_user_id());
        $_SESSION['form_success'] = __('profile.totp_disabled');
        header('Location: /profile');
        exit;
    }
}
