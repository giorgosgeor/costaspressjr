<?php
$db = require __DIR__ . '/../app/config/database.php';

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS order_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            payment_method VARCHAR(30) DEFAULT 'card',
            card_brand VARCHAR(30) DEFAULT NULL,
            card_holder VARCHAR(100) DEFAULT NULL,
            card_last4 VARCHAR(4) DEFAULT NULL,
            card_exp_month INT DEFAULT NULL,
            card_exp_year INT DEFAULT NULL,
            billing_zip VARCHAR(20) DEFAULT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) DEFAULT 'paid',
            payment_intent_id VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_order_payments_intent (payment_intent_id),
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )
    ");
    echo "order_payments table created successfully\n";

    // Drop card_cvv column if it exists (security: CVV must never be stored)
    try {
        $db->exec("ALTER TABLE order_payments DROP COLUMN card_cvv");
        echo "Dropped card_cvv column (security fix)\n";
    } catch (PDOException $e) {
        // Column may not exist, that's fine
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
