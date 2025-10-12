<?php
/**
 * Environment Configuration for WBHSMS CHO Koronadal
 * Coolify-compatible with fallback to .env
 */

// Optional: load from .env if running locally
function loadEnvFile($envPath) {
    if (!file_exists($envPath)) return;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // ignore comments
        if (!strpos($line, '=')) continue; // skip invalid lines
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        putenv("$name=$value");  // Set it for getenv()
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

// Use getenv() to read variables — works in Coolify
echo "<pre>";
echo "DB_HOST: " . getenv('DB_HOST') . PHP_EOL;
echo "DB_PORT: " . getenv('DB_PORT') . PHP_EOL;
echo "DB_DATABASE: " . getenv('DB_DATABASE') . PHP_EOL;
echo "DB_USERNAME: " . getenv('DB_USERNAME') . PHP_EOL;
echo "DB_PASSWORD: " . (getenv('DB_PASSWORD') ? 'SET' : 'NOT SET') . PHP_EOL;
echo "</pre>";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "<h3>❌ Database connection failed.</h3>";
    echo "<strong>Error Message:</strong> " . $e->getMessage() . "<br><br>";
    echo "<strong>Debug Info:</strong><br>";
    echo "Host: $host<br>";
    echo "Port: $port<br>";
    echo "Database: $db<br>";
    echo "Username: $user<br>";
    echo "Password: " . ($pass ? 'SET' : 'NOT SET') . "<br>";
    die();
}
?>
