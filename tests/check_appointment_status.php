<?php
require_once 'config/db.php';

echo "Checking appointment statuses in database...\n";

// Check distinct statuses
$query = "SELECT DISTINCT status FROM appointments WHERE status IS NOT NULL";
$result = $conn->query($query);

echo "Appointment statuses found:\n";
while ($row = $result->fetch_assoc()) {
    echo "- " . ($row['status'] ?? 'NULL') . "\n";
}

// Check for NULL statuses
$query = "SELECT COUNT(*) as null_count FROM appointments WHERE status IS NULL";
$result = $conn->query($query);
$null_row = $result->fetch_assoc();
echo "- NULL status count: " . $null_row['null_count'] . "\n";

// Check recent appointments with their statuses
echo "\nRecent appointments with statuses:\n";
$query = "SELECT appointment_id, status, scheduled_date, created_at FROM appointments ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['appointment_id'] . " | Status: " . ($row['status'] ?? 'NULL') . " | Date: " . $row['scheduled_date'] . "\n";
}

$conn->close();
?>