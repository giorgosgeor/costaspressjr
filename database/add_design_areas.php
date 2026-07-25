<?php
// Migration: add per-product design area columns to products table
$db = require __DIR__ . '/../app/config/database.php';

$columns = [
    'da_front_x'    => 'FLOAT NOT NULL DEFAULT 27.5',
    'da_front_y'    => 'FLOAT NOT NULL DEFAULT 25',
    'da_front_w'    => 'FLOAT NOT NULL DEFAULT 45',
    'da_front_h'    => 'FLOAT NOT NULL DEFAULT 60',
    'da_back_x'     => 'FLOAT NOT NULL DEFAULT 27.5',
    'da_back_y'     => 'FLOAT NOT NULL DEFAULT 25',
    'da_back_w'     => 'FLOAT NOT NULL DEFAULT 45',
    'da_back_h'     => 'FLOAT NOT NULL DEFAULT 60',
    'da_lsleeve_x'  => 'FLOAT NOT NULL DEFAULT 46',
    'da_lsleeve_y'  => 'FLOAT NOT NULL DEFAULT 27',
    'da_lsleeve_w'  => 'FLOAT NOT NULL DEFAULT 13',
    'da_lsleeve_h'  => 'FLOAT NOT NULL DEFAULT 16',
    'da_rsleeve_x'  => 'FLOAT NOT NULL DEFAULT 46',
    'da_rsleeve_y'  => 'FLOAT NOT NULL DEFAULT 27',
    'da_rsleeve_w'  => 'FLOAT NOT NULL DEFAULT 13',
    'da_rsleeve_h'  => 'FLOAT NOT NULL DEFAULT 16',
];

// Check which columns already exist
$existing = $db->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);

$added = 0;
foreach ($columns as $col => $def) {
    if (!in_array($col, $existing)) {
        $db->exec("ALTER TABLE products ADD COLUMN `$col` $def");
        echo "Added: $col\n";
        $added++;
    } else {
        echo "Exists: $col\n";
    }
}

echo "\nDone. $added column(s) added.\n";
