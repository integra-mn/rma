<?php
defined('RMS') or die('Direct access not permitted');

class PortalController {

    // GET /portal/login — show the partner login page.
    // Already-authenticated visitors are redirected to the page that matches
    // their role (partners → portal dashboard, staff → staff home).
    public function login(): void {
        $user = current_user();
        if ($user && $user['role'] === 'partner') { header('Location: /portal'); exit; }
        if ($user)                                { header('Location: /');        exit; }

        $error = $_SESSION['auth_error'] ?? null;
        unset($_SESSION['auth_error']);
        include views_path('portal/login.php');
    }

    // POST /portal/login — authenticate a partner user.
    // Non-partner roles that succeed in auth_attempt() get immediately logged
    // out with a clear "wrong login page" message — prevents staff from
    // accidentally ending up on the portal.
    public function login_post(): void {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $_SESSION['auth_error'] = __('auth.enter_credentials');
            header('Location: /portal/login'); exit;
        }

        $result = auth_attempt($email, $password);

        switch ($result['status']) {
            case 'ok':
                $user = current_user();
                if ($user && $user['role'] !== 'partner') {
                    session_destroy();
                    session_start();
                    $_SESSION['auth_error'] = __('portal.partners_only');
                    header('Location: /portal/login'); exit;
                }
                header('Location: /portal'); exit;

            case '2fa_required':
                $_SESSION['2fa_channels'] = $result['channels'];
                // Shared with staff login; the view branches on 2fa_sent.
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

        header('Location: /portal/login');
        exit;
    }

    // GET /portal — partner dashboard.
    // Everything here is scoped to the current user's partner_id — partners
    // never see another partner's data.
    public function dashboard(): void {
        require_partner();
        $user       = current_user();
        $partner_id = current_partner_id();
        $partner    = $partner_id
            ? db_row('SELECT * FROM partners WHERE id = ?', [$partner_id])
            : null;

        // No partner link yet? Render a notice-only dashboard.
        if (!$partner_id) {
            $stats = ['open' => 0, 'in_repair' => 0, 'ready_pickup' => 0, 'this_month' => 0];
            $recent = [];
            $page_title = __('nav.dashboard');
            include views_path('layout/header.php');
            include views_path('portal/dashboard.php');
            include views_path('layout/footer.php');
            return;
        }

        $stats = [
            // Open = any RMA in a non-terminal status
            'open' => (int)db_val(
                "SELECT COUNT(*) FROM rma_requests r
                 JOIN rma_statuses s ON s.id = r.status_id
                 WHERE r.partner_id = ? AND r.deleted_at IS NULL AND s.is_terminal = 0",
                [$partner_id]
            ),
            // In repair = has at least one repair_job in a non-terminal status
            'in_repair' => (int)db_val(
                "SELECT COUNT(DISTINCT r.id) FROM rma_requests r
                 JOIN repair_jobs j      ON j.rma_id = r.id AND j.deleted_at IS NULL
                 JOIN repair_statuses js ON js.id   = j.status_id
                 WHERE r.partner_id = ? AND r.deleted_at IS NULL AND js.is_terminal = 0",
                [$partner_id]
            ),
            // Ready pickup = RMA non-terminal AND every repair_job terminal
            'ready_pickup' => (int)db_val(
                "SELECT COUNT(*) FROM (
                    SELECT r.id
                    FROM rma_requests r
                    JOIN rma_statuses rs    ON rs.id = r.status_id
                    JOIN repair_jobs j      ON j.rma_id = r.id AND j.deleted_at IS NULL
                    JOIN repair_statuses js ON js.id = j.status_id
                    WHERE r.partner_id = ? AND r.deleted_at IS NULL AND rs.is_terminal = 0
                    GROUP BY r.id
                    HAVING SUM(CASE WHEN js.is_terminal = 0 THEN 1 ELSE 0 END) = 0
                 ) t",
                [$partner_id]
            ),
            // This month = RMAs submitted in the current calendar month
            'this_month' => (int)db_val(
                "SELECT COUNT(*) FROM rma_requests
                 WHERE partner_id = ? AND deleted_at IS NULL
                   AND YEAR(created_at) = YEAR(CURRENT_DATE)
                   AND MONTH(created_at) = MONTH(CURRENT_DATE)",
                [$partner_id]
            ),
        ];

