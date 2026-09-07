<?php
/**
 * Adds orders.tracking_token — the public "tracking number" customers (guests
 * included) use on /track-order to look up their order status. Unambiguous
 * uppercase alphabet (no 0/O/1/I/L/U), 12 chars ≈ 59 random bits, so the
 * token works as an unguessable lookup capability on its own.
 *
 * Run:  php database/add_order_tracking.php
 * Idempotent: skips the column if present, only backfills NULL tokens.
 */

require_once __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');
$pdo = require __DIR__ . '/../app/config/database.php';

const TRACKING_ALPHABET = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';

function generateTrackingToken(): string {
    $out = '';
    for ($i = 0; $i < 12; $i++) {
        $out .= TRACKING_ALPHABET[random_int(0, strlen(TRACKING_ALPHABET) - 1)];
    }
    return $out;
}

// 1. Column + unique index
$col = $pdo->query("SHOW COLUMNS FROM orders LIKE 'tracking_token'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE orders
        ADD COLUMN tracking_token VARCHAR(16) NULL DEFAULT NULL AFTER status,
        ADD UNIQUE KEY uq_orders_tracking_token (tracking_token)");
    echo "orders.tracking_token column added.\n";
} else {
    echo "orders.tracking_token already exists — skipping ALTER.\n";
}

// 2. Backfill existing orders
$ids = $pdo->query("SELECT id FROM orders WHERE tracking_token IS NULL OR tracking_token = ''")
           ->fetchAll(PDO::FETCH_COLUMN);
$upd = $pdo->prepare("UPDATE orders SET tracking_token = ? WHERE id = ?");
$done = 0;
foreach ($ids as $id) {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $upd->execute([generateTrackingToken(), (int)$id]);
            $done++;
            break;
        } catch (PDOException $e) {
            // duplicate token — retry
        }
    }
}
echo "backfilled $done order(s).\n";
