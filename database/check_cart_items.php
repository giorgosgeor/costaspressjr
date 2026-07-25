<?php
require __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');

$db = require __DIR__ . '/../app/config/database.php';
$stmt = $db->query('DESCRIBE cart_items');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . ' | ' . $row['Default'] . "\n";
}
