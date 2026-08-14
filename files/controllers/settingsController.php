<?php
defined('RMS') or die('Direct access not permitted');

class SettingsController {

    public function index(): void {
        // Legacy URL — the live settings page is /settings.
        header('Location: /settings', true, 301);
        exit;
    }

    public function save(): void {
        require_login();
        require_permission('settings', 'edit');

        $tab = $_POST['tab'] ?? 'general';

        match($tab) {
            'general'    => $this->save_general(),
            'appearance' => $this->save_appearance(),
            'smtp'       => $this->save_smtp(),
            'whatsapp'   => $this->save_whatsapp(),
            'sms'        => $this->save_sms(),
            'gsx'        => $this->save_gsx(),
            'tcl'        => $this->save_tcl(),
            'fiscal'     => $this->save_fiscal(),
            'image'      => $this->save_image(),
            'template'   => $this->save_template(),
            default      => null,
        };

        $_SESSION['form_success'] = __('msg.saved');
        $stab = match($tab) {
            'general'    => 'general',
            'appearance' => 'appearance',
            'smtp', 'whatsapp', 'sms' => 'smtp',
            'gsx', 'tcl' => 'integrations',
            'fiscal'     => 'fiscal',
            'image'      => 'image',
            'template'   => 'templates',
            default      => 'general',
        };
        header("Location: /settings?stab={$stab}");
        exit;
    }

    // ── Appearance ────────────────────────────────────────────

    private function save_appearance(): void {
        // Layout / typography stay global.
        $fields = [
            'sidebar_width'          => ['int',    (string)max(200, min(360, (int)($_POST['sidebar_width'] ?? 250)))],
            'topbar_height'          => ['int',    (string)max(48,  min(100, (int)($_POST['topbar_height'] ?? 64)))],
            'font_size'              => ['int',    (string)max(12,  min(18,  (int)($_POST['font_size'] ?? 14)))],
            'sidebar_font_size'      => ['int',    (string)max(12,  min(18,  (int)($_POST['sidebar_font_size'] ?? 13)))],
            'border_radius'          => ['int',    (string)max(0,   min(20,  (int)($_POST['border_radius'] ?? 8)))],
            'table_density'          => ['string', in_array($_POST['table_density']??'',['compact','normal','comfortable']) ? $_POST['table_density'] : 'normal'],
            'app_font'               => ['string', array_key_exists($_POST['app_font'] ?? '', APP_FONTS) ? $_POST['app_font'] : 'Montserrat'],
            'tab_style'              => ['string', in_array($_POST['tab_style'] ?? '', ['underline','boxed']) ? $_POST['tab_style'] : 'underline'],
            // The admin's current theme becomes the new default for new users.
            'default_theme'          => ['string', in_array($_POST['default_theme'] ?? '', ['midnight','ocean','focus'], true) ? $_POST['default_theme'] : 'midnight'],
        ];

        foreach ($fields as $key => [$type, $value]) {
            $this->set($key, $value, $type, 'appearance');
        }

        // Per-theme colours: each theme keeps its own palette, stored as a JSON
        // string under theme_colors_<code>. Fields are named theme_<code>_<key>
        // (+_hex); an invalid/blank value falls back to that theme's built-in.
        foreach (['midnight', 'ocean', 'focus'] as $code) {
            $defaults = theme_default_colors($code);
            $colors = [];
            foreach (THEME_COLOR_KEYS as $k) {
                $f   = 'theme_' . $code . '_' . $k;
                $val = $_POST[$f . '_hex'] ?? $_POST[$f] ?? '';
                $colors[$k] = preg_match('/^#[0-9a-fA-F]{6}$/', $val) ? $val : $defaults[$k];
            }
            $this->set('theme_colors_' . $code, json_encode($colors), 'string', 'appearance');
        }
    }

    // ── General ───────────────────────────────────────────────

