<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');

$db = require __DIR__ . '/../app/config/database.php';

// Add preview_images column to cart_items
try {
    $db->exec("ALTER TABLE cart_items ADD COLUMN preview_images TEXT DEFAULT NULL");
    echo "Added preview_images column to cart_items\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "preview_images column already exists on cart_items\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
