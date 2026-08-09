<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Minimal .env loader. Reads KEY=VALUE lines from the given path and
 * populates getenv() / $_ENV / $_SERVER. Existing environment variables
 * are preserved (so production can override via real env vars without
 * editing the file). Supports:
 *   - blank lines and `#` comments
 *   - single- and double-quoted values
 *   - unquoted values (whitespace trimmed)
 * Does NOT support: variable interpolation, escape sequences, multiline.
 */
function env_load(string $path): void {
    if (!is_readable($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;

        $pos = strpos($line, '=');
        if ($pos === false) continue;

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) continue;

        // Strip matching surrounding quotes.
        $len = strlen($val);
        if ($len >= 2
            && (($val[0] === '"' && $val[$len - 1] === '"')
             || ($val[0] === "'" && $val[$len - 1] === "'"))
        ) {
            $val = substr($val, 1, -1);
        }

        // Don't clobber real environment variables.
        if (getenv($key) !== false) continue;

        putenv("{$key}={$val}");
        $_ENV[$key]    = $val;
        $_SERVER[$key] = $val;
    }
}

function env(string $key, mixed $default = null): mixed {
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
}
