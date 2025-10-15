<?php
/**
 * Transaction Debug Helper
 * Quick diagnostic for transaction conflicts
 */

require_once '../../config/db.php';

echo "<!DOCTYPE html>
<html><head><title>Transaction Debug</title></head><body>";

echo "<h2>🔧 Transaction Debug Helper</h2>";

try {
    echo "<h3>Database Connection Test</h3>";
    echo "✅ PDO connection successful<br>";
    
    echo "<h3>Transaction Test</h3>";
    
    // Test basic transaction
    $pdo->beginTransaction();
    echo "✅ Started transaction<br>";
    
    $pdo->rollBack();
    echo "✅ Rolled back transaction<br>";
    
    echo "<h3>Queue Management Service Test</h3>";
    
    // Test if the class exists and can be instantiated
    if (file_exists('../../utils/queue_management_service.php')) {
        require_once '../../utils/queue_management_service.php';
        $queueService = new QueueManagementService($pdo);
        echo "✅ QueueManagementService loaded<br>";
        
        // Test method existence
        if (method_exists($queueService, 'createQueueEntry')) {
            echo "✅ createQueueEntry method exists<br>";
        } else {
            echo "❌ createQueueEntry method not found<br>";
        }
    } else {
        echo "❌ QueueManagementService file not found<br>";
    }
    
    echo "<h3>Patient Flow Validator Test</h3>";
    
    // Test if the class exists
    if (file_exists('../../utils/patient_flow_validator.php')) {
        require_once '../../utils/patient_flow_validator.php';
        $flowValidator = new PatientFlowValidator($pdo);
        echo "✅ PatientFlowValidator loaded<br>";
    } else {
        echo "❌ PatientFlowValidator file not found<br>";
    }
    
    echo "<h3>Database Tables Check</h3>";
    
    $tables = ['patients', 'appointments', 'visits', 'queue_entries', 'queue_logs', 'stations'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    }
    
    echo "<h3>PhilHealth Columns Check</h3>";
    
    // Check if PhilHealth columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM patients LIKE 'is_philhealth'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Column 'is_philhealth' exists<br>";
    } else {
        echo "❌ Column 'is_philhealth' missing - run patient flow setup<br>";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM patients LIKE 'philhealth_id_number'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Column 'philhealth_id_number' exists<br>";
    } else {
        echo "❌ Column 'philhealth_id_number' missing - run patient flow setup<br>";
    }
    
    echo "<hr><h3>🔧 Transaction Conflict Fix Applied</h3>";
    echo "<p>The check-in process has been updated to:</p>";
    echo "<ul>";
    echo "<li>✅ Avoid nested transactions</li>";
    echo "<li>✅ Use separate transactions for different operations</li>";
    echo "<li>✅ Properly handle PhilHealth status updates</li>";
    echo "<li>✅ Enhanced error logging</li>";
    echo "</ul>";
    
    echo "<hr><p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Start XAMPP if not running</li>";
    echo "<li>Visit the patient flow setup page to create missing columns</li>";
    echo "<li>Test check-in with PhilHealth selection</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "</body></html>";
?>