    private function save_general(): void {
        $fields = [
            'app_name'          => ['string', trim($_POST['app_name'] ?? 'Integra RMA')],
            // Shown to customers (emails, tracking page, printed documents) as
            // opposed to app_name, which is what staff see inside the app.
            'company_name'      => ['string', trim($_POST['company_name'] ?? '') ?: 'Integra Service'],
            'default_lang'      => ['string', $_POST['default_lang'] ?? 'en'],
            'default_location'  => ['int',    (int)($_POST['default_location'] ?? 0)],
            'rma_number_format' => ['string', $rma_format = trim($_POST['rma_number_format'] ?? '{LOC}-{YEAR}-{SEQ5}')],
            // Yearly reset is only meaningful when the number carries a year.
            // Without one, restarting the sequence would reissue numbers that
            // already exist, so the option is forced off rather than trusted
            // from the form — the checkbox is disabled in the UI, and a disabled
            // checkbox simply is not submitted.
            'rma_number_reset_yearly' => ['string',
                (!empty($_POST['rma_number_reset_yearly']) && preg_match('/\{(YY|YYYY|YEAR)\}/', $rma_format))
                    ? '1' : '0'],
            // Clamped rather than trusted. Below 5 minutes nobody could finish
            // a form; a week is not a session. The absolute limit is floored at
            // the idle one, because an absolute shorter than idle would end
            // sessions that were never idle and look like a bug.
            'session_idle_minutes' => ['int',
                max(5, min(1440, (int)($_POST['session_idle_minutes'] ?? 120)))],
            'session_max_minutes'  => ['int',
                max(max(5, min(1440, (int)($_POST['session_idle_minutes'] ?? 120))),
                    min(10080, (int)($_POST['session_max_minutes'] ?? 480)))],
            'pdf_engine'        => ['string', in_array($_POST['pdf_engine']??'',['html','mpdf']) ? $_POST['pdf_engine'] : 'html'],
            'pdf_paper_size'    => ['string', in_array($_POST['pdf_paper_size']??'',['A4','A5','Letter']) ? $_POST['pdf_paper_size'] : 'A4'],
        ];

        foreach ($fields as $key => [$type, $value]) {
            $this->set($key, (string)$value, $type, 'general');
        }

        // Handle logo upload
        if (!empty($_FILES['app_logo']['tmp_name'])) {
            $result = process_uploaded_image($_FILES['app_logo'], ROOT . '/assets', 'document');
            if ($result) {
                $this->set('app_logo', $result['file_path'], 'string', 'general');
            }
        }
    }

    // ── SMTP ──────────────────────────────────────────────────

    private function save_smtp(): void {
        $this->save_notify_audiences('email');

        // Switching email off is refused when it would leave a role with no way
        // to receive a 2FA code. An email that does not arrive is an
        // inconvenience; being unable to sign in is not, and this is the screen
        // where that mistake would be made.
        $want_on = ($_POST['smtp_enabled'] ?? '1') === '1';
        if (!$want_on && setting('smtp_enabled', '1') === '1') {
            $stranded = roles_stranded_without('email');
            if ($stranded) {
                $want_on = true;
                $_SESSION['form_error'] = __('settings.channel_last_for_roles', [
                    'roles' => implode(', ', array_map('role_label', $stranded)),
                ]);
            }
        }
        $this->set('smtp_enabled', $want_on ? '1' : '0', 'string', 'smtp');

        $fields = [
            'smtp_host'       => trim($_POST['smtp_host'] ?? ''),
            'smtp_port'       => (string)(int)($_POST['smtp_port'] ?? 587),
            'smtp_user'       => trim($_POST['smtp_user'] ?? ''),
            'smtp_from'       => trim($_POST['smtp_from'] ?? ''),
            'smtp_from_name'  => trim($_POST['smtp_from_name'] ?? 'Integra RMA'),
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
        ];

        foreach ($fields as $key => $value) {
            $this->set($key, $value, 'string', 'email');
        }

        // Only save password if provided (don't overwrite with empty)
        if (!empty($_POST['smtp_pass'])) {
            $this->set('smtp_pass', $_POST['smtp_pass'], 'string', 'email');
        }
    }

    // ── 2FA delivery channels ─────────────────────────────────

    // ── WhatsApp (Meta Cloud API) ─────────────────────────────

    // Messaging providers shared by SMS + WhatsApp. Field => is-secret.
    // Secrets keep their stored value when the field is submitted blank. Keep
    // this in sync with $provider_defs in views/admin/tabs/system.php.
    private const MSG_PROVIDERS = [
        'mtel'       => ['url' => false, 'user' => false, 'secret' => true],
        'infobip'    => ['base' => false, 'apikey' => true,  'from' => false],
        'vonage'     => ['apikey' => false, 'secret' => true, 'from' => false],
        'clickatell' => ['apikey' => true, 'from' => false],
    ];

    // Persists the chosen provider for a channel plus every provider's config.
    // A non-empty provider means the channel is active; '' means disabled.
    private function save_provider_channel(string $channel, array $extraValid = []): void {
        $valid    = array_merge([''], array_keys(self::MSG_PROVIDERS), $extraValid);
        $provider = in_array($_POST[$channel . '_provider'] ?? '', $valid, true)
            ? $_POST[$channel . '_provider'] : '';
        $this->set($channel . '_provider', $provider, 'string', 'integrations');

        foreach (self::MSG_PROVIDERS as $pkey => $fields) {
            foreach ($fields as $fk => $secret) {
                $skey = "{$channel}_{$pkey}_{$fk}";
                if ($secret) {
                    // Blank means "keep existing" so saved keys are not wiped.
                    if (!empty($_POST[$skey])) {
                        $this->set($skey, $_POST[$skey], 'string', 'integrations');
                    }
                } else {
                    $this->set($skey, trim($_POST[$skey] ?? ''), 'string', 'integrations');
                }
            }
        }
    }

