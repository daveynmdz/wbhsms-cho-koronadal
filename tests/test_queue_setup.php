<?php
require_once 'config/db.php';
echo 'Testing database connection and queue tables...' . PHP_EOL;

try {
    // Test stations
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM stations WHERE is_active = 1');
    $result = $stmt->fetch();
    echo 'Active stations: ' . $result['count'] . PHP_EOL;
    
    // List station types
    $stmt = $pdo->query('SELECT station_type, COUNT(*) as count FROM stations WHERE is_active = 1 GROUP BY station_type');
    while ($row = $stmt->fetch()) {
        echo '  ' . $row['station_type'] . ': ' . $row['count'] . PHP_EOL;
    }
    
    // Test employees  
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM employees');
    $result = $stmt->fetch();
    echo 'Total employees: ' . $result['count'] . PHP_EOL;
    
    echo 'Database connection successful!' . PHP_EOL;
} catch (Exception $e) {
    echo 'Database error: ' . $e->getMessage() . PHP_EOL;
}