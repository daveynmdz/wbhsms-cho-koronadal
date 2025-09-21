<?php
session_start();
require_once 'config/env.php';

$patient_id = $_GET['patient_id'] ?? null;

if (!$patient_id) {
    echo "<p>No patient ID provided. <a href='patient_list.php'>Go back</a></p>";
    exit();
}

try {
    // Verify patient exists - try different possible ID column names
    $possible_queries = [
        "SELECT * FROM patients WHERE id = ?",
        "SELECT * FROM patients WHERE patient_id = ?"
    ];
    
    $patient = null;
    foreach ($possible_queries as $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch();
            if ($patient) break;
        } catch (Exception $e) {
            continue;
        }
    }
    
    if (!$patient) {
        echo "<p>Patient not found. <a href='patient_list.php'>Go back</a></p>";
        exit();
    }
    
    // Set session for testing
    $_SESSION['patient_id'] = $patient_id;
    
    // Try to get name from different possible column combinations
    $first_name = $patient['first_name'] ?? $patient['fname'] ?? 'Unknown';
    $last_name = $patient['last_name'] ?? $patient['lname'] ?? 'User';
    $email = $patient['email'] ?? $patient['email_address'] ?? 'No email';
    
    $_SESSION['patient_name'] = $first_name . ' ' . $last_name;
    $_SESSION['patient_email'] = $email;
    
    echo "<h2>Test Login Successful!</h2>";
    echo "<p>Logged in as: {$first_name} {$last_name}</p>";
    echo "<p>Patient ID: {$patient_id}</p>";
    echo "<p>Email: {$email}</p>";
    echo "<p><a href='pages/patient/profile.php'>View Profile</a></p>";
    echo "<p><a href='pages/dashboard/dashboard_patient.php'>Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>