        // Most-recent 10 RMAs for this partner. Device info flows through
        // the devices table (rma → devices → device_models → device_brands).
        $recent = db_rows(
            "SELECT r.id, r.rma_number, r.created_at, r.priority,
                    s.label AS status_label, s.color AS status_color, s.code AS status_code,
                    c.name  AS customer_name,
                    b.name  AS brand_name, m.name AS model_name
             FROM rma_requests r
             JOIN rma_statuses s        ON s.id = r.status_id
             LEFT JOIN customers     c  ON c.id = r.customer_id
             LEFT JOIN devices       d  ON d.id = r.device_id
             LEFT JOIN device_models m  ON m.id = d.model_id
             LEFT JOIN device_brands b  ON b.id = m.brand_id
             WHERE r.partner_id = ? AND r.deleted_at IS NULL
             ORDER BY r.created_at DESC LIMIT 10",
            [$partner_id]
        );

        $page_title = __('nav.dashboard');
        include views_path('layout/header.php');
        include views_path('portal/dashboard.php');
        include views_path('layout/footer.php');
    }

    // GET /portal/rma — list all RMAs for this partner.
    public function rma_list(): void {
        require_partner();
        $user       = current_user();
        $partner_id = current_partner_id();

        $search = trim($_GET['q'] ?? '');
        $where  = 'r.partner_id = ? AND r.deleted_at IS NULL';
        $params = [$partner_id];

        if ($search !== '') {
            $where .= ' AND (r.rma_number LIKE ? OR c.name LIKE ? OR d.serial_number LIKE ? OR d.imei LIKE ?)';
            $needle = "%{$search}%";
            array_push($params, $needle, $needle, $needle, $needle);
        }

        $rmas = $partner_id ? db_rows(
            "SELECT r.id, r.rma_number, r.created_at, r.priority,
                    s.label AS status_label, s.color AS status_color, s.code AS status_code,
                    c.name  AS customer_name,
                    b.name  AS brand_name, m.name AS model_name
             FROM rma_requests r
             JOIN rma_statuses s        ON s.id = r.status_id
             LEFT JOIN customers     c  ON c.id = r.customer_id
             LEFT JOIN devices       d  ON d.id = r.device_id
             LEFT JOIN device_models m  ON m.id = d.model_id
             LEFT JOIN device_brands b  ON b.id = m.brand_id
             WHERE {$where}
             ORDER BY r.created_at DESC",
            $params
        ) : [];

        $page_title = __('portal.my_rmas');
        include views_path('layout/header.php');
        include views_path('portal/rma_list.php');
        include views_path('layout/footer.php');
    }

    // GET /portal/rma/{id} — RMA detail for a single RMA the partner owns.
    // Any mismatch returns 404 (not 403) — we don't confirm existence of
    // records the partner shouldn't know about.
    public function rma_view(string $id): void {
        require_partner();
        $partner_id = current_partner_id();
        if (!$partner_id) { http_response_code(404); include views_path('errors/404.php'); return; }

        $rma = $this->load_own_rma((int)$id, $partner_id);
        if (!$rma) { http_response_code(404); include views_path('errors/404.php'); return; }

        $user = current_user();
        $partner = db_row('SELECT * FROM partners WHERE id = ?', [$partner_id]);

        // Same data loads as the public /track page.
        $history = db_rows(
            "SELECT h.*, s.label AS status_label, s.color AS status_color
             FROM rma_status_history h
             JOIN rma_statuses s ON s.id = h.status_id
             WHERE h.rma_id = ? ORDER BY h.created_at ASC",
            [(int)$rma['id']]
        );

        $shipment = db_row(
            "SELECT * FROM delivery_shipments
             WHERE rma_id = ? AND direction = 'inbound'
             ORDER BY created_at DESC LIMIT 1",
            [(int)$rma['id']]
        );

        $invoice = db_row(
            "SELECT * FROM invoices
             WHERE rma_id = ? AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 1",
            [(int)$rma['id']]
        );

        // Partner sees all customer-visible comments (same as public tracking).
        $comments = db_rows(
            "SELECT c.body, c.created_at, c.source, c.user_id,
                    u.name AS author_name, u.role AS author_role
             FROM rma_comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.rma_id = ? AND c.visibility = 'customer' AND c.deleted_at IS NULL
             ORDER BY c.created_at ASC",
            [(int)$rma['id']]
        );

        $repair_jobs = db_rows(
            "SELECT j.*, s.label AS status_label, s.color AS status_color,
                    u.name AS technician_name
             FROM repair_jobs j
             LEFT JOIN repair_statuses s ON s.id = j.status_id
             LEFT JOIN users u           ON u.id = j.technician_id
             WHERE j.rma_id = ? AND j.deleted_at IS NULL
             ORDER BY j.created_at ASC",
            [(int)$rma['id']]
        );

        $photos = db_rows(
            "SELECT re.id, re.filename, re.original_name, re.stage, re.created_at
             FROM repair_evidence re
             LEFT JOIN repair_jobs j ON j.id = re.repair_job_id
             WHERE (re.rma_id = ? OR j.rma_id = ?)
               AND re.deleted_at IS NULL
             ORDER BY re.created_at ASC",
            [(int)$rma['id'], (int)$rma['id']]
        );

        // Flash messages from a posted comment (success or validation error)
        $flash_success = $_SESSION['form_success'] ?? null;
        $flash_error   = $_SESSION['form_error']   ?? null;
        unset($_SESSION['form_success'], $_SESSION['form_error']);

        $page_title = $rma['rma_number']; // shown in navbar + browser tab title

        include views_path('layout/header.php');
        include views_path('portal/rma_view.php');
        include views_path('layout/footer.php');
    }

    // GET /portal/rma/{id}/receipt — PDF receipt download.
    public function rma_receipt(string $id): void {
        require_partner();
        $partner_id = current_partner_id();
        if (!$partner_id) { http_response_code(404); return; }

        $rma = $this->load_own_rma((int)$id, $partner_id);
        if (!$rma) { http_response_code(404); return; }

        // Reuse the existing PDF helpers used by staff. The engine preference
        // (html/mpdf) follows the same app setting.
        $tracking_url = rtrim(setting('app_url', ''), '/') . '/track/' . ($rma['tracking_token'] ?? '');
        $qr_base64    = function_exists('qr_png_base64') ? qr_png_base64($tracking_url) : '';

        $engine = setting('pdf_engine', 'html');
        if ($engine === 'mpdf' && function_exists('generate_rma_pdf_mpdf')) {
            generate_rma_pdf_mpdf($rma, $tracking_url, $qr_base64);
        } else {
            generate_rma_pdf_html($rma, $tracking_url, $qr_base64);
        }
    }

    // Load an RMA by id IF it belongs to the given partner. Returns null
    // otherwise — caller treats as 404. Same SELECT the staff uses, joined
    // with status/brand/model/customer for the view.
    private function load_own_rma(int $id, int $partner_id): ?array {
        return db_row(
            "SELECT r.*,
                    s.label AS status_label, s.color AS status_color, s.code AS status_code,
                    c.name  AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                    b.name  AS brand_name,
                    m.name  AS model_name,
                    d.serial_number AS serial_number, d.imei AS imei,
                    l.name    AS location_name,
                    l.address AS location_address,
                    l.city    AS location_city,
                    l.phone   AS location_phone,
                    t.token   AS tracking_token
             FROM rma_requests r
             JOIN rma_statuses s        ON s.id = r.status_id
             LEFT JOIN customers     c  ON c.id = r.customer_id
             LEFT JOIN devices       d  ON d.id = r.device_id
             LEFT JOIN device_models m  ON m.id = d.model_id
             LEFT JOIN device_brands b  ON b.id = m.brand_id
             LEFT JOIN locations     l  ON l.id = r.location_id
             LEFT JOIN rma_tracking_tokens t ON t.rma_id = r.id
             WHERE r.id = ? AND r.partner_id = ? AND r.deleted_at IS NULL
             LIMIT 1",
            [$id, $partner_id]
        );
    }

    // POST /portal/rma/{id}/dispatch — partner confirms they've dispatched
    // the device to Integra. Only valid while the RMA is still draft/submitted;
    // advances status to awaiting_device.
    public function rma_dispatch(string $id): void {
        require_partner();
        $partner_id = current_partner_id();
        if (!$partner_id) { http_response_code(403); return; }

        $rma = $this->load_own_rma((int)$id, $partner_id);
        if (!$rma) { http_response_code(404); return; }

        if (!in_array($rma['status_code'] ?? '', ['draft', 'submitted'], true)) {
            $_SESSION['form_error'] = __('portal.cannot_dispatch');
            header('Location: /portal/rma/' . (int)$id); exit;
        }

        $tracking = trim($_POST['tracking'] ?? '');
        if (mb_strlen($tracking) > 200) $tracking = mb_substr($tracking, 0, 200);

        $new_status_id = (int)db_val("SELECT id FROM rma_statuses WHERE code = 'awaiting_device' LIMIT 1");
        if (!$new_status_id) {
            $_SESSION['form_error'] = 'Internal: awaiting_device status is not configured.';
            header('Location: /portal/rma/' . (int)$id); exit;
        }

        $note = 'Partner confirmed dispatch to Integra'
              . ($tracking !== '' ? ' — Tracking: ' . $tracking : '.');

        $this->advance_status((int)$id, $new_status_id, $note);
        $_SESSION['form_success'] = __('portal.dispatch_confirmed');
        header('Location: /portal/rma/' . (int)$id);
        exit;
    }

    // POST /portal/rma/{id}/received — partner confirms they've received the
    // repaired device back from Integra. Only valid when status is dispatched;
    // advances status to closed (terminal).
    public function rma_received(string $id): void {
        require_partner();
        $partner_id = current_partner_id();
        if (!$partner_id) { http_response_code(403); return; }

        $rma = $this->load_own_rma((int)$id, $partner_id);
        if (!$rma) { http_response_code(404); return; }

        if (($rma['status_code'] ?? '') !== 'dispatched') {
            $_SESSION['form_error'] = __('portal.cannot_close');
            header('Location: /portal/rma/' . (int)$id); exit;
        }

        $new_status_id = (int)db_val("SELECT id FROM rma_statuses WHERE code = 'closed' LIMIT 1");
        if (!$new_status_id) {
            $_SESSION['form_error'] = 'Internal: closed status is not configured.';
            header('Location: /portal/rma/' . (int)$id); exit;
        }

        $this->advance_status((int)$id, $new_status_id, 'Partner confirmed receipt — case closed.');
        $_SESSION['form_success'] = __('portal.receipt_confirmed');
        header('Location: /portal/rma/' . (int)$id);
        exit;
    }

    // Atomic status update: writes a history row and updates rma_requests.
    // If either fails the other is rolled back so status_id never drifts
    // away from its own history.
    private function advance_status(int $rma_id, int $new_status_id, string $note): void {
        db()->beginTransaction();
        try {
            db_insert('rma_status_history', [
                'rma_id'     => $rma_id,
                'status_id'  => $new_status_id,
                'changed_by' => current_user_id(),
                'note'       => $note,
            ]);
            db_update('rma_requests',
                ['status_id' => $new_status_id],
                'id = ?',
                [$rma_id]
            );
            db()->commit();
            audit('status_changed', 'rma_request', $rma_id);
        } catch (\Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            error_log('Portal status advance failed: ' . $e->getMessage());
            $_SESSION['form_error'] = __('portal.status_update_failed');
        }
    }

    // POST /portal/rma/{id}/comment — partner posts a comment on their RMA.
    // Partner-posted comments land with source='customer' / visibility='customer'
    // so they're visible on the staff RMA view, on this portal detail page,
    // and on the public /track/ tracking page.
    public function rma_comment(string $id): void {
        require_partner();
        $partner_id = current_partner_id();
        if (!$partner_id) { http_response_code(403); return; }

        // Ownership check: partner can only comment on their own RMAs.
        $rma = $this->load_own_rma((int)$id, $partner_id);
        if (!$rma) { http_response_code(404); return; }

        $body = trim($_POST['body'] ?? '');
        if ($body === '') {
            $_SESSION['form_error'] = __('portal.comment_required');
            header('Location: /portal/rma/' . (int)$id); exit;
        }
        // Guardrail — keep comments sane. 2000 chars is generous.
        if (mb_strlen($body) > 2000) $body = mb_substr($body, 0, 2000);

        $comment_id = db_insert('rma_comments', [
            'rma_id'     => (int)$id,
            'user_id'    => current_user_id(),
            'body'       => $body,
            'source'     => 'customer',
            'visibility' => 'customer',
        ]);
        audit('created', 'rma_comment', $comment_id);

        $_SESSION['form_success'] = __('portal.comment_posted');
        header('Location: /portal/rma/' . (int)$id);
        exit;
    }

    // GET /portal/rma/new — show the create form.
    public function rma_new(): void {
        require_partner();
        $partner_id = current_partner_id();
        if (!$partner_id) {
            http_response_code(403);
            echo __('portal.not_linked_contact');
            return;
        }

        $user    = current_user();
        $partner = db_row('SELECT * FROM partners WHERE id = ?', [$partner_id]);
        $brands  = db_rows('SELECT id, name FROM device_brands ORDER BY name');
        $models  = db_rows('SELECT id, name, brand_id FROM device_models WHERE is_active = 1 ORDER BY name');

        // Which of their own poslovnice this came from. Defaults to the one the
        // person is assigned to, so the common case needs no thought; still
        // changeable for anyone who covers more than one office.
        $branches   = partner_branches($partner_id);
        $my_branch  = current_partner_branch_id();

        // Flash state from a failed POST (sticky form)
        $error = $_SESSION['form_error'] ?? null;
        $old   = $_SESSION['form_old']   ?? [];
        unset($_SESSION['form_error'], $_SESSION['form_old']);

        $page_title = __('rma.new');
        include views_path('layout/header.php');
        include views_path('portal/rma_new.php');
        include views_path('layout/footer.php');
    }

    // POST /portal/rma/store — create customer + device + rma_request atomically.
    public function rma_store(): void {
        require_partner();
        $partner_id = current_partner_id();
        if (!$partner_id) {
            $_SESSION['form_error'] = __('portal.no_partner_link');
            header('Location: /portal/rma/new'); exit;
        }

        // Read + normalise input.
        $cust_name   = trim($_POST['cust_name']   ?? '');
        $cust_phone  = trim($_POST['cust_phone']  ?? '');
        $cust_email  = strtolower(trim($_POST['cust_email'] ?? ''));
        $model_id    = (int)($_POST['model_id'] ?? 0);
        $serial      = trim($_POST['serial_number'] ?? '');
        $imei        = trim($_POST['imei'] ?? '');
        $complaint   = trim($_POST['complaint'] ?? '');
        $acc_array   = (isset($_POST['accessories']) && is_array($_POST['accessories']))
                       ? array_values(array_filter($_POST['accessories'], 'is_string'))
                       : [];
        $acc_other   = trim($_POST['accessories_other'] ?? '');
        $is_warranty = !empty($_POST['is_warranty']);

        // Validate. Every RMA needs a customer name, a model, a complaint, and
        // at least one unique device identifier (serial or IMEI) so it can't
        // be confused with another RMA of the same model.
        $errs = [];
        if ($cust_name === '')   $errs[] = __('customers.name_required');
        if ($model_id <= 0)      $errs[] = __('portal.model_required');
        if ($complaint === '')   $errs[] = __('portal.complaint_required');
        if ($serial === '' && $imei === '') $errs[] = __('portal.serial_or_imei_required');

        if ($errs) {
            $_SESSION['form_error'] = implode(' ', $errs);
            $_SESSION['form_old']   = $_POST;
            header('Location: /portal/rma/new'); exit;
        }

        // Default location (first active). Partners don't have their own
        // location binding yet — simplest is to use the shop's primary one.
        $location_id = (int)db_val(
            "SELECT id FROM locations WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1"
        );
        if (!$location_id) {
            $_SESSION['form_error'] = __('portal.no_location');
            header('Location: /portal/rma/new'); exit;
        }

        // New RMAs start in "submitted" status.
        $status_id = (int)db_val("SELECT id FROM rma_statuses WHERE code = 'submitted' LIMIT 1");
        if (!$status_id) {
            $status_id = (int)db_val("SELECT id FROM rma_statuses WHERE is_terminal = 0 ORDER BY sort_order LIMIT 1");
        }

        $rma_number = $this->generate_portal_rma_number($location_id);
        $rma_id = null;

        // Atomic: customer → device → rma_request. If any step fails the
        // transaction rolls back so we never leave half-created rows behind.
        db()->beginTransaction();
        try {
            $customer_id = db_insert('customers', [
                'name'  => $cust_name,
                'phone' => $cust_phone ?: null,
                'email' => $cust_email ?: null,
            ]);
            audit('created', 'customer', $customer_id);

            $device_id = db_insert('devices', [
                'model_id'      => $model_id,
                'customer_id'   => $customer_id,
                'partner_id'    => $partner_id,
                'serial_number' => $serial ?: null,
                'imei'          => $imei   ?: null,
            ]);

            $rma_id = db_insert('rma_requests', [
                'rma_number'        => $rma_number,
                'status_id'         => $status_id,
                'location_id'       => $location_id,
                'partner_id'        => $partner_id,
                // Their office. Falls back to the branch on the person's account
                // when the form didn't carry one, so the figures stay complete
                // even if the field was left blank.
                'partner_branch_id' => valid_partner_branch_id($partner_id, $_POST['partner_branch_id'] ?? null)
                                       ?? current_partner_branch_id(),
                'customer_id'       => $customer_id,
                'device_id'         => $device_id,
                'complaint'         => $complaint,
                'is_warranty'       => $is_warranty ? 1 : 0,
                'submitted_by'      => current_user_id(),
                'accessories'       => !empty($acc_array) ? json_encode($acc_array) : null,
                'accessories_other' => $acc_other ?: null,
                'priority'          => 'normal',
            ]);
            audit('created', 'rma_request', $rma_id);

            db()->commit();
        } catch (\Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            error_log('Portal RMA create failed: ' . $e->getMessage());
            $_SESSION['form_error'] = __('portal.submit_failed');
            $_SESSION['form_old']   = $_POST;
            header('Location: /portal/rma/new'); exit;
        }

        $_SESSION['form_success'] = __('portal.rma_submitted', ['number' => $rma_number]);
        header('Location: /portal/rma/' . (int)$rma_id);
        exit;
    }

    // Mirror of rmaController::generate_rma_number() for portal submissions.
    // Kept private here to avoid exposing staff controller internals.
    private function generate_portal_rma_number(int $location_id): string {
        $loc  = db_row('SELECT code, city FROM locations WHERE id = ?', [$location_id]);
        $year = date('Y');

        if ($loc && $loc['code']) {
            $prefix = strtoupper($loc['code']);
        } elseif ($loc && $loc['city']) {
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $loc['city']), 0, 2));
        } else {
            $prefix = 'RMA';
        }

        $seq = (int)db_val(
            "SELECT COUNT(*) FROM rma_requests WHERE location_id = ? AND YEAR(created_at) = ?",
            [$location_id, $year]
        ) + 1;

        return "{$prefix}-{$year}-" . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    // GET /portal/logout — end session and return to portal login.
    public function logout(): void {
        audit('logout');
        session_destroy();
        header('Location: /portal/login');
        exit;
    }
}
