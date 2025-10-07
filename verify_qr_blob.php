<?php
/**
 * QR BLOB Database Verification Script
 * Checks if QR BLOB data is properly stored and retrievable
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>QR BLOB Database Verification</h2>";

try {
    require_once __DIR__ . '/config/db.php';
    
    echo "<h3>1. Recent Appointments with QR Data</h3>";
    
    // Check recent appointments
    $stmt = $conn->prepare("
        SELECT appointment_id, 
               LENGTH(qr_code_path) as qr_blob_size,
               CASE 
                   WHEN qr_code_path IS NULL THEN 'NULL'
                   WHEN LENGTH(qr_code_path) = 0 THEN 'EMPTY'
                   ELSE 'HAS_DATA'
               END as qr_status,
               created_at
        FROM appointments 
        ORDER BY appointment_id DESC 
        LIMIT 10
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr><th>Appointment ID</th><th>QR Status</th><th>BLOB Size (bytes)</th><th>Created</th><th>Actions</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $status_color = $row['qr_status'] == 'HAS_DATA' ? '#28a745' : '#dc3545';
        echo "<tr>";
        echo "<td><strong>" . $row['appointment_id'] . "</strong></td>";
        echo "<td style='color: $status_color; font-weight: bold;'>" . $row['qr_status'] . "</td>";
        echo "<td>" . ($row['qr_blob_size'] ?? 0) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        
        if ($row['qr_status'] == 'HAS_DATA') {
            $qr_url = "/wbhsms-cho-koronadal/qr_image.php?appointment_id=" . $row['appointment_id'];
            echo "<td><a href='$qr_url' target='_blank'>View QR</a></td>";
        } else {
            echo "<td>-</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    $stmt->close();
    
    echo "<h3>2. Test QR BLOB Retrieval Function</h3>";
    
    // Test the helper functions from submit_appointment.php
    include_once __DIR__ . '/pages/patient/appointment/submit_appointment.php';
    
    // Test with appointment number format
    $test_appointment_num = 'APT-20251007-00031';
    echo "Testing with appointment number: <strong>$test_appointment_num</strong><br>";
    
    // This should use the functions from submit_appointment.php
    $extracted_id = extractAppointmentIdFromNum($test_appointment_num);
    echo "Extracted appointment ID: <strong>" . ($extracted_id ?? 'NULL') . "</strong><br>";
    
    if ($extracted_id) {
        $qr_blob = getQRBlobFromDatabase($extracted_id);
        if ($qr_blob) {
            echo "✅ QR BLOB data found! Size: <strong>" . strlen($qr_blob) . " bytes</strong><br>";
            
            // Test if it's valid PNG
            if (substr($qr_blob, 0, 8) === "\x89PNG\r\n\x1a\n") {
                echo "✅ Valid PNG header detected<br>";
            } else {
                echo "❌ Invalid PNG header<br>";
            }
        } else {
            echo "❌ No QR BLOB data found<br>";
        }
    }
    
    echo "<h3>3. Database Schema Check</h3>";
    
    // Verify qr_code_path column type
    $result = $conn->query("DESCRIBE appointments");
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'qr_code_path') {
            echo "qr_code_path column type: <strong>" . $row['Type'] . "</strong><br>";
            echo "Nullable: <strong>" . $row['Null'] . "</strong><br>";
            break;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>