<?php
defined('RMS') or die('Direct access not permitted');

function generate_internal_sku(?int $category_id = null): string {
    if ($category_id) {
        // Get category prefix
        $cat = db_row('SELECT sku_prefix FROM device_categories WHERE id = ?', [$category_id]);
        $prefix = $cat && $cat['sku_prefix'] ? strtoupper($cat['sku_prefix']) : 'PRT';

        // Get or create sequence for this category
        $seq_row = db_row('SELECT last_seq FROM sku_sequences WHERE category_id = ?', [$category_id]);
        if ($seq_row) {
            $next = (int)$seq_row['last_seq'] + 1;
            db_update('sku_sequences', ['last_seq' => $next], 'category_id = ?', [$category_id]);
        } else {
            // Find highest existing sequence for this prefix
            $existing = db_val(
                "SELECT MAX(CAST(SUBSTRING(internal_sku, LENGTH(?) + 2) AS UNSIGNED))
                 FROM parts WHERE internal_sku LIKE ?",
                [$prefix, $prefix . '-%']
            );
            $next = (int)$existing + 1;
            db_insert('sku_sequences', ['category_id' => $category_id, 'last_seq' => $next]);
        }
    } else {
        $prefix = 'PRT';
        $existing = db_val(
            "SELECT MAX(CAST(SUBSTRING(internal_sku, 5) AS UNSIGNED))
             FROM parts WHERE internal_sku LIKE 'PRT-%'"
        );
        $next = (int)$existing + 1;
    }

    return $prefix . '-' . str_pad($next, 6, '0', STR_PAD_LEFT);
}

function match_part_by_sku(string $internal_sku = '', string $supplier_sku = '', string $name = ''): array {
    // 1. Match by internal SKU
    if ($internal_sku) {
        $part = db_row('SELECT * FROM parts WHERE internal_sku = ? AND deleted_at IS NULL', [$internal_sku]);
        if ($part) return ['part' => $part, 'match_type' => 'internal_sku', 'warning' => false];
    }

    // 2. Match by supplier SKU
    if ($supplier_sku) {
        $part = db_row('SELECT * FROM parts WHERE supplier_sku = ? AND deleted_at IS NULL', [$supplier_sku]);
        if ($part) return ['part' => $part, 'match_type' => 'supplier_sku', 'warning' => false];
    }

    // 3. Match by name (case-insensitive)
    if ($name) {
        $part = db_row('SELECT * FROM parts WHERE LOWER(name) = LOWER(?) AND deleted_at IS NULL', [$name]);
        if ($part) return ['part' => $part, 'match_type' => 'name', 'warning' => true];
    }

    // 4. No match — will auto-create on confirm
    return ['part' => null, 'match_type' => 'none', 'warning' => true];
}
