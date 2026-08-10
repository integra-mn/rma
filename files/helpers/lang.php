<?php
defined('RMS') or die('Direct access not permitted');

function load_lang(): array {
    static $strings = null;
    if ($strings !== null) return $strings;

    $lang = current_user()['lang'] ?? setting('default_lang', 'en');
    if (!preg_match('/^[a-z]{2}$/', $lang)) $lang = 'en';

    $root = dirname(dirname(__FILE__)); // helpers/ is 1 level deep → root = rms/
    $file = $root . '/lang/' . $lang . '/strings.php';

    $strings = file_exists($file) ? include $file : [];

    // Fallback to English for missing keys
    if ($lang !== 'en') {
        $en = $root . '/lang/en/strings.php';
        $strings = array_merge(file_exists($en) ? include $en : [], $strings);
    }

    return $strings;
}

/**
 * Translate into an explicitly chosen language, ignoring who is logged in.
 *
 * __() follows the current user's UI language, which is right for the app and
 * wrong for anything addressed to a customer: a technician working in English
 * would otherwise send English email to a Montenegrin customer. Customer-facing
 * text should use the shop's default language instead.
 */
function __in(string $lang, string $key, array $replace = []): string {
    static $cache = [];

    if (!preg_match('/^[a-z]{2}$/', $lang)) $lang = 'en';

    if (!isset($cache[$lang])) {
        $root    = dirname(__DIR__);
        $file    = "{$root}/lang/{$lang}/strings.php";
        $strings = file_exists($file) ? include $file : [];
        if ($lang !== 'en') {                       // fall back per key, as __() does
            $en      = "{$root}/lang/en/strings.php";
            $strings = array_merge(file_exists($en) ? include $en : [], $strings);
        }
        $cache[$lang] = $strings;
    }

    $text = $cache[$lang][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string) $v, $text);
    }
    return $text;
}

function __(string $key, array $replace = []): string {
    $strings = load_lang();
    $text    = $strings[$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string) $v, $text);
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Translate a DB-driven status by its `code`. Precedence for a Montenegrin user:
//   1. the admin-editable `label_me` column (Administration -> Statuses),
//   2. the shipped default in the language file (status.<code>),
//   3. the English `label` column / passed fallback.
// For any other language it uses the English `label`. Returns HTML-escaped text.
function status_label(string $code, string $fallback = ''): string {
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            foreach (['rma_statuses', 'repair_statuses'] as $t) {
                foreach (db_rows("SELECT code, label, label_me FROM {$t}") as $r) {
                    $map[$r['code']] = $r;
                }
            }
        } catch (\Throwable $e) {
            $map = []; // column/table missing (e.g. migration not yet run) -> fall back
        }
    }
    $lang = current_user()['lang'] ?? setting('default_lang', 'en');
    $row  = $map[$code] ?? null;

    if ($lang === 'me') {
        if ($row && !empty($row['label_me'])) {
            return htmlspecialchars($row['label_me'], ENT_QUOTES, 'UTF-8');
        }
        $strings = load_lang();
        if ($code !== '' && isset($strings['status.' . $code])) return __('status.' . $code);
    }
    if ($row) return htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8');
    return htmlspecialchars($fallback !== '' ? $fallback : $code, ENT_QUOTES, 'UTF-8');
}

function __raw(string $key, array $replace = []): string {
    $strings = load_lang();
    $text    = $strings[$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string) $v, $text);
    }
    return $text;
}
