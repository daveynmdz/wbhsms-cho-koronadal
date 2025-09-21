<?php
// Test registration without email for debugging
session_start();
require_once '../../../config/env.php';

// Set up a test registration directly (skip email OTP)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate what would be in session after successful OTP validation
    $_SESSION['registration'] = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'middle_name' => '',
        'suffix' => '',
        'barangay_id' => 1,
        'dob' => '1990-01-01',
        'sex' => 'Male',
        'contact_num' => '9123456789',
        'email' => 'test@example.com',
        'password' => password_hash('TestPass123', PASSWORD_DEFAULT),
        'isPWD' => 0,
        'pwd_id_number' => '',
        'isPhilHealth' => 0,
        'philhealth_type' => '',
        'philhealth_id_number' => '',
        'isSenior' => 0,
        'senior_citizen_id' => '',
        'emergency_first_name' => '',
        'emergency_last_name' => '',
        'emergency_relationship' => '',
        'emergency_contact_number' => ''
    ];

    // Simulate OTP validation success - direct insert
    $regData = $_SESSION['registration'];

    try {
        $pdo->beginTransaction();

        // First, insert the patient record without username to get the patient_id
        $sql = "INSERT INTO patients
                (first_name, middle_name, last_name, suffix, barangay_id, date_of_birth, sex, contact_number, email, password_hash, isPWD, pwd_id_number, isPhilHealth, philhealth_type, philhealth_id_number, isSenior, senior_citizen_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $regData['first_name'],
            $regData['middle_name'],
            $regData['last_name'],
            $regData['suffix'],
            $regData['barangay_id'],
            $regData['dob'],
            $regData['sex'],
            $regData['contact_num'],
            $regData['email'],
            $regData['password'],
            $regData['isPWD'],
            $regData['pwd_id_number'],
            $regData['isPhilHealth'],
            $regData['philhealth_type'],
            $regData['philhealth_id_number'],
            $regData['isSenior'],
            $regData['senior_citizen_id']
        ]);

        $patientId = $pdo->lastInsertId();

        // Now generate and update the username based on the patient_id
        $generatedUsername = 'P' . str_pad($patientId, 6, '0', STR_PAD_LEFT);
        $updateSql = "UPDATE patients SET username = ? WHERE patient_id = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$generatedUsername, $patientId]);

        // Check if patient has emergency contact data
        if (!empty($regData['emergency_first_name']) && !empty($regData['emergency_last_name'])) {
            $emergencyContactSql = "INSERT INTO emergency_contact 
                                   (patient_id, emergency_first_name, emergency_last_name, emergency_relationship, emergency_contact_number) 
                                   VALUES (?, ?, ?, ?, ?)";
            $emergencyStmt = $pdo->prepare($emergencyContactSql);
            $emergencyStmt->execute([
                $patientId,
                $regData['emergency_first_name'],
                $regData['emergency_last_name'],
                $regData['emergency_relationship'],
                $regData['emergency_contact_number']
            ]);
        }

        $pdo->commit();

        echo "SUCCESS: Patient registered with ID: $patientId, Username: $generatedUsername";
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "ERROR: " . $e->getMessage();
    }
    
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Registration</title>
</head>
<body>
    <h1>Test Registration (No Email)</h1>
    <form method="POST">
        <button type="submit">Test Direct Registration</button>
    </form>
</body>
</html>