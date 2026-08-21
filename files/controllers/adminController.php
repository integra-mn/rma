<?php
defined('RMS') or die('Direct access not permitted');

class AdminController {

    public function locations(): void {
        // Legacy URL — the live page is the Administration → Locations tab.
        header('Location: /administration?tab=locations', true, 301);
        exit;
    }

    public function location_store(): void {
        require_login();
        require_permission('administration', 'create');

        $count = (int) db_val('SELECT COUNT(*) FROM locations WHERE deleted_at IS NULL');
        if ($count >= 10) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('admin.loc_max')];
            header('Location: /administration?tab=locations');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if (!$name || !$code) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('admin.loc_name_code_required')];
            header('Location: /administration?tab=locations');
            exit;
        }

        $new_id = db_insert('locations', [
            'name'        => $name,
            'code'        => $code,
            'address'     => trim($_POST['address'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'city'        => trim($_POST['city'] ?? ''),
            'country'     => trim($_POST['country'] ?? 'Montenegro'),
            'phone'       => trim($_POST['phone'] ?? ''),
            'email'       => trim($_POST['email'] ?? ''),
            'is_active'   => 1,
        ]);
        audit('created', 'location', $new_id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('admin.location_created')];
        header('Location: /administration?tab=locations');
        exit;
    }

    public function location_toggle(): void {
        require_login();
        require_permission('administration', 'edit');

        $id  = (int)($_POST['id'] ?? 0);
        $loc = db_row('SELECT * FROM locations WHERE id = ?', [$id]);
        if (!$loc) { header('Location: /administration?tab=locations'); exit; }

        $new_state = $loc['is_active'] ? 0 : 1;

        // Cannot disable if users are assigned to it
        if (!$new_state) {
            $users = db_val('SELECT COUNT(*) FROM users WHERE location_id = ? AND is_active = 1', [$id]);
            if ($users) {
                $_SESSION['flash'] = ['type'=>'danger','message'=>__('admin.loc_cannot_disable', ['count'=>$users])];
                header('Location: /administration?tab=locations');
                exit;
            }
        }

        db_update('locations', ['is_active' => $new_state], 'id = ?', [$id]);
        audit('location_' . ($new_state ? 'enabled' : 'disabled'), 'location', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>($new_state ? __('admin.location_enabled') : __('admin.location_disabled'))];
        header('Location: /administration?tab=locations');
        exit;
    }

    public function location_update(): void {
        require_login();
        require_permission('administration', 'edit');

        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));

        if (!$name || !$code) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('admin.loc_name_code_required')];
            header('Location: /administration?tab=locations');
            exit;
        }

        db_update('locations', [
            'name'        => $name,
            'code'        => $code,
            'address'     => trim($_POST['address'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'city'        => trim($_POST['city'] ?? ''),
            'country'     => trim($_POST['country'] ?? 'Montenegro'),
            'phone'       => trim($_POST['phone'] ?? ''),
            'email'       => trim($_POST['email'] ?? ''),
        ], 'id = ?', [$id]);

        audit('updated', 'location', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('admin.location_updated')];
        header('Location: /administration?tab=locations');
        exit;
    }

    // ── Couriers ─────────────────────────────────────────────────

    private function courier_slug(string $name): string {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'courier';
        $slug = $base; $n = 2;
        while (db_val('SELECT COUNT(*) FROM couriers WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    public function courier_store(): void {
        require_login();
        require_permission('administration', 'create');
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('ship.courier_name_required')];
            header('Location: /administration?tab=couriers'); exit;
        }
        $new_id = db_insert('couriers', [
            'name'         => $name,
            'slug'         => $this->courier_slug($name),
            'tracking_url' => trim($_POST['tracking_url'] ?? '') ?: null,
            'phone'        => trim($_POST['phone'] ?? '') ?: null,
            'is_active'    => 1,
        ]);
        audit('created', 'courier', $new_id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('ship.courier_saved')];
        header('Location: /administration?tab=couriers'); exit;
    }

    public function courier_update(): void {
        require_login();
        require_permission('administration', 'edit');
        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || !$name) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('ship.courier_name_required')];
            header('Location: /administration?tab=couriers'); exit;
        }
        db_update('couriers', [
            'name'         => $name,
            'tracking_url' => trim($_POST['tracking_url'] ?? '') ?: null,
            'phone'        => trim($_POST['phone'] ?? '') ?: null,
        ], 'id = ?', [$id]);
        audit('updated', 'courier', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('ship.courier_saved')];
        header('Location: /administration?tab=couriers'); exit;
    }

    public function courier_toggle(): void {
        require_login();
        require_permission('administration', 'edit');
        $id = (int) ($_POST['id'] ?? 0);
        $c  = db_row('SELECT is_active FROM couriers WHERE id = ?', [$id]);
        if ($c) {
            db_update('couriers', ['is_active' => $c['is_active'] ? 0 : 1], 'id = ?', [$id]);
            audit('courier_' . ($c['is_active'] ? 'disabled' : 'enabled'), 'courier', $id);
        }
        header('Location: /administration?tab=couriers'); exit;
    }

    public function courier_delete(): void {
        require_login();
        require_permission('administration', 'delete');
        $id = (int) ($_POST['id'] ?? 0);
        // In-use couriers can't be hard-deleted (shipments/partners reference them);
        // fall back to disabling so history stays intact.
        $in_use = db_val('SELECT COUNT(*) FROM delivery_shipments WHERE courier_id = ?', [$id])
                + db_val('SELECT COUNT(*) FROM partners WHERE default_courier_id = ?', [$id]);
        if ($in_use) {
            db_update('couriers', ['is_active' => 0], 'id = ?', [$id]);
            $_SESSION['flash'] = ['type'=>'success','message'=>__('ship.courier_in_use_disabled')];
        } else {
            db()->prepare('DELETE FROM couriers WHERE id = ?')->execute([$id]);
            audit('deleted', 'courier', $id);
            $_SESSION['flash'] = ['type'=>'success','message'=>__('ship.courier_deleted')];
        }
        header('Location: /administration?tab=couriers'); exit;
    }

    public function users(): void {
        // Legacy URL — the live page is the Administration → Users tab.
        header('Location: /administration?tab=users', true, 301);
        exit;
    }

    public function user_store(): void {
        require_login();
        require_permission('administration', 'create');

        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $role  = $_POST['role'] ?? 'technician';
        $pass  = $_POST['password'] ?? '';

        if (!$name || !$email || !$phone || !$pass) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.name_email_pw_required')];
            header('Location: /administration?tab=users');
            exit;
        }

        if (db_val('SELECT id FROM users WHERE email = ?', [$email])) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.email_in_use', ['email'=>$email])];
            header('Location: /administration?tab=users');
            exit;
        }

        $valid_roles = ['super_admin','admin','reception','technician','partner'];
        if (!in_array($role, $valid_roles, true)) $role = 'technician';

        // Enforce single Super Admin.
        if ($role === 'super_admin') {
            $existing = (int) db_val("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND deleted_at IS NULL");
            if ($existing > 0) {
                $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.super_admin_exists')];
                header('Location: /administration?tab=users');
                exit;
            }
        }

        // A partner-role account with no partner behind it logs in to an empty
        // portal, which looks like a broken app rather than a missing field.
        // Refuse up front instead of creating one.
        if ($role === 'partner' && !(int)($_POST['partner_id'] ?? 0)) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.partner_required')];
            header('Location: /administration?tab=users');
            exit;
        }

        $id = db_insert('users', [
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'role'          => $role,
            'location_id'   => (int)($_POST['location_id'] ?? 0) ?: null,
            'lang'          => $_POST['lang'] ?? 'en',
            'theme'         => $_POST['theme'] ?? 'midnight',
            'is_active'     => 1,
            'must_change_pw'=> 1,
            'require_2fa'   => ((string)($_POST['require_2fa'] ?? '0') === '1') ? 1 : 0,
            'access_scope'  => in_array($_POST['access_scope'] ?? '', ['any','lan'], true)
                             ? $_POST['access_scope'] : default_access_scope($role),
        ]);

        $this->sync_partner_link($id, $role);

        audit('created', 'user', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('users.created', ['name'=>$name])];
        header('Location: /administration?tab=users');
        exit;
    }

    /**
     * Keep the person ↔ partner ↔ poslovnica link in step with the role.
     *
     * A partner-role account is useless without this row: the portal works out
     * who you are from partner_users, so an account without it logs in and sees
     * an empty portal. Equally, demoting someone out of the partner role has to
     * drop the link, or they keep partner access through a stale row.
     *
     * Exactly one row per user is kept, which is what the users list join and
     * current_partner_id() both assume.
     */
    private function sync_partner_link(int $user_id, string $role): void {
        if ($role !== 'partner') {
            db_delete('partner_users', 'user_id = ?', [$user_id]);
            return;
        }

        $partner_id = (int)($_POST['partner_id'] ?? 0);
        if (!$partner_id) {
            // No partner chosen — leave any existing link untouched rather than
            // silently cutting someone off from their portal.
            return;
        }

        // The branch is only accepted if it belongs to the partner just chosen.
        $branch_id = valid_partner_branch_id($partner_id, $_POST['partner_branch_id'] ?? null);
        $existing  = db_row('SELECT id, partner_id FROM partner_users WHERE user_id = ? LIMIT 1', [$user_id]);

        if ($existing && (int)$existing['partner_id'] === $partner_id) {
            db_update('partner_users', ['branch_id' => $branch_id], 'id = ?', [(int)$existing['id']]);
            return;
        }

        db_delete('partner_users', 'user_id = ?', [$user_id]);
        db_insert('partner_users', [
            'partner_id' => $partner_id,
            'user_id'    => $user_id,
            'branch_id'  => $branch_id,
            'role'       => 'staff',
            'invited_by' => current_user_id(),
        ]);
    }

    public function user_update(): void {
        require_login();
        require_permission('administration', 'edit');

        $id   = (int)($_POST['id'] ?? 0);
        $user = db_row('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) { header('Location: /administration?tab=users'); exit; }

        $valid_roles = ['super_admin','admin','reception','technician','partner'];
        $new_role    = in_array($_POST['role'] ?? '', $valid_roles, true) ? $_POST['role'] : $user['role'];

        // Enforce single Super Admin on promotion.
        if ($new_role === 'super_admin' && $user['role'] !== 'super_admin') {
            $existing = (int) db_val(
                "SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND deleted_at IS NULL AND id != ?",
                [$id]
            );
            if ($existing > 0) {
                $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.super_admin_exists_demote')];
                header('Location: /administration?tab=users');
                exit;
            }
        }
        // Prevent demoting the only Super Admin — there must always be one.
        if ($user['role'] === 'super_admin' && $new_role !== 'super_admin') {
            $others = (int) db_val(
                "SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND deleted_at IS NULL AND id != ?",
                [$id]
            );
            if ($others === 0) {
                $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.cannot_demote_only')];
                header('Location: /administration?tab=users');
                exit;
            }
        }

        $phone = trim($_POST['phone'] ?? '');
        if ($phone === '') {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.phone_required')];
            header('Location: /administration?tab=users');
            exit;
        }

        // Same rule as on create: promoting someone to partner without naming
        // the partner would leave them with a portal that shows nothing.
        if ($new_role === 'partner' && !(int)($_POST['partner_id'] ?? 0)) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.partner_required')];
            header('Location: /administration?tab=users');
            exit;
        }

        $data = [
            'name'        => trim($_POST['name'] ?? $user['name']),
            'email'       => strtolower(trim($_POST['email'] ?? $user['email'])),
            'phone'       => $phone,
            'role'        => $new_role,
            'location_id' => (int)($_POST['location_id'] ?? 0) ?: null,
            'lang'        => $_POST['lang'] ?? $user['lang'],
            // Dropdown, not a checkbox: it always submits, so test the VALUE.
            // isset() here would pin every user to active forever.
            'is_active'   => ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0,
            'require_2fa' => ((string)($_POST['require_2fa'] ?? '0') === '1') ? 1 : 0,
            'access_scope'=> in_array($_POST['access_scope'] ?? '', ['any','lan'], true)
                           ? $_POST['access_scope'] : ($user['access_scope'] ?? 'any'),
        ];

        // Only update password if provided
        if (!empty($_POST['password'])) {
            $data['password_hash']  = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $data['must_change_pw'] = 0;
        }

        db_update('users', $data, 'id = ?', [$id]);
        $this->sync_partner_link($id, $new_role);
        audit('updated', 'user', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('users.updated')];
        header('Location: /administration?tab=users');
        exit;
    }

    public function user_toggle(): void {
        require_login();
        require_permission('administration', 'edit');

        $id   = (int)($_POST['id'] ?? 0);
        $user = db_row('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) { header('Location: /administration?tab=users'); exit; }

        // Cannot deactivate yourself
        if ($id === current_user_id()) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.cannot_deactivate_self')];
            header('Location: /administration?tab=users');
            exit;
        }

        $new = $user['is_active'] ? 0 : 1;
        db_update('users', ['is_active' => $new], 'id = ?', [$id]);
        audit('user_' . ($new ? 'enabled' : 'disabled'), 'user', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>($new ? __('users.enabled') : __('users.disabled'))];
        header('Location: /administration?tab=users');
        exit;
    }

    public function location_delete(): void {
        require_login();
        require_permission('administration', 'delete');

        $id = (int)($_POST['id'] ?? 0);

        // Check if location has RMAs
        $rma_count = (int)db_val('SELECT COUNT(*) FROM rma_requests WHERE location_id = ?', [$id]);
        if ($rma_count > 0) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('admin.loc_cannot_delete', ['count'=>$rma_count])];
            header('Location: /administration?tab=locations');
            exit;
        }

        db_update('locations', ['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0], 'id = ?', [$id]);
        audit('deleted', 'location', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('admin.location_deleted')];
        header('Location: /administration?tab=locations');
        exit;
    }

    public function user_delete(): void {
        require_login();
        require_permission('administration', 'delete');

        $id = (int)($_POST['id'] ?? 0);
        if ($id === current_user_id()) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.cannot_delete_self')];
            header('Location: /administration?tab=users');
            exit;
        }

        // Check if user has RMA or repair records
        $rma_count = (int)db_val('SELECT COUNT(*) FROM rma_requests WHERE submitted_by = ?', [$id]);
        if ($rma_count > 0) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('users.cannot_delete_in_use')];
            header('Location: /administration?tab=users');
            exit;
        }

        db_update('users', ['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0], 'id = ?', [$id]);
        audit('deleted', 'user', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('users.deleted')];
        header('Location: /administration?tab=users');
        exit;
    }

    public function save_lang(): void {
        require_login();
        $lang = $_GET['l'] ?? 'en';
        if (!in_array($lang, ['en','me','de','fr','es','it'], true)) $lang = 'en';
        db_update('users', ['lang' => $lang], 'id = ?', [current_user_id()]);
        $_SESSION['user']['lang'] = $lang;
        $back = $_GET['from'] ?? '/';
        header('Location: ' . $back);
        exit;
    }

    public function save_theme(): void {
        require_login();
        $theme = $_GET['t'] ?? 'midnight';
        if (!in_array($theme, ['midnight','ocean','focus'], true)) $theme = 'midnight';
        db_update('users', ['theme' => $theme], 'id = ?', [current_user_id()]);
        $_SESSION['user']['theme'] = $theme;

        // If a redirect target was supplied, bounce back there (regular link
        // flow used by the Appearance theme picker). Only allow same-origin
        // relative paths to avoid open-redirect.
        $back = $_GET['redirect'] ?? '';
        if ($back !== '' && str_starts_with($back, '/') && !str_starts_with($back, '//')) {
            header('Location: ' . $back);
            exit;
        }

        // No redirect → return 204 for any remaining fetch() callers.
        http_response_code(204);
        exit;
    }

    // ── Categories ────────────────────────────────────────────

    public function index(): void {
        require_login();
        require_permission('administration', 'view');

        // Redirect /admin to /settings
        $uri = strtok($_SERVER['REQUEST_URI'], '?');
        if ($uri === '/admin') {
            $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
            header("Location: /settings{$qs}");
            exit;
        }

        $tab        = $_GET['tab'] ?? 'users';
        $page_title = __('nav.administration');

        if ($tab === 'users') {

        // Old bookmarks: the catalogue moved out to its own section.
        if ($tab === 'devices') { header('Location: /devices', true, 301); exit; }
            // Super Admin pinned to the top, then the usual active-first,
            // alphabetical order. CASE rather than a role sort so adding roles
            // later doesn't silently reshuffle the list.
            // partner_users is joined for the partner-side people: which partner
            // they work for and which of that partner's poslovnice they sit in.
            // At most one row per user — user_store()/user_update() enforce it.
            $users     = db_rows("SELECT u.*, l.name as location_name,
                                         pu.partner_id, pu.branch_id,
                                         p.name as partner_name, pb.name as branch_name
                                  FROM users u
                                  LEFT JOIN locations l ON l.id = u.location_id
                                  LEFT JOIN partner_users pu ON pu.user_id = u.id
                                  LEFT JOIN partners p ON p.id = pu.partner_id
                                  LEFT JOIN partner_branches pb ON pb.id = pu.branch_id
                                  WHERE u.deleted_at IS NULL
                                  ORDER BY CASE WHEN u.role = 'super_admin' THEN 0 ELSE 1 END,
                                           u.is_active DESC, u.name");
            $locations = db_rows('SELECT * FROM locations WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
            $partners  = db_rows('SELECT id, name FROM partners WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
            // Every active branch with its partner_id, so the form can narrow the
            // list once a partner is picked without another round trip. Roughly
            // 50 rows in total, so sending them all costs nothing.
            $all_branches = db_rows('SELECT id, partner_id, name, city FROM partner_branches
                                      WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
        } elseif ($tab === 'locations') {
            $locations = db_rows('SELECT * FROM locations WHERE deleted_at IS NULL ORDER BY name');
        } elseif ($tab === 'statuses') {
            $rma_statuses    = db_rows('SELECT * FROM rma_statuses ORDER BY sort_order, label');
            // Whether each status has ever been used, so the screen can offer
            // Delete only where it would succeed rather than offering it
            // everywhere and refusing half the time. History counts as use: a
            // case that passed through a status still names it.
            $status_usage = [];
            foreach (db_rows(
                'SELECT s.id,
                        (SELECT COUNT(*) FROM rma_requests r WHERE r.status_id = s.id AND r.deleted_at IS NULL)
                      + (SELECT COUNT(*) FROM rma_status_history h WHERE h.status_id = s.id) AS used
                   FROM rma_statuses s') as $row) {
                $status_usage[(int)$row['id']] = (int)$row['used'];
            }
        } elseif ($tab === 'codes') {
            // Filtered in SQL, not in the browser. It began as one query for
            // everything with the filters hiding rows client-side, which was
            // fine for the handful of codes typed in by hand — TCL's import
            // brought 1,257, and a page holding all of them is a page nobody
            // waits for.
            // The kind belongs in the query, not in the view. It used to be
            // filtered after paging, so a page of 100 rows could arrive holding
            // 8 of the kind on screen and the pager counted both kinds.
            $code_kind = in_array($_GET['sub'] ?? '', REPAIR_CODE_KINDS, true) ? $_GET['sub'] : 'error';

            $code_where  = ['c.deleted_at IS NULL', 'c.kind = ?'];
            $code_params = [$code_kind];
            if ($b = (int)($_GET['brand'] ?? 0))    { $code_where[] = 'c.brand_id = ?';    $code_params[] = $b; }
            if ($k = (int)($_GET['category'] ?? 0)) { $code_where[] = 'c.category_id = ?'; $code_params[] = $k; }
            if (($q = trim($_GET['q'] ?? '')) !== '') {
                $code_where[]  = '(c.code LIKE ? OR c.label LIKE ? OR c.label_me LIKE ?)';
                array_push($code_params, "%{$q}%", "%{$q}%", "%{$q}%");
            }
            $code_sql = implode(' AND ', $code_where);

            // Paged rather than capped. A cap answers "here are the first 200"
            // and says nothing about the rest, so a code on row 400 simply is
            // not there as far as the reader can tell. 100 a page: a screenful
            // to scan, and the shared pager keeps its width whether that is
            // three pages or forty.
            $code_total    = (int) db_val("SELECT COUNT(*) FROM repair_codes c WHERE {$code_sql}", $code_params);
            $code_per_page = 100;
            $code_page     = max(1, (int)($_GET['page'] ?? 1));
            $code_offset   = ($code_page - 1) * $code_per_page;

            $codes = db_rows("SELECT c.*, b.name AS brand_name, cat.name AS category_name
                                FROM repair_codes c
                                LEFT JOIN device_brands b ON b.id = c.brand_id
                                LEFT JOIN device_categories cat ON cat.id = c.category_id
                               WHERE {$code_sql}
                               ORDER BY c.kind, c.vendor_line, c.sort_order, c.code
                               LIMIT {$code_per_page} OFFSET {$code_offset}", $code_params);

            // Live search asks for the table alone. Same variables, same
            // partial the full page includes, so the two can never drift.
            if (($_GET['ajax'] ?? '') === '1') {
                $rows = $codes;
                $sub  = $code_kind;
                include views_path('admin/tabs/_codes_results.php');
                return;
            }
            // Two lists, because the two dropdowns ask different questions.
            // The form asks what a new code could belong to, so it offers every
            // brand. The filter asks what is worth narrowing to, so it offers
            // only brands that have codes — picking Samsung when Samsung has
            // none is a dead end, and the list grows by itself the moment a
            // code for a new brand is added.
            $brands     = db_rows('SELECT id, name FROM device_brands WHERE is_active = 1 ORDER BY name');
            $categories = db_rows('SELECT id, name FROM device_categories WHERE is_active = 1 ORDER BY sort_order, name');

            $filter_brands = db_rows(
                'SELECT DISTINCT b.id, b.name
                   FROM repair_codes c JOIN device_brands b ON b.id = c.brand_id
                  WHERE c.deleted_at IS NULL
                  ORDER BY b.name'
            );
            $filter_categories = db_rows(
                'SELECT DISTINCT cat.id, cat.name, cat.sort_order
                   FROM repair_codes c JOIN device_categories cat ON cat.id = c.category_id
                  WHERE c.deleted_at IS NULL
                  ORDER BY cat.sort_order, cat.name'
            );
        } elseif ($tab === 'couriers') {
            $couriers = db_rows('SELECT * FROM couriers ORDER BY name');
        } elseif ($tab === 'insurance') {
            $insurers       = db_rows('SELECT * FROM insurers WHERE deleted_at IS NULL ORDER BY name');
            $coverage_items = db_rows('SELECT * FROM insurance_coverage_items ORDER BY sort_order, label');
            $products       = db_rows('SELECT p.*, i.name AS insurer_name
                                         FROM insurance_products p
                                         JOIN insurers i ON i.id = p.insurer_id
                                        WHERE p.deleted_at IS NULL
                                        ORDER BY i.name, p.name');
        }

        include views_path('layout/header.php');
        include views_path('admin/index.php');
        include views_path('layout/footer.php');
    }

    /**
     * The two "Na cemu se koristi" boxes, folded back into the column's value.
     *
     * Returns null when neither is ticked — a status usable on nothing, which
     * the dropdown made impossible and checkboxes do not. Refusing the save is
     * the price of the clearer control, and it is a price the code field and
     * the name field already pay.
     */
    private function status_scope_input(): ?string {
        $picked = (array)($_POST['applies'] ?? []);
        $rma    = in_array('rma', $picked, true);
        $repair = in_array('repair', $picked, true);

        if ($rma && $repair) return 'both';
        if ($rma)            return 'rma';
        if ($repair)         return 'repair';
        return null;
    }

    /**
     * Remove a status nobody has used.
     *
     * Refused the moment a case sits in it or ever passed through it: both
     * rma_requests and rma_status_history carry the id, and the second is
     * somebody's case history — a line of it would simply vanish. The screen
     * already hides the button in that case; this is the check that matters.
     */
    public function status_delete(): void {
        require_login();
        require_permission('administration', 'delete');

        $id  = (int)($_POST['id'] ?? 0);
        $row = $id ? db_row('SELECT * FROM rma_statuses WHERE id = ?', [$id]) : null;
        if (!$row) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => __('admin.status_not_found')];
            header('Location: /administration?tab=statuses'); exit;
        }

        $used = (int) db_val('SELECT COUNT(*) FROM rma_requests WHERE status_id = ? AND deleted_at IS NULL', [$id])
              + (int) db_val('SELECT COUNT(*) FROM rma_status_history WHERE status_id = ?', [$id]);
        if ($used > 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => __('admin.status_in_use_refused')];
            header('Location: /administration?tab=statuses'); exit;
        }

        db()->prepare('DELETE FROM rma_statuses WHERE id = ?')->execute([$id]);
        audit('deleted', 'rma_status', $id, ['old' => $row]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => __('admin.status_deleted')];
        header('Location: /administration?tab=statuses'); exit;
    }

    // ── Vendor codes ─────────────────────────────────────────────

    /**
     * One code, read off the form and validated.
     *
     * Brand and type come back as null rather than 0 when left on "any": the
     * lookup asks `brand_id IS NULL OR brand_id = ?`, and a 0 would match no
     * brand at all instead of every one.
     */
    private function code_input(): array {
        $brand    = (int)($_POST['brand_id'] ?? 0);
        $category = (int)($_POST['category_id'] ?? 0);
        $note     = trim((string)($_POST['note'] ?? ''));

        return [
            'code'        => trim((string)($_POST['code'] ?? '')),
            'label'       => trim((string)($_POST['label'] ?? '')),
            'label_me'    => trim((string)($_POST['label_me'] ?? '')) ?: null,
            'note'        => $note !== '' ? $note : null,
            'brand_id'    => $brand ?: null,
            'category_id' => $category ?: null,
            'sort_order'  => max(0, min(999, (int)($_POST['sort_order'] ?? 10))),
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function code_redirect(string $kind, string $type, string $msg): void {
        $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
        header('Location: /administration?tab=codes&sub=' . urlencode($kind));
        exit;
    }

    public function code_store(): void {
        require_login();
        require_permission('administration', 'create');

        $kind = (string)($_POST['kind'] ?? '');
        if (!in_array($kind, REPAIR_CODE_KINDS, true)) $kind = 'error';

        $data = $this->code_input();
        if ($data['code'] === '' || $data['label'] === '') {
            $this->code_redirect($kind, 'danger', __('codes.code_label_required'));
        }

        $data['kind'] = $kind;
        db_insert('repair_codes', $data);
        audit('code_added', 'repair_code', 0, ['new' => $data]);

        $this->code_redirect($kind, 'success', __('codes.added'));
    }

    public function code_update(): void {
        require_login();
        require_permission('administration', 'edit');

        $id   = (int)($_POST['id'] ?? 0);
        $kind = (string)($_POST['kind'] ?? '');
        if (!in_array($kind, REPAIR_CODE_KINDS, true)) $kind = 'error';

        $before = $id ? db_row('SELECT * FROM repair_codes WHERE id = ?', [$id]) : null;
        if (!$before) {
            $this->code_redirect($kind, 'danger', __('codes.not_found'));
        }

        $data = $this->code_input();
        if ($data['code'] === '' || $data['label'] === '') {
            $this->code_redirect($kind, 'danger', __('codes.code_label_required'));
        }

        // kind is not editable: a job already pointing at this row picked it
        // from one of the two dropdowns, and flipping the kind would move the
        // technician's answer into the other box behind their back.
        $data['updated_at'] = date('Y-m-d H:i:s');
        db_update('repair_codes', $data, 'id = ?', [$id]);
        audit('code_updated', 'repair_code', $id, ['old' => $before, 'new' => $data]);

        $this->code_redirect((string)$before['kind'], 'success', __('codes.saved'));
    }

    // ── Status store ─────────────────────────────────────────────

    public function status_store(): void {
        require_login();
        require_permission('administration', 'create');

        $type    = $_POST['type'] ?? '';
        $label   = trim($_POST['label'] ?? '');
        $label_me = trim($_POST['label_me'] ?? '');
        $code    = trim($_POST['code'] ?? '');
        $color   = trim($_POST['color'] ?? '#888780');
        $sort    = (int)($_POST['sort_order'] ?? 10);
        $term    = isset($_POST['is_terminal']) ? 1 : 0;
        // Whether reaching this status is worth a message. Which channel and
        // which audience is decided in Podesavanja -> Komunikacija.
        $notify  = isset($_POST['notify']) ? 1 : 0;

        if (!$label || !$code || !in_array($type, ['rma', 'repair'])) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('admin.status_label_code_required')];
            header('Location: /administration?tab=statuses');
            exit;
        }

        // One table now. The parameter is kept so an old bookmark posting
        // type=repair lands somewhere sane rather than on a table nothing reads.
        $table = 'rma_statuses';
        $data  = [
            'label'       => $label,
            'label_me'    => $label_me !== '' ? $label_me : null,
            'code'        => $code,
            'color'       => $color,
            'sort_order'  => $sort,
            'is_terminal' => $term,
            'notify'      => $notify,
        ];
        // Which desk may set this status on an RMA. Nothing ticked means every
        // role, which is what a new status should do until somebody narrows it.
        // Repair statuses carry no split — the bench job is the technician's
        // either way — so the column only exists on rma_statuses.
        if ($type === 'rma') {
            $data['roles'] = $this->status_roles_input();
            // Unticked means a case can never come back here, so the status
            // leaves the dropdown once passed. Ticked by default: a new status
            // hides from nobody until somebody says so.
            $data['can_recur'] = isset($_POST['can_recur']) ? 1 : 0;
            // Where the status may be used, and — where work happens — whether
            // reaching it ends that work. Case-only statuses keep 0: the form
            // hides the tick, so an absent box must not read as "unticked by
            // the admin" on something that never had the question.
            $scope = $this->status_scope_input();
            if ($scope === null) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => __('admin.status_applies_required')];
                header('Location: /administration?tab=statuses');
                exit;
            }
            $data['applies_to']      = $scope;
            $data['is_terminal_job'] = ($scope !== 'rma' && isset($_POST['is_terminal_job'])) ? 1 : 0;
        }

        db_insert($table, $data);

        $_SESSION['flash'] = ['type'=>'success','message'=>__('admin.status_added')];
        header('Location: /administration?tab=statuses');
        exit;
    }

    // ── Status update ─────────────────────────────────────────────

    public function status_update(): void {
        require_login();
        require_permission('administration', 'edit');

        $type    = $_POST['type'] ?? '';
        $id      = (int)($_POST['id'] ?? 0);
        $label   = trim($_POST['label'] ?? '');
        $label_me = trim($_POST['label_me'] ?? '');
        $code    = trim($_POST['code'] ?? '');
        $color   = trim($_POST['color'] ?? '#888780');
        $sort    = (int)($_POST['sort_order'] ?? 10);
        $term    = isset($_POST['is_terminal']) ? 1 : 0;
        // Whether reaching this status is worth a message. Which channel and
        // which audience is decided in Podesavanja -> Komunikacija.
        $notify  = isset($_POST['notify']) ? 1 : 0;

        if (!$id || !$label || !$code || !in_array($type, ['rma', 'repair'])) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('admin.invalid_request')];
            header('Location: /administration?tab=statuses');
            exit;
        }

        // One table now. The parameter is kept so an old bookmark posting
        // type=repair lands somewhere sane rather than on a table nothing reads.
        $table = 'rma_statuses';
        $data  = [
            'label'       => $label,
            'label_me'    => $label_me !== '' ? $label_me : null,
            'code'        => $code,
            'color'       => $color,
            'sort_order'  => $sort,
            'is_terminal' => $term,
            'notify'      => $notify,
        ];
        // See status_store(): both are RMA-status properties only.
        if ($type === 'rma') {
            $data['roles']     = $this->status_roles_input();
            $data['can_recur'] = isset($_POST['can_recur']) ? 1 : 0;
            // Where the status may be used, and — where work happens — whether
            // reaching it ends that work. Case-only statuses keep 0: the form
            // hides the tick, so an absent box must not read as "unticked by
            // the admin" on something that never had the question.
            $scope = $this->status_scope_input();
            if ($scope === null) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => __('admin.status_applies_required')];
                header('Location: /administration?tab=statuses');
                exit;
            }
            $data['applies_to']      = $scope;
            $data['is_terminal_job'] = ($scope !== 'rma' && isset($_POST['is_terminal_job'])) ? 1 : 0;
        }

        db_update($table, $data, 'id = ?', [$id]);

        $_SESSION['flash'] = ['type'=>'success','message'=>__('admin.status_updated')];
        header('Location: /administration?tab=statuses');
        exit;
    }

    // ── Insurance: insurers and the coverage list ────────────────
    //
    // Both are configuration rather than daily work — a handful of rows that
    // policies then point at — so they live in Administracija. Policies
    // themselves are operational and get their own screen.

    private function insurer_data(): array {
        return [
            'name'           => trim($_POST['name'] ?? ''),
            'contact_person' => trim($_POST['contact_person'] ?? '') ?: null,
            'email'          => trim($_POST['email'] ?? '') ?: null,
            'phone'          => trim($_POST['phone'] ?? '') ?: null,
            'portal_url'     => trim($_POST['portal_url'] ?? '') ?: null,
            // How long after the incident this insurer accepts a report. Left at
            // 0 until somebody asks them, so the app never invents a deadline.
            'report_hours'   => max(0, min(8760, (int)($_POST['report_hours'] ?? 0))),
        ];
    }

    public function insurer_store(): void {
        require_login();
        require_permission('administration', 'create');
        $data = $this->insurer_data();
        if ($data['name'] === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => __('ins.name_required')];
            header('Location: /administration?tab=insurance'); exit;
        }
        $id = db_insert('insurers', $data);
        audit('created', 'insurer', $id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => __('ins.insurer_saved')];
        header('Location: /administration?tab=insurance'); exit;
    }

    public function insurer_update(): void {
        require_login();
        require_permission('administration', 'edit');
        $id   = (int)($_POST['id'] ?? 0);
        $data = $this->insurer_data();
        if (!$id || $data['name'] === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => __('ins.name_required')];
            header('Location: /administration?tab=insurance'); exit;
        }
        db_update('insurers', $data, 'id = ?', [$id]);
        audit('updated', 'insurer', $id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => __('ins.insurer_saved')];
        header('Location: /administration?tab=insurance'); exit;
    }

    /**
     * A product is only a template — picking one fills a policy in and is never
     * consulted again, because the policy carries its own terms. It exists so
     * the counter types a policy number and two dates rather than everything.
     */
    private function product_data(): array {
        return [
            'insurer_id'        => (int)($_POST['insurer_id'] ?? 0),
            'name'              => trim($_POST['name'] ?? ''),
            'coverage'          => implode(',', array_map('trim', (array)($_POST['coverage'] ?? []))) ?: null,
            'participation_pct' => max(0, min(100, (float)($_POST['participation_pct'] ?? 0))),
            'claims_allowed'    => max(0, min(99, (int)($_POST['claims_allowed'] ?? 1))),
        ];
    }

    public function product_store(): void {
        require_login();
        require_permission('administration', 'create');
        $data = $this->product_data();
        if (!$data['insurer_id'] || $data['name'] === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => __('ins.product_required')];
            header('Location: /administration?tab=insurance'); exit;
        }
        $id = db_insert('insurance_products', $data);
        audit('created', 'insurance_product', $id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => __('ins.product_saved')];
        header('Location: /administration?tab=insurance'); exit;
    }

    public function product_update(): void {
        require_login();
        require_permission('administration', 'edit');
        $id   = (int)($_POST['id'] ?? 0);
        $data = $this->product_data();
        if (!$id || !$data['insurer_id'] || $data['name'] === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => __('ins.product_required')];
            header('Location: /administration?tab=insurance'); exit;
        }
        // Policies already written keep what they were given: a product is a
        // starting point, not a rule, so editing one never reaches back.
        db_update('insurance_products', $data, 'id = ?', [$id]);
        audit('updated', 'insurance_product', $id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => __('ins.product_saved')];
        header('Location: /administration?tab=insurance'); exit;
    }

    private function coverage_data(): array {
        return [
            'label'      => trim($_POST['label'] ?? ''),
            'label_me'   => trim($_POST['label_me'] ?? '') ?: null,
            'code'       => trim($_POST['code'] ?? ''),
            'sort_order' => max(0, min(999, (int)($_POST['sort_order'] ?? 10))),
        ];
    }

    public function coverage_store(): void {
        require_login();
        require_permission('administration', 'create');
        $data = $this->coverage_data();
        if ($data['label'] === '' || $data['code'] === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => __('admin.status_label_code_required')];
            header('Location: /administration?tab=insurance'); exit;
        }
        $data['is_active'] = 1;
        $id = db_insert('insurance_coverage_items', $data);
        audit('created', 'coverage_item', $id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => __('ins.coverage_saved')];
        header('Location: /administration?tab=insurance'); exit;
    }

    public function coverage_update(): void {
        require_login();
        require_permission('administration', 'edit');
        $id   = (int)($_POST['id'] ?? 0);
        $data = $this->coverage_data();
        if (!$id || $data['label'] === '' || $data['code'] === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => __('admin.status_label_code_required')];
            header('Location: /administration?tab=insurance'); exit;
        }

        // Policies store the code, not the id, so renaming one in use would
        // quietly drop that item from every policy that ticked it. Labels are
        // free to change — a code that is in use is not.
        $old = db_val('SELECT code FROM insurance_coverage_items WHERE id = ?', [$id]);
        $locked = false;
        if ($old !== null && $old !== $data['code']) {
            $in_use = (int) db_val(
                'SELECT COUNT(*) FROM insurance_policies WHERE deleted_at IS NULL AND coverage LIKE ?',
                ['%' . $old . '%']
            );
            if ($in_use) { unset($data['code']); $locked = true; }
        }

        db_update('insurance_coverage_items', $data, 'id = ?', [$id]);
        audit('updated', 'coverage_item', $id);
        $_SESSION['flash'] = $locked
            ? ['type' => 'success', 'message' => __('ins.code_locked')]
            : ['type' => 'success', 'message' => __('ins.coverage_saved')];
        header('Location: /administration?tab=insurance'); exit;
    }

    /**
     * The ticked roles from the status modal, as the comma-separated list the
     * column stores. Unknown role codes are dropped; nothing ticked stores
     * NULL, which every role can set.
     */
    private function status_roles_input(): ?string {
        $picked = status_roles(implode(',', (array)($_POST['roles'] ?? [])));
        return $picked ? implode(',', $picked) : null;
    }

    public function settings(): void {
        require_login();
        require_permission('settings', 'view');

        $stab        = $_GET['stab'] ?? 'general';
        $page_title  = __('nav.settings');
        $vat_rates   = db_rows('SELECT * FROM vat_rates ORDER BY rate');
        $technicians = db_rows("SELECT id, name FROM users WHERE role IN ('technician','super_admin','admin') AND is_active = 1 ORDER BY name");
        $locations   = db_rows('SELECT * FROM locations WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
        $languages   = db_rows('SELECT * FROM languages WHERE is_active = 1 ORDER BY name');
        $themes      = db_rows('SELECT * FROM themes    WHERE is_active = 1 ORDER BY name');
        $templates   = db_rows('SELECT * FROM notification_templates ORDER BY channel, id');
        $chan_filter  = $_GET['channel'] ?? '';
        $lang_filter  = $_GET['lang'] ?? '';

        include views_path('layout/header.php');
        include views_path('admin/settings_page.php');
        include views_path('layout/footer.php');
    }

    // The permission matrix lives on the Settings → Permissions tab; this
    // legacy route just forwards there so a direct /admin/permissions hit
    // doesn't 404/500.
    public function permissions(): void {
        require_login();
        require_permission('settings', 'view');
        header('Location: /settings?stab=permissions');
        exit;
    }

    // Save the editable role permission matrix (Settings → Permissions).
    // Only Super Admin / Admin may change permissions. Super Admin and Admin
    // themselves are always full-access and are never stored, so they can't be
    // edited or locked out here.
    /**
     * Clear a user's authenticator app — the recovery path when someone loses
     * their phone. They drop back to email/SMS and can enrol again.
     */
    public function user_totp_reset(): void {
        require_login();
        require_permission('administration', 'edit');
        csrf_verify();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            totp_reset($id);
            $_SESSION['flash'] = ['type' => 'success', 'message' => __('users.totp_reset_done')];
        }
        header('Location: /settings?stab=users');
        exit;
    }

    public function permissions_save(): void {
        require_login();
        // Super Admin only. is_admin_user() also matched role 'admin', so an
        // Admin could POST here and grant itself anything back — which would
        // make removing its Settings access cosmetic rather than real.
        if (!is_super_admin()) { http_response_code(403); include views_path('errors/403.php'); exit; }

        // Admin + the three fixed roles are editable. Super Admin is always
        // full-access and never stored, so it can't be edited or locked out.
        // PERMISSION_MATRIX (permissions.php) is the single source of truth; a
        // crafted POST outside it is ignored, so no one can invent permissions.
        $editable_roles = ['admin', 'reception', 'technician', 'partner'];
        $matrix = PERMISSION_MATRIX;
        $posted = $_POST['perm'] ?? [];

        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($editable_roles as $role) {
                $locked = ROLE_LOCKED_PERMISSIONS[$role] ?? [];
                // Replace only the matrix modules for this role, leaving any
                // other modules (e.g. delivery/appointments) untouched.
                foreach ($matrix as $module => $actions) {
                    db_delete('role_permissions', 'role = ? AND module = ?', [$role, $module]);
                    foreach ($actions as $action) {
                        $key = "{$module}.{$action}";
                        // Locked permissions are always kept, even if the box
                        // wasn't posted (it renders disabled for that role).
                        if (!empty($posted[$role][$key]) || in_array($key, $locked, true)) {
                            db_insert('role_permissions', [
                                'role'   => $role,
                                'module' => $module,
                                'action' => $action,
                            ]);
                        }
                    }
                }
            }

            // 2FA channels live in security_policies, not role_permissions, so
            // they are saved separately. Super Admin is included here — unlike
            // the module matrix, its channels are a real setting.
            $valid_channels = ['totp', 'email', 'sms', 'whatsapp'];
            $posted_chan    = $_POST['chan'] ?? [];
            foreach (array_merge($editable_roles, ['super_admin']) as $role) {
                $picked = array_values(array_intersect(
                    $valid_channels,
                    array_keys(array_filter($posted_chan[$role] ?? []))
                ));
                // Never store an empty list: a role with 2FA required and no
                // channel could not log in at all. Email is the fallback
                // because it needs no hardware and no credit.
                if (!$picked) { $picked = ['email']; }
                db_update('security_policies',
                    ['allowed_2fa_channels' => implode(',', $picked)],
                    'role = ?', [$role]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'danger', 'message' => __('admin.perm_save_error')];
            header('Location: /settings?stab=permissions');
            exit;
        }

        audit('permissions_updated', 'role_permissions', 0);
        $_SESSION['flash'] = ['type' => 'success', 'message' => __('admin.perm_saved')];
        header('Location: /settings?stab=permissions');
        exit;
    }

    public function device_catalog(): void {
        // Legacy URL — the catalogue is its own section now. Pointing straight
        // at it avoids bouncing through /administration, which only redirects
        // here again.
        header('Location: /devices', true, 301);
        exit;
    }
}
