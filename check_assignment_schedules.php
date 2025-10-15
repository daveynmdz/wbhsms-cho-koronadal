<?php
// Check assignment_schedules table structure
require_once __DIR__ . '/config/db.php';

try {
    echo "=== ASSIGNMENT SCHEDULES TABLE STRUCTURE ===<br>";
    
    $stmt = $pdo->query("DESCRIBE assignment_schedules");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br>=== SAMPLE DATA ===<br>";
    $stmt = $pdo->query("SELECT * FROM assignment_schedules LIMIT 5");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($data) > 0) {
        echo "<table border='1'>";
        echo "<tr>";
        foreach (array_keys($data[0]) as $key) {
            echo "<th>$key</th>";
        }
        echo "</tr>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No data found in assignment_schedules table<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>