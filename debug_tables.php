<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "=== DATABASE TABLE CHECK ===<br>";
    
    $root_path = dirname(__DIR__);
    require_once $root_path . '/config/db.php';
    
    if (!isset($pdo)) {
        throw new Exception("PDO connection not available");
    }
    
    // Check for required tables
    $required_tables = [
        'stations',
        'station_assignments', 
        'queue_entries',
        'queue_logs',
        'appointments',
        'patients',
        'employees',
        'visits',
        'vitals'
    ];
    
    foreach ($required_tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch();
        
        echo "Table '$table': " . ($exists ? '<span style="color:green">EXISTS</span>' : '<span style="color:red">MISSING</span>') . "<br>";
        
        if ($exists) {
            // Check if table has data
            $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `$table`");
            $count_stmt->execute();
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "  - Records: $count<br>";
        }
    }
    
    echo "<br>=== STATION ASSIGNMENTS CHECK ===<br>";
    $stmt = $pdo->prepare("SELECT sa.*, s.station_name, s.station_type FROM station_assignments sa JOIN stations s ON sa.station_id = s.station_id WHERE sa.status = 'active' LIMIT 5");
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Active station assignments:<br>";
    foreach ($assignments as $assignment) {
        echo "- Employee ID {$assignment['employee_id']} assigned to {$assignment['station_name']} ({$assignment['station_type']})<br>";
    }
    
    echo "<br>=== STATIONS CHECK ===<br>";
    $stmt = $pdo->prepare("SELECT * FROM stations WHERE is_active = 1");
    $stmt->execute();
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Active stations:<br>";
    foreach ($stations as $station) {
        echo "- Station ID {$station['station_id']}: {$station['station_name']} ({$station['station_type']})<br>";
    }
    
} catch (Exception $e) {
    echo "<br><strong>ERROR:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
}
?>