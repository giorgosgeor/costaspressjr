<?php
/**
 * Build the first production database, and the images that go with it.
 *
 *   php database/export_for_production.php               catalog only
 *   php database/export_for_production.php --with-admin  also the admin account(s)
 *
 * Writes storage/export/<timestamp>/:
 *   database.sql   the structure of every table, plus the rows of the shop's
 *                  catalog (products, sizes, colours, variants, size charts,
 *                  premade designs, pickup points) and schema_migrations, so
 *                  migrate.php on the server knows what is already applied.
 *   images/        public/images/products and any other catalog image git
 *                  doesn't track. A git deploy doesn't carry these; upload
 *                  the folder's contents to public/ on the server.
 *
 * Left out on purpose — all of it test data from development: customers and
 * guests, carts, orders and payments, saved designs, favourites, tokens,
 * login attempts, pending checkouts, payment alerts.
 *
 * --with-admin copies the admin row(s) with their CURRENT password hash. Use
 * it only when the server has no shell to run database/create_admin.php, and
 * make sure that password is strong before exporting.
 *
 * Import into an EMPTY database. The dump has no DROP TABLE, so pointed at a
 * database that already has these tables it stops at the first CREATE
 * instead of wiping anything.
 *
 * mysqldump is taken from $MYSQLDUMP, XAMPP's default path, or the PATH.
 */

require __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');

const CATALOG_TABLES = [
    'available_colors', 'products', 'product_sizes', 'product_colors', 'product_variants',
    'design_sections', 'premade_designs', 'design_products', 'pickup_points', 'schema_migrations',
];

$opts      = getopt('', ['with-admin']);
$withAdmin = isset($opts['with-admin']);
$root      = dirname(__DIR__);

$dump = getenv('MYSQLDUMP') ?: (is_file('C:/xampp/mysql/bin/mysqldump.exe') ? 'C:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump');
$db   = require __DIR__ . '/../app/config/database.php';

$outDir = $root . '/storage/export/' . date('Ymd_His');
if (!mkdir($outDir . '/images', 0775, true)) {
    fwrite(STDERR, "Cannot create $outDir\n");
    exit(1);
}
$sqlFile = $outDir . '/database.sql';

/** Append one mysqldump run to the SQL file. The password goes in the environment, not the command line. */
function runDump(string $dump, array $args, string $sqlFile): void {
    $base = [
        $dump,
        '--host=' . Env::get('DB_HOST', '127.0.0.1'),
        '--user=' . Env::required('DB_USER'),
        '--default-character-set=' . Env::get('DB_CHARSET', 'utf8mb4'),
        '--single-transaction',
        '--skip-comments',
    ];
    $cmd  = implode(' ', array_map('escapeshellarg', array_merge($base, $args)));
    $env  = getenv() + ['MYSQL_PWD' => (string)Env::get('DB_PASS', '')];
    $proc = proc_open($cmd, [1 => ['file', $sqlFile, 'a'], 2 => ['pipe', 'w']], $pipes, null, $env);
    if (!is_resource($proc)) {
        throw new RuntimeException("could not run $dump");
    }
    $err  = stream_get_contents($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) {
        throw new RuntimeException("mysqldump exited $code: " . trim($err));
    }
}

$dbName = Env::required('DB_NAME');
try {
    file_put_contents($sqlFile, "-- Costaspressjr production seed, exported " . date('c') . "\n"
        . "-- Import into an empty database. See database/export_for_production.php.\n\n");
    runDump($dump, ['--no-data', '--skip-add-drop-table', $dbName], $sqlFile);
    runDump($dump, array_merge(['--no-create-info', '--skip-triggers', '--complete-insert', $dbName], CATALOG_TABLES), $sqlFile);
    if ($withAdmin) {
        runDump($dump, ['--no-create-info', '--skip-triggers', '--complete-insert', "--where=role='admin'", $dbName, 'users'], $sqlFile);
    }
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Export failed: ' . $e->getMessage() . "\n");
    exit(1);
}

// Every image path the catalog rows mention, whether a plain column or a
// JSON list, normalised to a path under public/.
$referenced = [];
foreach (['products', 'product_colors', 'product_variants', 'premade_designs', 'design_products'] as $table) {
    foreach ($db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        foreach ($row as $value) {
            if (!is_string($value) || !preg_match('/\.(png|jpe?g|webp|gif|svg)\b/i', $value)) continue;
            $decoded = json_decode($value, true);
            foreach (is_array($decoded) ? $decoded : [$value] as $path) {
                if (is_string($path) && $path !== '' && !preg_match('#^https?://#i', $path)) {
                    $referenced[preg_replace('#^/?(public/)?#', '', $path)] = true;
                }
            }
        }
    }
}

// What git tracks ships with the code; everything else has to go in images/.
$tracked = [];
exec('git -C ' . escapeshellarg($root) . ' ls-files public', $gitFiles, $gitCode);
foreach ($gitCode === 0 ? $gitFiles : [] as $f) {
    $tracked[substr($f, strlen('public/'))] = true;
}

$copy = [];
foreach (glob($root . '/public/images/products/*') ?: [] as $f) {
    if (is_file($f)) $copy['images/products/' . basename($f)] = true;
}
$missing = [];
foreach (array_keys($referenced) as $rel) {
    if (!is_file($root . '/public/' . $rel)) {
        $missing[] = $rel;
    } elseif (!isset($tracked[$rel])) {
        $copy[$rel] = true;
    }
}
foreach (array_keys($copy) as $rel) {
    $to = $outDir . '/images/' . $rel;
    if (!is_dir(dirname($to))) mkdir(dirname($to), 0775, true);
    copy($root . '/public/' . $rel, $to);
}

echo "Exported to $outDir\n\n";
foreach (array_merge(CATALOG_TABLES, $withAdmin ? ['users'] : []) as $table) {
    $n = $table === 'users'
        ? $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn()
        : $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    printf("  %-20s %5d rows\n", $table, $n);
}
printf("\n  database.sql         %5d KB\n", filesize($sqlFile) / 1024);
printf("  images/              %5d files (upload into public/ on the server)\n", count($copy));
if ($missing) {
    echo "\nWARNING: the catalog points at " . count($missing) . " image(s) that don't exist here, so they will be broken on the live site too:\n";
    foreach ($missing as $rel) echo "  public/$rel\n";
}
if (!$withAdmin) {
    echo "\nNo admin account included: run database/create_admin.php on the server.\n";
}
