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
                         -- Newest number first, not newest row. The two are
                         -- normally the same, since numbers are handed out in
                         -- order — but a renumbered case breaks that, and on
                         -- 2026-08-18 two of them did: 52181 and 52185 were
                         -- created after 52188 and sat above it in the list.
                         --
                         -- Padded because the number is text: sorted as text,
                         -- '100000' lands below '52150' the moment the sequence
                         -- passes 99999, since '1' precedes '5'. str_pad in
                         -- rma_number() does not truncate, so a sixth digit
                         -- simply appears and the list would silently reorder.
                         -- LPAD is the same in Postgres and MySQL, and leaves a
                         -- prefixed format (LOC-YEAR-SEQ) sorting sensibly too.
                         ORDER BY LPAD(r.rma_number, 12, '0') DESC
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

        // Insurance. The counter is where a policy is first seen — Integra does
        // not sell the cover, so nothing exists until the paper arrives with the
        // device — and where it is checked on every visit after that.
        $insurers       = db_rows('SELECT id, name FROM insurers WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
        $coverage_items = db_rows('SELECT * FROM insurance_coverage_items WHERE is_active = 1 ORDER BY sort_order, label');
        $ins_products   = db_rows('SELECT p.*, i.name AS insurer_name
                                     FROM insurance_products p
                                     JOIN insurers i ON i.id = p.insurer_id
                                    WHERE p.deleted_at IS NULL AND p.is_active = 1
                                    ORDER BY i.name, p.name');

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
                // Same phone or same email means the same person, so the RMA
                // joins the existing record rather than creating a second one.
                $customer_id = (int)$match['customer']['id'];

                // But say so. Everything typed here is dropped in favour of
                // what is already on file, and when the name differs that is
                // invisible: the operator believes they entered a new customer
                // and instead sees somebody else's name on the finished RMA.
                if (strcasecmp(trim($match['customer']['name']), $walkin_name) !== 0) {
                    $_SESSION['form_success'] = __('rma.customer_linked', [
                        'name'  => $match['customer']['name'],
                        'field' => __('rma.match_' . $match['match_type']),
                    ]);
                }
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

        // Both dates are required (Rajo, 2026-08-18). They are what warranty is
        // argued from later — is_warranty is a judgement, these two are the
        // evidence — and neither can be recovered once the customer has gone.
        if (!$this->parse_date($_POST['purchase_date'] ?? '')
            || !$this->parse_date($_POST['warranty_expiry'] ?? '')) {
            $_SESSION['form_error'] = __('rma.dates_required');
            header('Location: /rma/create');
            exit;
        }

        // Two cases for one phone, seconds apart, are a double-click on Save —
        // not two arrivals. 52180/52181 and 52184/52185 were made that way, 3
        // and 4 seconds apart, same customer and same complaint. Nobody opens a
        // second case for the same handset within a minute, so the earlier one
        // is opened instead of a twin being created.
        $twin_ident = $submitted_imei ?: $submitted_serial;
        if ($twin_ident !== '') {
            $twin = db_row(
                "SELECT r.id, r.rma_number FROM rma_requests r
                   JOIN devices d ON d.id = r.device_id
                  WHERE (d.imei = ? OR d.serial_number = ?)
                    AND r.deleted_at IS NULL
                    AND r.created_at > ?
                  ORDER BY r.id DESC LIMIT 1",
                [$twin_ident, $twin_ident, date('Y-m-d H:i:s', time() - 60)]
            );
            if ($twin) {
                $_SESSION['form_success'] = __('rma.duplicate_avoided', ['rma' => $twin['rma_number']]);
                header('Location: /rma/' . (int)$twin['id']);
                exit;
            }
        }

        // A handset already in Reklamacije or Popravke is here, so a second
        // case is somebody entering it twice. Only once every case for it has
        // reached a final status may it be booked in again. The check is on the
        // status, so it follows whatever is marked terminal in Administracija.
        if ($twin_ident !== '') {
            $open = device_open_case($twin_ident);
            if ($open) {
                $_SESSION['form_error'] = __('rma.device_already_open', [
                    'rma'    => $open['rma_number'],
                    'status' => status_label((string)$open['status_code'], (string)$open['status_label']),
                ]);
                header('Location: /rma/create');
                exit;
            }
        }

        // Device: use existing or create new
        $device_id = null;
        if ($existing_device_id) {
            $device_id = $existing_device_id;
        } elseif (!empty($_POST['model_id'])) {
            // Matches on IMEI or serial before creating, so a device coming
            // back keeps one row whether or not anyone pressed the match
            // button on the form.
            $device_id = device_find_or_create([
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

        // ── Insurance ────────────────────────────────────────────
        //
        // A policy is born here. Integra never sees one until a device arrives
        // carrying it, so there is nothing to look up in advance — but once
        // recorded it answers by itself every time that handset comes back.
        if (!empty($_POST['is_insured'])) {
            $ins_ident   = $submitted_imei ?: $submitted_serial;
            $incident    = $this->parse_date($_POST['incident_date'] ?? '');
            $damage_code = trim($_POST['damage_code'] ?? '') ?: null;

            // An existing policy for this handset wins over anything typed: the
            // second visit should need no typing, and two rows for one policy
            // would split its allowance in half.
            $policy = $ins_ident !== '' ? policy_for_device($ins_ident, $incident) : null;

            if (!$policy && trim($_POST['policy_number'] ?? '') !== '' && (int)($_POST['insurer_id'] ?? 0)) {
                $starts = $this->parse_date($_POST['policy_starts_on'] ?? '');
                $ends   = $this->parse_date($_POST['policy_ends_on'] ?? '');
                if ($starts && $ends) {
                    $policy_id = db_insert('insurance_policies', [
                        'insurer_id'        => (int)$_POST['insurer_id'],
                        'policy_number'     => trim($_POST['policy_number']),
                        'customer_id'       => $customer_id ?: null,
                        'partner_id'        => $partner_id ?: null,
                        'device_id'         => $device_id ?: null,
                        'imei'              => $submitted_imei ?: null,
                        'serial_number'     => $submitted_serial ?: null,
                        'starts_on'         => $starts,
                        'ends_on'           => $ends,
                        // Coverage is a list of ticks, because Full and Limited
                        // are names people use rather than rules.
                        'coverage'          => implode(',', array_map('trim', (array)($_POST['coverage'] ?? []))) ?: null,
                        'participation_pct' => max(0, min(100, (float)($_POST['participation_pct'] ?? 0))),
                        'claims_allowed'    => max(0, min(99, (int)($_POST['claims_allowed'] ?? 1))),
                    ]);
                    audit('created', 'insurance_policy', $policy_id);
                    $policy = db_row('SELECT * FROM insurance_policies WHERE id = ?', [$policy_id]);
                }
            }

            db_update('rma_requests', [
                'incident_date' => $incident,
                'damage_code'   => $damage_code,
                'policy_id'     => $policy['id'] ?? null,
            ], 'id = ?', [$rma_id]);

            // The case is itself the claim against that policy. It starts as
            // 'new' — recorded, not yet reported to the insurer — and the
            // reporting queue picks it up from there.
            if ($policy) {
                $claim_id = db_insert('insurance_claims', [
                    'policy_id'     => (int)$policy['id'],
                    'rma_id'        => $rma_id,
                    'status'        => 'new',
                    'damage_code'   => $damage_code,
                    'incident_date' => $incident,
                    // Only when the insurer has told us their window; 0 means
                    // nobody has asked, and an invented deadline is worse than
                    // none.
                    'report_due_at' => $this->claim_due_at($policy, $incident),
                ]);
                audit('created', 'insurance_claim', $claim_id);
            }
        }

        // Tracking token. 64 hex characters was 94 characters of URL in a text
        // message, on its own enough to push every SMS into a second segment.
        //
        // 16 hex characters is 64 bits. That is safe here because the token is
        // not what protects the page: /track asks for the phone number or email
        // on the RMA before showing anything, and locks out after repeated
        // failures. Guessing a token buys you the verify form.
        //
        // Existing 64-character tokens keep working — the route matches any
        // length of hex, so receipts already printed and QR codes already
        // handed over are unaffected.
        db_insert('rma_tracking_tokens', [
            'rma_id' => $rma_id,
            'token'  => bin2hex(random_bytes(8)),
        ]);

        // Log initial status
        db_insert('rma_status_history', [
            'rma_id'     => $rma_id,
            'status_id'  => $status_id,
            'changed_by' => current_user_id(),
            // A key, not a sentence: Istorija then reads in the viewer's
            // language rather than the clerk's.
            'note'       => 'history.created',
        ]);

        audit('created', 'rma', $rma_id);

        // Send receipt email to customer
        send_rma_receipt($rma_id);
        // Same trigger as the email. Does nothing until Podesavanja turns SMS
        // on, and never texts a partner — they are contacted by email.
        send_rma_sms($rma_id);

        $_SESSION['form_success'] = __('rma.created_with_number', ['number'=>$rma_number]);
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
                              d.model_id, dm.brand_id,
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

        $history  = db_rows("SELECT h.*, s.code as status_code, s.label as status_label, s.color as status_color,
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

        // The dropdown offers what this desk may set — reception the counter
        // steps, the bench the ones in between (Administracija -> Statusi).
        $all_statuses = db_rows('SELECT * FROM rma_statuses ORDER BY sort_order');
        $statuses     = statuses_for_user($all_statuses);
        $current_id   = (int)$rma['status_id'];

        // A status the case has already been in and can never return to drops
        // out: an entry point is not somewhere a case goes back to. Ones that do
        // come round again — Ceka se dio for a second part, Na popravci after
        // each wait — are marked as recurring and stay. Admins keep the whole
        // list, so a wrong turn can always be undone by somebody.
        if (!is_admin_user()) {
            $visited = array_map('intval', array_column(
                db_rows('SELECT DISTINCT status_id FROM rma_status_history WHERE rma_id = ?', [(int)$id]),
                'status_id'
            ));
            $statuses = array_values(array_filter($statuses, fn($s) =>
                (int)$s['id'] === $current_id
                || (int)($s['can_recur'] ?? 1) === 1
                || !in_array((int)$s['id'], $visited, true)
            ));
        }

        // Where the case stands is said by the badge in the header and by the
        // timeline. It is offered in the dropdown only when this desk could set
        // it — otherwise the box opens on "no change" and lists this desk's own
        // statuses, rather than showing a technician Uredjaj primljen as though
        // it were theirs to set. Posting nothing changes nothing.
        $status_current_offered = (bool) array_filter(
            $statuses, fn($s) => (int)$s['id'] === $current_id
        );

        // Has this handset been here before? Excluding this case, or every
        // device would report itself.
        $repeat_ident = $rma['imei'] ?: ($rma['serial_number'] ?? '');
        $repeat = $repeat_ident !== ''
            ? device_repeat_state((string)$repeat_ident, (int)$rma['id'])
            : ['level' => 'none', 'visits' => 0, 'days' => null, 'case' => null, 'open' => null];

        $technicians = db_rows("SELECT id, name FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY name");

        // The insurance claim, when this case is one.
        $claim = claim_for_rma((int)$id);

        // For the identity modal: correcting the device the case was opened
        // against, not just the numbers written on it.
        $brands = db_rows('SELECT id, name FROM device_brands WHERE is_active = 1 ORDER BY name');
        $models = db_rows('SELECT id, brand_id, name FROM device_models WHERE is_active = 1 ORDER BY name');

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
                // The dropdown only offers what this desk may set, but a
                // filtered dropdown is a convenience, not a rule — and this
                // one can send an SMS to a customer, so check it again here.
                $target = db_row('SELECT * FROM rma_statuses WHERE id = ?', [$status_id]);
                if (!$target || !can_set_status($target)) {
                    $_SESSION['form_error'] = __('rma.status_not_allowed');
                    header("Location: /rma/{$id}");
                    exit;
                }

                db_update('rma_requests', ['status_id' => $status_id], 'id = ?', [(int)$id]);
                db_insert('rma_status_history', [
                    'rma_id'     => (int)$id,
                    'status_id'  => $status_id,
                    'changed_by' => current_user_id(),
                    'note'       => trim($_POST['note'] ?? ''),
                ]);
                audit_change('rma', (int)$id, ['status_id' => $rma['status_id']], ['status_id' => $status_id]);

                // Recomputed rather than set here: the case may have left by
                // shipment earlier, and this way a corrected date is picked up
                // whichever way round the two happen.
                stamp_rma_dispatch((int)$id);

                // After the write, never before: a gateway refusing a message
                // must not undo a status the technician has already set.
                notify_rma_status((int)$id);

                $_SESSION['form_success'] = __('rma.status_updated');
            }
        }

        if ($action === 'assign') {
            $tech_id = (int)($_POST['assigned_tech'] ?? 0) ?: null;
            db_update('rma_requests', ['assigned_tech' => $tech_id], 'id = ?', [(int)$id]);
            $_SESSION['form_success'] = __('rma.tech_assigned');
        }

        // Correcting what was typed at the counter: the customer name and the
        // SN/IMEI. Neither lives on the RMA — the name is the customers row and
        // the numbers are the devices row — so a typo caught after submitting
        // could not be fixed anywhere until now.
        if ($action === 'identity') {
            $name = trim($_POST['customer_name'] ?? '');
            if ($name !== '' && $rma['customer_id']) {
                $old = db_row('SELECT name FROM customers WHERE id = ?', [(int)$rma['customer_id']]);
                if ($old && $old['name'] !== $name) {
                    // Writing to the customers row is the point: the correction
                    // has to show in Korisnici and on every other RMA of theirs,
                    // not just on this one.
                    db_update('customers', ['name' => $name], 'id = ?', [(int)$rma['customer_id']]);
                    audit_change('customer', (int)$rma['customer_id'],
                                 ['name' => $old['name']], ['name' => $name]);
                }
            }

            // The phone is where the SMS goes and half of what /track asks for,
            // so a wrong number means the customer hears nothing and cannot look
            // the case up. It writes to the customers row for the same reason
            // the name does. Blank is left alone rather than treated as "clear
            // it": emptying somebody's only contact is far more likely to be a
            // slip than an intention, and SN/IMEI above are the fields that
            // genuinely need clearing.
            $cc    = preg_replace('/\D/', '', $_POST['customer_phone_country_code'] ?? '382');
            $phone = normalize_phone(trim($_POST['customer_phone'] ?? ''), $cc ?: '382');
            if ($phone !== '' && $rma['customer_id']) {
                $old = db_row('SELECT phone FROM customers WHERE id = ?', [(int)$rma['customer_id']]);
                if ($old && $old['phone'] !== $phone) {
                    db_update('customers', ['phone' => $phone], 'id = ?', [(int)$rma['customer_id']]);
                    audit_change('customer', (int)$rma['customer_id'],
                                 ['phone' => $old['phone']], ['phone' => $phone]);
                }
            }

            if ($rma['device_id']) {
                $sn   = trim($_POST['serial_number'] ?? '');
                $imei = trim($_POST['imei'] ?? '');
                $old  = db_row('SELECT serial_number, imei, model_id, purchase_date, warranty_expiry
                                  FROM devices WHERE id = ?', [(int)$rma['device_id']]);
                // Empty clears the field — a number typed onto the wrong device
                // has to be removable, not just correctable.
                $new = ['serial_number' => $sn ?: null, 'imei' => $imei ?: null];

                // The wrong model can be picked at the counter as easily as the
                // wrong number, and it decides which repairs the device shows up
                // in later. Blank leaves it alone rather than unsetting it: a
                // case must always name a model.
                if (!empty($_POST['model_id'])) {
                    $new['model_id'] = (int)$_POST['model_id'];
                }

                // The two dates warranty is argued from. Required at intake and
                // correctable here — a receipt often turns up afterwards — but
                // an empty box leaves the stored date alone rather than wiping
                // it: intake will not accept a case without them, so clearing
                // one afterwards could only ever be an accident.
                foreach (['purchase_date', 'warranty_expiry'] as $df) {
                    $parsed = $this->parse_date($_POST[$df] ?? '');
                    if ($parsed) $new[$df] = $parsed;
                }

                $changed = false;
                foreach ($new as $k => $v) {
                    if ((string)($old[$k] ?? '') !== (string)($v ?? '')) { $changed = true; break; }
                }
                if ($old && $changed) {
                    db_update('devices', $new, 'id = ?', [(int)$rma['device_id']]);
                    audit_change('device', (int)$rma['device_id'], $old, $new);
                }
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => __('rma.identity_updated')];
        }

        if ($action === 'details') {
            // Only what the form actually sent. It posts a priority and a
            // technician and nothing else, so writing the rest from absent POST
            // keys quietly emptied them: every press of Azuriraj blanked the
            // diagnosis and the estimated date, and set is_warranty to 0 —
            // turning Pod garancijom into a refusal nobody had made.
            //
            // Warranty is not settable from here at all. It has three states
            // and rules about which may follow which (helpers/warranty.php);
            // this card has no way to express either, so it leaves them alone.
            $data = ['priority' => $_POST['priority'] ?? $rma['priority']];

            if (array_key_exists('assigned_tech', $_POST)) {
                $data['assigned_tech'] = (int)$_POST['assigned_tech'] ?: null;
            }
            if (array_key_exists('estimated_completion', $_POST)) {
                $data['estimated_completion'] = $_POST['estimated_completion'] ?: null;
            }
            if (array_key_exists('diagnosis', $_POST)) {
                $data['diagnosis'] = trim((string)$_POST['diagnosis']);
            }

            db_update('rma_requests', $data, 'id = ?', [(int)$id]);
            $_SESSION['flash'] = ['type'=>'success','message'=>__('rma.details_updated')];
        }

        header("Location: /rma/{$id}");
        exit;
    }

    /**
     * Move a claim along, and record what the insurer said.
     *
     * Forward-only with one loop — dopuna back to prijavljena once the missing
     * thing has been sent. Anything else is refused here rather than merely
     * being absent from the buttons, since this decides who pays.
     */
    public function claim_update(string $id): void {
        require_login();
        // claims.edit, not rma.edit: following a claim is the office's job, and
        // the tick for it existed on the Dozvole screen while nothing checked
        // it. A technician may move the case along; the claim is not theirs.
        require_permission('claims', 'edit');
        $this->guard_rma_location((int)$id);

        $claim = claim_for_rma((int)$id);
        if (!$claim) { http_response_code(404); return; }

        $to = trim($_POST['status'] ?? '');
        if (!claim_can_move((string)$claim['status'], $to)) {
            $_SESSION['form_error'] = __('ins.claim_bad_move');
            header("Location: /rma/{$id}"); exit;
        }

        $data = ['status' => $to];

        if ($to === 'reported') {
            // The portal's own number is the key to their side of it, and the
            // only thing anybody can chase this with later.
            $number = trim($_POST['claim_number'] ?? '');
            if ($number !== '') $data['claim_number'] = $number;
            if (empty($claim['reported_at'])) {
                $data['reported_at'] = date('Y-m-d H:i:s');
                $data['reported_by'] = current_user_id();
            }
        }

        if ($to === 'approved') {
            $amount = (float) str_replace(',', '.', (string)($_POST['approved_amount'] ?? '0'));
            if ($amount > 0) {
                $split = claim_split($amount, (float)$claim['participation_pct']);
                $data['approved_amount']      = $amount;
                $data['participation_amount'] = $split['customer'];
            }
            $data['decided_at'] = date('Y-m-d H:i:s');
        }

        if ($to === 'refused') $data['decided_at'] = date('Y-m-d H:i:s');

        $note = trim($_POST['notes'] ?? '');
        if ($note !== '') $data['notes'] = $note;

        db_update('insurance_claims', $data, 'id = ?', [(int)$claim['id']]);
        audit_change('insurance_claim', (int)$claim['id'], ['status' => $claim['status']], $data);

        $_SESSION['form_success'] = __('ins.claim_saved');
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

        // Vendor resolution: an explicit choice, else the device's own brand.
        //
        // It used to fall back to Apple whenever nothing was passed, from when
        // Apple was the only adapter. That meant asking Apple about a TCL
        // phone and believing the answer — the brand is right there on the
        // device and is the only honest way to pick.
        $vendor_id = (int)($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) {
            $vendor_id = (int) db_val(
                "SELECT v.id FROM vendors v
                   JOIN device_brands b ON LOWER(b.name) = LOWER(v.slug) OR LOWER(b.name) = LOWER(v.name)
                  WHERE b.id = ? AND v.is_active = 1
                  LIMIT 1",
                [(int)($rma['brand_id'] ?? 0)]
            );
        }
        if (!$vendor_id) { echo json_encode(['ok' => false, 'error' => __('rma.no_vendor_for_brand')]); return; }

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
        // An outbound shipment with a dispatch date is the device leaving —
        // the clock a repeated repair is measured from.
        stamp_rma_dispatch($rid);
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
            stamp_rma_dispatch($rid);
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
        // A shipment entered by mistake and removed should not leave the case
        // looking dispatched, so this recomputes downwards too.
        stamp_rma_dispatch($rid);
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

        // Phone matching on the LAST 8 DIGITS, which is the subscriber number.
        // The same Montenegrin mobile is written +382 69 222 444, 069 222 444
        // or 069222444; the trunk 0 replaces the 382, so the digit strings
        // never overlap and a plain contains-search finds nothing. The last
        // eight digits are identical in every form. phone_last_digits() is the
        // same rule find_customer_match() already uses for duplicate detection.
        $digits = preg_replace('/\D/', '', $q);
        $tail   = strlen($digits) > 8 ? substr($digits, -8) : $digits;
        $dlike  = strlen($tail) >= 3 ? "%{$tail}%" : null;
        $elike  = "%{$q}%";

        // refused@... is what reception records when a customer gives no email.
        // Dozens of unrelated people carry the same one, so searching it would
        // return strangers; excluded from the email side of the search, while
        // the phone side is untouched.
        $not_placeholder = "AND LOWER(COALESCE(email, '')) NOT LIKE 'refused@%'";

        if ($dlike) {
            // Match digits-stripped phone OR email as typed
            $customers = db_rows(
                "SELECT id, name, phone, email, city, address FROM customers
                 WHERE deleted_at IS NULL
                   AND (REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?
                        OR (email LIKE ? {$not_placeholder}))
                 ORDER BY name LIMIT 10",
                [$dlike, $elike]
            );
        } else {
            $customers = db_rows(
                "SELECT id, name, phone, email, city, address FROM customers
                 WHERE deleted_at IS NULL AND email LIKE ? {$not_placeholder}
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

    /**
     * Every case this physical device has been through.
     *
     * Addressed by IMEI or serial, never by devices.id: the identifier is what
     * identifies a phone, and one phone can hold several device rows.
     */
    public function device_history(string $ident): void {
        require_login();
        require_permission('rma', 'view');

        $ident  = trim($ident);
        $device = device_by_identifier($ident);
        $cases  = device_cases($ident);

        if (!$device) { http_response_code(404); include views_path('errors/404.php'); return; }

        $page_title            = trim(($device['brand_name'] ?? '') . ' ' . ($device['model_name'] ?? ''))
                                 ?: __('rma.device_history');
        $breadcrumb_parent     = __('rma.title');
        $breadcrumb_parent_url = '/rma';

        include views_path('layout/header.php');
        include views_path('rma/device_history.php');
        include views_path('layout/footer.php');
    }

    /**
     * The counter's answer: is this covered, at what participation, and how
     * much of the allowance is left.
     *
     * Worded here rather than in the browser so the sentences live in the
     * language files with every other string. The numbers are OUR record — a
     * claim made directly with the insurer is invisible to us — and the wording
     * says so until a portal can be read.
     */
    public function insurance_status(): void {
        require_login();
        require_permission('rma', 'create');
        header('Content-Type: application/json');

        $ident    = trim($_GET['ident'] ?? '');
        $incident = $this->parse_date($_GET['incident'] ?? '');
        $damage   = trim($_GET['damage'] ?? '');

        // Nothing useful to say until the device is known. The date and the
        // damage sharpen the answer but are not needed to give one.
        if ($ident === '') { echo json_encode(['level' => 'none']); exit; }

        $r      = insurance_check($ident, $incident, $damage ?: null);
        $policy = $r['policy'] ?? null;

        if ($r['covered']) {
            $line = __('ins.chk_covered', ['pct' => rtrim(rtrim(number_format($r['participation'], 2, '.', ''), '0'), '.')]);
            $used = __('ins.chk_used', ['used' => $r['used'], 'allowed' => $r['allowed']]);
            if ($r['pending'] > 0) $used .= ' ' . __('ins.chk_pending', ['n' => $r['pending']]);
            echo json_encode(['level' => 'ok', 'message' => $line, 'detail' => $used]);
            exit;
        }

        $detail = '';
        switch ($r['reason']) {
            case 'expired':
                $line   = __('ins.chk_expired', ['to' => $policy ? format_date($policy['ends_on']) : '']);
                break;
            case 'not_covered':
                $line   = __('ins.chk_not_covered');
                $detail = __('ins.chk_paid_repair');
                break;
            case 'no_allowance':
                $line   = __('ins.chk_no_allowance', ['used' => $r['used'] + $r['pending'], 'allowed' => $r['allowed']]);
                $detail = __('ins.chk_our_record');
                break;
            default:
                $line   = __('ins.chk_no_policy');
        }
        echo json_encode(['level' => 'warn', 'message' => $line, 'detail' => $detail]);
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

        if (!$device) { echo json_encode(['id' => null]); exit; }

        // What the counter needs to know before accepting it: has this handset
        // been here before, and did it go out recently. Keyed on the identifier
        // typed in, so it reads across duplicate device rows.
        $state = device_repeat_state($param);
        // Unscoped, and the same call the save handler makes, so what the form
        // says and what the server does cannot disagree.
        $blocking = device_open_case($param);
        $device['blocked'] = $blocking ? [
            'rma'    => $blocking['rma_number'],
            'status' => status_label((string)$blocking['status_code'], (string)$blocking['status_label']),
        ] : null;
        // A policy already on file for this handset. The counter should type
        // none of it a second time — and a device arriving with cover is an
        // insurance case whether or not anyone remembered to tick the box.
        $known = policy_for_device($param);
        $device['policy'] = $known ? [
            'policy_number' => $known['policy_number'],
            'insurer_name'  => $known['insurer_name'],
            'ends_on'       => format_date($known['ends_on']),
        ] : null;

        $device['repeat'] = [
            'level'  => $state['level'],
            'visits' => $state['visits'],
            'days'   => $state['days'],
            'case'   => $state['case']['rma_number'] ?? null,
            'open'   => $state['open']['rma_number'] ?? null,
            'ident'  => $param,
        ];

        echo json_encode($device);
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * When a claim must be reported by, or null while the insurer's window is
     * unknown. Counted from the incident, which is the date every insurer
     * measures from and the one thing the counter must ask for.
     */
    private function claim_due_at(array $policy, ?string $incident): ?string {
        $hours = (int) db_val('SELECT report_hours FROM insurers WHERE id = ?', [(int)$policy['insurer_id']]);
        if ($hours <= 0 || !$incident) return null;
        return date('Y-m-d H:i:s', strtotime($incident) + $hours * 3600);
    }


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
