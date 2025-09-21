<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Database Connection Test</h2>";

// Test the same connection logic as in your app
require_once 'config/env.php';

try {
    echo "<p>✓ Environment variables loaded successfully</p>";
    echo "<p>DB_HOST: " . ($_ENV['DB_HOST'] ?? 'not set') . "</p>";
    echo "<p>DB_NAME: " . ($_ENV['DB_NAME'] ?? 'not set') . "</p>";
    echo "<p>DB_USER: " . ($_ENV['DB_USER'] ?? 'not set') . "</p>";
    
    // Test PDO connection
    echo "<p>Testing PDO connection...</p>";
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "<p>✓ PDO connection successful</p>";
    
    // Test if patients table exists
    echo "<p>Testing patients table...</p>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'patients'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✓ Patients table exists</p>";
        
        // Count patients
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients");
        $count = $stmt->fetch()['count'];
        echo "<p>✓ Patients table has {$count} records</p>";
    } else {
        echo "<p>✗ Patients table does not exist</p>";
    }
    
    // Test session simulation
    echo "<p>Testing with a sample patient ID...</p>";
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM patients LIMIT 1");
    $sample_patient = $stmt->fetch();
    if ($sample_patient) {
        echo "<p>✓ Sample patient found: ID {$sample_patient['id']} - {$sample_patient['first_name']} {$sample_patient['last_name']}</p>";
    } else {
        echo "<p>✗ No patients found in database</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>Error details: " . $e->getFile() . " on line " . $e->getLine() . "</p>";
}
?>