<?php
defined('RMS') or die('Direct access not permitted');

class RmaController {

    // ── List ──────────────────────────────────────────────────

    public function index(): void {
        require_login();
        require_permission('rma', 'view');

        $page_title = __('rma.title');
        $page       = min(100000, max(1, (int)($_GET['page'] ?? 1)));
        $per_page   = 25;
        $offset     = ($page - 1) * $per_page;
        $search     = trim($_GET['q'] ?? '');
        $status_f   = (int)($_GET['status'] ?? 0);
        $priority_f = trim($_GET['priority'] ?? '');

        $where  = 'r.deleted_at IS NULL';
        $params = [];

        // Location scope for lite admins. Uses location_scope_sql() rather than
        // building the IN list here: a user with no locations yet (a freshly
        // installed system) would otherwise produce `location_id IN ()`, a SQL
        // syntax error. The helper emits `1=0` for that case.
        $loc_ids = allowed_location_ids();
        if ($loc_ids !== null) {
            $where .= ' AND ' . location_scope_sql('r');
            $params = array_merge($params, $loc_ids);
        }

        // Partners only ever see RMAs linked to their own partner account.
        // An unlinked partner (no pivot row) matches partner_id = 0, i.e. none.
        if ((current_user()['role'] ?? '') === 'partner') {
            $where   .= ' AND r.partner_id = ?';
            $params[] = current_partner_id() ?? 0;
        }

        if ($search) {
            $where   .= ' AND (r.rma_number LIKE ? OR c.name LIKE ? OR p.name LIKE ? OR d.imei LIKE ? OR d.serial_number LIKE ?)';
            for ($i = 0; $i < 5; $i++) { $params[] = "%{$search}%"; }
        }
        if ($status_f) {
            $where   .= ' AND r.status_id = ?';
            $params[] = $status_f;
        }
        if ($priority_f) {
            $where   .= ' AND r.priority = ?';
            $params[] = $priority_f;
        }

        $total = (int) db_val("SELECT COUNT(*) FROM rma_requests r
                                LEFT JOIN customers c ON c.id = r.customer_id
                                LEFT JOIN partners p ON p.id = r.partner_id
                                LEFT JOIN devices d ON d.id = r.device_id
                                WHERE {$where}", $params);

        $rmas = db_rows("SELECT r.*, s.code as status_code, s.label as status_label, s.color as status_color,
                                c.name as customer_name, p.name as partner_name,
                                l.name as location_name,
                                u.name as tech_name
                         FROM rma_requests r
                         JOIN rma_statuses s ON s.id = r.status_id
                         LEFT JOIN customers c ON c.id = r.customer_id
                         LEFT JOIN partners p ON p.id = r.partner_id
                         LEFT JOIN locations l ON l.id = r.location_id
                         LEFT JOIN users u ON u.id = r.assigned_tech
                         LEFT JOIN devices d ON d.id = r.device_id
                         WHERE {$where}
                         ORDER BY r.created_at DESC
                         LIMIT {$per_page} OFFSET {$offset}", $params);

        $statuses  = db_rows('SELECT * FROM rma_statuses ORDER BY sort_order');
        $success   = $_SESSION['form_success'] ?? null;
        $error     = $_SESSION['form_error'] ?? null;
        unset($_SESSION['form_success'], $_SESSION['form_error']);

        // Live search / filter / pagination: return just the results fragment.
        if (($_GET['ajax'] ?? '') === '1') {
            include views_path('rma/_results.php');
            return;
        }

        include views_path('layout/header.php');
        include views_path('rma/index.php');
        include views_path('layout/footer.php');
    }

    // ── Create form ───────────────────────────────────────────

    public function create(): void {
        require_login();
        require_permission('rma', 'create');

        $page_title            = __('rma.new');
        $breadcrumb_parent     = __('rma.title');
        $breadcrumb_parent_url = '/rma';
        $customers  = db_rows('SELECT id, name, phone, email FROM customers WHERE deleted_at IS NULL ORDER BY name');
        $partners   = db_rows('SELECT id, name FROM partners WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
        // Every active branch, keyed by partner — the form filters them client
        // side when a partner is picked, so no round trip is needed.
        $partner_branches = db_rows(
            'SELECT id, partner_id, name, city FROM partner_branches
              WHERE deleted_at IS NULL AND is_active = 1
              ORDER BY name'
        );
        $brands     = db_rows('SELECT * FROM device_brands WHERE is_active = 1 ORDER BY name');
        $models     = db_rows('SELECT m.*, b.name as brand_name FROM device_models m JOIN device_brands b ON b.id = m.brand_id WHERE m.is_active = 1 ORDER BY b.name, m.name');
        $categories = db_rows('SELECT * FROM device_categories WHERE is_active = 1 ORDER BY sort_order, name');
        $technicians = db_rows("SELECT id, name FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY name");
        $locations  = db_rows('SELECT * FROM locations WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
        $statuses   = db_rows("SELECT * FROM rma_statuses WHERE is_terminal = 0 ORDER BY sort_order");

        $user       = current_user();
        $error      = $_SESSION['form_error'] ?? null;
        unset($_SESSION['form_error']);

        include views_path('layout/header.php');
        include views_path('rma/create.php');
        include views_path('layout/footer.php');
    }

    // ── Store ─────────────────────────────────────────────────

    public function store(): void {
        require_login();
        require_permission('rma', 'create');

        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $partner_id  = (int)($_POST['partner_id'] ?? 0);
        $location_id = (int)($_SESSION['user']['location_id'] ?? 0);
        if (!$location_id) {
            // fallback: first active location
            $location_id = (int)db_val('SELECT id FROM locations WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1');
        }
        $complaint   = trim($_POST['complaint'] ?? '');
        $accessories = $_POST['accessories'] ?? [];
        $acc_other   = trim($_POST['accessories_other'] ?? '');

        // Handle walk-in customer creation
        if (!$customer_id && trim($_POST['walkin_name'] ?? '')) {
            $walkin_name    = trim($_POST['walkin_name']);
            $country_code   = preg_replace('/\D/', '', $_POST['walkin_phone_country_code'] ?? '382');
            $walkin_phone   = normalize_phone(trim($_POST['walkin_phone'] ?? ''), $country_code);
            $walkin_email   = strtolower(trim($_POST['walkin_email'] ?? ''));
            $walkin_city    = trim($_POST['walkin_city'] ?? '');
            $walkin_address = trim($_POST['walkin_address'] ?? '');

            // Check for duplicates server-side
            $match = find_customer_match($walkin_name, $walkin_phone, $walkin_email);

            if ($match['customer'] && $match['confidence'] === 'exact') {
                // Auto-link to exact match
                $customer_id = (int)$match['customer']['id'];
            } else {
                // Create new customer
                $customer_id = db_insert('customers', [
                    'name'     => $walkin_name,
                    'phone'    => $walkin_phone,
                    'email'    => $walkin_email ?: null,
                    'city'     => $walkin_city,
                    'address'  => $walkin_address,
                    'zip_code' => trim($_POST['walkin_zip'] ?? ''),
                ]);
                audit('created', 'customer', $customer_id);
            }
        }

        if (!$customer_id || !$complaint) {
            $_SESSION['form_error'] = __('rma.customer_complaint_required');
            header('Location: /rma/create');
            exit;
        }

        // Every RMA must identify the device uniquely — either via IMEI or
        // a serial number. A model alone is not enough: two customers with
        // the same phone model would be indistinguishable at hand-over.
        $submitted_imei   = trim($_POST['imei'] ?? '');
        $submitted_serial = trim($_POST['serial_number'] ?? '');
        $existing_device_id = (int)($_POST['device_id'] ?? 0);

        if (!$existing_device_id && !$submitted_imei && !$submitted_serial) {
            $_SESSION['form_error'] = __('rma.imei_or_serial_required');
            header('Location: /rma/create');
            exit;
        }

        // Device: use existing or create new
        $device_id = null;
        if ($existing_device_id) {
            $device_id = $existing_device_id;
        } elseif (!empty($_POST['model_id'])) {
            $device_id = db_insert('devices', [
                'model_id'        => (int)$_POST['model_id'],
                'customer_id'     => $customer_id ?: null,
                'partner_id'      => $partner_id  ?: null,
                'serial_number' => trim($_POST['serial_number'] ?? '') ?: null,
                'imei'          => trim($_POST['imei'] ?? '') ?: null,
                'color'         => trim($_POST['color'] ?? '') ?: null,
                'capacity'      => trim($_POST['capacity'] ?? '') ?: null,
                'purchase_date'   => $this->parse_date($_POST['purchase_date'] ?? ''),
                'warranty_expiry' => $this->parse_date($_POST['warranty_expiry'] ?? ''),
            ]);
        }

        // Get first open status
        $first_status = db_row("SELECT id FROM rma_statuses WHERE code = 'submitted' LIMIT 1");
        $status_id = $first_status ? $first_status['id']
                   : db_val('SELECT id FROM rma_statuses WHERE is_terminal = 0 ORDER BY sort_order LIMIT 1');

        if (!empty($_POST['status_id'])) $status_id = (int)$_POST['status_id'];

        $rma_number = $this->generate_rma_number($location_id);

        $rma_id = db_insert('rma_requests', [
            'location_id'         => $location_id,
            'device_id'           => $device_id,
            'customer_id'         => $customer_id,
            'partner_id'          => $partner_id  ?: null,
            'partner_branch_id'   => valid_partner_branch_id($partner_id, $_POST['partner_branch_id'] ?? null),
            'submitted_by'        => current_user_id(),
            'assigned_tech'       => (int)($_POST['assigned_tech'] ?? 0) ?: null,
            'status_id'           => $status_id,
            'rma_number'          => $rma_number,
            'complaint'           => $complaint,
            'service_box'         => trim($_POST['service_box'] ?? '') ?: null,
            'accessories'         => !empty($accessories) ? json_encode($accessories) : null,
            'accessories_other'   => $acc_other ?: null,
            'is_warranty'         => ($_POST['is_warranty'] ?? '0') === '1' ? 1 : 0,
            'warranty_refusal'    => !empty($_POST['warranty_refusal']) ? json_encode($_POST['warranty_refusal']) : null,
            'priority'            => $_POST['priority'] ?? 'normal',
            'estimated_completion' => $this->parse_date($_POST['estimated_completion'] ?? '') 
                                     ?: ($days = (int)($_POST['estimated_completion_days'] ?? 0)
                                        ? date('Y-m-d', strtotime("+{$days} days"))
                                        : null),
        ]);

        // Generate tracking token
        db_insert('rma_tracking_tokens', [
            'rma_id' => $rma_id,
            'token'  => bin2hex(random_bytes(32)),
        ]);

        // Log initial status
        db_insert('rma_status_history', [
            'rma_id'     => $rma_id,
            'status_id'  => $status_id,
            'changed_by' => current_user_id(),
            'note'       => 'RMA created.',
        ]);

        audit('created', 'rma', $rma_id);

        // Send receipt email to customer
        send_rma_receipt($rma_id);

        $_SESSION['form_success'] = __('rma.created_with_number', [':number'=>$rma_number]);
        header("Location: /rma/{$rma_id}");
        exit;
    }

    // ── View ──────────────────────────────────────────────────

    /**
     * Enforce location scope for a single RMA: 404 if it doesn't exist,
     * 403 if it belongs to a location outside the current user's scope.
     * Super Admin (allowed_location_ids() === null) is never restricted.
     */
    private function guard_rma_location(int $id): void {
        $row = db_row('SELECT location_id FROM rma_requests WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$row) { http_response_code(404); include views_path('errors/404.php'); exit; }
        $allowed = allowed_location_ids();
        if ($allowed !== null && !in_array((int)$row['location_id'], array_map('intval', $allowed), true)) {
            http_response_code(403); include views_path('errors/403.php'); exit;
        }
    }

    public function view(string $id): void {
        require_login();
        require_permission('rma', 'view');
        $this->guard_rma_location((int)$id);

        $rma = db_row("SELECT r.*, s.label as status_label, s.color as status_color, s.code as status_code,
                              c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                              p.name as partner_name,
                              pb.name as partner_branch_name,
                              l.name as location_name,
                              u.name as tech_name,
                              dm.name as model_name, db2.name as brand_name,
                              d.serial_number, d.imei, d.color as device_color,
                              d.purchase_date, d.warranty_expiry,
                              t.token as tracking_token
                       FROM rma_requests r
                       JOIN rma_statuses s ON s.id = r.status_id
                       LEFT JOIN customers c ON c.id = r.customer_id
                       LEFT JOIN partners p ON p.id = r.partner_id
                       LEFT JOIN partner_branches pb ON pb.id = r.partner_branch_id
                       LEFT JOIN locations l ON l.id = r.location_id
                       LEFT JOIN users u ON u.id = r.assigned_tech
                       LEFT JOIN devices d ON d.id = r.device_id
                       LEFT JOIN device_models dm ON dm.id = d.model_id
                       LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                       LEFT JOIN rma_tracking_tokens t ON t.rma_id = r.id
                       WHERE r.id = ? AND r.deleted_at IS NULL", [(int)$id]);

        if (!$rma) { http_response_code(404); include views_path('errors/404.php'); return; }

        // Partners may only open RMAs that belong to their own partner account.
        if ((current_user()['role'] ?? '') === 'partner'
            && (int)($rma['partner_id'] ?? 0) !== (int)(current_partner_id() ?? 0)) {
            http_response_code(403); include views_path('errors/403.php'); return;
        }

        $history  = db_rows("SELECT h.*, s.label as status_label, s.color as status_color,
                                     u.name as changed_by_name
                              FROM rma_status_history h
                              JOIN rma_statuses s ON s.id = h.status_id
                              LEFT JOIN users u ON u.id = h.changed_by
                              WHERE h.rma_id = ? ORDER BY h.created_at ASC", [(int)$id]);

        $comments = db_rows("SELECT c.*, u.name as author_name, u.role as author_role
                              FROM rma_comments c
                              LEFT JOIN users u ON u.id = c.user_id
                              WHERE c.rma_id = ? AND c.deleted_at IS NULL
                              ORDER BY c.created_at ASC", [(int)$id]);

        $attachments = db_rows("SELECT * FROM rma_attachments
                                 WHERE rma_id = ? AND deleted_at IS NULL
                                 ORDER BY created_at ASC", [(int)$id]);

        $statuses    = db_rows('SELECT * FROM rma_statuses ORDER BY sort_order');
        $technicians = db_rows("SELECT id, name FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY name");

        // Logistics: shipments for this RMA + couriers for the add/edit forms.
        $shipments = db_rows("SELECT sh.*, c.name AS courier_name, c.tracking_url AS courier_tracking_url
                              FROM delivery_shipments sh
                              LEFT JOIN couriers c ON c.id = sh.courier_id
                              WHERE sh.rma_id = ?
                              ORDER BY sh.direction, sh.created_at DESC", [(int)$id]);
        $couriers  = db_rows('SELECT id, name, tracking_url FROM couriers WHERE is_active = 1 ORDER BY name');
        // Partner's preferred courier pre-selects new shipments (process usually
        // starts from a partner).
        $partner_courier_id = (int) db_val(
            'SELECT default_courier_id FROM partners WHERE id = ?', [(int)($rma['partner_id'] ?? 0)]
        );

        $page_title            = $rma['rma_number'];
        $breadcrumb_parent     = __('rma.title');
        $breadcrumb_parent_url = '/rma';
        $success    = $_SESSION['form_success'] ?? null;
        $error      = $_SESSION['form_error'] ?? null;
        unset($_SESSION['form_success'], $_SESSION['form_error']);

        include views_path('layout/header.php');
        include views_path('rma/view.php');
        include views_path('layout/footer.php');
    }

    // ── Update status ─────────────────────────────────────────

    public function update(string $id): void {
        require_login();
        require_permission('rma', 'edit');
        $this->guard_rma_location((int)$id);

        $rma = db_row('SELECT * FROM rma_requests WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$rma) { http_response_code(404); return; }

        $action = $_POST['action'] ?? 'status';

        if ($action === 'status') {
            $status_id = (int)($_POST['status_id'] ?? 0);
            if ($status_id && $status_id !== (int)$rma['status_id']) {
                db_update('rma_requests', ['status_id' => $status_id], 'id = ?', [(int)$id]);
                db_insert('rma_status_history', [
                    'rma_id'     => (int)$id,
                    'status_id'  => $status_id,
                    'changed_by' => current_user_id(),
                    'note'       => trim($_POST['note'] ?? ''),
                ]);
                audit_change('rma', (int)$id, ['status_id' => $rma['status_id']], ['status_id' => $status_id]);
                $_SESSION['form_success'] = __('rma.status_updated');
            }
        }

        if ($action === 'assign') {
            $tech_id = (int)($_POST['assigned_tech'] ?? 0) ?: null;
            db_update('rma_requests', ['assigned_tech' => $tech_id], 'id = ?', [(int)$id]);
            $_SESSION['form_success'] = __('rma.tech_assigned');
        }

        if ($action === 'details') {
            db_update('rma_requests', [
                'priority'             => $_POST['priority'] ?? $rma['priority'],
                'estimated_completion' => $_POST['estimated_completion'] ?: null,
                'assigned_tech'        => (int)($_POST['assigned_tech'] ?? 0) ?: null,
                'is_warranty'          => isset($_POST['is_warranty']) ? 1 : 0,
                'diagnosis'            => trim($_POST['diagnosis'] ?? ''),
            ], 'id = ?', [(int)$id]);
            $_SESSION['flash'] = ['type'=>'success','message'=>__('rma.details_updated')];
        }

        header("Location: /rma/{$id}");
        exit;
    }

    // ── Comment ───────────────────────────────────────────────

    public function comment(string $id): void {
        require_login();
        require_permission('rma', 'edit');
        $this->guard_rma_location((int)$id);

        $body = trim($_POST['body'] ?? '');
        if (!$body) {
            header("Location: /rma/{$id}");
            exit;
        }

        // Every entry in this log is visible to the customer on the tracking
        // page; `kind` only tells us whose words these are.
        //   staff    -> staff observation/update, authored by staff
        //   customer -> customer said this (staff logs it on their behalf)
        //
        // Legacy handling: older forms posted `visibility=internal|customer`.
        // Those are preserved as-is so existing rows / external callers keep
        // working — but the new button set always writes visibility=customer.
        $kind = $_POST['kind'] ?? null;
        if ($kind === 'customer') {
            $visibility = 'customer';
            $source     = 'customer';
        } elseif ($kind === 'staff') {
            $visibility = 'customer';
            $source     = 'staff';
        } else {
            // Legacy path — respect the older visibility flag if no kind set.
            $visibility = in_array($_POST['visibility'] ?? '', ['internal', 'customer'], true)
                ? $_POST['visibility'] : 'internal';
            $source     = 'staff';
        }

        db_insert('rma_comments', [
            'rma_id'     => (int)$id,
            'user_id'    => current_user_id(),
            'body'       => $body,
            'visibility' => $visibility,
            'source'     => $source,
        ]);
        audit('commented', 'rma', (int)$id);
        header("Location: /rma/{$id}");
        exit;
    }

    // ── Device search (JSON) ──────────────────────────────────

    // ── Vendor warranty lookup (GSX for Apple, etc.) ──────────────────────
    public function warranty_check(string $id): void {
        require_login();
        require_permission('rma', 'edit');
        header('Content-Type: application/json');

        $rma = db_row(
            "SELECT r.id, r.location_id, d.imei, d.serial_number, dm.brand_id
             FROM rma_requests r
             LEFT JOIN devices d ON d.id = r.device_id
             LEFT JOIN device_models dm ON dm.id = d.model_id
             WHERE r.id = ? AND r.deleted_at IS NULL",
            [(int)$id]
        );
        if (!$rma) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'RMA not found']); return; }

        $allowed = allowed_location_ids();
        if ($allowed !== null && !in_array((int)$rma['location_id'], array_map('intval', $allowed), true)) {
            http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Forbidden']); return;
        }

        $identifier = $rma['imei'] ?: $rma['serial_number'];
        if (!$identifier) {
            echo json_encode(['ok' => false, 'error' => 'No IMEI or serial on this RMA']);
            return;
        }

        // Vendor resolution: explicit override via POST (admin picks vendor)
        // → fall back to device's brand -> vendor mapping (future) →
        // default to Apple for now since it's the only adapter shipping.
        $vendor_id = (int)($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) {
            $vendor_id = (int) db_val("SELECT id FROM vendors WHERE slug = 'apple' AND is_active = 1 LIMIT 1");
        }
        if (!$vendor_id) { echo json_encode(['ok' => false, 'error' => 'No active vendor configured']); return; }

        $result = vendor_warranty_lookup($vendor_id, $identifier);
        if (!$result) { echo json_encode(['ok' => false, 'error' => 'Vendor adapter not available']); return; }

        audit('warranty_checked', 'rma', (int)$id,
              ['new' => ['vendor_id' => $vendor_id, 'status' => $result['status']]]);

        echo json_encode(['ok' => true, 'vendor_id' => $vendor_id, 'result' => $result]);
    }

    public function receipt(string $id): void {
        require_login();
        require_permission('rma', 'view');
        $this->guard_rma_location((int)$id);

        // Create storage/tmp if needed for mPDF
        $tmp = ROOT . '/storage/tmp';
        if (!is_dir($tmp)) @mkdir($tmp, 0755, true);

        // ?engine=mpdf forces direct PDF download via mPDF (triggered by
        // the "Save as PDF" button). Anything else falls back to whatever
        // the pdf_engine setting dictates (default HTML print view).
        $engine = in_array($_GET['engine'] ?? '', ['mpdf', 'html'], true) ? $_GET['engine'] : null;
        generate_rma_receipt_pdf((int)$id, $_GET['mode'] ?? 'view', $engine);
    }

    public function send_receipt(string $id): void {
        require_login();
        require_permission('rma', 'view');
        $this->guard_rma_location((int)$id);

        $sent = send_rma_receipt((int)$id);
        $_SESSION['form_success'] = $sent
            ? __('rma.receipt_sent')
            : __('rma.receipt_failed');
        header("Location: /rma/{$id}");
        exit;
    }

    // ── Shipments (logistics) ─────────────────────────────────

    public function shipment_store(string $rma_id): void {
        require_login();
        require_permission('shipments', 'create');
        $rid = (int) $rma_id;
        if (!db_val('SELECT id FROM rma_requests WHERE id = ? AND deleted_at IS NULL', [$rid])) {
            http_response_code(404); include views_path('errors/404.php'); return;
        }
        $data = $this->shipment_data();
        $data['rma_id'] = $rid;
        db_insert('delivery_shipments', $data);
        audit('shipment_created', 'rma', $rid);
        $_SESSION['form_success'] = __('ship.saved');
        header("Location: /rma/{$rid}");
        exit;
    }

    public function shipment_update(string $rma_id): void {
        require_login();
        require_permission('shipments', 'edit');
        $rid = (int) $rma_id;
        $sid = (int) ($_POST['id'] ?? 0);
        if (db_row('SELECT id FROM delivery_shipments WHERE id = ? AND rma_id = ?', [$sid, $rid])) {
            db_update('delivery_shipments', $this->shipment_data(), 'id = ?', [$sid]);
            audit('shipment_updated', 'rma', $rid);
            $_SESSION['form_success'] = __('ship.saved');
        }
        header("Location: /rma/{$rid}");
        exit;
    }

    public function shipment_delete(string $rma_id): void {
        require_login();
        require_permission('shipments', 'edit');
        $rid = (int) $rma_id;
        $sid = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM delivery_shipments WHERE id = ? AND rma_id = ?')->execute([$sid, $rid]);
        audit('shipment_deleted', 'rma', $rid);
        header("Location: /rma/{$rid}");
        exit;
    }

    // Printable shipping label (HTML print view — user prints / saves as PDF).
    public function shipment_label(string $rma_id, string $sid): void {
        require_login();
        require_permission('shipments', 'view');
        $rid = (int) $rma_id;
        $shipment = db_row(
            "SELECT sh.*, c.name AS courier_name FROM delivery_shipments sh
             LEFT JOIN couriers c ON c.id = sh.courier_id
             WHERE sh.id = ? AND sh.rma_id = ?", [(int) $sid, $rid]
        );
        if (!$shipment) { http_response_code(404); include views_path('errors/404.php'); return; }
        $rma = db_row(
            "SELECT r.*, cu.name AS customer_name, cu.phone AS customer_phone,
                    cu.address AS customer_address, cu.city AS customer_city, cu.zip_code AS customer_zip,
                    p.name AS partner_name, p.address AS partner_address, p.city AS partner_city, p.phone AS partner_phone,
                    l.name AS location_name, l.address AS location_address, l.city AS location_city, l.phone AS location_phone
             FROM rma_requests r
             LEFT JOIN customers cu ON cu.id = r.customer_id
             LEFT JOIN partners  p  ON p.id  = r.partner_id
             LEFT JOIN locations l  ON l.id  = r.location_id
             WHERE r.id = ?", [$rid]
        );
        include views_path('rma/shipment_label.php');
    }

    // Column values for a shipment, shared by store + update.
    private function shipment_data(): array {
        $dir     = in_array($_POST['direction'] ?? '', ['inbound', 'outbound'], true) ? $_POST['direction'] : 'inbound';
        $status  = in_array($_POST['status'] ?? '', SHIPMENT_STATUSES, true) ? $_POST['status'] : 'pending';
        $courier = (int) ($_POST['courier_id'] ?? 0) ?: null;
        $cost    = trim((string) ($_POST['cost'] ?? '')) === '' ? null : round((float) $_POST['cost'], 2);
        return [
            'direction'       => $dir,
            'courier_id'      => $courier,
            'tracking_number' => trim($_POST['tracking_number'] ?? '') ?: null,
            'status'          => $status,
            'cost'            => $cost,
            'notes'           => trim($_POST['notes'] ?? '') ?: null,
            'dispatched_at'   => trim($_POST['dispatched_at'] ?? '') ?: null,
            'delivered_at'    => trim($_POST['delivered_at'] ?? '') ?: null,
        ];
    }

    public function customer_search(): void {
        require_login();
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 3) { echo json_encode([]); exit; }

        // Strip to digits only for phone matching
        $digits = preg_replace('/\D/', '', $q);
        $dlike  = strlen($digits) >= 3 ? "%{$digits}%" : null;
        $elike  = "%{$q}%";

        if ($dlike) {
            // Match digits-stripped phone OR email as typed
            $customers = db_rows(
                "SELECT id, name, phone, email, city, address FROM customers
                 WHERE deleted_at IS NULL
                   AND (REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ? OR email LIKE ?)
                 ORDER BY name LIMIT 10",
                [$dlike, $elike]
            );
        } else {
            $customers = db_rows(
                "SELECT id, name, phone, email, city, address FROM customers
                 WHERE deleted_at IS NULL AND email LIKE ?
                 ORDER BY name LIMIT 10",
                [$elike]
            );
        }

        foreach ($customers as &$c) {
            $c['phone_display'] = format_phone($c['phone'] ?? '');
        }
        echo json_encode($customers);
        exit;
    }

    public function device_search(): void {
        require_login();
        $sn   = trim($_GET['sn'] ?? '');
        $imei = trim($_GET['imei'] ?? '');
        header('Content-Type: application/json');

        if (!$sn && !$imei) { echo json_encode(['id' => null]); exit; }

        $where  = $sn ? 'd.serial_number = ?' : 'd.imei = ?';
        $param  = $sn ?: $imei;

        $device = db_row("SELECT d.id, d.serial_number, d.imei, dm.name as model, db2.name as brand
                          FROM devices d
                          JOIN device_models dm ON dm.id = d.model_id
                          JOIN device_brands db2 ON db2.id = dm.brand_id
                          WHERE {$where} LIMIT 1", [$param]);

        echo json_encode($device ?: ['id' => null]);
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────

    // ── Helpers ───────────────────────────────────────────────

    private function parse_date(string $val): ?string {
        $val = trim($val);
        if (!$val) return null;
        // yyyy-mm-dd (from date picker) — pass through
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;
        // dd-mm-yyyy
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $val, $m))
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        // dd-Mon-yyyy (display format)
        $months = ['Jan'=>'01','Feb'=>'02','Mar'=>'03','Apr'=>'04','May'=>'05','Jun'=>'06',
                   'Jul'=>'07','Aug'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dec'=>'12'];
        if (preg_match('/^(\d{2})-([A-Za-z]{3})-(\d{4})$/', $val, $m)) {
            $mn = $months[ucfirst(strtolower($m[2]))] ?? null;
            if ($mn) return $m[3] . '-' . $mn . '-' . $m[1];
        }
        return null;
    }

    /**
     * Claim the next RMA sequence number.
     *
     * Counting existing rows was wrong in three ways: two people saving at the
     * same moment got the same number, permanently deleting an RMA made the
     * count fall and reissue a number, and the count was per-location while the
     * format may not include {LOC}. All three produced a unique-constraint error
     * in front of whoever was at the counter.
     *
     * A row is locked FOR UPDATE instead: portable across Postgres and MySQL,
     * and any second caller waits rather than reading a stale value.
     *
     * scope 'global' never resets; scope '<year>' restarts each January, chosen
     * by the rma_number_reset_yearly setting.
     */
    private function next_rma_sequence(string $year): int {
        $scope = setting('rma_number_reset_yearly', '0') === '1' ? $year : 'global';

        $own = !db()->inTransaction();
        if ($own) db()->beginTransaction();

        try {
            // Create the row if this scope is new (first RMA of a new year).
            //
            // Must not be "try INSERT, catch duplicate": in Postgres a
            // unique-violation aborts the WHOLE transaction, and catching the
            // exception does not recover it — every later statement then fails
            // with "current transaction is aborted". Let the database swallow
            // the conflict instead, so no error is ever raised.
            $sql = db_is_pg()
                ? 'INSERT INTO rma_counters (scope, next_value) VALUES (?, 1) ON CONFLICT (scope) DO NOTHING'
                : 'INSERT IGNORE INTO rma_counters (scope, next_value) VALUES (?, 1)';
            db()->prepare($sql)->execute([$scope]);

            $st = db()->prepare('SELECT next_value FROM rma_counters WHERE scope = ? FOR UPDATE');
            $st->execute([$scope]);
            $seq = (int) $st->fetchColumn();
            if ($seq < 1) $seq = 1;

            db()->prepare('UPDATE rma_counters SET next_value = ? WHERE scope = ?')
                ->execute([$seq + 1, $scope]);

            if ($own) db()->commit();
            return $seq;
        } catch (Throwable $e) {
            if ($own && db()->inTransaction()) db()->rollBack();
            throw $e;
        }
    }


    private function generate_rma_number(int $location_id): string {
        $loc  = db_row('SELECT code, name, city FROM locations WHERE id = ?', [$location_id]);
        $year = date('Y');

        // Use location code, fallback to first 2 letters of city
        if ($loc && $loc['code']) {
            $prefix = strtoupper($loc['code']);
        } elseif ($loc && $loc['city']) {
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $loc['city']), 0, 2));
        } else {
            $prefix = 'RMA';
        }

        // One global counter, independent of location. {LOC} and the year in the
        // format are for humans reading the number - they say where and when it
        // came from, but they do not split the sequence.
        $seq = $this->next_rma_sequence($year);

        // Build from the admin-configured format (Settings → General → RMA
        // numbering). Tokens are replaced case-sensitively.
        $format = trim((string) setting('rma_number_format', '{LOC}-{YEAR}-{SEQ5}'));
        if ($format === '') { $format = '{LOC}-{YEAR}-{SEQ5}'; }

        return strtr($format, [
            '{LOC}'  => $prefix,
            '{YEAR}' => $year,                                  // 4-digit year
            '{YYYY}' => $year,                                  // 4-digit year (alias)
            '{YY}'   => substr($year, -2),                      // 2-digit year
            '{SEQ4}' => str_pad((string)$seq, 4, '0', STR_PAD_LEFT),
            '{SEQ5}' => str_pad((string)$seq, 5, '0', STR_PAD_LEFT),
            '{SEQ6}' => str_pad((string)$seq, 6, '0', STR_PAD_LEFT),
        ]);
    }
}
