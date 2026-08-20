<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Vendor error and resolution codes.
 *
 * Kept per brand and per device type so the bench sees a short list rather
 * than every code the company has ever recorded. See the migration for why
 * the table starts empty.
 */

const REPAIR_CODE_KINDS = ['error', 'resolution'];

/** The two kinds, translated, for a dropdown or a tab bar. */
function repair_code_kinds(): array {
    return [
        'error'      => __('codes.kind_error'),
        'resolution' => __('codes.kind_resolution'),
    ];
}

/**
 * Codes offered for one device.
 *
 * A NULL brand or category on the row means "any", so the general codes
 * (customer declined, out of warranty) come back for every device alongside
 * the ones written for this brand. Ordered so the specific ones sort first:
 * a technician holding an iPhone wants Apple's list at the top, not the
 * catch-alls.
 */
function repair_codes_for(string $kind, ?int $brand_id, ?int $category_id): array {
    if (!in_array($kind, REPAIR_CODE_KINDS, true)) return [];

    return db_rows(
        "SELECT c.*, b.name AS brand_name, cat.name AS category_name
           FROM repair_codes c
           LEFT JOIN device_brands b ON b.id = c.brand_id
           LEFT JOIN device_categories cat ON cat.id = c.category_id
          WHERE c.deleted_at IS NULL AND c.is_active = 1 AND c.kind = ?
            AND (c.brand_id IS NULL OR c.brand_id = ?)
            AND (c.category_id IS NULL OR c.category_id = ?)
          ORDER BY CASE WHEN c.brand_id IS NULL THEN 1 ELSE 0 END,
                   c.sort_order, c.code",
        [$kind, $brand_id ?? 0, $category_id ?? 0]
    );
}

/** One code row by id, or null. Deleted rows still resolve: a job that used a
 *  code keeps showing what it was, or its history would rewrite itself. */
function repair_code(?int $id): ?array {
    if (!$id) return null;
    return db_row('SELECT * FROM repair_codes WHERE id = ?', [$id]) ?: null;
}

/** "E-1234 · Serijski broj nije pronadjen", in the reader's language. */
function repair_code_label(?array $c): string {
    if (!$c) return '';
    $lang  = current_user()['lang'] ?? setting('default_lang', 'en');
    $label = ($lang === 'me' && !empty($c['label_me'])) ? $c['label_me'] : $c['label'];
    return trim((string)$c['code']) . ' · ' . $label;
}

/** Where a code applies, for the admin list: "Apple · Telefon", "Sve". */
function repair_code_scope(array $c): string {
    $parts = array_filter([$c['brand_name'] ?? null, $c['category_name'] ?? null]);
    return $parts ? implode(' · ', $parts) : __('codes.scope_all');
}
