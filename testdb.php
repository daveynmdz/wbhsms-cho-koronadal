<?php
$dsn = "mysql:host=31.97.106.60;port=5432;dbname=default;charset=utf8mb4";
$user = "cho-admin";
$pass = "Admin123";

try {
    $pdo = new PDO($dsn, $user, $pass);
    echo "Connection successful!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>