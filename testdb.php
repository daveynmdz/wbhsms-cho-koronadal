<?php
// Set to 'local' or 'remote' to test each connection
$test = 'remote'; // change to 'remote' to test remote server

require_once __DIR__ . '/config/env.php';

if ($test === 'local') {
    if (file_exists(__DIR__ . '/config/.env.local')) {
        loadEnv(__DIR__ . '/config/.env.local');
        echo "Testing LOCAL connection...<br>";
    } else {
        die(".env.local not found");
    }
} else {
    if (file_exists(__DIR__ . '/config/.env')) {
        loadEnv(__DIR__ . '/config/.env');
        echo "Testing REMOTE connection...<br>";
    } else {
        die(".env not found");
    }
}

$host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost';
$port = isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : '3306';
$db   = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '';
$user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : '';
$pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
    echo "Connection successful!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>