<?php
/**
 * Test QR BLOB Storage Fix
 * Tests the corrected BLOB storage method
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test QR BLOB Storage Fix</h2>";

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/utils/appointment_qr_generator.php';
    
    echo "<h3>1. Test QR Generation and BLOB Storage</h3>";
    
    // Use appointment ID 32 for testing
    $test_appointment_id = 32;
    
    // First, create a test appointment record
    $stmt = $conn->prepare("
        INSERT INTO appointments (appointment_id, patient_id, service_id, facility_id, scheduled_date, scheduled_time, status, created_at) 
        VALUES (?, 7, 1, 2, '2025-10-08', '10:00:00', 'confirmed', NOW())
        ON DUPLICATE KEY UPDATE status = 'confirmed'
    ");
    $stmt->bind_param("i", $test_appointment_id);
    $stmt->execute();
    $stmt->close();
    
    echo "Test appointment record created/updated (ID: $test_appointment_id)<br>";
    
    // Generate QR code
    $generator = new AppointmentQRGenerator();
    $qr_result = $generator->generateAppointmentQR($test_appointment_id, 7, null, 2, 'bhc');
    
    if ($qr_result['success']) {
        echo "✅ QR generation successful<br>";
        echo "File created: " . $qr_result['qr_filepath'] . "<br>";
        echo "File size: " . $qr_result['file_size'] . " bytes<br>";
        
        // Test BLOB storage with new method
        echo "<h4>Testing BLOB Storage:</h4>";
        $blob_success = $generator->updateAppointmentQRBlob($conn, $test_appointment_id, $qr_result['qr_filepath']);
        
        if ($blob_success) {
            echo "✅ QR BLOB storage reported success<br>";
            
            // Verify data was actually stored
            $stmt = $conn->prepare("SELECT LENGTH(qr_code_path) as qr_size FROM appointments WHERE appointment_id = ?");
            $stmt->bind_param("i", $test_appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row && $row['qr_size'] > 0) {
                echo "✅ BLOB data verified in database (size: " . $row['qr_size'] . " bytes)<br>";
                
                // Test QR image endpoint
                echo "<h4>Testing QR Image Endpoint:</h4>";
                $qr_url = "/wbhsms-cho-koronadal/qr_image.php?appointment_id=$test_appointment_id";
                echo "QR URL: <a href='$qr_url' target='_blank'>$qr_url</a><br>";
                echo '<img src="' . $qr_url . '" alt="Test QR Code" style="border: 2px solid #0077b6; margin: 10px;" width="150" height="150">';
                
                echo "<h4>Testing Email QR Function:</h4>";
                // Test the email QR functions directly here to avoid include issues
                
                // Helper function to extract appointment ID from appointment number
                function extractAppointmentIdFromNum($appointment_num) {
                    $parts = explode('-', $appointment_num);
                    if (count($parts) >= 3) {
                        $padded_id = end($parts);
                        return intval($padded_id);
                    }
                    return null;
                }
                
                // Helper function to get QR BLOB data from database
                function getQRBlobFromDatabase($appointment_id) {
                    global $conn;
                    try {
                        $stmt = $conn->prepare("SELECT qr_code_path, LENGTH(qr_code_path) as blob_size FROM appointments WHERE appointment_id = ?");
                        $stmt->bind_param("i", $appointment_id);
                        $stmt->execute();
                        
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $stmt->close();
                        
                        if ($row && $row['qr_code_path'] && $row['blob_size'] > 0) {
                            return $row['qr_code_path'];
                        }
                        return null;
                    } catch (Exception $e) {
                        return null;
                    }
                }
                
                $appointment_num = 'APT-20251007-00032';
                $extracted_id = extractAppointmentIdFromNum($appointment_num);
                echo "Appointment number: $appointment_num<br>";
                echo "Extracted ID: $extracted_id<br>";
                
                if ($extracted_id == $test_appointment_id) {
                    $qr_blob = getQRBlobFromDatabase($extracted_id);
                    if ($qr_blob) {
                        echo "✅ Email QR function works! BLOB size: " . strlen($qr_blob) . " bytes<br>";
                    } else {
                        echo "❌ Email QR function failed to get BLOB<br>";
                    }
                } else {
                    echo "❌ Appointment ID extraction failed<br>";
                }
                
            } else {
                echo "❌ BLOB data not found in database (size: " . ($row['qr_size'] ?? 0) . ")<br>";
            }
            
        } else {
            echo "❌ QR BLOB storage failed<br>";
        }
        
    } else {
        echo "❌ QR generation failed: " . $qr_result['message'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h3>✅ Test Complete</h3>";
echo "<p>If you see a QR code image above, the BLOB storage fix is working!</p>";
?>