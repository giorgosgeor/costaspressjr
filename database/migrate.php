<?php
/**
 * Migration runner.
 *
 *   php database/migrate.php           # apply pending migrations
 *   php database/migrate.php --status  # list applied / pending
 *   php database/migrate.php --baseline# mark every present migration as
 *                                      # applied without running them
 *                                      # (use this once on an existing
 *                                      # database to bootstrap tracking)
 *
 * Migrations live in database/migrations/ as numbered .sql files
 * (001_xxx.sql, 002_xxx.sql, …). Each file should be idempotent where
 * possible (CREATE TABLE IF NOT EXISTS, etc.). The runner swallows
 * "Duplicate column" / "table already exists" errors so re-running is safe
 * even when a statement isn't strictly idempotent at the SQL level.
 *
 * Applied filenames are tracked in the schema_migrations table.
 */

require __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');

$pdo = require __DIR__ . '/../app/config/database.php';
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS schema_migrations (
    filename    VARCHAR(191) NOT NULL PRIMARY KEY,
    applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
);

$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

$applied = $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied ?: []);

$mode = $argv[1] ?? '';

if ($mode === '--status') {
    foreach ($files as $f) {
        $name = basename($f);
        echo (isset($applied[$name]) ? '[x] ' : '[ ] ') . $name . "\n";
    }
    exit(0);
}

if ($mode === '--baseline') {
    $stmt = $pdo->prepare("INSERT IGNORE INTO schema_migrations (filename) VALUES (?)");
    foreach ($files as $f) {
        $name = basename($f);
        $stmt->execute([$name]);
        echo "marked applied: $name\n";
    }
    exit(0);
}

$pending = array_values(array_filter($files, fn($f) => !isset($applied[basename($f)])));
if (!$pending) {
    echo "Nothing to migrate.\n";
    exit(0);
}

$insert = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)");
foreach ($pending as $f) {
    $name = basename($f);
    echo "Applying $name … ";
    $sql = file_get_contents($f);
    if ($sql === false) {
        echo "could not read file\n";
        exit(1);
    }

    foreach (splitStatements($sql) as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // Treat known-idempotent failures as "already applied" so the
            // migration completes cleanly when partially run before.
            $msg = $e->getMessage();
            if (
                str_contains($msg, 'Duplicate column name')      // ALTER ADD COLUMN re-run
                || str_contains($msg, 'already exists')          // CREATE TABLE re-run
                || str_contains($msg, 'Duplicate key name')      // CREATE INDEX re-run
                || str_contains($msg, "Can't DROP")              // DROP COLUMN missing
            ) {
                continue;
            }
            echo "FAILED\n  $msg\n";
            exit(1);
        }
    }

    $insert->execute([$name]);
    echo "done\n";
}

echo "All migrations applied.\n";

/**
 * Split a SQL file into top-level statements. This is a basic splitter — it
 * does not handle stored procedures with embedded semicolons. Migrations
 * here are vanilla DDL, so it's enough.
 */
function splitStatements(string $sql): array {
    $sql = preg_replace('!/\*.*?\*/!s', '', $sql);          // /* … */
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);          // -- comments
    $parts = preg_split('/;\s*[\r\n]+/', $sql);
    return array_values(array_filter(array_map('trim', $parts)));
}