    private function save_whatsapp(): void {
        $this->save_notify_audiences('whatsapp');
        $this->save_provider_channel('whatsapp');
    }

    // ── SMS gateway (Infobip / Vonage / Clickatell / custom) ────────────

    /**
     * Who this channel is used to notify.
     *
     * Statuses say WHETHER a step is worth a message; this says who hears it
     * on this channel. Saved by whichever channel form was submitted, so the
     * switches sit under the gateway they belong to.
     */
    private function save_notify_audiences(string $channel): void {
        foreach (['customer', 'partner'] as $who) {
            $key = "notify_{$who}_{$channel}";
            $this->set($key, isset($_POST[$key]) ? '1' : '0', 'string', 'integrations');
        }
    }

    private function save_sms(): void {
        $this->save_notify_audiences('sms');
        $this->save_provider_channel('sms');


        // Custom HTTP gateway
    }

    // ── Apple GSX (stored on vendor_adapters row, not flat settings) ──

    private function save_gsx(): void {
        // Whether Apple devices show the "Check warranty" button on an RMA.
        // Saved as a flat setting so it works even if the adapter isn't seeded.
        $this->set('gsx_warranty_check', ($_POST['gsx_warranty_check'] ?? '0') === '1' ? '1' : '0', 'bool', 'gsx');

        $row = db_row(
            "SELECT a.id, a.credentials
             FROM vendors v JOIN vendor_adapters a ON a.vendor_id = v.id
             WHERE v.slug = 'apple' LIMIT 1"
        );
        if (!$row) return; // Apple vendor/adapter not seeded — skip silently

        $current = json_decode((string)($row['credentials'] ?? '{}'), true) ?: [];

        // Merge submitted values. Blank auth_token => keep existing
        // (same convention as SMTP password field).
        $creds = [
            'sold_to'         => trim($_POST['gsx_sold_to'] ?? ''),
            'ship_to'         => trim($_POST['gsx_ship_to'] ?? ''),
            'auth_token'      => trim($_POST['gsx_auth_token'] ?? '') !== ''
                                 ? trim($_POST['gsx_auth_token'])
                                 : ($current['auth_token'] ?? ''),
            'cert_path'       => trim($_POST['gsx_cert_path'] ?? ''),
            'key_path'        => trim($_POST['gsx_key_path']  ?? ''),
            'timeout_seconds' => max(5, min(60, (int)($_POST['gsx_timeout'] ?? 15))),
        ];

        db_update('vendor_adapters', [
            'endpoint_url' => trim($_POST['gsx_endpoint_url'] ?? ''),
            'credentials'  => json_encode($creds),
            'is_active'    => ($_POST['gsx_enabled'] ?? '0') === '1' ? 1 : 0,
        ], 'id = ?', [(int)$row['id']]);
    }

    // ── TCL vendor integration (flat settings) ────────────────
    private function save_tcl(): void {
        $this->set('tcl_enabled',        ($_POST['tcl_enabled'] ?? '0') === '1' ? '1' : '0',        'bool',   'integrations');
        $this->set('tcl_warranty_check', ($_POST['tcl_warranty_check'] ?? '0') === '1' ? '1' : '0', 'bool',   'integrations');
        $this->set('tcl_base_url',       trim($_POST['tcl_base_url'] ?? ''),                        'string', 'integrations');
        // Blank API key means "keep existing".
        if (!empty($_POST['tcl_api_key'])) {
            $this->set('tcl_api_key', $_POST['tcl_api_key'], 'string', 'integrations');
        }
    }

    // ── "Test connection" button on the GSX card ──────────────────────────
    public function gsx_test(): void {
        require_login();
        require_permission('settings', 'edit');
        header('Content-Type: application/json');

        $vendor_id = (int) db_val("SELECT id FROM vendors WHERE slug = 'apple' LIMIT 1");
        if (!$vendor_id) { echo json_encode(['ok' => false, 'message' => __('settings.gsx_vendor_not_configured')]); return; }

        $adapter = vendor_adapter($vendor_id);
        if (!$adapter) { echo json_encode(['ok' => false, 'message' => __('settings.gsx_adapter_not_loadable')]); return; }

        $res = $adapter->ping();
        // Record last_tested_at regardless of outcome so admins can see when
        // the test last ran.
        db()->prepare(
            "UPDATE vendor_adapters SET last_tested_at = NOW()
             WHERE vendor_id = ?"
        )->execute([$vendor_id]);

        echo json_encode(['ok' => !empty($res['ok']), 'message' => $res['message'] ?? '']);
    }

    // ── Fiscalization ─────────────────────────────────────────

