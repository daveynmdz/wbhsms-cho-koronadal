<?php
/**
 * Environment Configuration for WBHSMS CHO Koronadal
 * Coolify-compatible with fallback to .env
 */

// Load from .env if running locally
function loadEnvFile($envPath) {
    if (!file_exists($envPath)) return;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        putenv("$name=$value");
    }
}

// Load .env only if not running in Docker
if (!getenv('DB_HOST')) {
    $root_dir = dirname(__DIR__);
    if (file_exists($root_dir . '/.env')) {
        loadEnvFile($root_dir . '/.env');
    } elseif (file_exists(__DIR__ . '/.env.local')) {
        loadEnvFile(__DIR__ . '/.env.local');
    }
}

// Assign environment variables
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_DATABASE') ?: 'wbhsms_database';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

// Attempt database connection
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Log successful connection for debugging (without output)
    error_log("Database connection successful to {$db} on {$host}:{$port}");
} catch (PDOException $e) {
    // Log error instead of outputting it to prevent headers issues
    error_log("Database connection failed: " . $e->getMessage());
    
    // In production/deployment, you might want to show a generic error
    // For now, we'll exit with the error to help with debugging
    exit("Database connection failed. Please check your configuration.");
}
