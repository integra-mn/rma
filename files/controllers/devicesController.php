<?php
defined('RMS') or die('Direct access not permitted');

class DevicesController {

    public function index(): void {
        require_login();
        require_permission('settings', 'view');

        $tab        = $_GET['tab'] ?? 'groups';
        $page_title = __('nav.devices');

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

        include views_path('layout/header.php');
        include views_path('devices/index.php');
        include views_path('layout/footer.php');
    }

    // ── Part groups CRUD (Administration / Devices / Part Group tab) ─────
    public function part_group_store(): void {
        require_login();
        require_permission('settings', 'edit');
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            db_insert('part_groups', [
                'name'       => $name,
                'slug'       => $this->unique_slug('part_groups', $name),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ]);
            audit('created', 'part_group', 0);
        }
        header('Location: /administration?tab=devices&dtab=part-groups');
        exit;
    }

    public function part_group_update(): void {
        require_login();
        require_permission('settings', 'edit');
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            db_update('part_groups', [
                'name'       => $name,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'is_active'  => isset($_POST['is_active']) ? 1 : 0,
            ], 'id = ?', [$id]);
            audit('updated', 'part_group', $id);
        }
        header('Location: /administration?tab=devices&dtab=part-groups');
        exit;
    }

    public function part_group_delete(): void {
        require_login();
        require_permission('settings', 'edit');
        $id = (int)($_POST['id'] ?? 0);
        // Refuse if any parts are still tagged to this group — staff should
        // re-tag them first, or we'd orphan the classification.
        $used = (int) db_val('SELECT COUNT(*) FROM parts WHERE part_group_id = ? AND deleted_at IS NULL', [$id]);
        if ($used) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('devices.group_in_use_parts', ['count'=>$used])];
        } else {
            db()->prepare('DELETE FROM part_groups WHERE id = ?')->execute([$id]);
            audit('deleted', 'part_group', $id);
        }
        header('Location: /administration?tab=devices&dtab=part-groups');
        exit;
    }

    public function category_store(): void {
        require_login();
        require_permission('settings', 'edit');
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            db_insert('device_categories', [
                'name'       => $name,
                'slug'       => $this->unique_slug('device_categories', $name),
                'sku_prefix' => strtoupper(trim($_POST['sku_prefix'] ?? '')),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ]);
            audit('created', 'device_category', 0);
        }
        header('Location: /administration?tab=devices&dtab=groups');
        exit;
    }

    /**
     * Generate a URL-safe unique slug for a lookup table. Appends -2, -3…
     * if the base slug collides.
     */
    private function unique_slug(string $table, string $name): string {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        if ($base === '') $base = 'item';

        $slug = $base;
        $n = 2;
        while ((int)db_val("SELECT COUNT(*) FROM {$table} WHERE slug = ?", [$slug]) > 0) {
            $slug = $base . '-' . $n++;
            if ($n > 999) break; // guard against runaway loops
        }
        return $slug;
    }

    public function category_delete(): void {
        require_login();
        require_permission('settings', 'edit');
        $id = (int)($_POST['id'] ?? 0);
        $used = (int)db_val('SELECT COUNT(*) FROM device_models WHERE category_id = ?', [$id]);
        if ($used) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('devices.category_in_use', ['count'=>$used])];
        } else {
            db_delete('device_categories', 'id = ?', [$id]);
            audit('deleted', 'device_category', $id);
        }
        header('Location: /administration?tab=devices&dtab=groups');
        exit;
    }

    public function brand_store(): void {
        require_login();
        require_permission('settings', 'edit');
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            db_insert('device_brands', [
                'name' => $name,
                'slug' => $this->unique_slug('device_brands', $name),
            ]);
            audit('created', 'device_brand', 0);
        }
        header('Location: /administration?tab=devices&dtab=brands');
        exit;
    }

    public function brand_delete(): void {
        require_login();
        require_permission('settings', 'edit');
        $id = (int)($_POST['id'] ?? 0);
        $used = (int)db_val('SELECT COUNT(*) FROM device_models WHERE brand_id = ?', [$id]);
        if ($used) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('devices.brand_in_use', ['count'=>$used])];
        } else {
            db_delete('device_brands', 'id = ?', [$id]);
            audit('deleted', 'device_brand', $id);
        }
        header('Location: /administration?tab=devices&dtab=brands');
        exit;
    }

    public function model_store(): void {
        require_login();
        require_permission('settings', 'edit');
        $name     = trim($_POST['name'] ?? '');
        $brand_id = (int)($_POST['brand_id'] ?? 0);
        $cat_id   = (int)($_POST['category_id'] ?? 0);
        if ($name && $brand_id && $cat_id) {
            db_insert('device_models', [
                'name'        => $name,
                'brand_id'    => $brand_id,
                'category_id' => $cat_id,
            ]);
            audit('created', 'device_model', 0);
        }
        header('Location: /administration?tab=devices&dtab=models');
        exit;
    }

    public function model_delete(): void {
        require_login();
        require_permission('settings', 'edit');
        $id = (int)($_POST['id'] ?? 0);
        $used = (int)db_val('SELECT COUNT(*) FROM devices WHERE model_id = ?', [$id]);
        if ($used) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>__('devices.model_in_use', ['count'=>$used])];
        } else {
            db_delete('device_models', 'id = ?', [$id]);
            audit('deleted', 'device_model', $id);
        }
        header('Location: /administration?tab=devices&dtab=models');
        exit;
    }

    // ── Update handlers (edit-in-popup) ───────────────────────────

    public function category_update(): void {
        require_login();
        require_permission('settings', 'edit');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            db_update('device_categories', [
                'name'       => $name,
                'sku_prefix' => strtoupper(trim($_POST['sku_prefix'] ?? '')),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ], 'id = ?', [$id]);
            audit('updated', 'device_category', $id);
        }
        header('Location: /administration?tab=devices&dtab=groups');
        exit;
    }

    public function brand_update(): void {
        require_login();
        require_permission('settings', 'edit');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            db_update('device_brands', ['name' => $name], 'id = ?', [$id]);
            audit('updated', 'device_brand', $id);
        }
        header('Location: /administration?tab=devices&dtab=brands');
        exit;
    }

    public function model_update(): void {
        require_login();
        require_permission('settings', 'edit');
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $brand = (int)($_POST['brand_id'] ?? 0);
        $cat   = (int)($_POST['category_id'] ?? 0);
        if ($id && $name && $brand && $cat) {
            db_update('device_models', [
                'name'        => $name,
                'brand_id'    => $brand,
                'category_id' => $cat,
            ], 'id = ?', [$id]);
            audit('updated', 'device_model', $id);
        }
        header('Location: /administration?tab=devices&dtab=models');
        exit;
    }
}
