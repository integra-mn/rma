<?php
defined('RMS') or die('Direct access not permitted');

class PartnersController {

    public function index(): void {
        require_login();
        require_permission('partners', 'view');

        $page_title = __('partners.title');
        $search     = trim($_GET['q'] ?? '');
        $page       = min(100000, max(1, (int)($_GET['page'] ?? 1)));
        $per_page   = 25;
        $offset     = ($page - 1) * $per_page;

        $where  = 'p.deleted_at IS NULL';
        $params = [];
        if ($search) {
            $where   .= ' AND (p.name LIKE ? OR p.email LIKE ? OR p.contact_person LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total    = (int) db_val("SELECT COUNT(*) FROM partners p WHERE {$where}", $params);
        $partners = db_rows("SELECT p.*,
                              COUNT(DISTINCT pu.id) as user_count,
                              COUNT(DISTINCT r.id)  as rma_count
                             FROM partners p
                             LEFT JOIN partner_users pu ON pu.partner_id = p.id
                             LEFT JOIN rma_requests r ON r.partner_id = p.id AND r.deleted_at IS NULL
                             WHERE {$where}
                             GROUP BY p.id
                             ORDER BY p.name
                             LIMIT {$per_page} OFFSET {$offset}", $params);

        $error   = $_SESSION['form_error'] ?? null;
        $success = $_SESSION['form_success'] ?? null;
        unset($_SESSION['form_error'], $_SESSION['form_success']);

        include views_path('layout/header.php');
        include views_path('partners/index.php');
        include views_path('layout/footer.php');
    }

    public function store(): void {
        require_login();
        require_permission('partners', 'edit');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['form_error'] = __('partners.name_required');
            header('Location: /partners');
            exit;
        }

        // Checked here as well as on the form: `required` is a client-side hint
        // and a partner created without a branch would look broken everywhere a
        // branch dropdown appears.
        $branch = trim($_POST['branch_name'] ?? '');
        if ($branch === '') {
            $_SESSION['form_error'] = __('partners.branch_required');
            header('Location: /partners');
            exit;
        }

        $id = db_insert('partners', [
            'name'           => $name,
            'tax_id'         => trim($_POST['tax_id'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
            'address'        => trim($_POST['address'] ?? ''),
            'zip_code'       => trim($_POST['zip_code'] ?? ''),
            'city'           => trim($_POST['city'] ?? ''),
            'country'        => trim($_POST['country'] ?? 'Montenegro'),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'notes'          => trim($_POST['notes'] ?? ''),
            'is_active'      => 1,
        ]);
        audit('created', 'partner', $id);

        // The first poslovnica. A row rather than text on the partner, so RMAs
        // can be counted against it; further branches are added on the
        // partner's own page.
        db_insert('partner_branches', [
            'partner_id' => $id,
            'name'       => $branch,
            'city'       => trim($_POST['city'] ?? '') ?: null,
            'is_active'  => 1,
        ]);
        audit('branch_added', 'partner', $id, ['new' => ['branch' => $branch]]);

        $_SESSION['form_success'] = __('partners.added');
        header('Location: /partners');
        exit;
    }

    public function edit(string $id): void {
        require_login();
        require_permission('partners', 'view');

        $partner = db_row('SELECT * FROM partners WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$partner) { http_response_code(404); include views_path('errors/404.php'); return; }

        $branches = db_rows(
            'SELECT * FROM partner_branches
              WHERE partner_id = ? AND deleted_at IS NULL
              ORDER BY is_active DESC, name',
            [(int)$id]
        );

        $users = db_rows('SELECT u.id, u.name, u.email, pu.role
                          FROM partner_users pu
                          JOIN users u ON u.id = pu.user_id
                          WHERE pu.partner_id = ?
                          ORDER BY u.name', [(int)$id]);

        $rma_count = (int) db_val('SELECT COUNT(*) FROM rma_requests WHERE partner_id = ? AND deleted_at IS NULL', [(int)$id]);
        $couriers  = db_rows('SELECT id, name FROM couriers WHERE is_active = 1 ORDER BY name');

        $page_title = __('partners.edit');
        $error      = $_SESSION['form_error'] ?? null;
        $success    = $_SESSION['form_success'] ?? null;
        unset($_SESSION['form_error'], $_SESSION['form_success']);

        include views_path('layout/header.php');
        include views_path('partners/edit.php');
        include views_path('layout/footer.php');
    }

    public function update(string $id): void {
        require_login();
        require_permission('partners', 'edit');

        $partner = db_row('SELECT * FROM partners WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$partner) { http_response_code(404); include views_path('errors/404.php'); return; }

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['form_error'] = __('partners.name_required');
            header("Location: /partners/{$id}/edit");
            exit;
        }

        $new = [
            'name'           => $name,
            'tax_id'         => trim($_POST['tax_id'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
            'address'        => trim($_POST['address'] ?? ''),
            'zip_code'       => trim($_POST['zip_code'] ?? ''),
            'city'           => trim($_POST['city'] ?? ''),
            'country'        => trim($_POST['country'] ?? ''),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'notes'          => trim($_POST['notes'] ?? ''),
            'default_courier_id' => (int)($_POST['default_courier_id'] ?? 0) ?: null,
            // An RMA with this partner always notifies the partner; this says
            // whether the end user hears from us as well.
            'notify_customer' => isset($_POST['notify_customer']) ? 1 : 0,
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        ];

        audit_change('partner', (int)$id, $partner, $new);
        db_update('partners', $new, 'id = ?', [(int)$id]);
        $_SESSION['form_success'] = __('partners.updated');
        header('Location: /partners');
        exit;
    }

    public function delete(string $id): void {
        require_login();
        require_permission('partners', 'edit');

        $in_use = db_val('SELECT COUNT(*) FROM rma_requests WHERE partner_id = ? AND deleted_at IS NULL', [(int)$id]);
        if ($in_use) {
            $_SESSION['form_error'] = __('partners.in_use');
        } else {
            db_soft_delete('partners', (int)$id);
            audit('deleted', 'partner', (int)$id);
            $_SESSION['form_success'] = __('partners.deleted');
        }
        header('Location: /partners');
        exit;
    }

    // ── Branches (poslovnice) ─────────────────────────────────────
    //
    // Real rows rather than a text field on the partner, so RMAs can be counted
    // per branch. See migrations/2026_08_11_partner_branches.sql.

    public function branch_store(string $id): void {
        require_login();
        require_permission('partners', 'edit');
        csrf_verify();

        $partner_id = (int) $id;
        $name       = trim($_POST['branch_name'] ?? '');

        if ($name === '') {
            $_SESSION['form_error'] = __('partners.branch_name_required');
            header("Location: /partners/{$partner_id}/edit"); exit;
        }

        db_insert('partner_branches', [
            'partner_id' => $partner_id,
            'name'       => $name,
            'city'       => trim($_POST['branch_city'] ?? '') ?: null,
            'phone'      => trim($_POST['branch_phone'] ?? '') ?: null,
            'is_active'  => 1,
        ]);

        audit('branch_added', 'partner', $partner_id, ['new' => ['branch' => $name]]);
        $_SESSION['form_success'] = __('partners.branch_saved');
        header("Location: /partners/{$partner_id}/edit"); exit;
    }

    public function branch_update(string $id): void {
        require_login();
        require_permission('partners', 'edit');
        csrf_verify();

        $partner_id = (int) $id;
        $branch_id  = (int) ($_POST['branch_id'] ?? 0);
        $name       = trim($_POST['branch_name'] ?? '');

        if ($name === '') {
            $_SESSION['form_error'] = __('partners.branch_name_required');
            header("Location: /partners/{$partner_id}/edit"); exit;
        }

        // Scoped to the partner: a crafted post must not rename someone else's
        // branch by guessing an id.
        if ($branch_id) {
            db_update('partner_branches', [
                'name'  => $name,
                'city'  => trim($_POST['branch_city'] ?? '') ?: null,
                'phone' => trim($_POST['branch_phone'] ?? '') ?: null,
            ], 'id = ? AND partner_id = ?', [$branch_id, $partner_id]);
            audit('branch_updated', 'partner', $partner_id, ['new' => ['branch' => $name]]);
            $_SESSION['form_success'] = __('partners.branch_saved');
        }
        header("Location: /partners/{$partner_id}/edit"); exit;
    }

    /**
     * Soft-delete: RMAs already reference the branch, and analytics would lose
     * its history if the row vanished.
     */
    public function branch_delete(string $id): void {
        require_login();
        require_permission('partners', 'edit');
        csrf_verify();

        $partner_id = (int) $id;
        $branch_id  = (int) ($_POST['branch_id'] ?? 0);

        if ($branch_id) {
            // Every partner keeps at least one poslovnica. It is required when
            // the partner is created, and without this the rule would only hold
            // until someone removed the last one — putting the partner back in
            // the state where every branch dropdown looks empty and broken.
            $remaining = (int) db_val(
                'SELECT COUNT(*) FROM partner_branches
                  WHERE partner_id = ? AND deleted_at IS NULL',
                [$partner_id]
            );
            if ($remaining <= 1) {
                $_SESSION['form_error'] = __('partners.branch_last');
                header("Location: /partners/{$partner_id}/edit"); exit;
            }

            db_update('partner_branches',
                ['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0],
                'id = ? AND partner_id = ?', [$branch_id, $partner_id]);
            audit('branch_removed', 'partner', $partner_id);
            $_SESSION['form_success'] = __('partners.branch_removed');
        }
        header("Location: /partners/{$partner_id}/edit"); exit;
    }
}
