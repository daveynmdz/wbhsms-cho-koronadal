<?php
/**
 * Test Script for Automatic Status Updater
 * 
 * This script tests the automatic status update functionality
 * by creating test scenarios and verifying the status changes.
 */

// Include the automatic status updater
$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/automatic_status_updater.php';

echo "<h2>Testing Automatic Status Updater</h2>\n";
echo "<pre>\n";

// Create an instance of the updater
$updater = new AutomaticStatusUpdater($conn);

// Test 1: Check current status before updates
echo "=== BEFORE AUTOMATIC UPDATES ===\n";

// Check appointments
$sql = "SELECT appointment_id, scheduled_date, scheduled_time, status, 
               CONCAT(scheduled_date, ' ', scheduled_time) as scheduled_datetime,
               CASE 
                   WHEN CONCAT(scheduled_date, ' ', scheduled_time) < NOW() AND status = 'confirmed'
                   THEN 'SHOULD BE CANCELLED'
                   ELSE 'OK'
               END as should_update
        FROM appointments 
        ORDER BY scheduled_date DESC, scheduled_time DESC";

$result = $conn->query($sql);
echo "APPOINTMENTS:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf("ID: %d | Date: %s | Time: %s | Status: %s | Should Update: %s\n", 
            $row['appointment_id'], 
            $row['scheduled_date'], 
            $row['scheduled_time'], 
            $row['status'],
            $row['should_update']
        );
    }
} else {
    echo "No appointments found.\n";
}

echo "\n";

// Check referrals
$sql = "SELECT r.referral_id, r.referral_num, r.status, r.referral_date,
               CASE 
                   WHEN r.status = 'active' AND EXISTS (
                       SELECT 1 FROM appointments a 
                       WHERE a.referral_id = r.referral_id 
                       AND a.status IN ('confirmed', 'completed')
                   )
                   THEN 'SHOULD BE ACCEPTED'
                   WHEN r.status = 'active' AND r.referral_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
                   THEN 'SHOULD BE EXPIRED'
                   ELSE 'OK'
               END as should_update
        FROM referrals r 
        ORDER BY r.referral_date DESC";

$result = $conn->query($sql);
echo "REFERRALS:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf("ID: %d | Num: %s | Status: %s | Date: %s | Should Update: %s\n", 
            $row['referral_id'], 
            $row['referral_num'], 
            $row['status'],
            $row['referral_date'],
            $row['should_update']
        );
    }
} else {
    echo "No referrals found.\n";
}

echo "\n=== RUNNING AUTOMATIC UPDATES ===\n";

// Run the automatic updates
$update_result = $updater->runAllUpdates();

if ($update_result['success']) {
    echo "✓ Updates completed successfully!\n";
    echo "Total records updated: " . $update_result['total_updates'] . "\n\n";
    
    foreach ($update_result['details'] as $type => $details) {
        if ($details['success']) {
            echo "✓ {$type}: " . $details['message'] . "\n";
        } else {
            echo "✗ {$type}: " . $details['error'] . "\n";
        }
    }
} else {
    echo "✗ Updates completed with errors:\n";
    foreach ($update_result['errors'] as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n=== AFTER AUTOMATIC UPDATES ===\n";

// Check appointments again
$sql = "SELECT appointment_id, scheduled_date, scheduled_time, status, cancellation_reason
        FROM appointments 
        ORDER BY scheduled_date DESC, scheduled_time DESC";

$result = $conn->query($sql);
echo "APPOINTMENTS:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf("ID: %d | Date: %s | Time: %s | Status: %s", 
            $row['appointment_id'], 
            $row['scheduled_date'], 
            $row['scheduled_time'], 
            $row['status']
        );
        if ($row['cancellation_reason']) {
            echo " | Reason: " . substr($row['cancellation_reason'], 0, 50) . "...";
        }
        echo "\n";
    }
} else {
    echo "No appointments found.\n";
}

echo "\n";

// Check referrals again
$sql = "SELECT referral_id, referral_num, status, referral_date
        FROM referrals 
        ORDER BY referral_date DESC";

$result = $conn->query($sql);
echo "REFERRALS:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf("ID: %d | Num: %s | Status: %s | Date: %s\n", 
            $row['referral_id'], 
            $row['referral_num'], 
            $row['status'],
            $row['referral_date']
        );
    }
} else {
    echo "No referrals found.\n";
}

echo "\n=== TEST COMPLETED ===\n";

echo "</pre>\n";

// Additional test scenarios
echo "<h3>Additional Test Information</h3>\n";
echo "<pre>\n";

// Show current time for reference
echo "Current DateTime: " . date('Y-m-d H:i:s') . "\n";

// Show appointments that should be automatically cancelled
$sql = "SELECT COUNT(*) as count 
        FROM appointments 
        WHERE status = 'confirmed' 
        AND CONCAT(scheduled_date, ' ', scheduled_time) < NOW()";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
echo "Confirmed appointments with past date/time: " . $row['count'] . "\n";

// Show referrals that should be automatically accepted
$sql = "SELECT COUNT(*) as count 
        FROM referrals r
        INNER JOIN appointments a ON r.referral_id = a.referral_id
        WHERE r.status = 'active'
        AND a.status IN ('confirmed', 'completed')";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
echo "Active referrals linked to appointments: " . $row['count'] . "\n";

// Show referrals that should be expired
$sql = "SELECT COUNT(*) as count 
        FROM referrals 
        WHERE status = 'active' 
        AND referral_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND referral_id NOT IN (
            SELECT DISTINCT referral_id 
            FROM appointments 
            WHERE referral_id IS NOT NULL 
            AND status IN ('confirmed', 'completed')
        )";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
echo "Active referrals older than 30 days (not used): " . $row['count'] . "\n";

echo "</pre>\n";
?>