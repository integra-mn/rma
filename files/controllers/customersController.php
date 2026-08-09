<?php
defined('RMS') or die('Direct access not permitted');

class CustomersController {

    public function index(): void {
        require_login();
        require_permission('customers', 'view');

        $page_title = __('customers.title');
        $search     = trim($_GET['q'] ?? '');
        $page       = min(100000, max(1, (int)($_GET['page'] ?? 1)));
        $per_page   = 25;
        $offset     = ($page - 1) * $per_page;

        $where  = 'deleted_at IS NULL';
        $params = [];
        if ($search) {
            $where   .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total     = (int) db_val("SELECT COUNT(*) FROM customers WHERE {$where}", $params);
        $customers = db_rows("SELECT * FROM customers WHERE {$where} ORDER BY name LIMIT {$per_page} OFFSET {$offset}", $params);

        $error   = $_SESSION['form_error'] ?? null;
        $success = $_SESSION['form_success'] ?? null;
        unset($_SESSION['form_error'], $_SESSION['form_success']);

        include views_path('layout/header.php');
        include views_path('customers/index.php');
        include views_path('layout/footer.php');
    }

    public function store(): void {
        require_login();
        require_permission('customers', 'create');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['form_error'] = __('customers.name_required');
            header('Location: /customers');
            exit;
        }

        $country_code = preg_replace('/\D/', '', $_POST['phone_country_code'] ?? '382');
        $phone = normalize_phone(trim($_POST['phone'] ?? ''), $country_code);

        $id = db_insert('customers', [
            'name'     => $name,
            'email'    => strtolower(trim($_POST['email'] ?? '')),
            'phone'    => $phone,
            'address'  => trim($_POST['address'] ?? ''),
            'city'     => trim($_POST['city'] ?? ''),
            'zip_code' => trim($_POST['zip_code'] ?? ''),
            'country'  => trim($_POST['country'] ?? ''),
            'notes'    => trim($_POST['notes'] ?? ''),
        ]);
        audit('created', 'customer', $id);
        $_SESSION['form_success'] = __('customers.added');
        header('Location: /customers');
        exit;
    }

    public function edit(string $id): void {
        require_login();
        require_permission('customers', 'edit');

        $customer = db_row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$customer) { http_response_code(404); include views_path('errors/404.php'); return; }

        $rma_count = (int) db_val('SELECT COUNT(*) FROM rma_requests WHERE customer_id = ? AND deleted_at IS NULL', [(int)$id]);

        $page_title = __('customers.edit');
        $error      = $_SESSION['form_error'] ?? null;
        $success    = $_SESSION['form_success'] ?? null;
        unset($_SESSION['form_error'], $_SESSION['form_success']);

        include views_path('layout/header.php');
        include views_path('customers/edit.php');
        include views_path('layout/footer.php');
    }

    public function update(string $id): void {
        require_login();
        require_permission('customers', 'edit');

        $customer = db_row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$customer) { http_response_code(404); include views_path('errors/404.php'); return; }

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['form_error'] = __('customers.name_required');
            header("Location: /customers/{$id}/edit");
            exit;
        }

        $new = [
            'name'    => $name,
            'email'   => trim($_POST['email'] ?? ''),
            'phone'   => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city'    => trim($_POST['city'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'notes'   => trim($_POST['notes'] ?? ''),
        ];

        audit_change('customer', (int)$id, $customer, $new);
        db_update('customers', $new, 'id = ?', [(int)$id]);
        $_SESSION['form_success'] = __('customers.updated');
        header('Location: /customers');
        exit;
    }

    public function delete(string $id): void {
        require_login();
        require_permission('customers', 'edit');

        $in_use = db_val('SELECT COUNT(*) FROM rma_requests WHERE customer_id = ? AND deleted_at IS NULL', [(int)$id]);
        if ($in_use) {
            $_SESSION['form_error'] = __('customers.in_use');
        } else {
            db_soft_delete('customers', (int)$id);
            audit('deleted', 'customer', (int)$id);
            $_SESSION['form_success'] = __('customers.deleted');
        }
        header('Location: /customers');
        exit;
    }

    public function search(): void {
        require_login();
        header('Content-Type: application/json');

        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { echo json_encode([]); exit; }

        $like = "%{$q}%";
        $last8 = substr(preg_replace('/\D/', '', normalize_phone($q)), -8);

        $rows = db_rows(
            "SELECT id, name, phone, email, city FROM customers
             WHERE deleted_at IS NULL
             AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)
             ORDER BY name LIMIT 10",
            [$like, $like, "%{$last8}"]
        );

        echo json_encode($rows);
        exit;
    }

    public function check_duplicate(): void {
        require_login();
        header('Content-Type: application/json');

        $name  = trim($_GET['name'] ?? '');
        $phone = trim($_GET['phone'] ?? '');
        $email = trim($_GET['email'] ?? '');

        $matches = find_customer_matches($name, $phone, $email);
        echo json_encode($matches);
        exit;
    }
}
