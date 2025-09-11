<?php
require_once __DIR__ . '/env.php'; // This loads your .env variables
loadEnv(__DIR__ . '/.env');        // This reads your .env file

$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected successfully!<br>";

    // Test a query: get first patient name, if available
    $stmt = $pdo->query('SELECT * FROM patients LIMIT 1');
    $row = $stmt->fetch();
    if ($row) {
        echo "First patient record found: ";
        print_r($row);
    } else {
        echo "No patient records found.";
    }

} catch (\PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>