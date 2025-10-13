<?php
/**
 * QR Code Functionality Verification Test
 * Tests QR code generation and retrieval after queue refactoring
 */

require_once '../config/db.php';
require_once '../utils/qr_code_generator.php';

echo "=== QR Code Functionality Verification ===\n\n";

try {
    // Test 1: Check recent appointments have QR codes
    echo "1. Checking recent appointments for QR codes...\n";
    $stmt = $pdo->prepare("
        SELECT appointment_id, patient_id, qr_code_path, status, created_at
        FROM appointments 
        WHERE facility_id = 1 
        ORDER BY appointment_id DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($appointments as $apt) {
        $has_qr = !empty($apt['qr_code_path']);
        
        echo "   Appointment #{$apt['appointment_id']}: ";
        echo "QR Code: " . ($has_qr ? "✓" : "✗") . " | ";
        echo "Status: {$apt['status']} | ";
        echo "Date: {$apt['created_at']}\n";
    }
    
    // Test 2: Verify QR code data (stored as base64 in database)
    echo "\n2. Checking QR code data in database...\n";
    foreach ($appointments as $apt) {
        if (!empty($apt['qr_code_path'])) {
            $qr_length = strlen($apt['qr_code_path']);
            $is_base64 = base64_encode(base64_decode($apt['qr_code_path'])) === $apt['qr_code_path'];
            echo "   Appointment #{$apt['appointment_id']}: ";
            echo "Data length: {$qr_length} bytes | ";
            echo "Format: " . ($is_base64 ? "Base64 ✓" : "Other") . "\n";
        }
    }
    
    // Test 3: Check QR verification codes format (check if stored separately)
    echo "\n3. Checking for separate QR verification data...\n";
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'wbhsms_database' 
        AND TABLE_NAME = 'appointments' 
        AND COLUMN_NAME LIKE '%qr%'
    ");
    $stmt->execute();
    $qr_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "   QR-related columns: " . implode(', ', $qr_columns) . "\n";
    
    // Test 4: Verify no queue entries exist for recent bookings
    echo "\n4. Checking queue entries for recent appointments...\n";
    foreach ($appointments as $apt) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as queue_count 
            FROM queue_entries 
            WHERE appointment_id = ?
        ");
        $stmt->execute([$apt['appointment_id']]);
        $queue_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "   Appointment #{$apt['appointment_id']}: ";
        echo "Queue entries: {$queue_data['queue_count']} ";
        echo "(" . ($queue_data['queue_count'] == 0 ? "CORRECT - No queue during booking" : "CHECK - Has queue entries") . ")\n";
    }
    
    echo "\n=== QR Code Verification Complete ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>