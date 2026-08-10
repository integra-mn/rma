<?php
defined('RMS') or die('Direct access not permitted');

function setting(string $key, mixed $default = null, ?int $location_id = null): mixed {
    static $cache = [];

    $cache_key = ($location_id ?? 'global') . ':' . $key;

    if (!isset($cache[$cache_key])) {
        $row = db_row(
            'SELECT value, type FROM settings WHERE key_name = ? AND location_id ' .
            ($location_id ? '= ?' : 'IS NULL') . ' LIMIT 1',
            $location_id ? [$key, $location_id] : [$key]
        );

        if (!$row && $location_id) {
            $row = db_row(
                'SELECT value, type FROM settings WHERE key_name = ? AND location_id IS NULL LIMIT 1',
                [$key]
            );
        }

        if ($row) {
            $cache[$cache_key] = match($row['type']) {
                'int'  => (int) $row['value'],
                'bool' => (bool) $row['value'],
                'json' => json_decode($row['value'], true),
                default => $row['value'],
            };
        } else {
            $cache[$cache_key] = $default;
        }
    }

    return $cache[$cache_key];
}

// The UI fonts a user can pick in Settings → Appearance. All are self-hosted
// (see assets/css/fonts.css and assets/fonts/), so no external font request is
// made at runtime. Each value maps to a full CSS font stack with sensible
// system fallbacks used until the web font loads.
const APP_FONTS = [
    'Montserrat' => "'Montserrat', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif",
    'Inter'      => "'Inter', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif",
    'Roboto'     => "'Roboto', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif",
    'Open Sans'  => "'Open Sans', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif",
    'Ubuntu'     => "'Ubuntu', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif",
    'Maven Pro'  => "'Maven Pro', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif",
];

// The CSS font-family stack for the currently selected UI font (global setting).
function app_font_stack(): string {
    $font = setting('app_font', 'Montserrat');
    return APP_FONTS[$font] ?? APP_FONTS['Montserrat'];
}

// ── Per-theme colours ────────────────────────────────────────────────────
// Each theme (light / blue / contrast) keeps its OWN palette. Saved values live
// in the settings table as JSON under `theme_colors_<code>`; anything unset falls
// back to the theme's built-in palette below. Order here is the order shown in
// the Appearance theme dropdowns.
const THEME_COLOR_KEYS = [
    'sidebar_bg', 'sidebar_hover', 'sidebar_link', 'sidebar_active',
    'page_bg', 'card_bg', 'border_color', 'text_color', 'accent_color', 'accent_dark',
];

function theme_default_colors(string $code): array {
    // Sidebar bg/hover/link + card are the same across the built-in themes;
    // only the accent family, page/border/text and the active-link colour differ.
    $shared = ['sidebar_bg'=>'#1A1A1F','sidebar_hover'=>'#26262c','sidebar_link'=>'#9b99a4','card_bg'=>'#ffffff'];
    $per = [
        'midnight' => ['sidebar_active'=>'#1D9E75','page_bg'=>'#f4f4f0','border_color'=>'#d3d1c7','text_color'=>'#2c2c2a','accent_color'=>'#1D9E75','accent_dark'=>'#0F6E56'],
        'ocean'    => ['sidebar_active'=>'#5dcaa5','page_bg'=>'#eef3f9','border_color'=>'#b5d4f4','text_color'=>'#042c53','accent_color'=>'#185fa5','accent_dark'=>'#0c447c'],
        'focus'    => ['sidebar_active'=>'#00cc66','page_bg'=>'#ffffff','border_color'=>'#000000','text_color'=>'#000000','accent_color'=>'#005a32','accent_dark'=>'#003d22'],
    ];
    return array_merge($shared, $per[$code] ?? $per['midnight']);
}

// A theme's effective colours: saved per-theme overrides layered on the built-in
// defaults. For the light theme, any key not yet saved per-theme falls back to the
// legacy global colour setting (accent_color, sidebar_bg, …) — before per-theme
// colours existed, one global set applied to every theme, and it was effectively
// the light theme's — so existing installs keep their look with no migration.
function theme_colors(string $code): array {
    $defaults = theme_default_colors($code);
    $saved    = json_decode((string) setting('theme_colors_' . $code, '{}'), true) ?: [];
    foreach (THEME_COLOR_KEYS as $k) {
        if (!empty($saved[$k]) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $saved[$k])) {
            $defaults[$k] = $saved[$k];
        } elseif ($code === 'midnight') {
            $legacy = setting($k, null);
            if (is_string($legacy) && preg_match('/^#[0-9a-fA-F]{6}$/', $legacy)) {
                $defaults[$k] = $legacy;
            }
        }
    }
    return $defaults;
}

function setting_set(string $key, mixed $value, string $type = 'string', ?int $location_id = null): void {
    $encoded = is_array($value) ? json_encode($value) : (string) $value;

    $existing = db_row(
        'SELECT id FROM settings WHERE key_name = ? AND location_id ' .
        ($location_id ? '= ?' : 'IS NULL'),
        $location_id ? [$key, $location_id] : [$key]
    );

    if ($existing) {
        db_update('settings', [
            'value'      => $encoded,
            'updated_by' => current_user_id(),
        ], 'id = ?', [$existing['id']]);
    } else {
        db_insert('settings', [
            'key_name'    => $key,
            'value'       => $encoded,
            'type'        => $type,
            'location_id' => $location_id,
            'updated_by'  => current_user_id(),
        ]);
    }

    static $cache = [];
    unset($cache[($location_id ?? 'global') . ':' . $key]);
}

/**
 * The business name customers see — emails, tracking page, printed documents.
 *
 * Deliberately separate from app_name, which is what staff see inside the app:
 * "Integra RMA" is the tool, "Integra Service" is who the customer dealt with.
 * Settings → General.
 */
function company_name(): string {
    $name = trim((string) setting('company_name', ''));
    return $name !== '' ? $name : 'Integra Service';
}

/**
 * Normalise a customer's language for anything they receive.
 *
 * Deliberately independent of the logged-in employee: staff pick their own UI
 * language in Moj profil, and that must not change what a customer reads.
 * Montenegro is the default; EN exists for the occasional foreign walk-in.
 */
function customer_lang(?string $lang): string {
    return in_array($lang, ['me', 'en'], true) ? $lang : 'me';
}
