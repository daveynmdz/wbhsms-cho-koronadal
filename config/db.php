<?php
/**
 * Database Configuration for WBHSMS CHO Koronadal
 * Provides both PDO and MySQLi connections for compatibility
 */

// Enable error reporting based on environment
$debug = getenv('APP_DEBUG') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

// Load environment-based PDO connection
require_once __DIR__ . '/env.php'; // This provides $pdo

// MySQLi connection (for legacy use cases)
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_DATABASE') ?: 'wbhsms_database';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '3306';

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
