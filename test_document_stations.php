<?php
require_once 'config/db.php';

echo "Testing Document Stations...\n";

try {
    // Check for document stations
    $stmt = $pdo->prepare('SELECT station_id, station_name, station_type, is_active FROM stations WHERE station_type = ?');
    $stmt->execute(['document']);
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Document stations found: " . count($stations) . "\n";
    foreach ($stations as $station) {
        echo "- ID: {$station['station_id']}, Name: {$station['station_name']}, Active: " . ($station['is_active'] ? 'Yes' : 'No') . "\n";
    }
    
    // If no document stations, let's see what station types exist
    if (empty($stations)) {
        echo "\nNo document stations found. Checking all station types...\n";
        $stmt = $pdo->prepare('SELECT DISTINCT station_type FROM stations');
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Available station types: " . implode(', ', $types) . "\n";
        
        // Let's also see all stations
        $stmt = $pdo->prepare('SELECT station_id, station_name, station_type, is_active FROM stations LIMIT 10');
        $stmt->execute();
        $all_stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nFirst 10 stations:\n";
        foreach ($all_stations as $station) {
            echo "- ID: {$station['station_id']}, Name: {$station['station_name']}, Type: {$station['station_type']}, Active: " . ($station['is_active'] ? 'Yes' : 'No') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>