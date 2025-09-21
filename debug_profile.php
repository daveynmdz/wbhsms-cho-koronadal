<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

echo "<h2>Profile Debug Information</h2>";

// Check session
echo "<h3>Session Information:</h3>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Patient ID in session: " . ($_SESSION['patient_id'] ?? 'NOT SET') . "</p>";
echo "<p>All session data:</p><pre>";
print_r($_SESSION);
echo "</pre>";

// Check if we have a patient_id
$patient_id = $_SESSION['patient_id'] ?? null;
if (!$patient_id) {
    echo "<p style='color: red;'>No patient_id in session. You need to log in first.</p>";
    echo "<p><a href='../auth/patient_login.php'>Go to Login</a></p>";
    exit();
}

echo "<h3>Database Connection Test:</h3>";

try {
    require_once '../../config/db.php';
    echo "<p>✓ Database config loaded successfully</p>";
    
    // Test PDO connection
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patients");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>✓ Database connection working. Total patients: " . $result['count'] . "</p>";
    
    // Test specific patient lookup
    echo "<h3>Patient Lookup Test:</h3>";
    echo "<p>Looking for patient ID: {$patient_id}</p>";
    
    // Try different possible ID column names
    $possible_queries = [
        "SELECT * FROM patients WHERE id = ?",
        "SELECT * FROM patients WHERE patient_id = ?"
    ];
    
    $patient_row = null;
    $successful_query = "";
    
    foreach ($possible_queries as $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$patient_id]);
            $patient_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient_row) {
                $successful_query = $query;
                break;
            }
        } catch (Exception $e) {
            echo "<p>Query failed: {$query} - {$e->getMessage()}</p>";
        }
    }
    
    if ($patient_row) {
        echo "<p>✓ Patient found using query: {$successful_query}</p>";
        echo "<pre>";
        print_r($patient_row);
        echo "</pre>";
        echo "<p><a href='pages/patient/profile.php'>Try Profile Page Again</a></p>";
    } else {
        echo "<p style='color: red;'>✗ Patient with ID {$patient_id} not found in database</p>";
        
        // Show available patients with any available columns
        try {
            $stmt = $pdo->query("SELECT * FROM patients LIMIT 5");
            $patients = $stmt->fetchAll();
            echo "<p>Available patients (first 5):</p><ul>";
            foreach ($patients as $p) {
                $id = $p['id'] ?? $p['patient_id'] ?? 'unknown';
                $name = ($p['first_name'] ?? $p['fname'] ?? 'Unknown') . ' ' . ($p['last_name'] ?? $p['lname'] ?? 'User');
                echo "<li>ID: {$id} - {$name}</li>";
            }
            echo "</ul>";
        } catch (Exception $e) {
            echo "<p>Error getting patient list: {$e->getMessage()}</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>