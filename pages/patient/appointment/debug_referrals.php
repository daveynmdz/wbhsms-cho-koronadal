<?php
/**
 * Referrals Debug Script
 * Use this to diagnose referral fetching issues
 */

// Set error reporting for testing
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include session and database
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';
require_once $root_path . '/config/db.php';

// Check if user is logged in
if (!isset($_SESSION['patient_id'])) {
    echo "<h2>❌ Debug Error</h2>";
    echo "Patient not logged in. Please log in first.<br>";
    echo "<a href='../auth/login.php'>Go to Login</a>";
    exit;
}

$patient_id = $_SESSION['patient_id'];

echo "<h2>🔍 Referrals Debug Report</h2>";
echo "<p><strong>Patient ID:</strong> " . htmlspecialchars($patient_id) . "</p>";

// Check database connection
echo "<h3>1. Database Connection</h3>";
if (!$conn) {
    echo "❌ Database connection is null<br>";
    exit;
} elseif ($conn->connect_error) {
    echo "❌ Database connection error: " . htmlspecialchars($conn->connect_error) . "<br>";
    exit;
} else {
    echo "✅ Database connection successful<br>";
}

// Check if patient exists
echo "<h3>2. Patient Verification</h3>";
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$stmt->close();

if ($patient) {
    echo "✅ Patient found: " . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "<br>";
    echo "Email: " . htmlspecialchars($patient['email']) . "<br>";
} else {
    echo "❌ Patient not found in database<br>";
    exit;
}

// Check all referrals for this patient
echo "<h3>3. All Referrals for Patient</h3>";
$query = "
    SELECT r.referral_id, r.referral_num, r.referral_reason, r.destination_type,
           r.referred_to_facility_id, r.external_facility_name, r.status, r.service_id,
           r.referral_date, r.created_at,
           f.name as facility_name, f.type as facility_type,
           s.name as service_name, s.description as service_description
    FROM referrals r
    LEFT JOIN facilities f ON r.referred_to_facility_id = f.facility_id
    LEFT JOIN services s ON r.service_id = s.service_id
    WHERE r.patient_id = ?
    ORDER BY r.referral_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$all_referrals = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "<p><strong>Total referrals found:</strong> " . count($all_referrals) . "</p>";

if (count($all_referrals) === 0) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border: 1px solid #ffeaa7;'>";
    echo "<strong>No referrals found for this patient.</strong><br>";
    echo "This could mean:<br>";
    echo "• Patient has never received any referrals<br>";
    echo "• Patient ID might be incorrect<br>";
    echo "• Referrals might be in a different table or database<br>";
    echo "</div>";
} else {
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th>Referral #</th><th>Status</th><th>Reason</th><th>Facility</th><th>Service</th><th>Date</th>";
    echo "</tr>";
    
    foreach ($all_referrals as $ref) {
        $status = $ref['status'] ?? 'NULL';
        $status_color = '';
        if (strtolower(trim($status)) === 'active' || $status === 'NULL') {
            $status_color = 'background: #d4edda; color: #155724;'; // Green for active
        } else {
            $status_color = 'background: #f8d7da; color: #721c24;'; // Red for inactive
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($ref['referral_num']) . "</td>";
        echo "<td style='$status_color'><strong>" . htmlspecialchars($status) . "</strong></td>";
        echo "<td>" . htmlspecialchars($ref['referral_reason']) . "</td>";
        echo "<td>" . htmlspecialchars($ref['facility_name'] ?: $ref['external_facility_name'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($ref['service_name'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($ref['referral_date'] ?: $ref['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check active referrals specifically
echo "<h3>4. Active Referrals Filter</h3>";
$active_referrals = array_filter($all_referrals, function($ref) {
    return !isset($ref['status']) || 
           $ref['status'] === null || 
           strtolower(trim($ref['status'])) === 'active';
});

echo "<p><strong>Active referrals found:</strong> " . count($active_referrals) . "</p>";

if (count($active_referrals) > 0) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "<strong>✅ Active referrals available for appointment booking:</strong><br>";
    foreach ($active_referrals as $ref) {
        echo "• " . htmlspecialchars($ref['referral_num']) . " - " . htmlspecialchars($ref['referral_reason']) . "<br>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "<strong>❌ No active referrals available for appointment booking.</strong><br>";
    if (count($all_referrals) > 0) {
        echo "All referrals have non-active status. Patient may need to request new referrals.";
    } else {
        echo "No referrals exist for this patient.";
    }
    echo "</div>";
}

// Check referrals table structure
echo "<h3>5. Referrals Table Structure</h3>";
$result = $conn->query("DESCRIBE referrals");
if ($result) {
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f8f9fa;'><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Sample data insertion (optional)
echo "<h3>6. Test Data Creation</h3>";
if (isset($_GET['create_test_referral']) && $_GET['create_test_referral'] === '1') {
    try {
        // Create a test referral
        $stmt = $conn->prepare("
            INSERT INTO referrals (patient_id, referral_num, referral_reason, status, service_id, destination_type, referral_date) 
            VALUES (?, ?, 'Test referral for appointment booking', 'active', 1, 'internal', CURDATE())
        ");
        $test_ref_num = 'TEST-' . date('Ymd') . '-' . $patient_id;
        $stmt->bind_param("is", $patient_id, $test_ref_num);
        
        if ($stmt->execute()) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
            echo "✅ Test referral created successfully: " . htmlspecialchars($test_ref_num) . "<br>";
            echo "<a href='debug_referrals.php'>Refresh to see the new referral</a>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
            echo "❌ Failed to create test referral: " . htmlspecialchars($stmt->error);
            echo "</div>";
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
        echo "❌ Error creating test referral: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
} else {
    echo "<p>No active referrals found. You can create a test referral to verify the booking system:</p>";
    echo "<a href='debug_referrals.php?create_test_referral=1' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Create Test Referral</a>";
}

echo "<br><br><h3>7. Next Steps</h3>";
echo "<ul>";
echo "<li>If no referrals exist, the patient needs to get referrals from a healthcare provider</li>";
echo "<li>If referrals exist but are not active, check with the referring doctor</li>";
echo "<li>If you created a test referral, try booking an appointment now</li>";
echo "<li><a href='book_appointment.php'>Go back to appointment booking</a></li>";
echo "</ul>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
table { margin: 10px 0; font-size: 14px; }
th { background: #f8f9fa; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>