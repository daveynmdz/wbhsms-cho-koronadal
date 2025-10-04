<?php
require_once 'config/db.php';

echo "Fixing appointment statuses...\n";

// Update all appointments with NULL status to 'confirmed'
$query = "UPDATE appointments SET status = 'confirmed' WHERE status IS NULL";
$result = $conn->query($query);

if ($result) {
    echo "Updated " . $conn->affected_rows . " appointments with NULL status to 'confirmed'\n";
} else {
    echo "Error updating appointments: " . $conn->error . "\n";
}

// Check current status distribution
$query = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status";
$result = $conn->query($query);

echo "\nCurrent appointment status distribution:\n";
while ($row = $result->fetch_assoc()) {
    $status = $row['status'] ?? 'NULL';
    echo "$status: " . $row['count'] . "\n";
}

$conn->close();
?>