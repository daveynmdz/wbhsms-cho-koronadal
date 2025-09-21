<?php
require_once 'config/env.php';

try {
    // First, let's see what columns exist in the patients table
    $stmt = $pdo->query("DESCRIBE patients");
    $columns = $stmt->fetchAll();
    
    echo "<h3>Patients Table Structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
    }
    echo "</table>";
    
    // Now try to get patients with available columns
    // Let's try different common column name patterns
    $possible_queries = [
        "SELECT patient_id, first_name, last_name, email FROM patients LIMIT 5",
        "SELECT id, first_name, last_name, email FROM patients LIMIT 5", 
        "SELECT patient_id, fname, lname, email FROM patients LIMIT 5",
        "SELECT * FROM patients LIMIT 5"
    ];
    
    $patients = [];
    $successful_query = "";
    
    foreach ($possible_queries as $query) {
        try {
            $stmt = $pdo->query($query);
            $patients = $stmt->fetchAll();
            $successful_query = $query;
            break;
        } catch (Exception $e) {
            // Continue to next query
            continue;
        }
    }
    
    if (empty($successful_query)) {
        throw new Exception("Could not determine the correct column structure for patients table");
    }
    
    echo "<p><strong>Successful query:</strong> {$successful_query}</p>";
    
    echo "<h2>Available Patients for Testing</h2>";
    
    if (empty($patients)) {
        echo "<p>No patients found in database. You may need to register a patient first.</p>";
        echo "<p><a href='pages/registration/patient_registration.php'>Register New Patient</a></p>";
    } else {
        echo "<p>Here are some patients you can test with:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        
        // Get the first patient to determine available columns
        $first_patient = $patients[0];
        $columns = array_keys($first_patient);
        
        // Display table header
        echo "<tr>";
        foreach ($columns as $col) {
            echo "<th>" . ucfirst(str_replace('_', ' ', $col)) . "</th>";
        }
        echo "<th>Actions</th></tr>";
        
        // Display patient data
        foreach ($patients as $patient) {
            echo "<tr>";
            foreach ($columns as $col) {
                echo "<td>" . htmlspecialchars($patient[$col] ?? '') . "</td>";
            }
            
            // For the test login link, try to find the ID column
            $patient_id = $patient['id'] ?? $patient['patient_id'] ?? $patient[array_keys($patient)[0]];
            echo "<td><a href='test_login.php?patient_id={$patient_id}'>Test Login</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<br><p><a href='pages/auth/patient_login.php'>Go to Patient Login</a></p>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>