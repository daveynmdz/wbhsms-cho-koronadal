<?php
// At the VERY TOP of your PHP file (before session_start or other code)
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL); // log everything, just don't display in prod

require_once __DIR__ . '/env.php';

// mysqli connection for backward compatibility (some files might still use it)
$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$port = $_ENV['DB_PORT'] ?? '3306';

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// PDO connection is already available from env.php as $pdo
