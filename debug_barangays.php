<?php
require_once 'config/env.php';

try {
    echo "<h2>Database Structure Analysis</h2>";
    
    // Check barangay table structure
    echo "<h3>Barangay Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE barangay");
    $barangay_columns = $stmt->fetchAll();
    echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($barangay_columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
    }
    echo "</table>";
    
    // Show sample barangay data
    echo "<h3>Sample Barangay Data:</h3>";
    $stmt = $pdo->query("SELECT * FROM barangay LIMIT 10");
    $barangays = $stmt->fetchAll();
    if (!empty($barangays)) {
        echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
        $first_row = $barangays[0];
        echo "<tr>";
        foreach (array_keys($first_row) as $header) {
            echo "<th>{$header}</th>";
        }
        echo "</tr>";
        foreach ($barangays as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check patients table for barangay-related columns
    echo "<h3>Patients Table Columns (barangay-related):</h3>";
    $stmt = $pdo->query("DESCRIBE patients");
    $patient_columns = $stmt->fetchAll();
    $barangay_related = [];
    foreach ($patient_columns as $col) {
        if (stripos($col['Field'], 'barangay') !== false) {
            $barangay_related[] = $col;
        }
    }
    
    if (!empty($barangay_related)) {
        echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        foreach ($barangay_related as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No barangay-related columns found in patients table.</p>";
    }
    
    // Test join query
    echo "<h3>Test Join Query:</h3>";
    $possible_join_queries = [
        "SELECT p.id, p.first_name, p.last_name, p.barangay_id, b.barangay_name as barangay FROM patients p LEFT JOIN barangay b ON p.barangay_id = b.barangay_id LIMIT 3",
        "SELECT p.patient_id, p.first_name, p.last_name, p.barangay_id, b.barangay_name as barangay FROM patients p LEFT JOIN barangay b ON p.barangay_id = b.barangay_id LIMIT 3"
    ];
    
    foreach ($possible_join_queries as $i => $query) {
        echo "<h4>Query " . ($i + 1) . ":</h4>";
        echo "<code>{$query}</code><br>";
        try {
            $stmt = $pdo->query($query);
            $results = $stmt->fetchAll();
            if (!empty($results)) {
                echo "<p>✅ <strong>SUCCESS!</strong> This query works.</p>";
                echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
                $first_row = $results[0];
                echo "<tr>";
                foreach (array_keys($first_row) as $header) {
                    echo "<th>{$header}</th>";
                }
                echo "</tr>";
                foreach ($results as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
                break; // Use the first working query
            }
        } catch (Exception $e) {
            echo "<p>❌ <strong>FAILED:</strong> " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>