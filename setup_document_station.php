<?php
require_once 'config/db.php';

echo "Setting up Document Station...\n";

try {
    // Check if document station exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM stations WHERE station_type = ? AND is_active = 1');
    $stmt->execute(['document']);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "No active document stations found. Creating one...\n";
        
        // Insert a document station
        $stmt = $pdo->prepare('INSERT INTO stations (station_name, station_type, description, is_active, created_at) VALUES (?, ?, ?, 1, NOW())');
        $stmt->execute([
            'Document Station',
            'document', 
            'Main document processing station for medical certificates and referrals'
        ]);
        
        $station_id = $pdo->lastInsertId();
        echo "Created document station with ID: $station_id\n";
        
        // Optionally assign current admin user to this station
        $stmt = $pdo->prepare('SELECT employee_id FROM employees WHERE role = ? AND is_active = 1 LIMIT 1');
        $stmt->execute(['admin']);
        $admin_id = $stmt->fetchColumn();
        
        if ($admin_id) {
            $stmt = $pdo->prepare('INSERT INTO station_assignments (station_id, employee_id, assigned_date, status, created_at) VALUES (?, ?, CURDATE(), ?, NOW())');
            $stmt->execute([$station_id, $admin_id, 'active']);
            echo "Assigned admin (ID: $admin_id) to document station\n";
        }
        
    } else {
        echo "Found $count active document station(s)\n";
    }
    
    // Show current document stations
    $stmt = $pdo->prepare('SELECT station_id, station_name, station_type, is_active FROM stations WHERE station_type = ?');
    $stmt->execute(['document']);
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent document stations:\n";
    foreach ($stations as $station) {
        echo "- ID: {$station['station_id']}, Name: {$station['station_name']}, Active: " . ($station['is_active'] ? 'Yes' : 'No') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nSetup complete! You can now access the document station.\n";
?>