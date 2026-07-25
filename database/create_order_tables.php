<?php
$db = require __DIR__ . '/../app/config/database.php';

$tables = [
    "CREATE TABLE IF NOT EXISTS order_item_designs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_item_id INT NOT NULL,
        design_data JSON NOT NULL,
        preview_image_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS order_item_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_item_id INT NOT NULL,
        original_filename VARCHAR(255) DEFAULT NULL,
        stored_file_path VARCHAR(255) NOT NULL,
        placement ENUM('front', 'back', 'left-sleeve', 'right-sleeve') NOT NULL DEFAULT 'front',
        position_x INT DEFAULT 0,
        position_y INT DEFAULT 0,
        width INT DEFAULT 80,
        height INT DEFAULT 80,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS order_item_texts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_item_id INT NOT NULL,
        text_content TEXT NOT NULL,
        font_family VARCHAR(100) DEFAULT 'Arial',
        font_size INT DEFAULT 24,
        text_color VARCHAR(10) DEFAULT '#000000',
        is_bold TINYINT(1) DEFAULT 0,
        is_italic TINYINT(1) DEFAULT 0,
        is_underline TINYINT(1) DEFAULT 0,
        placement ENUM('front', 'back', 'left-sleeve', 'right-sleeve') NOT NULL DEFAULT 'front',
        position_x INT DEFAULT 0,
        position_y INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $sql) {
    try {
        $db->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        echo "OK: " . $m[1] . "\n";
    } catch (PDOException $e) {
        echo "ERR: " . $e->getMessage() . "\n";
    }
}
