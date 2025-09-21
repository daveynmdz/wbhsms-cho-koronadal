<?php
// Debug the patient data to see what's happening with barangay
session_start();
require_once 'config/env.php';

$patient_id = $_SESSION['patient_id'] ?? 1; // Use 1 as fallback for testing

try {
    // Try different possible ID column names for patients table
    $possible_queries = [
        "SELECT * FROM patients WHERE id = ?",
        "SELECT * FROM patients WHERE patient_id = ?"
    ];
    
    $patient_row = null;
    foreach ($possible_queries as $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$patient_id]);
            $patient_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient_row) break;
        } catch (Exception $e) {
            continue;
        }
    }
    
    echo "<h2>Patient Data Debug</h2>";
    echo "<h3>Raw Patient Row from Database:</h3>";
    echo "<pre>";
    print_r($patient_row);
    echo "</pre>";
    
    if ($patient_row) {
        echo "<h3>Specific Field Values:</h3>";
        echo "<p>Barangay field value: '" . ($patient_row['barangay'] ?? 'NOT SET') . "'</p>";
        echo "<p>Barangay empty check: " . (empty($patient_row['barangay'] ?? '') ? 'TRUE (empty)' : 'FALSE (not empty)') . "</p>";
        echo "<p>Barangay isset check: " . (isset($patient_row['barangay']) ? 'TRUE (set)' : 'FALSE (not set)') . "</p>";
        
        // Check other fields too
        $check_fields = ['first_name', 'last_name', 'email', 'contact_num', 'contact_number', 'phone', 'contact'];
        foreach ($check_fields as $field) {
            if (isset($patient_row[$field])) {
                echo "<p>{$field}: '" . $patient_row[$field] . "'</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>