<?php
// app/config/database.php

$host = Env::get('DB_HOST', '127.0.0.1');
$dbname = Env::required('DB_NAME');
$user = Env::required('DB_USER');
$pass = Env::get('DB_PASS', '');
$charset = Env::get('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    return new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Service temporarily unavailable.');
}