    private function save_fiscal(): void {
        $fields = [
            'fiscal_enabled'       => ['bool',   isset($_POST['fiscal_enabled']) ? '1' : '0'],
            'fiscal_env'           => ['string', $_POST['fiscal_env'] ?? 'test'],
            'fiscal_tin'           => ['string', trim($_POST['fiscal_tin'] ?? '')],
            'fiscal_operator_code' => ['string', trim($_POST['fiscal_operator_code'] ?? '')],
            'fiscal_bunit_code'    => ['string', trim($_POST['fiscal_bunit_code'] ?? '')],
            'fiscal_tcr_code'      => ['string', trim($_POST['fiscal_tcr_code'] ?? '')],
            'fiscal_cert_pass'     => ['string', trim($_POST['fiscal_cert_pass'] ?? '')],
            'fiscal_sw_code'       => ['string', trim($_POST['fiscal_sw_code'] ?? '')],
            'fiscal_maint_code'    => ['string', trim($_POST['fiscal_maint_code'] ?? '')],
        ];

        foreach ($fields as $key => [$type, $value]) {
            $this->set($key, $value, $type, 'fiscal');
        }

        // Certificate file upload
        if (!empty($_FILES['fiscal_cert']['tmp_name'])) {
            $dest = ROOT . '/config/fiscal_cert.pfx';
            move_uploaded_file($_FILES['fiscal_cert']['tmp_name'], $dest);
            $this->set('fiscal_cert_path', '/config/fiscal_cert.pfx', 'string', 'fiscal');
        }
    }

    // ── Image ─────────────────────────────────────────────────

    private function save_image(): void {
        $fields = [
            'img_max_width'     => (string)max(640, min(4096, (int)($_POST['img_max_width'] ?? 1920))),
            'img_max_height'    => (string)max(640, min(4096, (int)($_POST['img_max_height'] ?? 1920))),
            'img_quality'       => (string)max(50, min(100, (int)($_POST['img_quality'] ?? 85))),
            'img_thumb_size'    => (string)max(100, min(800, (int)($_POST['img_thumb_size'] ?? 400))),
            'img_max_upload_mb' => (string)max(1, min(50, (int)($_POST['img_max_upload_mb'] ?? 20))),
        ];

        foreach ($fields as $key => $value) {
            $this->set($key, $value, 'int', 'media');
        }
    }

    // ── Notification template ─────────────────────────────────

    private function save_template(): void {
        require_permission('settings', 'edit');

        $id      = (int)($_POST['template_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body'] ?? '');

        if ($id && $body) {
            db_update('notification_templates', [
                'subject'    => $subject ?: null,
                'body'       => $body,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            audit('updated', 'notification_template', $id);
        }
    }

    // ── SMTP test send ────────────────────────────────────────

    /**
     * Send a one-off SMS through the configured gateway so the settings can be
     * verified without waiting for a real RMA event. Reports the gateway's own
     * refusal reason (e.g. "IP not whitelisted") rather than a generic failure.
     */
    public function sms_test(): void {
        require_login();
        require_permission('settings', 'edit');

        header('Content-Type: application/json');

        $to = trim($_POST['to'] ?? '');
        if ($to === '') {
            echo json_encode(['success' => false, 'message' => __('settings.sms_no_number')]);
            exit;
        }

        $res = sms_send_result($to, 'Integra RMA — test.');
        echo json_encode([
            'success' => $res['ok'],
            'message' => $res['ok'] ? __('settings.sms_test_sent') : $res['error'],
        ]);
        exit;
    }

    /**
     * Send a real test email through the same code path the app uses, so a
     * green result proves authentication, TLS and delivery — not merely that
     * the SMTP port is open.
     */
    public function smtp_test(): void {
        require_login();
        require_permission('settings', 'edit');

        header('Content-Type: application/json');

        $to = trim($_POST['to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => __('settings.smtp_invalid_email')]);
            exit;
        }

        $res = send_email_result(
            $to,
            '',
            __('settings.smtp_test_subject'),
            '<p>' . __('settings.smtp_test_body') . '</p>'
        );

        echo json_encode([
            'success' => $res['ok'],
            'message' => $res['ok'] ? __('settings.smtp_test_sent', ['email' => $to]) : $res['error'],
        ]);
        exit;
    }

    // ── Helper ────────────────────────────────────────────────

    private function set(string $key, string $value, string $type, string $group): void {
        $existing = db_row('SELECT id FROM settings WHERE key_name = ? AND location_id IS NULL', [$key]);
        if ($existing) {
            db_update('settings', ['value' => $value, 'updated_by' => current_user_id()], 'id = ?', [$existing['id']]);
        } else {
            db_insert('settings', ['key_name' => $key, 'value' => $value, 'type' => $type, 'group_name' => $group, 'updated_by' => current_user_id()]);
        }
    }
}
