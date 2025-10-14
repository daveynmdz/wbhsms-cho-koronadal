<?php
require_once 'config/db.php';

echo "Checking latest queue entries...\n";

// Check the latest queue entry
$stmt = $pdo->query('SELECT qe.*, s.station_type, s.station_name FROM queue_entries qe LEFT JOIN stations s ON qe.station_id = s.station_id ORDER BY qe.created_at DESC LIMIT 5');
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($entries as $entry) {
    echo "Queue Entry ID: " . $entry['queue_entry_id'] . "\n";
    echo "Appointment ID: " . $entry['appointment_id'] . "\n";
    echo "Status: " . $entry['status'] . "\n";
    echo "Station Type: " . ($entry['station_type'] ?: 'NULL') . "\n";
    echo "Station Name: " . ($entry['station_name'] ?: 'NULL') . "\n";
    echo "Created: " . $entry['created_at'] . "\n";
    echo "---\n";
}

// Check latest appointments
echo "\nLatest appointments:\n";
$stmt = $pdo->query('SELECT * FROM appointments ORDER BY created_at DESC LIMIT 3');
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($appointments as $apt) {
    echo "Appointment ID: " . $apt['appointment_id'] . " - Status: " . $apt['status'] . "\n";
}
?>