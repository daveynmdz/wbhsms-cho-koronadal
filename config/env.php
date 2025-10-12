<?php
/**
 * Environment Configuration for WBHSMS CHO Koronadal
 * XAMPP-optimized database connection
 */

function loadEnv($envPath) {
    if (!file_exists($envPath)) return;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // ignore comments
        if (!strpos($line, '=')) continue; // skip invalid lines
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        $_ENV[$name] = $value;
    }
}

// Load environment variables - prioritize root .env for simplicity
$root_dir = dirname(__DIR__);
if (file_exists($root_dir . '/.env')) {
    loadEnv($root_dir . '/.env');
} elseif (file_exists(__DIR__ . '/.env.local')) {
    loadEnv(__DIR__ . '/.env.local');
}

// Database configuration with XAMPP-friendly defaults
$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$db   = $_ENV['DB_NAME'] ?? 'wbhsms_database';
$user = $_ENV['DB_USERNAME'] ?? 'root';  // XAMPP default
$pass = $_ENV['DB_PASSWORD'] ?? '';      // XAMPP default (no password)

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Provide helpful error message for XAMPP users
    $error_msg = "Database connection failed. ";
    if ($user === 'root' && empty($pass)) {
        $error_msg .= "Make sure XAMPP MySQL is running and database '{$db}' exists. ";
    }
    $error_msg .= "Error: " . $e->getMessage();
    die($error_msg);
}

?>
