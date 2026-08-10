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
        $_SESSION['form_success'] = __('partners.added');
        header('Location: /partners');
        exit;
    }

    public function edit(string $id): void {
        require_login();
        require_permission('partners', 'view');

        $partner = db_row('SELECT * FROM partners WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$partner) { http_response_code(404); include views_path('errors/404.php'); return; }

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
}
