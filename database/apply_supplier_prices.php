<?php
/**
 * Apply supplier_prices.php to the products, product_sizes,
 * product_colors and product_variants tables.
 *
 * Run with --dry-run (default) to preview; --apply to commit.
 *
 * Requires the 2026_06_14_add_pricing_columns.sql migration:
 *   - products.supplier_ref
 *   - product_variants.unit_price
 *
 * The script is idempotent — re-running with the same data leaves the
 * DB in the same target state. INSERT IGNORE is used for sizes / colour
 * associations / variants; price columns are then UPDATEd to the
 * computed values regardless of pre-existing rows. Stock values are
 * preserved when variants already exist.
 */

declare(strict_types=1);

require __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');

$dryRun  = !in_array('--apply', $argv, true);
$verbose = in_array('--verbose', $argv, true) || in_array('-v', $argv, true);

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    Env::get('DB_HOST', '127.0.0.1'),
    Env::get('DB_NAME'),
    Env::get('DB_CHARSET', 'utf8mb4')
);

try {
    $pdo = new PDO($dsn, Env::get('DB_USER'), Env::get('DB_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot connect to DB: " . $e->getMessage() . "\n");
    exit(1);
}

// Column sanity checks
foreach (
    [
        ['table' => 'products',         'column' => 'supplier_ref'],
        ['table' => 'products',         'column' => 'size_chart_image'],
        ['table' => 'product_variants', 'column' => 'unit_price'],
    ] as $check
) {
    $cols = $pdo->query("SHOW COLUMNS FROM {$check['table']} LIKE '{$check['column']}'")->fetchAll();
    if (!$cols) {
        fwrite(STDERR, "ERROR: {$check['table']}.{$check['column']} not found. Run the migrations under database/migrations/ first.\n");
        exit(1);
    }
}

$prices = require __DIR__ . '/supplier_prices.php';

// Cache colour-name → id map
$colorIdByName = [];
foreach ($pdo->query("SELECT id, color_name FROM available_colors") as $row) {
    $colorIdByName[strtolower(trim($row['color_name']))] = (int)$row['id'];
}

$summary = [
    'products_inserted' => 0,
    'products_updated'  => 0,
    'sizes_seeded'      => 0,
    'colors_linked'     => 0,
    'variants_created'  => 0,
    'variants_priced'   => 0,
    'charts_set'        => 0,
    'unmapped'          => [],
    'missing_colors'    => [],
];

/**
 * BIG = 3XL and up. Matches 3XL/4XL/5XL/.../XXXL etc.
 * (2XL / XXL stays on the normal price.)
 */
function isBigSize(string $sizeName): bool {
    $n = strtoupper(trim($sizeName));
    if (preg_match('/^[3-9]XL$/', $n)) return true;       // 3XL, 4XL, 5XL, …
    if (preg_match('/^X{3,}L$/', $n))  return true;       // XXXL, XXXXL, …
    return false;
}

function isWhiteColor(string $colorName): bool {
    $c = strtolower(trim($colorName));
    return $c === 'white' || $c === 'ivory' || $c === 'off white' || $c === 'off-white';
}

/**
 * Resolve the variant price using the supplier matrix. Returns null when
 * nothing applies (caller falls back to base + size modifier).
 */
function variantPrice(array $p, bool $sizeIsBig, bool $colorIsWhite): ?float {
    $white = $p['white'] ?? null;
    $color = $p['color'] ?? null;
    $big   = $p['big']   ?? null;
    $cbig  = $p['cbig']  ?? null;
    $norm  = $p['norm']  ?? null;

    if ($sizeIsBig && !$colorIsWhite) return $cbig ?? $big ?? $color ?? $norm;
    if ($sizeIsBig &&  $colorIsWhite) return $big  ?? $white ?? $norm;
    if (!$sizeIsBig && !$colorIsWhite) return $color ?? $norm;
    return $white ?? $norm;
}

function sizeOrder(string $sizeName): int {
    static $order = [
        'one size'=>1,'one-size'=>1,'os'=>1,
        'xs'=>10,'s'=>20,'m'=>30,'l'=>40,'xl'=>50,
        '2xl'=>60,'xxl'=>60,
        '3xl'=>70,'xxxl'=>70,
        '4xl'=>80,'xxxxl'=>80,
        '5xl'=>90,'xxxxxl'=>90,
        '11oz'=>10,'15oz'=>20,
    ];
    return $order[strtolower(trim($sizeName))] ?? 100;
}

$pdo->beginTransaction();

try {
    foreach ($prices as $ref => $p) {
        $name = $p['name'];
        $slug = $p['slug'];
        $sizes  = $p['sizes']  ?? [];
        $colors = $p['colors'] ?? null; // null → all available colours

        $basePrice = $p['white'] ?? $p['norm'] ?? null;
        if ($basePrice === null) {
            $summary['unmapped'][] = "$ref ({$name}): no white or norm price";
            continue;
        }

        // Non-supplier keys (e.g. JACKET) don't get a supplier_ref written.
        $supplierRef = (strlen($ref) && $ref[0] === '#') ? $ref : null;

        // ── 1. Upsert the product row ─────────────────────────────────
        $existing = null;
        if (!empty($p['existing_id'])) {
            $st = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $st->execute([$p['existing_id']]);
            $existing = $st->fetch();
        }
        if (!$existing && $supplierRef !== null) {
            $st = $pdo->prepare("SELECT * FROM products WHERE supplier_ref = ?");
            $st->execute([$supplierRef]);
            $existing = $st->fetch();
        }
        if (!$existing) {
            $st = $pdo->prepare("SELECT * FROM products WHERE slug = ?");
            $st->execute([$slug]);
            $existing = $st->fetch();
        }

        if ($existing) {
            $productId = (int)$existing['id'];
            $st = $pdo->prepare("UPDATE products SET name=?, slug=?, supplier_ref=?, base_price=? WHERE id=?");
            $st->execute([$name, $slug, $supplierRef, $basePrice, $productId]);
            $summary['products_updated']++;
            if ($verbose) echo "  · UPDATE  $ref → product #$productId ({$name}) base={$basePrice}\n";
        } else {
            $st = $pdo->prepare("INSERT INTO products (name, slug, supplier_ref, base_price, active) VALUES (?,?,?,?,1)");
            $st->execute([$name, $slug, $supplierRef, $basePrice]);
            $productId = (int)$pdo->lastInsertId();
            $summary['products_inserted']++;
            if ($verbose) echo "  + INSERT  $ref → product #$productId ({$name}) base={$basePrice}\n";
        }

        // 1b. Size-chart image path
        if (!empty($p['size_chart'])) {
            $st = $pdo->prepare("UPDATE products SET size_chart_image=? WHERE id=?");
            $st->execute([$p['size_chart'], $productId]);
            $summary['charts_set']++;
        }

        // ── 2. Seed sizes for this product ────────────────────────────
        if (!$sizes) {
            $summary['unmapped'][] = "$ref ({$name}): no sizes listed";
        }
        $sizeIdByName = [];
        foreach ($sizes as $sizeName) {
            $st = $pdo->prepare("SELECT id FROM product_sizes WHERE product_id=? AND size_name=?");
            $st->execute([$productId, $sizeName]);
            $row = $st->fetch();
            if ($row) {
                $sizeId = (int)$row['id'];
            } else {
                $st = $pdo->prepare("INSERT INTO product_sizes (product_id, size_name, size_order, price_modifier, is_available) VALUES (?,?,?,?,1)");
                $st->execute([$productId, $sizeName, sizeOrder($sizeName), 0.00]);
                $sizeId = (int)$pdo->lastInsertId();
                $summary['sizes_seeded']++;
            }
            $sizeIdByName[$sizeName] = $sizeId;

            // Set price_modifier so non-variant pricing also works:
            //   modifier = bigPrice - basePrice   for BIG sizes (if 'big' set)
            //   modifier = 0                      otherwise
            $modifier = 0.00;
            if (isBigSize($sizeName) && isset($p['big'])) {
                $modifier = round(((float)$p['big']) - (float)$basePrice, 2);
            }
            $st = $pdo->prepare("UPDATE product_sizes SET size_order=?, price_modifier=? WHERE id=?");
            $st->execute([sizeOrder($sizeName), $modifier, $sizeId]);
        }

        // ── 3. Seed colour associations ───────────────────────────────
        $colorList = $colors ?: array_keys($colorIdByName);
        $usedColorIds = [];
        foreach ($colorList as $colorName) {
            $key = strtolower(trim($colorName));
            if (!isset($colorIdByName[$key])) {
                $summary['missing_colors'][] = "$colorName (referenced by $ref)";
                continue;
            }
            $colorId = $colorIdByName[$key];
            $usedColorIds[$colorName] = $colorId;

            $st = $pdo->prepare("SELECT id FROM product_colors WHERE product_id=? AND color_id=?");
            $st->execute([$productId, $colorId]);
            if (!$st->fetch()) {
                $st = $pdo->prepare("INSERT INTO product_colors (product_id, color_id, is_available) VALUES (?,?,1)");
                $st->execute([$productId, $colorId]);
                $summary['colors_linked']++;
            }
        }

        // ── 4. Seed variants for every (size, colour) combo ───────────
        foreach ($sizeIdByName as $sizeName => $sizeId) {
            $sizeIsBig = isBigSize($sizeName);
            foreach ($usedColorIds as $colorName => $colorId) {
                $colorIsWhite = isWhiteColor($colorName);
                $unitPrice = variantPrice($p, $sizeIsBig, $colorIsWhite);

                $st = $pdo->prepare("SELECT id FROM product_variants WHERE product_id=? AND size_id=? AND color_id=?");
                $st->execute([$productId, $sizeId, $colorId]);
                $vRow = $st->fetch();
                if ($vRow) {
                    $variantId = (int)$vRow['id'];
                } else {
                    $st = $pdo->prepare("INSERT INTO product_variants (product_id, size_id, color_id, stock_quantity, unit_price, is_available) VALUES (?,?,?,0,?,1)");
                    $st->execute([$productId, $sizeId, $colorId, $unitPrice]);
                    $variantId = (int)$pdo->lastInsertId();
                    $summary['variants_created']++;
                }
                $st = $pdo->prepare("UPDATE product_variants SET unit_price=?, is_available=1 WHERE id=?");
                $st->execute([$unitPrice, $variantId]);
                $summary['variants_priced']++;
                if ($verbose && $unitPrice !== null) {
                    echo sprintf("    · variant #%d  %-8s / %-12s → %.2f\n", $variantId, $sizeName, $colorName, $unitPrice);
                }
            }
        }
    }

    if ($dryRun) {
        $pdo->rollBack();
        echo "\nDRY RUN — no changes committed.\n";
    } else {
        $pdo->commit();
        echo "\nApplied.\n";
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "FAILED: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\nSummary:\n";
echo "  products inserted:        " . $summary['products_inserted'] . "\n";
echo "  products updated:         " . $summary['products_updated']  . "\n";
echo "  sizes seeded (new rows):  " . $summary['sizes_seeded']      . "\n";
echo "  colors linked (new rows): " . $summary['colors_linked']     . "\n";
echo "  variants created:         " . $summary['variants_created']  . "\n";
echo "  variants (re)priced:      " . $summary['variants_priced']   . "\n";
echo "  size charts assigned:     " . $summary['charts_set']        . "\n";

if ($summary['unmapped']) {
    echo "  notes / unmapped:\n";
    foreach ($summary['unmapped'] as $u) echo "    - $u\n";
}
if ($summary['missing_colors']) {
    echo "  missing colours in available_colors:\n";
    foreach (array_unique($summary['missing_colors']) as $u) echo "    - $u\n";
}
echo "\n";
if ($dryRun) {
    echo "To commit:  php database/apply_supplier_prices.php --apply\n";
    echo "Add -v / --verbose to see every row.\n";
}
