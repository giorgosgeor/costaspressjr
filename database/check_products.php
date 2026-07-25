<?php
require __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');

$db = require __DIR__ . '/../app/config/database.php';

echo "=== custom_designs ===\n";
$r = $db->query('DESCRIBE custom_designs');
while ($row = $r->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . ' | ' . $row[1] . PHP_EOL;
}

echo "\n=== cart_items ===\n";
$r = $db->query('DESCRIBE cart_items');
while ($row = $r->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . ' | ' . $row[1] . PHP_EOL;
}

echo "\n=== order_items ===\n";
$r = $db->query('DESCRIBE order_items');
while ($row = $r->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . ' | ' . $row[1] . PHP_EOL;
}
