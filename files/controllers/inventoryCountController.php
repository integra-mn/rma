<?php
defined('RMS') or die('Direct access not permitted');

class InventoryCountController {

    // ── Start new count ───────────────────────────────────────

    public function start(): void {
        require_login();
        require_permission('settings', 'edit');

        $location_id = (int)($_POST['location_id'] ?? 0);
        if (!$location_id) {
            $_SESSION['form_error'] = __('parts.location_required');
            header('Location: /parts?tab=inventory');
            exit;
        }

        // Check no active count for this location
        $existing = db_val(
            "SELECT id FROM inventory_counts WHERE location_id = ? AND status = 'active'",
            [$location_id]
        );
        if ($existing) {
            $_SESSION['form_error'] = __('parts.count_exists');
            header('Location: /parts?tab=inventory');
            exit;
        }

        // Document number: POP-YYYY-NNNN, sequence resets each year.
        $year = date('Y');
        $last = db_val(
            "SELECT reference FROM inventory_counts WHERE reference LIKE ? ORDER BY id DESC LIMIT 1",
            ["POP-{$year}-%"]
        );
        $seq       = $last ? ((int) substr($last, -4)) + 1 : 1;
        $reference = sprintf('POP-%s-%04d', $year, $seq);

        // Create count
        $count_id = db_insert('inventory_counts', [
            'reference'   => $reference,
            'location_id' => $location_id,
            'status'      => 'active',
            'created_by'  => current_user_id(),
            'started_at'  => date('Y-m-d H:i:s'),
        ]);

        // Snapshot current stock quantities
        $parts = db_rows(
            "SELECT p.id, COALESCE(ps.quantity, 0) as qty
             FROM parts p
             LEFT JOIN parts_stock ps ON ps.part_id = p.id AND ps.location_id = ?
             WHERE p.deleted_at IS NULL AND p.is_active = 1
             ORDER BY p.name",
            [$location_id]
        );

        foreach ($parts as $part) {
            db_insert('inventory_count_items', [
                'count_id'   => $count_id,
                'part_id'    => (int)$part['id'],
                'system_qty' => (int)$part['qty'],
            ]);
        }

        audit('started', 'inventory_count', $count_id);
        $_SESSION['form_success'] = __('parts.count_started', [':count'=>count($parts)]);
        header('Location: /parts?tab=inventory');
        exit;
    }

    // ── Save counted quantities ───────────────────────────────

    public function save(string $id): void {
        require_login();
        require_permission('parts', 'view');

        $count = $this->get_count((int)$id);
        if (!$count || $count['status'] !== 'active') {
            header('Location: /parts?tab=inventory');
            exit;
        }

        $counts = $_POST['counted'] ?? [];
        foreach ($counts as $item_id => $qty) {
            $qty = trim($qty);
            if ($qty === '' || !is_numeric($qty)) continue;
            db_update('inventory_count_items',
                ['counted_qty' => max(0, (int)$qty)],
                'id = ? AND count_id = ?',
                [(int)$item_id, (int)$id]
            );
        }

        // Save notes per item
        $notes = $_POST['notes'] ?? [];
        foreach ($notes as $item_id => $note) {
            $note = trim($note);
            if (!$note) continue;
            db_update('inventory_count_items',
                ['note' => $note],
                'id = ? AND count_id = ?',
                [(int)$item_id, (int)$id]
            );
        }

        $_SESSION['form_success'] = __('parts.counts_saved');
        header("Location: /parts?tab=inventory&count_id={$id}");
        exit;
    }

    // ── Confirm and apply adjustments ─────────────────────────

    public function confirm(string $id): void {
        require_login();
        require_permission('settings', 'edit');

        $count = $this->get_count((int)$id);
        if (!$count || $count['status'] !== 'active') {
            $_SESSION['form_error'] = __('parts.count_not_found');
            header('Location: /parts?tab=inventory');
            exit;
        }

        $items = db_rows(
            "SELECT * FROM inventory_count_items WHERE count_id = ? AND counted_qty IS NOT NULL",
            [(int)$id]
        );

        if (empty($items)) {
            $_SESSION['form_error'] = __('parts.count_none_entered');
            header("Location: /parts?tab=inventory&count_id={$id}");
            exit;
        }

        foreach ($items as $item) {
            $variance = (int)$item['counted_qty'] - (int)$item['system_qty'];
            if ($variance === 0) continue;

            // Update stock
            $stock = db_row(
                'SELECT * FROM parts_stock WHERE part_id = ? AND location_id = ?',
                [(int)$item['part_id'], (int)$count['location_id']]
            );

            if ($stock) {
                db_update('parts_stock',
                    ['quantity' => (int)$item['counted_qty']],
                    'part_id = ? AND location_id = ?',
                    [(int)$item['part_id'], (int)$count['location_id']]
                );
            } else {
                db_insert('parts_stock', [
                    'part_id'     => (int)$item['part_id'],
                    'location_id' => (int)$count['location_id'],
                    'quantity'    => (int)$item['counted_qty'],
                ]);
            }

            // Log stock movement
            db_insert('stock_movements', [
                'part_id'        => (int)$item['part_id'],
                'location_id'    => (int)$count['location_id'],
                'type'           => 'adjust',
                'quantity'       => $variance,
                'reference_type' => 'inventory_count',
                'reference_id'   => (int)$id,
                'reason'         => 'Inventory count #' . $id . ($item['note'] ? ' — ' . $item['note'] : ''),
                'created_by'     => current_user_id(),
            ]);
        }

        db_update('inventory_counts', [
            'status'       => 'confirmed',
            'confirmed_by' => current_user_id(),
            'confirmed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$id]);

        audit('confirmed', 'inventory_count', (int)$id);
        $_SESSION['form_success'] = __('parts.count_confirmed');
        header('Location: /parts?tab=inventory');
        exit;
    }

    // ── Cancel ────────────────────────────────────────────────

    public function cancel(string $id): void {
        require_login();
        require_permission('settings', 'edit');

        db_update('inventory_counts', ['status' => 'cancelled'], 'id = ?', [(int)$id]);
        audit('cancelled', 'inventory_count', (int)$id);
        $_SESSION['form_success'] = __('parts.count_cancelled');
        header('Location: /parts?tab=inventory');
        exit;
    }

    // ── Helper ────────────────────────────────────────────────

    private function get_count(int $id): ?array {
        return db_row(
            "SELECT ic.*, l.name as location_name, u.name as created_by_name
             FROM inventory_counts ic
             JOIN locations l ON l.id = ic.location_id
             LEFT JOIN users u ON u.id = ic.created_by
             WHERE ic.id = ?",
            [$id]
        );
    }
}
