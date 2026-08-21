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
    // Longest placeholder first. With :to and :total both in play, replacing
    // :to first turns ":total" into "25tal" — the same shape of bug as the
    // ":code" one that produced "::code". Sorting by length kills the class of
    // it rather than renaming one placeholder and waiting for the next.
    uksort($replace, fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string) $v, $text);
    }
    return $text;
}

function __(string $key, array $replace = []): string {
    $strings = load_lang();
    $text    = $strings[$key] ?? $key;
    // Longest placeholder first. With :to and :total both in play, replacing
    // :to first turns ":total" into "25tal" — the same shape of bug as the
    // ":code" one that produced "::code". Sorting by length kills the class of
    // it rather than renaming one placeholder and waiting for the next.
    uksort($replace, fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
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
function status_label(string $code, string $fallback = '', ?string $lang_override = null): string {
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            // One table. repair_statuses was read here too until it was
            // dropped; nothing points at it any more and no row anywhere holds
            // one of its ids.
            foreach (db_rows('SELECT code, label, label_me FROM rma_statuses') as $r) {
                $map[$r['code']] = $r;
            }
        } catch (\Throwable $e) {
            $map = []; // column/table missing (e.g. migration not yet run) -> fall back
        }
    }
    // $lang_override lets a page pick the language explicitly — the public
    // tracking page has no logged-in user and should follow the CUSTOMER's
    // language, the same rule the printed receipt uses.
    $lang = $lang_override ?? (current_user()['lang'] ?? setting('default_lang', 'en'));
    $row  = $map[$code] ?? null;

    if ($lang === 'me') {
        if ($row && !empty($row['label_me'])) {
            return htmlspecialchars($row['label_me'], ENT_QUOTES, 'UTF-8');
        }
        if ($code !== '' && __in($lang, 'status.' . $code) !== 'status.' . $code) {
            return htmlspecialchars(__in($lang, 'status.' . $code), ENT_QUOTES, 'UTF-8');
        }
    }
    if ($row) return htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8');
    return htmlspecialchars($fallback !== '' ? $fallback : $code, ENT_QUOTES, 'UTF-8');
}

/**
 * Render a status-history note.
 *
 * Notes the app writes are stored as translation keys ("history.created"), so
 * Istorija reads in the viewer's language rather than whatever language the
 * clerk happened to be using. Anything a person typed is shown verbatim.
 *
 * $lang forces a language for pages with no logged-in user — the public
 * tracking page follows the customer.
 */
function history_note(string $note, ?string $lang = null): string {
    $note = trim($note);
    if ($note === '') { return ''; }
    if (str_starts_with($note, 'history.')) {
        $text = $lang !== null ? __in($lang, $note) : __raw($note);
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
}

function __raw(string $key, array $replace = []): string {
    $strings = load_lang();
    $text    = $strings[$key] ?? $key;
    // Longest placeholder first. With :to and :total both in play, replacing
    // :to first turns ":total" into "25tal" — the same shape of bug as the
    // ":code" one that produced "::code". Sorting by length kills the class of
    // it rather than renaming one placeholder and waiting for the next.
    uksort($replace, fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string) $v, $text);
    }
    return $text;
}
