<?php
/**
 * Setup Script for Station Management
 * This script creates the necessary database tables for staff station assignments
 */

require_once __DIR__ . '/config/db.php';

try {
    echo "Setting up Station Management tables...\n";
    
    // Read and execute the SQL file
    $sql_file = dirname(__DIR__) . '/database/station_management.sql';
    
    if (!file_exists($sql_file)) {
        die("Error: station_management.sql file not found!\n");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Split SQL into individual statements
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            if (!$conn->query($statement)) {
                echo "Warning: " . $conn->error . "\n";
            }
        }
    }
    
    echo "Station Management setup completed successfully!\n";
    echo "Tables created:\n";
    echo "- stations\n";
    echo "- station_assignments\n";
    echo "\nSample stations have been inserted.\n";
    
} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage() . "\n");
}
?>