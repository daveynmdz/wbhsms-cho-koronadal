<?php
// Quick check of assignment_schedules table structure
require_once __DIR__ . '/config/db.php';

try {
    echo "=== ASSIGNMENT_SCHEDULES TABLE COLUMNS ===<br>";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM assignment_schedules");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<strong>Available columns:</strong><br>";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})<br>";
    }
    
    echo "<br>=== SAMPLE DATA (first 3 rows) ===<br>";
    $stmt = $pdo->query("SELECT * FROM assignment_schedules LIMIT 3");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($data) > 0) {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    } else {
        echo "No data found<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>