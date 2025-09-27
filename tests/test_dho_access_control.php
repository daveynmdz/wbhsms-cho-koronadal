<?php
/**
 * DHO Access Control Test
 * Tests that DHO users can only access patients from their assigned district
 */

require_once '../config/db.php';

// Helper function to simulate DHO session
function simulateDHOSession($employee_id) {
    $_SESSION['employee_id'] = $employee_id;
    $_SESSION['role'] = 'DHO';
}

// Helper function to test district access
function testDistrictAccess($employee_id, $patient_id) {
    global $pdo;
    
    // Simulate the same query used in patient_records_management.php
    $district_check = $pdo->prepare("
        SELECT COUNT(*) as can_access 
        FROM patients p
        JOIN barangay b ON p.barangay_id = b.barangay_id
        JOIN facilities f ON b.district_id = f.district_id
        JOIN employees e ON e.facility_id = f.facility_id
        WHERE e.employee_id = ? AND (p.id = ? OR p.patient_id = ?)
    ");
    
    $district_check->execute([$employee_id, $patient_id, $patient_id]);
    $result = $district_check->fetch(PDO::FETCH_ASSOC);
    
    return $result['can_access'] > 0;
}

// Test data validation
function validateTestData() {
    global $pdo;
    
    // Check if we have test data for DHO employees
    $dho_count = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE role = 'DHO'")->fetch()['count'];
    
    // Check if we have patients in different districts
    $district_count = $pdo->query("
        SELECT COUNT(DISTINCT f.district_id) as count 
        FROM facilities f
        JOIN barangay b ON f.district_id = b.district_id
        JOIN patients p ON b.barangay_id = p.barangay_id
    ")->fetch()['count'];
    
    return [
        'dho_employees' => $dho_count,
        'districts_with_patients' => $district_count
    ];
}

// Main test execution
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DHO Access Control Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-result { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .test-header { background: #007bff; color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-header">
            <h1>DHO Access Control Test</h1>
            <p>Testing district-based patient access restrictions for District Health Officers</p>
        </div>

        <?php
        try {
            // Validate test data
            $validation = validateTestData();
            
            echo "<div class='test-result info'>";
            echo "<h3>Test Environment Validation</h3>";
            echo "<ul>";
            echo "<li>DHO Employees in database: {$validation['dho_employees']}</li>";
            echo "<li>Districts with patients: {$validation['districts_with_patients']}</li>";
            echo "</ul>";
            echo "</div>";
            
            if ($validation['dho_employees'] == 0) {
                echo "<div class='test-result warning'>";
                echo "<h3>Warning: No DHO Test Data</h3>";
                echo "<p>No employees with role 'DHO' found in the database. Test results may be limited.</p>";
                echo "</div>";
            }
            
            // Get sample DHO employees
            $dho_employees = $pdo->query("
                SELECT e.employee_id, e.first_name, e.last_name, f.facility_name, f.district_id
                FROM employees e
                JOIN facilities f ON e.facility_id = f.facility_id
                WHERE e.role = 'DHO'
                LIMIT 3
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Get sample patients from different districts
            $patients_query = "
                SELECT p.id as patient_id, p.first_name, p.last_name, 
                       b.barangay_name, f.district_id, f.facility_name
                FROM patients p
                JOIN barangay b ON p.barangay_id = b.barangay_id
                JOIN facilities f ON b.district_id = f.district_id
                ORDER BY f.district_id, p.id
                LIMIT 10
            ";
            $patients = $pdo->query($patients_query)->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<div class='test-result info'>";
            echo "<h3>Sample Test Data</h3>";
            
            if (!empty($dho_employees)) {
                echo "<h4>DHO Employees:</h4>";
                echo "<table>";
                echo "<tr><th>Employee ID</th><th>Name</th><th>Facility</th><th>District ID</th></tr>";
                foreach ($dho_employees as $dho) {
                    echo "<tr>";
                    echo "<td>{$dho['employee_id']}</td>";
                    echo "<td>{$dho['first_name']} {$dho['last_name']}</td>";
                    echo "<td>{$dho['facility_name']}</td>";
                    echo "<td>{$dho['district_id']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            
            if (!empty($patients)) {
                echo "<h4>Sample Patients:</h4>";
                echo "<table>";
                echo "<tr><th>Patient ID</th><th>Name</th><th>Barangay</th><th>District ID</th></tr>";
                foreach (array_slice($patients, 0, 5) as $patient) {
                    echo "<tr>";
                    echo "<td>{$patient['patient_id']}</td>";
                    echo "<td>{$patient['first_name']} {$patient['last_name']}</td>";
                    echo "<td>{$patient['barangay_name']}</td>";
                    echo "<td>{$patient['district_id']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            echo "</div>";
            
            // Perform access control tests
            echo "<div class='test-result info'>";
            echo "<h3>Access Control Test Results</h3>";
            
            if (!empty($dho_employees) && !empty($patients)) {
                echo "<table>";
                echo "<tr><th>DHO Employee</th><th>DHO District</th><th>Patient</th><th>Patient District</th><th>Access Allowed</th><th>Result</th></tr>";
                
                $test_count = 0;
                $success_count = 0;
                
                // Test each DHO against each patient
                foreach ($dho_employees as $dho) {
                    foreach (array_slice($patients, 0, 3) as $patient) {
                        $has_access = testDistrictAccess($dho['employee_id'], $patient['patient_id']);
                        $should_have_access = ($dho['district_id'] == $patient['district_id']);
                        $test_passed = ($has_access == $should_have_access);
                        
                        $test_count++;
                        if ($test_passed) $success_count++;
                        
                        echo "<tr>";
                        echo "<td>{$dho['first_name']} {$dho['last_name']} (#{$dho['employee_id']})</td>";
                        echo "<td>{$dho['district_id']}</td>";
                        echo "<td>{$patient['first_name']} {$patient['last_name']} (#{$patient['patient_id']})</td>";
                        echo "<td>{$patient['district_id']}</td>";
                        echo "<td>" . ($has_access ? 'YES' : 'NO') . "</td>";
                        echo "<td style='color:" . ($test_passed ? 'green' : 'red') . "'>";
                        echo $test_passed ? '✓ PASS' : '✗ FAIL';
                        echo "</td>";
                        echo "</tr>";
                    }
                }
                echo "</table>";
                
                // Summary
                $pass_rate = round(($success_count / $test_count) * 100, 1);
                echo "<div class='test-result " . ($pass_rate == 100 ? 'success' : 'warning') . "'>";
                echo "<h4>Test Summary</h4>";
                echo "<p>Tests Passed: $success_count / $test_count ($pass_rate%)</p>";
                
                if ($pass_rate == 100) {
                    echo "<p><strong>✓ All access control tests passed!</strong> DHO district restrictions are working correctly.</p>";
                } else {
                    echo "<p><strong>⚠ Some tests failed.</strong> District access control may need adjustment.</p>";
                }
                echo "</div>";
                
            } else {
                echo "<div class='test-result warning'>";
                echo "<p>Insufficient test data to perform comprehensive access control tests.</p>";
                echo "</div>";
            }
            echo "</div>";
            
            // Test the actual DHO patient records query
            echo "<div class='test-result info'>";
            echo "<h3>DHO Patient Records Query Test</h3>";
            
            if (!empty($dho_employees)) {
                $test_dho = $dho_employees[0];
                $query = "
                    SELECT p.id, p.first_name, p.last_name, p.contact_number,
                           b.barangay_name, f.district_id
                    FROM patients p
                    JOIN barangay b ON p.barangay_id = b.barangay_id
                    JOIN facilities f ON b.district_id = f.district_id
                    JOIN employees e ON e.facility_id = f.facility_id
                    WHERE e.employee_id = ?
                    ORDER BY p.last_name, p.first_name
                    LIMIT 5
                ";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([$test_dho['employee_id']]);
                $dho_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<h4>Patients accessible to DHO: {$test_dho['first_name']} {$test_dho['last_name']} (District {$test_dho['district_id']})</h4>";
                
                if (!empty($dho_patients)) {
                    echo "<table>";
                    echo "<tr><th>Patient ID</th><th>Name</th><th>Contact</th><th>Barangay</th><th>District</th></tr>";
                    foreach ($dho_patients as $patient) {
                        echo "<tr>";
                        echo "<td>{$patient['id']}</td>";
                        echo "<td>{$patient['first_name']} {$patient['last_name']}</td>";
                        echo "<td>{$patient['contact_number']}</td>";
                        echo "<td>{$patient['barangay_name']}</td>";
                        echo "<td>{$patient['district_id']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    echo "<div class='test-result success'>";
                    echo "<p>✓ DHO patient records query is working correctly. Found " . count($dho_patients) . " accessible patients.</p>";
                    echo "</div>";
                } else {
                    echo "<div class='test-result warning'>";
                    echo "<p>No patients found for this DHO. This could indicate:</p>";
                    echo "<ul>";
                    echo "<li>No patients in the DHO's assigned district</li>";
                    echo "<li>District relationships not properly configured</li>";
                    echo "<li>Employee facility assignment issues</li>";
                    echo "</ul>";
                    echo "</div>";
                }
            }
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='test-result error'>";
            echo "<h3>Test Error</h3>";
            echo "<p>An error occurred during testing: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
        ?>

        <div class="test-result info">
            <h3>Manual Testing Instructions</h3>
            <ol>
                <li>Log in as a DHO user to the system</li>
                <li>Navigate to the DHO Patient Records Management page</li>
                <li>Verify that only patients from your assigned district are visible</li>
                <li>Try accessing a patient profile by clicking "View Profile"</li>
                <li>Verify the DHO sidebar is displayed and "Patient Records" is highlighted</li>
                <li>Verify the "Back to Patient Records" button works in the profile view</li>
                <li>Test search and filter functionality</li>
            </ol>
        </div>
        
        <div class="test-result info">
            <h3>Test Links</h3>
            <p><a href="../pages/management/dho/patient_records_management.php" target="_blank">→ DHO Patient Records Management</a></p>
            <p><a href="../pages/management/auth/employee_login.php" target="_blank">→ Employee Login</a></p>
        </div>
    </div>
</body>
</html>