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

// Load .env files - .env.local overrides .env for local development
if (!getenv('DB_HOST')) {
    $root_dir = dirname(__DIR__);
    
    // Load production .env first
    if (file_exists($root_dir . '/.env')) {
        loadEnvFile($root_dir . '/.env');
    }
    
    // Then load .env.local to override for local development
    if (file_exists($root_dir . '/.env.local')) {
        loadEnvFile($root_dir . '/.env.local');
    }
}

// Assign environment variables
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_DATABASE') ?: 'wbhsms_database';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

// Handle host:port format in DB_HOST (for compatibility)
if (strpos($host, ':') !== false) {
    list($host, $port_from_host) = explode(':', $host, 2);
    $port = $port_from_host; // Use port from host if specified
}

// Attempt database connection
try {
    // Force TCP/IP connection instead of socket by ensuring host format
    $connection_host = $host;
    if ($host === 'localhost') {
        $connection_host = '127.0.0.1'; // Force TCP/IP for localhost
    }
    
    $dsn = "mysql:host=$connection_host;port=$port;dbname=$db;charset=utf8mb4";
    error_log("Attempting PDO connection with DSN: $dsn, user: $user");
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 30, // 30 second timeout
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
    ]);
    
    // Test the connection with a simple query
    $pdo->query("SELECT 1");
    
    // Log successful connection for debugging (without output)
    error_log("PDO Database connection successful to {$db} on {$connection_host}:{$port}");
} catch (PDOException $e) {
    // Log detailed error information
    error_log("PDO Database connection failed - DSN: $dsn, User: $user, Error: " . $e->getMessage());
    error_log("PDO Error Code: " . $e->getCode() . ", SQL State: " . ($e->errorInfo[0] ?? 'unknown'));
    
    // Set $pdo to null so other parts of the application can handle gracefully
    $pdo = null;
    
    // Store the error message for display if needed
    $db_connection_error = $e->getMessage();
}
