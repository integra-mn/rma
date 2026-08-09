<?php
defined('RMS') or die('Direct access not permitted');

require_once __DIR__ . '/env.php';
// .env.local overrides .env in development. env_load preserves the first
// value seen for a given key, so loading .env.local first gives it priority.
env_load(ROOT . '/.env.local');
env_load(ROOT . '/.env');

// Credentials now come from the environment. The .env file (web-root) is
// blocked from public access by .htaccess; on shared hosting, real env
// vars take precedence when present.
define('DB_DRIVER',  env('DB_DRIVER', 'mysql'));   // 'mysql' | 'pgsql'
define('DB_HOST',    env('DB_HOST', ''));
define('DB_PORT',    env('DB_PORT', DB_DRIVER === 'pgsql' ? '5432' : '3306'));
define('DB_NAME',    env('DB_NAME', ''));
define('DB_USER',    env('DB_USER', ''));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

/** True when running on PostgreSQL — lets code branch on the few real differences. */
function db_is_pg(): bool { return DB_DRIVER === 'pgsql'; }

// Optional trusted proxy list used by helpers/auth.php::client_ip().
if (($proxies = env('TRUSTED_PROXIES', '')) !== '') {
    define('TRUSTED_PROXIES', array_map('trim', explode(',', $proxies)));
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (DB_HOST === '' || DB_NAME === '' || DB_USER === '') {
            http_response_code(500);
            die('Database configuration is missing. Copy .env.example to .env and fill in credentials.');
        }
        $dsn = db_is_pg()
            ? 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME
            : 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * Translate the handful of MySQL-only constructs the app's SQL still uses into
 * their Postgres equivalents. Queries stay written in one dialect (MySQL) and
 * this is the single place that knows the differences.
 *
 * Deliberately narrow — only these five patterns are rewritten:
 *   LIKE                       -> ILIKE            (MySQL LIKE is case-insensitive)
 *   DATE_FORMAT(x, '<fmt>')    -> to_char(x, '<fmt>')
 *   DATEDIFF(a, b)             -> (a::date - b::date)
 *   DATE_ADD/SUB(x, INTERVAL n UNIT) -> (x +/- n * INTERVAL '1 unit')
 *   YEAR(x) / MONTH(x)         -> EXTRACT(... FROM x)
 * Results are cached per unique SQL string, so the cost is paid once.
 */
function db_translate(string $sql): string {
    if (!db_is_pg()) return $sql;
    static $cache = [];
    if (isset($cache[$sql])) return $cache[$sql];
    $out = $sql;

    // MySQL's LIKE is case-insensitive; Postgres needs ILIKE to match behaviour.
    $out = preg_replace('/\bLIKE\b/i', 'ILIKE', $out);

    // DATE_FORMAT -> to_char, translating the strftime-style format specifiers.
    $fmt_map = ['%Y' => 'YYYY', '%y' => 'YY', '%m' => 'MM', '%d' => 'DD',
                '%b' => 'Mon',  '%M' => 'Month', '%H' => 'HH24', '%i' => 'MI', '%s' => 'SS'];
    $out = preg_replace_callback(
        "/\bDATE_FORMAT\s*\(\s*(.+?)\s*,\s*'([^']*)'\s*\)/i",
        fn($m) => "to_char({$m[1]}, '" . strtr($m[2], $fmt_map) . "')",
        $out
    );

    // DATEDIFF(a, b) -> whole days between two dates.
    $out = preg_replace_callback(
        '/\bDATEDIFF\s*\(/i',
        fn($m) => '__DATEDIFF__(',
        $out
    );
    while (preg_match('/__DATEDIFF__\(/', $out)) {
        $out = db_rewrite_call($out, '__DATEDIFF__', function (array $args) {
            return '((' . $args[0] . ')::date - (' . $args[1] . ')::date)';
        });
    }

    // DATE_ADD / DATE_SUB with an INTERVAL literal.
    $out = preg_replace_callback(
        '/\bDATE_(ADD|SUB)\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\S+)\s+(\w+)\s*\)/i',
        function ($m) {
            $op   = strtoupper($m[1]) === 'ADD' ? '+' : '-';
            $unit = strtolower($m[4]);
            // Explicit casts: a bare `?` placeholder has no inferable type here.
            return "(({$m[2]})::timestamp {$op} ({$m[3]})::int * INTERVAL '1 {$unit}')";
        },
        $out
    );

    // YEAR(x) / MONTH(x)
    $out = preg_replace('/\bYEAR\s*\(\s*([^()]+?)\s*\)/i',  'EXTRACT(YEAR FROM $1)',  $out);
    $out = preg_replace('/\bMONTH\s*\(\s*([^()]+?)\s*\)/i', 'EXTRACT(MONTH FROM $1)', $out);

    return $cache[$sql] = $out;
}

/** Rewrite the first `name(...)` call, splitting top-level comma arguments. */
function db_rewrite_call(string $sql, string $name, callable $build): string {
    $start = strpos($sql, $name . '(');
    if ($start === false) return $sql;
    $open  = $start + strlen($name);
    $depth = 0; $args = []; $cur = ''; $i = $open;
    for ($len = strlen($sql); $i < $len; $i++) {
        $ch = $sql[$i];
        if ($ch === '(') { $depth++; if ($depth === 1) continue; }
        elseif ($ch === ')') { $depth--; if ($depth === 0) { $args[] = trim($cur); break; } }
        elseif ($ch === ',' && $depth === 1) { $args[] = trim($cur); $cur = ''; continue; }
        $cur .= $ch;
    }
    return substr($sql, 0, $start) . $build($args) . substr($sql, $i + 1);
}

function db_row(string $sql, array $params = []): ?array {
    $st = db()->prepare(db_translate($sql));
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: null;
}

function db_rows(string $sql, array $params = []): array {
    $st = db()->prepare(db_translate($sql));
    $st->execute($params);
    return $st->fetchAll();
}

function db_val(string $sql, array $params = []): mixed {
    $st = db()->prepare(db_translate($sql));
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_NUM);
    return $row ? $row[0] : null;
}

