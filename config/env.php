<?php

$dsn = "mysql:host=31.97.106.60;port=5432;dbname=default;charset=utf8mb4";
$pdo = new PDO($dsn, 'cho-admin', 'Admin123');

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}
?>
