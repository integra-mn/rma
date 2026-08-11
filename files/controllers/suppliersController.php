<?php
defined('RMS') or die('Direct access not permitted');

class SuppliersController {

    public function index(): void {
        require_login();
        require_permission('parts', 'view');

        $page_title = __('parts.tab_suppliers');
        $search     = trim($_GET['q'] ?? '');
        $where      = 'deleted_at IS NULL AND is_active = 1';
        $params     = [];

        if ($search) {
            $where   .= ' AND (name LIKE ? OR email LIKE ? OR city LIKE ?)';
            $like     = "%{$search}%";
            $params   = [$like, $like, $like];
        }

        $suppliers = db_rows("SELECT s.*,
                                     (SELECT COUNT(*) FROM parts WHERE supplier_id = s.id AND deleted_at IS NULL) as part_count
                              FROM suppliers s
                              WHERE {$where}
                              ORDER BY s.name", $params);

        $success = $_SESSION['form_success'] ?? null;
        $error   = $_SESSION['form_error']   ?? null;
        unset($_SESSION['form_success'], $_SESSION['form_error']);

        include views_path('layout/header.php');
        include views_path('suppliers/index.php');
        include views_path('layout/footer.php');
    }

    public function store(): void {
        require_login();
        require_permission('parts', 'edit');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['form_error'] = __('suppliers.name_required');
            header('Location: /suppliers');
            exit;
        }

        $id = db_insert('suppliers', [
            'name'      => $name,
            'contact'   => trim($_POST['contact'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
            'phone'     => trim($_POST['phone'] ?? ''),
            'address'   => trim($_POST['address'] ?? ''),
            'city'      => trim($_POST['city'] ?? ''),
            'zip_code'  => trim($_POST['zip_code'] ?? ''),
            'country'   => trim($_POST['country'] ?? ''),
            'notes'     => trim($_POST['notes'] ?? ''),
            'is_active' => 1,
        ]);

        audit('created', 'supplier', $id);
        $_SESSION['form_success'] = __('suppliers.added');
        header('Location: /suppliers');
        exit;
    }

    public function update(): void {
        require_login();
        require_permission('parts', 'edit');

        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || !$name) {
            $_SESSION['form_error'] = __('suppliers.name_required');
            header('Location: /suppliers');
            exit;
        }

        db_update('suppliers', [
            'name'      => $name,
            'contact'   => trim($_POST['contact'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
            'phone'     => trim($_POST['phone'] ?? ''),
            'address'   => trim($_POST['address'] ?? ''),
            'city'      => trim($_POST['city'] ?? ''),
            'zip_code'  => trim($_POST['zip_code'] ?? ''),
            'country'   => trim($_POST['country'] ?? ''),
            'notes'     => trim($_POST['notes'] ?? ''),
        ], 'id = ?', [$id]);

        audit('updated', 'supplier', $id);
        $_SESSION['form_success'] = __('suppliers.updated');
        header('Location: /suppliers');
        exit;
    }
}
