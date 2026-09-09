<?php
/**
 * Per-product placement for premade designs.
 *
 * A premade design can be offered on many garments, but its position lived on
 * premade_designs — a single set of coordinates shared by every product. A
 * chest print that sits correctly on a t-shirt ends up on the hood of a hoodie
 * or off the edge of a cap.
 *
 * These columns move placement onto the design/product LINK row, so the same
 * design can sit differently on each garment. Existing values are copied from
 * the design so nothing shifts when this runs; the design-level columns stay
 * as the fallback for links that have not been positioned yet.
 *
 * Run:  php database/add_per_product_design_position.php
 * Idempotent: skips columns that already exist, only backfills NULLs.
 */

require_once __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');
$pdo = require __DIR__ . '/../app/config/database.php';

$columns = [
    'design_pos_x'         => 'FLOAT NULL DEFAULT NULL',
    'design_pos_y'         => 'FLOAT NULL DEFAULT NULL',
    'design_pos_size'      => 'FLOAT NULL DEFAULT NULL',
    'design_pos_back_x'    => 'FLOAT NULL DEFAULT NULL',
    'design_pos_back_y'    => 'FLOAT NULL DEFAULT NULL',
    'design_pos_back_size' => 'FLOAT NULL DEFAULT NULL',
];

$added = 0;
foreach ($columns as $name => $type) {
    $exists = $pdo->query("SHOW COLUMNS FROM design_products LIKE " . $pdo->quote($name))->fetch();
    if ($exists) {
        echo "design_products.$name already exists — skipping.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE design_products ADD COLUMN `$name` $type");
    $added++;
}
echo "added $added column(s).\n";

// One row per (design, product) pair — guards against duplicate links, which
// would otherwise give the editor two rows to write and an ambiguous read.
$dupes = $pdo->query("
    SELECT design_id, product_id, COUNT(*) c
    FROM design_products GROUP BY design_id, product_id HAVING c > 1
")->fetchAll(PDO::FETCH_ASSOC);
if ($dupes) {
    echo "WARNING: duplicate design/product links found; de-duplicating:\n";
    foreach ($dupes as $d) {
        echo "  design {$d['design_id']} / product {$d['product_id']} x{$d['c']}\n";
        $pdo->prepare("
            DELETE FROM design_products
            WHERE design_id = ? AND product_id = ?
              AND id NOT IN (SELECT * FROM (
                    SELECT MIN(id) FROM design_products WHERE design_id = ? AND product_id = ?
                  ) keep)
        ")->execute([$d['design_id'], $d['product_id'], $d['design_id'], $d['product_id']]);
    }
}
$idx = $pdo->query("SHOW INDEX FROM design_products WHERE Key_name = 'uq_design_product'")->fetch();
if (!$idx) {
    $pdo->exec("ALTER TABLE design_products ADD UNIQUE KEY uq_design_product (design_id, product_id)");
    echo "added unique key on (design_id, product_id).\n";
}

// Seed each link with the design's current position so nothing moves today.
$n = $pdo->exec("
    UPDATE design_products dp
    JOIN premade_designs d ON d.id = dp.design_id
    SET dp.design_pos_x         = COALESCE(dp.design_pos_x,         d.design_pos_x),
        dp.design_pos_y         = COALESCE(dp.design_pos_y,         d.design_pos_y),
        dp.design_pos_size      = COALESCE(dp.design_pos_size,      d.design_pos_size),
        dp.design_pos_back_x    = COALESCE(dp.design_pos_back_x,    d.design_pos_back_x),
        dp.design_pos_back_y    = COALESCE(dp.design_pos_back_y,    d.design_pos_back_y),
        dp.design_pos_back_size = COALESCE(dp.design_pos_back_size, d.design_pos_back_size)
    WHERE dp.design_pos_x IS NULL OR dp.design_pos_y IS NULL OR dp.design_pos_size IS NULL
       OR dp.design_pos_back_x IS NULL OR dp.design_pos_back_y IS NULL OR dp.design_pos_back_size IS NULL
");
echo "backfilled $n link row(s) from their design's position.\n";
