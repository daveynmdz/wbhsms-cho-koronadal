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
    // Log connection attempt for debugging
    error_log("Attempting MySQLi connection to: $host:$port, database: $db, user: $user");
    
    $conn = new mysqli($host, $user, $pass, $db, $port);

    if ($conn->connect_error) {
        error_log("MySQLi connection failed: " . $conn->connect_error);
        throw new Exception('MySQLi connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
    error_log("MySQLi connection successful");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    
    // Set connection to null instead of dying
    $conn = null;
    
    if ($debug) {
        $db_connection_error = 'Database Error: ' . $e->getMessage() . 
            " (Host: $host, Database: $db, User: $user, Port: $port)";
    } else {
        // For production, log the error but provide a user-friendly message
        error_log("Production database connection failed - Host: $host, DB: $db, User: $user, Port: $port");
        $db_connection_error = 'Database service temporarily unavailable. Please try again later.';
    }
}
?>
