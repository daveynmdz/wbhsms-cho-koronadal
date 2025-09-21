<?php

function loadEnv($envPath) {
    if (!file_exists($envPath)) return;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // ignore comments
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        $_ENV[$name] = $value;
    }
}

// Load environment variables first
if (file_exists(__DIR__ . '/.env.local')) {
    loadEnv(__DIR__ . '/.env.local');
} elseif (file_exists(__DIR__ . '/.env')) {
    loadEnv(__DIR__ . '/.env');
}

// Create PDO connection using environment variables
$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$db   = $_ENV['DB_NAME'] ?? 'wbhsms_cho';
$user = $_ENV['DB_USER'] ?? 'cho-admin';
$pass = $_ENV['DB_PASS'] ?? 'Admin123';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

?>
