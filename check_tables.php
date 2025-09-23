<?php
require_once 'config/db.php';

// Function to describe a table structure
function describeTable($conn, $tableName) {
    $result = $conn->query("DESCRIBE $tableName");
    if (!$result) {
        echo "Error describing table $tableName: " . $conn->error . "\n";
        return;
    }
    
    echo "Table structure for '$tableName':\n";
    echo "-----------------------------\n";
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} - {$row['Type']}" . 
             ($row['Key'] ? " (Key: {$row['Key']})" : "") . 
             ($row['Default'] ? " Default: {$row['Default']}" : "") . 
             ($row['Extra'] ? " {$row['Extra']}" : "") . 
             "\n";
    }
    echo "\n";
}

// Tables we want to check
$tables = ['patients', 'personal_information', 'barangay', 'emergency_contact'];

foreach ($tables as $table) {
    describeTable($conn, $table);
}
?>