<?php
// Test registration system with new database
echo "<h1>Testing Registration System</h1>";

try {
    require_once '../../../config/env.php';
    echo "<p>✅ Database connection successful</p>";
    
    // Test patients table structure
    $stmt = $pdo->prepare("DESCRIBE patients");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Patients Table Structure:</h2><ul>";
    foreach ($fields as $field) {
        echo "<li><strong>{$field['Field']}</strong>: {$field['Type']} " . 
             ($field['Null'] === 'NO' ? '(Required)' : '(Optional)') . "</li>";
    }
    echo "</ul>";
    
    // Test barangay loading
    $stmt = $pdo->prepare("SELECT barangay_id, barangay_name FROM barangay WHERE status = 'active' ORDER BY barangay_name");
    $stmt->execute();
    $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Available Barangays (" . count($barangays) . "):</h2><ul>";
    foreach ($barangays as $brgy) {
        echo "<li>ID: {$brgy['barangay_id']} - {$brgy['barangay_name']}</li>";
    }
    echo "</ul>";
    
    // Check for any existing patients
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patients");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h2>Current Patients: {$result['count']}</h2>";
    
    // Show recent patients if any
    if ($result['count'] > 0) {
        $stmt = $pdo->prepare("SELECT patient_id, username, first_name, last_name, isPWD, isPhilHealth, isSenior, created_at FROM patients ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Recent Registrations:</h3><table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>PWD</th><th>PhilHealth</th><th>Senior</th><th>Created</th></tr>";
        foreach ($patients as $patient) {
            echo "<tr>";
            echo "<td>{$patient['patient_id']}</td>";
            echo "<td>{$patient['username']}</td>";
            echo "<td>{$patient['first_name']} {$patient['last_name']}</td>";
            echo "<td>" . ($patient['isPWD'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($patient['isPhilHealth'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($patient['isSenior'] ? 'Yes' : 'No') . "</td>";
            echo "<td>{$patient['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>Registration URLs:</h2>";
    echo "<p><a href='../patient_registration.php' target='_blank'>🔗 Patient Registration Form</a></p>";
    echo "<p><a href='../../auth/patient_login.php' target='_blank'>🔗 Patient Login</a></p>";
    
    echo "<h2>Form Field Summary:</h2>";
    echo "<ul>";
    echo "<li><strong>Required:</strong> First Name, Last Name, Date of Birth, Sex, Barangay, Contact Number, Email, Password</li>";
    echo "<li><strong>Optional:</strong> Middle Name, Suffix</li>";
    echo "<li><strong>Additional Information:</strong>";
    echo "<ul>";
    echo "<li>PWD Status + ID Number (conditional)</li>";
    echo "<li>PhilHealth Member + Type + ID Number (conditional)</li>";
    echo "<li>Senior Citizen + ID (conditional, must be 60+)</li>";
    echo "</ul></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>