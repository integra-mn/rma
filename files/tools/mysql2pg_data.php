<?php
/**
 * Copy all data from the MySQL database into PostgreSQL.
 *
 * Used both to migrate the production database and to load a realistic dataset
 * into local Postgres for testing. Idempotent: each target table is truncated
 * before load, so it can be re-run safely.
 *
 * Usage (from files/):
 *   php tools/mysql2pg_data.php                      # dry run: row counts only
 *   php tools/mysql2pg_data.php --run                # perform the copy
 *
 * Connection settings come from the environment / .env, with MY_* overrides for
 * the MySQL side so the app's own .env can already point at Postgres:
 *   MY_HOST MY_PORT MY_NAME MY_USER MY_PASS
 */
declare(strict_types=1);

define('RMS', true);
define('ROOT', dirname(__DIR__));
require ROOT . '/config/env.php';
env_load(ROOT . '/.env.local');
env_load(ROOT . '/.env');

$RUN = in_array('--run', $argv, true);

// ── Source: MySQL ────────────────────────────────────────────────
$myHost = getenv('MY_HOST') ?: '127.0.0.1';
$myPort = getenv('MY_PORT') ?: '3306';
$myName = getenv('MY_NAME') ?: 'integra_rma';
$myUser = getenv('MY_USER') ?: 'root';
$myPass = getenv('MY_PASS') ?: '';

// ── Target: Postgres (from the app's env) ────────────────────────
$pgHost = env('DB_HOST', '127.0.0.1');
$pgPort = env('DB_PORT', '5432');
$pgName = env('DB_NAME', 'integra_rma');
$pgUser = env('DB_USER', 'postgres');
$pgPass = env('DB_PASS', '');

$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

try {
    $my = new PDO("mysql:host=$myHost;port=$myPort;dbname=$myName;charset=utf8mb4", $myUser, $myPass, $opts);
    $pg = new PDO("pgsql:host=$pgHost;port=$pgPort;dbname=$pgName", $pgUser, $pgPass, $opts);
} catch (Throwable $e) {
    fwrite(STDERR, "connection failed: {$e->getMessage()}\n");
    exit(1);
}

/** Tables present in BOTH databases — copy those, report the rest. */
$myTables = $my->query(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name"
)->fetchAll(PDO::FETCH_COLUMN);

$pgTables = $pg->query(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'"
)->fetchAll(PDO::FETCH_COLUMN);

$both = array_values(array_intersect($myTables, $pgTables));
$only = array_values(array_diff($myTables, $pgTables));
if ($only) fwrite(STDERR, "skipping (not in Postgres): " . implode(', ', $only) . "\n");

// Generated columns can't be written to — skip them on the target side.
$generated = [];
foreach ($pg->query(
    "SELECT table_name, column_name FROM information_schema.columns
     WHERE table_schema = current_schema() AND is_generated = 'ALWAYS'"
) as $r) {
    $generated[$r['table_name']][$r['column_name']] = true;
}

if (!$RUN) {
    printf("%-34s %8s\n", 'TABLE', 'ROWS');
    $total = 0;
    foreach ($both as $t) {
        $n = (int) $my->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $total += $n;
        if ($n) printf("%-34s %8d\n", $t, $n);
    }
    echo str_repeat('-', 44), "\n";
    printf("%-34s %8d\n", "TOTAL (" . count($both) . " tables)", $total);
    echo "\nDry run. Re-run with --run to copy.\n";
    exit(0);
}

// FKs would force a specific table order; disabling triggers avoids that.
$pg->exec('SET session_replication_role = replica');

// Clear everything in ONE statement first. Truncating table-by-table with
// CASCADE would wipe rows already copied (e.g. truncating `users` cascades into
// `rma_requests`), so the whole set must be emptied before any insert happens.
$pg->exec('TRUNCATE TABLE ' . implode(', ', $both) . ' CASCADE');

$copied = 0; $failed = [];
foreach ($both as $t) {
    $rows = $my->query("SELECT * FROM `$t`")->fetchAll();
    if (!$rows) continue;

    $cols = array_keys($rows[0]);
    if (isset($generated[$t])) {
        $cols = array_values(array_filter($cols, fn($c) => !isset($generated[$t][$c])));
    }
    $list = implode(',', $cols);
    $phs  = implode(',', array_fill(0, count($cols), '?'));
    $st   = $pg->prepare("INSERT INTO {$t} ({$list}) VALUES ({$phs})");

    $pg->beginTransaction();
    try {
        foreach ($rows as $row) {
            $vals = [];
            foreach ($cols as $c) {
                $v = $row[$c];
                // MySQL's zero-dates have no Postgres equivalent.
                if (is_string($v) && preg_match('/^0000-00-00/', $v)) $v = null;
                $vals[] = $v;
            }
            $st->execute($vals);
        }
        $pg->commit();
        $copied += count($rows);
        printf("%-34s %8d\n", $t, count($rows));
    } catch (Throwable $e) {
        $pg->rollBack();
        $failed[$t] = $e->getMessage();
        fwrite(STDERR, "FAILED {$t}: {$e->getMessage()}\n");
    }
}

$pg->exec('SET session_replication_role = DEFAULT');

// Identity columns were fed explicit ids, so their sequences are still at 1.
foreach ($pg->query(
    "SELECT table_name, column_name FROM information_schema.columns
     WHERE table_schema = current_schema() AND is_identity = 'YES'"
) as $r) {
    $t = $r['table_name']; $c = $r['column_name'];
    $pg->exec(
        "SELECT setval(pg_get_serial_sequence('{$t}', '{$c}'),
                       COALESCE((SELECT MAX({$c}) FROM {$t}), 0) + 1, false)"
    );
}

echo "\ncopied {$copied} rows into " . count($both) . " tables\n";
if ($failed) {
    echo "FAILED tables: " . implode(', ', array_keys($failed)) . "\n";
    exit(1);
}
echo "sequences reset. done.\n";
