<?php
defined('RMS') or die('Direct access not permitted');

class PartsController {

    // ── Parts list ────────────────────────────────────────────

    public function index(): void {
        require_login();
        require_permission('parts', 'view');

        $page_title = __('nav.parts');
        $tab        = $_GET['tab'] ?? 'stock';
        $search     = trim($_GET['q'] ?? '');
        $page       = min(100000, max(1, (int)($_GET['page'] ?? 1)));
        $per_page   = 30;
        $offset     = ($page - 1) * $per_page;

        $success = $_SESSION['form_success'] ?? null;
        $error   = $_SESSION['form_error'] ?? null;
        unset($_SESSION['form_success'], $_SESSION['form_error']);

        if ($tab === 'stock') {
            $locations = db_rows('SELECT * FROM locations WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
            $user      = current_user();
            $default_loc = $user['location_id'] ?? ($locations[0]['id'] ?? 0);
            $loc_id    = (int)($_GET['loc'] ?? $default_loc);
            $parts_stock = db_rows("SELECT p.*, ps.quantity,
                                           s.name as supplier_name
                                    FROM parts p
                                    LEFT JOIN parts_stock ps ON ps.part_id = p.id AND ps.location_id = ?
                                    LEFT JOIN suppliers s ON s.id = p.supplier_id
                                    WHERE p.deleted_at IS NULL AND p.is_active = 1
                                    ORDER BY p.name", [$loc_id]);
            include views_path('layout/header.php');
            include views_path('parts/stock.php');
            include views_path('layout/footer.php');
            return;
        }

        if ($tab === 'inventory') {
            $locations  = db_rows('SELECT * FROM locations WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
            $user       = current_user();
            $loc_id     = (int)($_GET['loc'] ?? $user['location_id'] ?? ($locations[0]['id'] ?? 0));
            $count_id   = (int)($_GET['count_id'] ?? 0);

            // Find active count for this location
            $active_count = db_row(
                "SELECT ic.*, l.name as location_name, u.name as created_by_name
                 FROM inventory_counts ic
                 JOIN locations l ON l.id = ic.location_id
                 LEFT JOIN users u ON u.id = ic.created_by
                 WHERE ic.location_id = ? AND ic.status = 'active'",
                [$loc_id]
            );

            // If count_id specified, load that count instead
            if ($count_id && !$active_count) {
                $active_count = db_row(
                    "SELECT ic.*, l.name as location_name, u.name as created_by_name
                     FROM inventory_counts ic
                     JOIN locations l ON l.id = ic.location_id
                     LEFT JOIN users u ON u.id = ic.created_by
                     WHERE ic.id = ?",
                    [$count_id]
                );
            }

            $count_items = [];
            if ($active_count) {
                $count_items = db_rows(
                    "SELECT ici.*, p.name as part_name, p.internal_sku,
                                      p.supplier_sku, s.name as supplier_name
                     FROM inventory_count_items ici
                     JOIN parts p ON p.id = ici.part_id
                     LEFT JOIN suppliers s ON s.id = p.supplier_id
                     WHERE ici.count_id = ?
                     ORDER BY p.name",
                    [(int)$active_count['id']]
                );
            }

            $past_counts = db_rows(
                "SELECT ic.*, l.name as location_name,
                        COUNT(ici.id) as item_count,
                        SUM(ABS(ici.variance)) as total_variance
                 FROM inventory_counts ic
                 JOIN locations l ON l.id = ic.location_id
                 LEFT JOIN inventory_count_items ici ON ici.count_id = ic.id
                 WHERE ic.status != 'active'
                 GROUP BY ic.id, l.name
                 ORDER BY ic.started_at DESC
                 LIMIT 10"
            );

            include views_path('layout/header.php');
            include views_path('parts/inventory.php');
            include views_path('layout/footer.php');
            return;
        }

        if ($tab === 'receipts') {
            $suppliers = db_rows('SELECT id, name FROM suppliers WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
            $locations = db_rows('SELECT * FROM locations WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
            $receipts  = db_rows("SELECT gr.*, s.name as supplier_name, l.name as location_name,
                                         COUNT(gri.id) as item_count,
                                         SUM(gri.quantity) as total_units
                                  FROM goods_receipts gr
                                  JOIN suppliers s ON s.id = gr.supplier_id
                                  JOIN locations l ON l.id = gr.location_id
                                  LEFT JOIN goods_receipt_items gri ON gri.receipt_id = gr.id
                                  GROUP BY gr.id, s.name, l.name
                                  ORDER BY gr.created_at DESC
                                  LIMIT 50");
            include views_path('layout/header.php');
            include views_path('parts/receipts_tab.php');
            include views_path('layout/footer.php');
            return;
        }

        // Parts tab (default)
        $where  = 'p.deleted_at IS NULL';
        $params = [];
        if ($search) {
            $where   .= ' AND (p.name LIKE ? OR p.internal_sku LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total = (int) db_val("SELECT COUNT(*) FROM parts p WHERE {$where}", $params);
        $parts = db_rows("SELECT p.*, s.name as supplier_name,
                                 COALESCE(SUM(ps.quantity),0) as total_stock
                          FROM parts p
                          LEFT JOIN suppliers s ON s.id = p.supplier_id
                          LEFT JOIN parts_stock ps ON ps.part_id = p.id
                          WHERE {$where}
                          GROUP BY p.id, s.name
                          ORDER BY p.name
                          LIMIT {$per_page} OFFSET {$offset}", $params);

        $suppliers = db_rows('SELECT id, name FROM suppliers WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
        $vat_rates = db_rows('SELECT * FROM vat_rates ORDER BY rate DESC');

        include views_path('layout/header.php');
        include views_path('parts/index.php');
        include views_path('layout/footer.php');
    }

    // ── Store part ────────────────────────────────────────────

    public function store(): void {
        require_login();
        require_permission('parts', 'create');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['form_error'] = __('parts.name_required');
            header('Location: /parts');
            exit;
        }

        // Check supplier SKU uniqueness
        $supplier_sku = trim($_POST['supplier_sku'] ?? '') ?: null;
        if ($supplier_sku && db_val('SELECT id FROM parts WHERE supplier_sku = ? AND deleted_at IS NULL', [$supplier_sku])) {
            $_SESSION['form_error'] = __('parts.supplier_sku_exists', [':sku'=>$supplier_sku]);
            header('Location: /parts');
            exit;
        }

        // Generate internal SKU based on category (if provided)
        $category_id  = (int)($_POST['category_id'] ?? 0) ?: null;
        $brand_id     = (int)($_POST['brand_id'] ?? 0) ?: null;
        $internal_sku = generate_internal_sku($category_id);

        $id = db_insert('parts', [
            'name'          => $name,
            'internal_sku'  => $internal_sku,
            'supplier_sku'  => $supplier_sku,
            'description'   => trim($_POST['description'] ?? ''),
            'unit_price'    => (float)str_replace(',', '.', $_POST['unit_price'] ?? '0'),
            'supplier_id'   => (int)($_POST['supplier_id'] ?? 0) ?: null,
            'brand_id'      => $brand_id,
            'category_id'   => $category_id,
            'part_group_id' => (int)($_POST['part_group_id'] ?? 0) ?: null,
            'vat_rate_id'   => (int)($_POST['vat_rate_id'] ?? 0) ?: null,
            'min_stock'     => (int)($_POST['reorder_level'] ?? $_POST['min_stock'] ?? 5),
            'is_active'     => 1,
        ]);
        audit('created', 'part', $id);
        $_SESSION['form_success'] = __('parts.added_with_sku', [':sku'=>$internal_sku]);
        header('Location: /parts');
        exit;
    }

    // ── Update part ───────────────────────────────────────────

    public function update(string $id): void {
        require_login();
        require_permission('parts', 'edit');

        $part = db_row('SELECT * FROM parts WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$part) { http_response_code(404); return; }

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['form_error'] = __('parts.name_required');
            header("Location: /parts");
            exit;
        }

        $new = [
            'name'          => $name,
            'supplier_sku'  => trim($_POST['supplier_sku'] ?? '') ?: null,
            'description'   => trim($_POST['description'] ?? ''),
            'unit_price'    => (float)str_replace(',', '.', $_POST['unit_price'] ?? '0'),
            'supplier_id'   => (int)($_POST['supplier_id'] ?? 0) ?: null,
            'brand_id'      => (int)($_POST['brand_id']    ?? 0) ?: null,
            'category_id'   => (int)($_POST['category_id'] ?? 0) ?: null,
            'part_group_id' => (int)($_POST['part_group_id'] ?? 0) ?: null,
            'vat_rate_id'   => (int)($_POST['vat_rate_id'] ?? 0) ?: null,
            'min_stock'     => (int)($_POST['reorder_level'] ?? $_POST['min_stock'] ?? 5),
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
        ];

        audit_change('part', (int)$id, $part, $new);
        db_update('parts', $new, 'id = ?', [(int)$id]);
        $_SESSION['form_success'] = __('parts.updated');
        header('Location: /parts');
        exit;
    }

    // ── Delete part ───────────────────────────────────────────

    public function delete(string $id): void {
        require_login();
        require_permission('parts', 'delete');

        $in_use = db_val('SELECT COUNT(*) FROM part_usage WHERE part_id = ?', [(int)$id]);
        if ($in_use) {
            $_SESSION['form_error'] = __('parts.in_use');
        } else {
            db_soft_delete('parts', (int)$id);
            audit('deleted', 'part', (int)$id);
            $_SESSION['form_success'] = __('parts.deleted');
        }
        header('Location: /parts');
        exit;
    }

    // ── Store supplier ────────────────────────────────────────

    public function supplier_store(): void {
        require_login();
        require_permission('parts', 'create');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $_SESSION['form_error'] = __('suppliers.name_required');
            header('Location: /parts?tab=suppliers');
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
            'country'   => trim($_POST['country'] ?? 'Montenegro'),
            'notes'     => trim($_POST['notes'] ?? ''),
            'is_active' => 1,
        ]);
        audit('created', 'supplier', $id);
        $_SESSION['form_success'] = __('suppliers.added');
        header('Location: /parts?tab=suppliers');
        exit;
    }

    // ── Update stock ──────────────────────────────────────────

    public function supplier_update(): void {
        require_login();
        require_permission('settings', 'edit');

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || !$name) {
            $_SESSION['form_error'] = __('suppliers.name_required');
            header('Location: /parts?tab=suppliers');
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
        header('Location: /parts?tab=suppliers');
        exit;
    }

    public function stock_update(): void {
        require_login();
        require_permission('parts', 'edit');

        $part_id     = (int)($_POST['part_id'] ?? 0);
        $location_id = (int)($_POST['location_id'] ?? 0);
        $quantity    = max(0, (int)($_POST['quantity'] ?? 0));

        if (!$part_id || !$location_id) {
            header('Location: /parts?tab=stock');
            exit;
        }

        $existing = db_row('SELECT * FROM parts_stock WHERE part_id = ? AND location_id = ?',
                           [$part_id, $location_id]);

        if ($existing) {
            db_update('parts_stock', ['quantity' => $quantity],
                      'part_id = ? AND location_id = ?', [$part_id, $location_id]);
        } else {
            db_insert('parts_stock', [
                'part_id'     => $part_id,
                'location_id' => $location_id,
                'quantity'    => $quantity,
            ]);
        }

        audit('stock_updated', 'part', $part_id, ['new' => ['location_id' => $location_id, 'qty' => $quantity]]);
        $_SESSION['form_success'] = __('parts.stock_updated');
        header("Location: /parts?tab=stock&loc={$location_id}");
        exit;
    }
}
