<?php
/**
 * Migration: Add design_id to cart_items and order_items,
 * add preview_images to custom_designs
 */

$db = require __DIR__ . '/../app/config/database.php';

$queries = [
    // Add preview_images JSON column to custom_designs
    "ALTER TABLE custom_designs ADD COLUMN IF NOT EXISTS preview_images TEXT NULL DEFAULT NULL COMMENT 'JSON with preview image paths per view'",
    
    // Add design_id to cart_items
    "ALTER TABLE cart_items ADD COLUMN IF NOT EXISTS design_id INT NULL DEFAULT NULL",
    
    // Add design_id and preview_images to order_items
    "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS design_id INT NULL DEFAULT NULL",
    "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS preview_images TEXT NULL DEFAULT NULL COMMENT 'JSON with preview image paths per view'",
];

echo "<h2>Running migration: Design previews support</h2><pre>\n";

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        echo "✅ OK: " . substr($sql, 0, 80) . "...\n";
    } catch (PDOException $e) {
        // Check if it's a "duplicate column" error (column already exists)
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⏭️ SKIP (already exists): " . substr($sql, 0, 80) . "...\n";
        } else {
            echo "❌ ERROR: " . $e->getMessage() . "\n   SQL: " . substr($sql, 0, 80) . "...\n";
        }
    }
}

// Create previews directory
$previewDir = __DIR__ . '/../public/images/designs/previews';
if (!is_dir($previewDir)) {
    mkdir($previewDir, 0755, true);
    echo "✅ Created previews directory: $previewDir\n";
} else {
    echo "⏭️ Previews directory already exists\n";
}

echo "\n✅ Migration complete!\n</pre>";
