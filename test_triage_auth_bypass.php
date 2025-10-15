<?php
/**
 * Temporary Triage Station Test - Bypassing Auth
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set up test admin session
$_SESSION['employee_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Admin';

echo "<!DOCTYPE html>
<html>
<head><title>Test Triage Station</title></head>
<body>";

try {
    echo "<h3>Testing Triage Station Components</h3>";
    
    $root_path = dirname(__DIR__);
    
    // Load dependencies
    require_once $root_path . '/config/db.php';
    require_once $root_path . '/utils/queue_management_service.php';
    
    $employee_id = $_SESSION['employee_id'];
    $employee_role = $_SESSION['role'];
    $queueService = new QueueManagementService($pdo);
    
    echo "<p><strong>✓</strong> Dependencies loaded successfully</p>";
    
    // Check authorization
    $allowed_roles = ['nurse', 'admin', 'doctor'];
    if (!in_array(strtolower($employee_role), $allowed_roles)) {
        echo "<p><strong>✗</strong> Not authorized for triage operations</p>";
        exit();
    }
    echo "<p><strong>✓</strong> Authorization check passed</p>";
    
    // Get current date
    $current_date = date('Y-m-d');
    
    // Get triage station assignment
    $assignment_query = "SELECT sa.*, s.station_name, s.station_type 
                         FROM station_assignments sa 
                         JOIN stations s ON sa.station_id = s.station_id 
                         WHERE sa.employee_id = ? 
                         AND s.station_type = 'triage'
                         AND sa.assigned_date <= ? 
                         AND (sa.end_date IS NULL OR sa.end_date >= ?)
                         AND sa.status = 'active'
                         ORDER BY sa.assigned_date DESC LIMIT 1";
    
    $stmt = $pdo->prepare($assignment_query);
    $stmt->execute([$employee_id, $current_date, $current_date]);
    $triage_station = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no assignment and user is admin, get first available triage station
    if (!$triage_station && strtolower($employee_role) === 'admin') {
        $stations_query = "SELECT s.station_id, s.station_name, s.station_type, s.is_active 
                           FROM stations s 
                           WHERE s.station_type = 'triage' AND s.is_active = 1
                           ORDER BY s.station_name";
        $stmt = $pdo->prepare($stations_query);
        $stmt->execute();
        $available_stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($available_stations)) {
            $triage_station = $available_stations[0];
            $triage_station['assignment_id'] = null;
            $triage_station['employee_id'] = $employee_id;
        }
    }
    
    if ($triage_station) {
        echo "<p><strong>✓</strong> Station assigned: " . htmlspecialchars($triage_station['station_name']) . "</p>";
        
        $station_id = $triage_station['station_id'];
        
        // Test queue data retrieval
        $waiting_queue = $queueService->getStationQueue($station_id, 'waiting');
        $in_progress_queue = $queueService->getStationQueue($station_id, 'in_progress');
        $completed_queue = $queueService->getStationQueue($station_id, 'done', $current_date, 10);
        $skipped_queue = $queueService->getStationQueue($station_id, 'skipped');
        
        echo "<p><strong>✓</strong> Queue data retrieved successfully</p>";
        echo "<ul>";
        echo "<li>Waiting: " . count($waiting_queue) . " patients</li>";
        echo "<li>In Progress: " . count($in_progress_queue) . " patients</li>";
        echo "<li>Completed: " . count($completed_queue) . " patients</li>";
        echo "<li>Skipped: " . count($skipped_queue) . " patients</li>";
        echo "</ul>";
        
        // Get queue statistics
        $queue_stats = $queueService->getStationQueueStats($station_id, $current_date);
        echo "<p><strong>✓</strong> Queue statistics retrieved</p>";
        
        echo "<h4>All Core Functions Working!</h4>";
        echo "<p><a href='triage_station.php?station_id=" . $station_id . "'>Try accessing actual triage station now</a></p>";
        
    } else {
        echo "<p><strong>✗</strong> No triage station available</p>";
        
        // Show available stations
        $all_stations = $pdo->query("SELECT * FROM stations WHERE station_type = 'triage'")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h4>Available Triage Stations:</h4><ul>";
        foreach ($all_stations as $station) {
            echo "<li>ID: {$station['station_id']}, Name: {$station['station_name']}, Active: " . ($station['is_active'] ? 'Yes' : 'No') . "</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>