/**
 * Does this table have an auto-generated `id` column? Cached per request.
 * A few link tables (role_permissions, sku_sequences…) have composite keys and
 * no id, so Postgres must not get a RETURNING clause for them.
 */
function db_has_id_column(string $table): bool {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    if (db_is_pg()) {
        $found = db_val(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ? AND column_name = 'id'",
            [$table]
        );
    } else {
        $found = db_val("SHOW COLUMNS FROM {$table} LIKE 'id'");
    }
    return $cache[$table] = (bool) $found;
}

function db_insert(string $table, array $data): int {
    $cols = implode(',', array_keys($data));
    $phs  = implode(',', array_fill(0, count($data), '?'));
    $sql  = "INSERT INTO {$table} ({$cols}) VALUES ({$phs})";

    // Postgres has no lastInsertId() without a sequence name — RETURNING is the
    // portable way to get the new row's id back.
    if (db_is_pg()) {
        if (!db_has_id_column($table)) {
            db()->prepare($sql)->execute(array_values($data));
            return 0;
        }
        $st = db()->prepare($sql . ' RETURNING id');
        $st->execute(array_values($data));
        $row = $st->fetch(PDO::FETCH_NUM);
        return $row ? (int) $row[0] : 0;
    }

    db()->prepare($sql)->execute(array_values($data));
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $where_params = []): int {
    $set = implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)));
    $st  = db()->prepare(db_translate("UPDATE {$table} SET {$set} WHERE {$where}"));
    $st->execute([...array_values($data), ...$where_params]);
    return $st->rowCount();
}

function db_delete(string $table, string $where, array $params = []): int {
    $st = db()->prepare(db_translate("DELETE FROM {$table} WHERE {$where}"));
    $st->execute($params);
    return $st->rowCount();
}

function db_soft_delete(string $table, int $id): int {
    return db_update($table, ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
}
