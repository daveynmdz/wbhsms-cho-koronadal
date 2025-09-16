<?php
// env.php

$dsn = "mysql:host=31.97.106.60;port=5432;dbname=default;charset=utf8mb4";
$pdo = new PDO($dsn, 'cho-admin', 'Admin123');

function loadEnv($envPath) {
    if (!file_exists($envPath)) return;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // ignore comments
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        $_ENV[$name] = $value;
        putenv("$name=$value"); // 👈 add this line
    }
}

require_once __DIR__ . '/config.php';
if (file_exists(__DIR__ . '/.env.local')) {
    loadEnv(__DIR__ . '/.env.local');
} elseif (file_exists(__DIR__ . '/.env')) {
    loadEnv(__DIR__ . '/.env');
}