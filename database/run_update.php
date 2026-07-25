<?php
/**
 * Run this script once to update the custom_designs table
 * Execute with: php database/run_update.php
 */

// Load database config
require __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');

$db = require __DIR__ . '/../app/config/database.php';

try {
    echo "Updating custom_designs table...\n";
    
    // Check if 'name' column exists
    $result = $db->query("SHOW COLUMNS FROM custom_designs LIKE 'name'");
    if ($result->rowCount() === 0) {
        $db->exec("ALTER TABLE custom_designs ADD COLUMN name VARCHAR(100) DEFAULT NULL AFTER id");
        echo "Added 'name' column\n";
    } else {
        echo "'name' column already exists\n";
    }
    
    // Check if 'email' column exists
    $result = $db->query("SHOW COLUMNS FROM custom_designs LIKE 'email'");
    if ($result->rowCount() === 0) {
        $db->exec("ALTER TABLE custom_designs ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER color_hex");
        echo "Added 'email' column\n";
    } else {
        echo "'email' column already exists\n";
    }
    
    // Modify size_id to allow NULL
    $db->exec("ALTER TABLE custom_designs MODIFY COLUMN size_id INT DEFAULT NULL");
    echo "Modified 'size_id' to allow NULL\n";
    
    // Modify color_id to allow NULL
    $db->exec("ALTER TABLE custom_designs MODIFY COLUMN color_id INT DEFAULT NULL");
    echo "Modified 'color_id' to allow NULL\n";
    
    echo "\nDatabase update complete!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
