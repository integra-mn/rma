<?php
defined('RMS') or die('Direct access not permitted');

class RepairController {

    // ── List ──────────────────────────────────────────────────

    public function index(): void {
        require_login();
        require_permission('repair', 'view');

        $page_title = __('nav.repairs');
        $page       = min(100000, max(1, (int)($_GET['page'] ?? 1)));
        $per_page   = 25;
        $offset     = ($page - 1) * $per_page;
        $search     = trim($_GET['q'] ?? '');
        $status_f   = trim($_GET['status'] ?? '');

        $where  = 'j.deleted_at IS NULL';
        $params = [];

        // Use location_scope_sql() rather than building the IN list here: a user
        // with no locations yet (a freshly installed system, before any location
        // is created) would otherwise produce `location_id IN ()`, which is a SQL
        // syntax error. The helper emits `1=0` for that case.
        $loc_ids = allowed_location_ids();
        if ($loc_ids !== null) {
            $where .= ' AND ' . location_scope_sql('j');
            $params = array_merge($params, $loc_ids);
        }

        // Partners only ever see repairs for RMAs submitted by their own company.
        if ((current_user()['role'] ?? '') === 'partner') {
            $where   .= ' AND r.partner_id = ?';
            $params[] = current_partner_id() ?? 0;
        }

        if ($search) {
            $where   .= ' AND (r.rma_number LIKE ? OR c.name LIKE ? OR u.name LIKE ? OR d.imei LIKE ? OR d.serial_number LIKE ?)';
            for ($i = 0; $i < 5; $i++) { $params[] = "%{$search}%"; }
        }
        if ($status_f) {
            $where   .= ' AND s.code = ?';
            $params[] = $status_f;
        }

        $total = (int) db_val("SELECT COUNT(*) FROM repair_jobs j
                                JOIN rma_requests r ON r.id = j.rma_id
                                JOIN repair_statuses s ON s.id = j.status_id
                                LEFT JOIN customers c ON c.id = r.customer_id
                                LEFT JOIN users u ON u.id = j.technician_id
                                LEFT JOIN devices d ON d.id = r.device_id
                                WHERE {$where}", $params);

        $jobs = db_rows("SELECT j.*, s.label as status_label, s.color as status_color, s.code as status_code,
                                r.rma_number, r.priority,
                                c.name as customer_name,
                                u.name as tech_name,
                                l.name as location_name,
                                (SELECT SUM(minutes) FROM repair_time_logs WHERE job_id = j.id) as total_minutes,
                                (SELECT COUNT(*) FROM part_usage WHERE job_id = j.id) as parts_used
                         FROM repair_jobs j
                         JOIN rma_requests r ON r.id = j.rma_id
                         JOIN repair_statuses s ON s.id = j.status_id
                         LEFT JOIN customers c ON c.id = r.customer_id
                         LEFT JOIN users u ON u.id = j.technician_id
                         LEFT JOIN locations l ON l.id = j.location_id
                         LEFT JOIN devices d ON d.id = r.device_id
                         WHERE {$where}
                         ORDER BY j.created_at DESC
                         LIMIT {$per_page} OFFSET {$offset}", $params);

        $statuses = db_rows('SELECT * FROM repair_statuses ORDER BY sort_order');
        $success  = $_SESSION['form_success'] ?? null;
        unset($_SESSION['form_success']);

        // Live search / filter / pagination: return just the results fragment.
        if (($_GET['ajax'] ?? '') === '1') {
            include views_path('repair/_results.php');
            return;
        }

        include views_path('layout/header.php');
        include views_path('repair/index.php');
        include views_path('layout/footer.php');
    }

    // ── View ──────────────────────────────────────────────────

    /**
     * Enforce location scope for a single repair job: 404 if it doesn't
     * exist, 403 if its location is outside the current user's scope.
     * Super Admin (allowed_location_ids() === null) is never restricted.
     */
    private function guard_job_location(int $id): void {
        $row = db_row('SELECT location_id FROM repair_jobs WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$row) { http_response_code(404); include views_path('errors/404.php'); exit; }
        $allowed = allowed_location_ids();
        if ($allowed !== null && !in_array((int)$row['location_id'], array_map('intval', $allowed), true)) {
            http_response_code(403); include views_path('errors/403.php'); exit;
        }
    }

    public function view(string $id): void {
        require_login();
        require_permission('repair', 'view');
        $this->guard_job_location((int)$id);

        $job = db_row("SELECT j.*,
                              s.label as status_label, s.color as status_color, s.code as status_code,
                              r.rma_number, r.complaint, r.priority, r.is_warranty, r.warranty_refusal, r.partner_id,
                              c.name as customer_name,
                              u.name as tech_name,
                              l.name as location_name,
                              dm.name as model_name, dm.category_id AS device_category_id,
                              db2.id AS device_brand_id, db2.name as brand_name,
                              d.serial_number, d.imei,
                              p.name as partner_name
                       FROM repair_jobs j
                       JOIN rma_requests r ON r.id = j.rma_id
                       JOIN repair_statuses s ON s.id = j.status_id
                       LEFT JOIN customers c ON c.id = r.customer_id
                       LEFT JOIN users u ON u.id = j.technician_id
                       LEFT JOIN locations l ON l.id = j.location_id
                       LEFT JOIN devices d ON d.id = r.device_id
                       LEFT JOIN device_models dm ON dm.id = d.model_id
                       LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                       LEFT JOIN partners p ON p.id = r.partner_id
                       WHERE j.id = ? AND j.deleted_at IS NULL", [(int)$id]);

        if (!$job) { http_response_code(404); include views_path('errors/404.php'); return; }

        // Partners may only open repairs for RMAs that belong to their company.
        if ((current_user()['role'] ?? '') === 'partner'
            && (int)($job['partner_id'] ?? 0) !== (int)(current_partner_id() ?? 0)) {
            http_response_code(403); include views_path('errors/403.php'); return;
        }

        $time_logs = db_rows("SELECT t.*, u.name as user_name
                              FROM repair_time_logs t
                              LEFT JOIN users u ON u.id = t.user_id
                              WHERE t.job_id = ? ORDER BY t.logged_at ASC", [(int)$id]);

        $parts_used = db_rows("SELECT pu.*, p.name as part_name, p.supplier_sku as sku,
                                      u.name as logged_by_name
                               FROM part_usage pu
                               JOIN parts p ON p.id = pu.part_id
                               LEFT JOIN users u ON u.id = pu.logged_by
                               WHERE pu.job_id = ? ORDER BY pu.created_at ASC", [(int)$id]);

        // Silent auto-filter by device brand AND device category (kind of
        // device). The tech never sees non-matching parts — no "show all"
        // switch needed because the device dictates what's relevant.
        //
        // The user-facing narrowing lives in a separate "Parts group" axis
        // (battery / display / cable / …) which is per-part metadata,
        // independent of the device.
        //
        // Universal rows (brand_id NULL AND category_id NULL) are kept
        // visible across every selection — they are consumables/tools that
        // apply to all repairs.
        $available_parts = db_rows(
            "SELECT p.*, ps.quantity as stock,
                    pg.name AS part_group_name,
                    (p.brand_id IS NULL AND p.category_id IS NULL) AS is_universal
             FROM parts p
             LEFT JOIN parts_stock ps ON ps.part_id = p.id AND ps.location_id = ?
             LEFT JOIN part_groups  pg ON pg.id = p.part_group_id
             WHERE p.is_active = 1 AND p.deleted_at IS NULL
               AND (p.brand_id    = ? OR p.brand_id    IS NULL)
               AND (p.category_id = ? OR p.category_id IS NULL)
             ORDER BY is_universal ASC, p.name ASC",
            [$job['location_id'], $job['device_brand_id'], $job['device_category_id']]
        );

        $statuses    = db_rows('SELECT * FROM repair_statuses ORDER BY sort_order');
        $technicians = db_rows("SELECT id, name FROM users WHERE role IN ('technician','super_admin','admin') AND is_active = 1 AND deleted_at IS NULL ORDER BY name");

        // Vendor submission (GSX for Apple). Only relevant if the device is
        // an Apple product and the Apple vendor adapter is active.
        $gsx_vendor_id = (int) db_val(
            "SELECT v.id FROM vendors v JOIN vendor_adapters a ON a.vendor_id = v.id
             WHERE v.slug = 'apple' AND v.is_active = 1 AND a.is_active = 1 LIMIT 1"
        );
        $is_apple = stripos((string)$job['brand_name'], 'apple') !== false;
        $gsx_submission = ($is_apple && $gsx_vendor_id)
            ? db_row(
                'SELECT id, vendor_ref, ra_number, status, submitted_at
                 FROM vendor_rma_submissions
                 WHERE rma_id = ? AND vendor_id = ?
                 ORDER BY id DESC LIMIT 1',
                [(int)$job['rma_id'], $gsx_vendor_id]
            )
            : null;

        $total_minutes = array_sum(array_column($time_logs, 'minutes'));

        $page_title          = $job['rma_number'];
        $breadcrumb_parent     = __('nav.repairs');
        $breadcrumb_parent_url = '/repair';
        $success    = $_SESSION['flash']['message'] ?? ($_SESSION['form_success'] ?? null);
        $error      = $_SESSION['form_error'] ?? null;
        unset($_SESSION['flash'], $_SESSION['form_success'], $_SESSION['form_error']);

        include views_path('layout/header.php');
        include views_path('repair/view.php');
        include views_path('layout/footer.php');
    }

    // ── Create from RMA ───────────────────────────────────────

    public function create(string $rma_id): void {
        require_login();
        require_permission('repair', 'create');

        $rma = db_row('SELECT * FROM rma_requests WHERE id = ? AND deleted_at IS NULL', [(int)$rma_id]);
        if (!$rma) { http_response_code(404); return; }

        $allowed = allowed_location_ids();
        if ($allowed !== null && !in_array((int)$rma['location_id'], array_map('intval', $allowed), true)) {
            http_response_code(403); include views_path('errors/403.php'); exit;
        }

        // Check if job already exists for this RMA
        $existing = db_val('SELECT id FROM repair_jobs WHERE rma_id = ? AND deleted_at IS NULL', [(int)$rma_id]);
        if ($existing) {
            $_SESSION['form_success'] = __('repair.job_exists');
            header("Location: /repair/{$existing}");
            exit;
        }

        $first_status = db_row("SELECT id FROM repair_statuses WHERE code = 'pending' LIMIT 1");
        $status_id    = $first_status['id'] ?? db_val('SELECT id FROM repair_statuses ORDER BY sort_order LIMIT 1');

        // Findings start empty — the technician records their own diagnosis,
        // which doesn't always match the customer's complaint. The complaint
        // remains visible on the RMA and tracking page under "Reported issue".
        $job_id = db_insert('repair_jobs', [
            'rma_id'        => (int)$rma_id,
            'location_id'   => $rma['location_id'],
            'technician_id' => $rma['assigned_tech'],
            'status_id'     => $status_id,
            'description'   => null,
        ]);

        // Repair-job creation implies the device is physically in the shop.
        $this->sync_rma_from_repair((int)$rma_id, 'job_created');

        audit('created', 'repair_job', $job_id);
        $_SESSION['form_success'] = __('repair.job_created');
        header("Location: /repair/{$job_id}");
        exit;
    }

    // ── Update ────────────────────────────────────────────────

    public function update(string $id): void {
        require_login();
        require_permission('repair', 'edit');
        $this->guard_job_location((int)$id);

        $job    = db_row('SELECT * FROM repair_jobs WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$job) { http_response_code(404); return; }

        $action = $_POST['action'] ?? 'status';

        if ($action === 'status') {
            $status_id = (int)($_POST['status_id'] ?? 0);
            $data      = ['status_id' => $status_id];

            if ($status_id) {
                $st = db_row('SELECT * FROM repair_statuses WHERE id = ?', [$status_id]);
                if ($st && $st['code'] === 'in_progress' && !$job['started_at']) {
                    $data['started_at'] = date('Y-m-d H:i:s');
                }
                if ($st && $st['is_terminal'] && !$job['completed_at']) {
                    $data['completed_at'] = date('Y-m-d H:i:s');
                }
                db_update('repair_jobs', $data, 'id = ?', [(int)$id]);
                audit_change('repair_job', (int)$id, $job, $data);
                if ($st) {
                    $this->sync_rma_from_repair((int)$job['rma_id'], $st['code']);
                }
                $_SESSION['form_success'] = __('repair.status_updated');
            }
        }

        if ($action === 'warranty') {
            $is_warranty     = (int)($_POST['is_warranty'] ?? 0);
            $refusals        = $_POST['warranty_refusal'] ?? [];
            db_update('rma_requests', [
                'is_warranty'      => $is_warranty,
                'warranty_refusal' => !empty($refusals) ? json_encode($refusals) : null,
            ], 'id = ?', [$job['rma_id']]);
            $_SESSION['flash'] = ['type'=>'success','message'=>__('repair.warranty_updated')];
            header("Location: /repair/{$id}");
            exit;
        }

        if ($action === 'details') {
            $data = [
                'description' => trim($_POST['description'] ?? ''),
                'resolution'  => trim($_POST['resolution'] ?? ''),
            ];

            // Also update status if provided
            $status_id = (int)($_POST['status_id'] ?? 0);
            $new_code  = null;
            if ($status_id) {
                $data['status_id'] = $status_id;
                $st = db_row('SELECT * FROM repair_statuses WHERE id = ?', [$status_id]);
                if ($st && $st['code'] === 'in_progress' && !$job['started_at']) {
                    $data['started_at'] = date('Y-m-d H:i:s');
                }
                if ($st && $st['is_terminal'] && !$job['completed_at']) {
                    $data['completed_at'] = date('Y-m-d H:i:s');
                }
                $new_code = $st['code'] ?? null;
            }

            db_update('repair_jobs', $data, 'id = ?', [(int)$id]);
            audit_change('repair_job', (int)$id, $job, $data);
            if ($new_code) {
                $this->sync_rma_from_repair((int)$job['rma_id'], $new_code);
            }
            $_SESSION['form_success'] = __('msg.saved');
        }

        header("Location: /repair/{$id}");
        exit;
    }

    // ── Log time ──────────────────────────────────────────────

    public function log_time(string $id): void {
        require_login();
        require_permission('repair', 'edit');
        $this->guard_job_location((int)$id);

        $minutes = (int)($_POST['minutes'] ?? 0);
        $note    = trim($_POST['note'] ?? '');

        if ($minutes > 0) {
            db_insert('repair_time_logs', [
                'job_id'    => (int)$id,
                'user_id'   => current_user_id(),
                'minutes'   => $minutes,
                'note'      => $note,
                'logged_at' => date('Y-m-d H:i:s'),
            ]);
            audit('time_logged', 'repair_job', (int)$id);
            $_SESSION['form_success'] = __('repair.time_logged');
        }

        header("Location: /repair/{$id}");
        exit;
    }

    // ── Log part usage ────────────────────────────────────────

    public function log_part(string $id): void {
        require_login();
        require_permission('repair', 'edit');
        $this->guard_job_location((int)$id);

        $job      = db_row('SELECT * FROM repair_jobs WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$job) { http_response_code(404); return; }

        $part_id  = (int)($_POST['part_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if (!$part_id) {
            header("Location: /repair/{$id}");
            exit;
        }

        $part  = db_row('SELECT * FROM parts WHERE id = ? AND deleted_at IS NULL', [$part_id]);
        $stock = db_row('SELECT * FROM parts_stock WHERE part_id = ? AND location_id = ?',
                        [$part_id, $job['location_id']]);

        if (!$part) {
            $_SESSION['form_error'] = __('repair.part_not_found');
            header("Location: /repair/{$id}");
            exit;
        }

        if ($stock && $stock['quantity'] < $quantity) {
            $_SESSION['form_error'] = __('repair.insufficient_stock', [':qty'=>$stock['quantity']]);
            header("Location: /repair/{$id}");
            exit;
        }

        // Log usage
        db_insert('part_usage', [
            'job_id'      => (int)$id,
            'part_id'     => $part_id,
            'location_id' => $job['location_id'],
            'quantity'    => $quantity,
            'unit_cost'   => $part['unit_price'] ?? $part['unit_cost'] ?? 0,
            'logged_by'   => current_user_id(),
        ]);

        // Deduct from stock
        if ($stock) {
            db_update('parts_stock',
                ['quantity' => $stock['quantity'] - $quantity],
                'part_id = ? AND location_id = ?',
                [$part_id, $job['location_id']]
            );
        }

        // Log stock movement
        db_insert('stock_movements', [
            'part_id'        => $part_id,
            'location_id'    => $job['location_id'],
            'type'           => 'use',
            'quantity'       => -$quantity,
            'reference_type' => 'repair_job',
            'reference_id'   => (int)$id,
            'reason'         => 'Used in repair job #' . $id,
            'created_by'     => current_user_id(),
        ]);

        audit('part_used', 'repair_job', (int)$id, ['new' => ['part_id' => $part_id, 'qty' => $quantity]]);
        $_SESSION['form_success'] = __('repair.part_logged');
        header("Location: /repair/{$id}");
        exit;
    }

    // ── Submit the repair to Apple GSX (technician-initiated) ────────────
    public function submit_to_gsx(string $id): void {
        require_login();
        require_permission('repair', 'edit');
        header('Content-Type: application/json');

        $job = db_row(
            'SELECT j.*, r.rma_number, r.complaint, r.is_warranty,
                    d.imei, d.serial_number,
                    dm.name AS model_name, db2.name AS brand_name
             FROM repair_jobs j
             JOIN rma_requests r ON r.id = j.rma_id
             LEFT JOIN devices d ON d.id = r.device_id
             LEFT JOIN device_models dm ON dm.id = d.model_id
             LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
             WHERE j.id = ? AND j.deleted_at IS NULL',
            [(int)$id]
        );
        if (!$job) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Repair not found']); return; }

        $allowed = allowed_location_ids();
        if ($allowed !== null && !in_array((int)$job['location_id'], array_map('intval', $allowed), true)) {
            http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Forbidden']); return;
        }

        // Gatekeeping — only submit when it makes sense.
        if (stripos((string)$job['brand_name'], 'apple') === false) {
            echo json_encode(['ok' => false, 'error' => 'Device is not an Apple product']); return;
        }
        if (trim((string)($job['description'] ?? '')) === '') {
            echo json_encode(['ok' => false, 'error' => 'Add technician findings before submitting']); return;
        }

        $vendor_id = (int) db_val(
            "SELECT v.id FROM vendors v JOIN vendor_adapters a ON a.vendor_id = v.id
             WHERE v.slug = 'apple' AND v.is_active = 1 AND a.is_active = 1 LIMIT 1"
        );
        if (!$vendor_id) {
            echo json_encode(['ok' => false, 'error' => 'Apple GSX integration not configured or disabled']); return;
        }

        // Repair submission must be attributed to the technician, per Apple's
        // AASP terms — each certified tech uses their own credentials.
        // Refuse if the current user hasn't set theirs up.
        $user_id = current_user_id();
        if (!user_has_vendor_credentials($user_id, $vendor_id)) {
            echo json_encode([
                'ok' => false,
                'error' => 'You have not set up your personal Apple GSX credentials. Open your profile and add them before submitting.',
            ]); return;
        }

        $adapter = vendor_adapter($vendor_id, $user_id);
        if (!$adapter) {
            echo json_encode(['ok' => false, 'error' => 'GSX adapter not loadable']); return;
        }

        $result = $adapter->createRepair($job);
        db_insert('vendor_sync_log', [
            'vendor_id'   => $vendor_id,
            'rma_id'      => (int)$job['rma_id'],
            'action'      => 'create_repair',
            'request'     => json_encode(['job_id' => (int)$id, 'rma' => $job['rma_number']]),
            'response'    => json_encode($result),
            'http_status' => null,
            'success'     => !empty($result['success']) ? 1 : 0,
        ]);

        if (empty($result['success'])) {
            echo json_encode(['ok' => false, 'error' => $result['message'] ?? 'Submission failed', 'raw' => $result]);
            return;
        }

        db_insert('vendor_rma_submissions', [
            'rma_id'        => (int)$job['rma_id'],
            'vendor_id'     => $vendor_id,
            'vendor_ref'    => $result['vendor_ref'] ?? null,
            'ra_number'     => $result['ra_number']  ?? null,
            'status'        => 'submitted',
            'submitted_at'  => date('Y-m-d H:i:s'),
            'submitted_by'  => current_user_id(),
        ]);
        audit('gsx_submitted', 'repair_job', (int)$id, [
            'new' => ['vendor_ref' => $result['vendor_ref'], 'ra_number' => $result['ra_number']],
        ]);

        echo json_encode([
            'ok'         => true,
            'vendor_ref' => $result['vendor_ref'] ?? null,
            'ra_number'  => $result['ra_number']  ?? null,
        ]);
    }

    /**
     * Forward-only RMA status advance driven by repair-job transitions.
     *
     * Mapping (repair event -> target RMA status code):
     *   job_created -> device_received
     *   in_progress -> in_diagnosis
     *   on_hold     -> awaiting_parts
     *   completed   -> repaired
     *
     * Rules:
     *  - Never moves the RMA backward (uses sort_order to compare).
     *  - Never overrides terminal RMA statuses (closed/cancelled/unrepairable).
     *  - Logs the transition to rma_status_history with a clear note so
     *    admins can see it was auto-triggered.
     *  - Silently no-ops on anything unmapped (e.g. pending, cancelled).
     */
    private function sync_rma_from_repair(int $rma_id, string $repair_event): void {
        static $map = [
            'job_created' => 'device_received',
            'in_progress' => 'in_diagnosis',
            'on_hold'     => 'awaiting_parts',
            'completed'   => 'repaired',
        ];
        $target_code = $map[$repair_event] ?? null;
        if (!$target_code) return;

        $rma = db_row(
            "SELECT r.id, r.status_id, s.code AS cur_code, s.sort_order AS cur_order, s.is_terminal
             FROM rma_requests r
             JOIN rma_statuses s ON s.id = r.status_id
             WHERE r.id = ?",
            [$rma_id]
        );
        if (!$rma) return;
        if ((int)$rma['is_terminal'] === 1) return;          // never resurrect terminal RMAs

        $target = db_row(
            'SELECT id, sort_order FROM rma_statuses WHERE code = ? LIMIT 1',
            [$target_code]
        );
        if (!$target) return;
        if ((int)$target['sort_order'] <= (int)$rma['cur_order']) return; // forward-only

        db_update('rma_requests', ['status_id' => (int)$target['id']], 'id = ?', [$rma_id]);
        db_insert('rma_status_history', [
            'rma_id'     => $rma_id,
            'status_id'  => (int)$target['id'],
            'changed_by' => current_user_id(),
            'note'       => 'Auto-updated from repair job (' . $repair_event . ')',
        ]);
        audit('status_auto_sync', 'rma', $rma_id, [
            'new' => ['from' => $rma['cur_code'], 'to' => $target_code, 'trigger' => $repair_event],
        ]);
    }
}
