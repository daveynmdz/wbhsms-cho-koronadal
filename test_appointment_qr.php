<?php
/**
 * Simple Appointment QR Test - Isolates the QR generation issue
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Appointment QR Code Test</h2>";

// Include necessary files
$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';

echo "<h3>Testing QR Generation with Real Appointment Data</h3>";

try {
    // Get the latest appointment from the database
    $stmt = $conn->prepare("SELECT * FROM appointments ORDER BY appointment_id DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();
    
    if ($appointment) {
        echo "✅ Found latest appointment: ID " . $appointment['appointment_id'] . "<br>";
        echo "Patient ID: " . $appointment['patient_id'] . "<br>";
        echo "Facility ID: " . $appointment['facility_id'] . "<br>";
        echo "Scheduled Date: " . $appointment['scheduled_date'] . "<br>";
        echo "Scheduled Time: " . $appointment['scheduled_time'] . "<br>";
        
        // Try to include and use the QR generator
        echo "<hr><h4>Loading QR Generator</h4>";
        require_once $root_path . '/utils/appointment_qr_generator.php';
        
        if (class_exists('AppointmentQRGenerator')) {
            echo "✅ AppointmentQRGenerator class loaded<br>";
            
            $qr_generator = new AppointmentQRGenerator();
            echo "✅ QR Generator instance created<br>";
            
            // Get facility type
            $facility_stmt = $conn->prepare("SELECT type FROM facilities WHERE facility_id = ?");
            $facility_stmt->bind_param("i", $appointment['facility_id']);
            $facility_stmt->execute();
            $facility_result = $facility_stmt->get_result();
            $facility_data = $facility_result->fetch_assoc();
            $facility_type = $facility_data['type'] ?? 'unknown';
            $facility_stmt->close();
            
            echo "Facility Type: " . $facility_type . "<br>";
            
            echo "<hr><h4>Generating QR Code</h4>";
            echo "Parameters: appointment_id={$appointment['appointment_id']}, patient_id={$appointment['patient_id']}, referral_id={$appointment['referral_id']}, facility_id={$appointment['facility_id']}, facility_type=$facility_type<br>";
            
            $qr_result = $qr_generator->generateAppointmentQR(
                $appointment['appointment_id'],
                $appointment['patient_id'],
                $appointment['referral_id'],
                $appointment['facility_id'],
                $facility_type
            );
            
            if ($qr_result['success']) {
                echo "<h4 style='color: green;'>✅ QR Generation SUCCESS!</h4>";
                echo "QR Data (JSON): " . htmlspecialchars($qr_result['qr_json']) . "<br>";
                echo "QR File Path: " . $qr_result['qr_filepath'] . "<br>";
                echo "QR File Size: " . $qr_result['file_size'] . " bytes<br>";
                echo "QR URL: " . $qr_result['qr_url'] . "<br>";
                
                if (file_exists($qr_result['qr_filepath'])) {
                    echo "<h4>Generated QR Code:</h4>";
                    $relative_url = str_replace($root_path, '/wbhsms-cho-koronadal', $qr_result['qr_filepath']);
                    echo "<img src='" . $relative_url . "' alt='Generated QR Code' style='border:2px solid #0077b6; border-radius:8px; margin:10px;'><br>";
                    
                    // Update the appointment record with QR path
                    $update_stmt = $conn->prepare("UPDATE appointments SET qr_code_path = ? WHERE appointment_id = ?");
                    $update_stmt->bind_param("si", $qr_result['qr_filepath'], $appointment['appointment_id']);
                    if ($update_stmt->execute()) {
                        echo "✅ Database updated with QR path<br>";
                    } else {
                        echo "❌ Failed to update database: " . $update_stmt->error . "<br>";
                    }
                    $update_stmt->close();
                    
                } else {
                    echo "❌ QR file not found at: " . $qr_result['qr_filepath'] . "<br>";
                }
            } else {
                echo "<h4 style='color: red;'>❌ QR Generation FAILED!</h4>";
                echo "Error: " . $qr_result['message'] . "<br>";
                if (isset($qr_result['error'])) {
                    echo "Details: " . $qr_result['error'] . "<br>";
                }
            }
            
        } else {
            echo "❌ AppointmentQRGenerator class not found<br>";
        }
        
    } else {
        echo "❌ No appointments found in database<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<a href='/wbhsms-cho-koronadal/test_qr_standalone.php'>← QR Library Test</a> | ";
echo "<a href='/wbhsms-cho-koronadal/pages/patient/appointment/book_appointment.php'>← Back to Booking</a>";
?>