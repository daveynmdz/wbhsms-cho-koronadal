<?php
/**
 * Database Configuration for WBHSMS CHO Koronadal
 * Provides both PDO and MySQLi connections for compatibility
 */

// Enable error reporting based on environment
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '1') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

// Load environment configuration
require_once __DIR__ . '/env.php';

// PDO connection is already available from env.php as $pdo

// MySQLi connection for backward compatibility
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'wbhsms_cho';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$port = $_ENV['DB_PORT'] ?? '3306';

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    if ($conn->connect_error) {
        throw new Exception('MySQLi connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    if ($debug) {
        die('Database Error: ' . $e->getMessage());
    } else {
        die('Database connection failed. Please check your configuration.');
    }
}

?>
