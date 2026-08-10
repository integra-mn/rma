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
        require_permission('settings', 'edit');

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
        require_permission('settings', 'edit');

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
        require_permission('settings', 'edit');

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
        require_permission('settings', 'edit');
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
        require_permission('settings', 'edit');
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
        require_permission('settings', 'edit');
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
        require_permission('settings', 'edit');
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
        require_permission('settings', 'edit');

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

        audit('created', 'user', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('users.created', ['name'=>$name])];
        header('Location: /administration?tab=users');
        exit;
    }

    public function user_update(): void {
        require_login();
        require_permission('settings', 'edit');

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

        $data = [
            'name'        => trim($_POST['name'] ?? $user['name']),
            'email'       => strtolower(trim($_POST['email'] ?? $user['email'])),
            'phone'       => $phone,
            'role'        => $new_role,
            'location_id' => (int)($_POST['location_id'] ?? 0) ?: null,
            'lang'        => $_POST['lang'] ?? $user['lang'],
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
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
        audit('updated', 'user', $id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('users.updated')];
        header('Location: /administration?tab=users');
        exit;
    }

    public function user_toggle(): void {
        require_login();
        require_permission('settings', 'edit');

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
        require_permission('settings', 'edit');

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
        require_permission('settings', 'edit');

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
        require_permission('settings', 'view');

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
            // Super Admin pinned to the top, then the usual active-first,
            // alphabetical order. CASE rather than a role sort so adding roles
            // later doesn't silently reshuffle the list.
            $users     = db_rows("SELECT u.*, l.name as location_name
                                  FROM users u
                                  LEFT JOIN locations l ON l.id = u.location_id
                                  WHERE u.deleted_at IS NULL
                                  ORDER BY CASE WHEN u.role = 'super_admin' THEN 0 ELSE 1 END,
                                           u.is_active DESC, u.name");
            $locations = db_rows('SELECT * FROM locations WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
        } elseif ($tab === 'locations') {
            $locations = db_rows('SELECT * FROM locations WHERE deleted_at IS NULL ORDER BY name');
        } elseif ($tab === 'devices') {
            $tab        = 'devices'; // pass to devices/index.php as $tab
            $categories = db_rows('SELECT * FROM device_categories ORDER BY sort_order, name');
            $brands     = db_rows('SELECT b.*, COUNT(m.id) as model_count
                                   FROM device_brands b
                                   LEFT JOIN device_models m ON m.brand_id = b.id
                                   GROUP BY b.id ORDER BY b.name');
            $models     = db_rows('SELECT m.*, b.name as brand_name, c.name as category_name
                                   FROM device_models m
                                   JOIN device_brands b ON b.id = m.brand_id
                                   JOIN device_categories c ON c.id = m.category_id
                                   ORDER BY b.name, m.name');
            $part_groups = db_rows(
                'SELECT pg.*, COUNT(p.id) AS part_count
                 FROM part_groups pg
                 LEFT JOIN parts p ON p.part_group_id = pg.id AND p.deleted_at IS NULL
                 GROUP BY pg.id
                 ORDER BY pg.sort_order, pg.name'
            );
        } elseif ($tab === 'statuses') {
            $rma_statuses    = db_rows('SELECT * FROM rma_statuses ORDER BY sort_order, label');
            $repair_statuses = db_rows('SELECT * FROM repair_statuses ORDER BY sort_order, label');
        } elseif ($tab === 'couriers') {
            $couriers = db_rows('SELECT * FROM couriers ORDER BY name');
        }

        include views_path('layout/header.php');
        include views_path('admin/index.php');
        include views_path('layout/footer.php');
    }

    // ── Status store ─────────────────────────────────────────────

    public function status_store(): void {
        require_login();
        require_permission('settings', 'edit');

        $type    = $_POST['type'] ?? '';
        $label   = trim($_POST['label'] ?? '');
        $label_me = trim($_POST['label_me'] ?? '');
        $code    = trim($_POST['code'] ?? '');
        $color   = trim($_POST['color'] ?? '#888780');
        $sort    = (int)($_POST['sort_order'] ?? 10);
        $term    = isset($_POST['is_terminal']) ? 1 : 0;

        if (!$label || !$code || !in_array($type, ['rma', 'repair'])) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('admin.status_label_code_required')];
            header('Location: /administration?tab=statuses');
            exit;
        }

        $table = $type === 'rma' ? 'rma_statuses' : 'repair_statuses';
        db_insert($table, [
            'label'       => $label,
            'label_me'    => $label_me !== '' ? $label_me : null,
            'code'        => $code,
            'color'       => $color,
            'sort_order'  => $sort,
            'is_terminal' => $term,
        ]);

        $_SESSION['flash'] = ['type'=>'success','message'=>__('admin.status_added')];
        header('Location: /administration?tab=statuses');
        exit;
    }

    // ── Status update ─────────────────────────────────────────────

    public function status_update(): void {
        require_login();
        require_permission('settings', 'edit');

        $type    = $_POST['type'] ?? '';
        $id      = (int)($_POST['id'] ?? 0);
        $label   = trim($_POST['label'] ?? '');
        $label_me = trim($_POST['label_me'] ?? '');
        $code    = trim($_POST['code'] ?? '');
        $color   = trim($_POST['color'] ?? '#888780');
        $sort    = (int)($_POST['sort_order'] ?? 10);
        $term    = isset($_POST['is_terminal']) ? 1 : 0;

        if (!$id || !$label || !$code || !in_array($type, ['rma', 'repair'])) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('admin.invalid_request')];
            header('Location: /administration?tab=statuses');
            exit;
        }

        $table = $type === 'rma' ? 'rma_statuses' : 'repair_statuses';
        db_update($table, [
            'label'       => $label,
            'label_me'    => $label_me !== '' ? $label_me : null,
            'code'        => $code,
            'color'       => $color,
            'sort_order'  => $sort,
            'is_terminal' => $term,
        ], 'id = ?', [$id]);

        $_SESSION['flash'] = ['type'=>'success','message'=>__('admin.status_updated')];
        header('Location: /administration?tab=statuses');
        exit;
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
    public function permissions_save(): void {
        require_login();
        if (!is_admin_user()) { http_response_code(403); include views_path('errors/403.php'); exit; }

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
        // Legacy URL — the live page is the Administration → Devices tab.
        header('Location: /administration?tab=devices', true, 301);
        exit;
    }

    // ── Category actions ──────────────────────────────────────

    public function category_store(): void {
        require_login();
        require_permission('settings', 'edit');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('catalog.cat_required')];
            header('Location: /admin/device-catalog');
            exit;
        }

        $slug = $this->slugify($name);
        $existing = db_val('SELECT id FROM device_categories WHERE slug = ?', [$slug]);
        if ($existing) $slug .= '-' . time();

        $prefix = strtoupper(trim($_POST['sku_prefix'] ?? ''));
        if (!$prefix) {
            // Auto-generate from first 3 letters of name
            $prefix = strtoupper(preg_replace('/[^A-Z]/', '', strtoupper($name)));
            $prefix = substr($prefix, 0, 4);
        }

        $new_id = db_insert('device_categories', [
            'name'       => $name,
            'slug'       => $slug,
            'sku_prefix' => $prefix,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active'  => 1,
        ]);
        audit('created', 'device_category', $new_id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('catalog.category_added')];
        header('Location: /admin/device-catalog');
        exit;
    }

    public function category_delete(): void {
        require_login();
        require_permission('settings', 'edit');

        $id = (int)($_POST['id'] ?? 0);
        $in_use = db_val('SELECT COUNT(*) FROM device_models WHERE category_id = ?', [$id]);
        if ($in_use) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('catalog.cat_in_use')];
        } else {
            db_delete('device_categories', 'id = ?', [$id]);
            audit('deleted', 'device_category', $id);
            $_SESSION['flash'] = ['type'=>'success','message'=>__('catalog.category_deleted')];
        }
        header('Location: /admin/device-catalog');
        exit;
    }

    // ── Brand actions ─────────────────────────────────────────

    public function brand_store(): void {
        require_login();
        require_permission('settings', 'edit');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('catalog.brand_required')];
            header('Location: /admin/device-catalog');
            exit;
        }

        $slug = $this->slugify($name);
        $existing = db_val('SELECT id FROM device_brands WHERE slug = ?', [$slug]);
        if ($existing) $slug .= '-' . time();

        $new_id = db_insert('device_brands', [
            'name'      => $name,
            'slug'      => $slug,
            'is_active' => 1,
        ]);
        audit('created', 'device_brand', $new_id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('catalog.brand_added')];
        header('Location: /admin/device-catalog');
        exit;
    }

    public function brand_delete(): void {
        require_login();
        require_permission('settings', 'edit');

        $id = (int)($_POST['id'] ?? 0);
        $in_use = db_val('SELECT COUNT(*) FROM device_models WHERE brand_id = ?', [$id]);
        if ($in_use) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('catalog.brand_in_use')];
        } else {
            db_delete('device_brands', 'id = ?', [$id]);
            audit('deleted', 'device_brand', $id);
            $_SESSION['flash'] = ['type'=>'success','message'=>__('catalog.brand_deleted')];
        }
        header('Location: /admin/device-catalog');
        exit;
    }

    // ── Model actions ─────────────────────────────────────────

    public function model_store(): void {
        require_login();
        require_permission('settings', 'edit');

        $name        = trim($_POST['name'] ?? '');
        $brand_id    = (int)($_POST['brand_id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);

        if (!$name || !$brand_id || !$category_id) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('catalog.fields_required')];
            header('Location: /admin/device-catalog');
            exit;
        }

        $new_id = db_insert('device_models', [
            'name'         => $name,
            'brand_id'     => $brand_id,
            'category_id'  => $category_id,
            'model_number' => trim($_POST['model_number'] ?? ''),
            'release_year' => $_POST['release_year'] ?: null,
            'is_active'    => 1,
        ]);
        audit('created', 'device_model', $new_id);
        $_SESSION['flash'] = ['type'=>'success','message'=>__('catalog.model_added')];
        header('Location: /admin/device-catalog');
        exit;
    }

    public function model_delete(): void {
        require_login();
        require_permission('settings', 'edit');

        $id = (int)($_POST['id'] ?? 0);
        $in_use = db_val('SELECT COUNT(*) FROM devices WHERE model_id = ?', [$id]);
        if ($in_use) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('catalog.model_in_use')];
        } else {
            db_delete('device_models', 'id = ?', [$id]);
            audit('deleted', 'device_model', $id);
            $_SESSION['flash'] = ['type'=>'success','message'=>__('catalog.model_deleted')];
        }
        header('Location: /admin/device-catalog');
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────

    private function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
