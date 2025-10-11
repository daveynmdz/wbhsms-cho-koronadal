<?php
/**
 * Station Setup and Initialization Script
 * CHO Koronadal Queue Management System
 * 
 * Purpose: Initialize stations, create station assignments, and set up system defaults
 * Access: Admin only
 * 
 * This script creates the necessary stations and initial assignments for the CHO queue system
 */

// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// Check if role is authorized for admin functions
if (strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../management/admin/dashboard.php');
    exit();
}

// Include database connection and queue management service
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

$message = '';
$error = '';
$setup_log = [];

// Initialize queue management service
try {
    $queueService = new QueueManagementService($pdo);
} catch (Exception $e) {
    die("Queue Service initialization failed: " . $e->getMessage());
}

// Handle setup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_stations':
                createDefaultStations();
                break;
            case 'assign_admin':
                assignAdminToAllStations();
                break;
            case 'reset_stations':
                resetAllStations();
                break;
            case 'update_station_status':
                updateStationStatus();
                break;
            default:
                throw new Exception("Invalid action specified");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/**
 * Create default stations based on CHO requirements
 */
function createDefaultStations() {
    global $pdo, $setup_log, $message;
    
    // Define stations according to the station directory
    $stations = [
        // Check-In Station
        [
            'station_name' => 'Check-In Counter',
            'station_type' => 'checkin',
            'service_id' => 10,
            'station_number' => 16
        ],
        
        // Triage Stations (1-3)
        [
            'station_name' => 'Triage 1',
            'station_type' => 'triage',
            'service_id' => 1,
            'station_number' => 1
        ],
        [
            'station_name' => 'Triage 2',
            'station_type' => 'triage',
            'service_id' => 1,
            'station_number' => 2
        ],
        [
            'station_name' => 'Triage 3',
            'station_type' => 'triage',
            'service_id' => 1,
            'station_number' => 3
        ],
        
        // Consultation Stations (5-11)
        [
            'station_name' => 'Primary Care 1',
            'station_type' => 'consultation',
            'service_id' => 1,
            'station_number' => 5
        ],
        [
            'station_name' => 'Primary Care 2',
            'station_type' => 'consultation',
            'service_id' => 1,
            'station_number' => 6
        ],
        [
            'station_name' => 'Dental Services',
            'station_type' => 'consultation',
            'service_id' => 2,
            'station_number' => 7
        ],
        [
            'station_name' => 'TB Treatment',
            'station_type' => 'consultation',
            'service_id' => 3,
            'station_number' => 8
        ],
        [
            'station_name' => 'Vaccination',
            'station_type' => 'consultation',
            'service_id' => 4,
            'station_number' => 9
        ],
        [
            'station_name' => 'Family Planning',
            'station_type' => 'consultation',
            'service_id' => 6,
            'station_number' => 10
        ],
        [
            'station_name' => 'Animal Bite Treatment',
            'station_type' => 'consultation',
            'service_id' => 7,
            'station_number' => 11
        ],
        
        // Laboratory Station
        [
            'station_name' => 'Laboratory',
            'station_type' => 'lab',
            'service_id' => 8,
            'station_number' => 13
        ],
        
        // Pharmacy Stations (14-15)
        [
            'station_name' => 'Dispensing 1',
            'station_type' => 'pharmacy',
            'service_id' => 1,
            'station_number' => 14
        ],
        [
            'station_name' => 'Dispensing 2',
            'station_type' => 'pharmacy',
            'service_id' => 1,
            'station_number' => 15
        ],
        
        // Billing Station
        [
            'station_name' => 'Billing',
            'station_type' => 'billing',
            'service_id' => 9,
            'station_number' => 4
        ],
        
        // Document Station
        [
            'station_name' => 'Medical Documents',
            'station_type' => 'document',
            'service_id' => 9,
            'station_number' => 12
        ]
    ];
    
    $pdo->beginTransaction();
    
    try {
        foreach ($stations as $station) {
            // Check if station already exists
            $check_stmt = $pdo->prepare("SELECT station_id FROM stations WHERE station_number = ? AND station_type = ?");
            $check_stmt->execute([$station['station_number'], $station['station_type']]);
            
            if ($check_stmt->rowCount() > 0) {
                $setup_log[] = "Station {$station['station_name']} (#{$station['station_number']}) already exists - skipped";
                continue;
            }
            
            // Insert new station
            $insert_stmt = $pdo->prepare("
                INSERT INTO stations (station_name, station_type, service_id, station_number, is_active, is_open)
                VALUES (?, ?, ?, ?, 1, 0)
            ");
            
            $insert_stmt->execute([
                $station['station_name'],
                $station['station_type'],
                $station['service_id'],
                $station['station_number']
            ]);
            
            $setup_log[] = "Created station: {$station['station_name']} (#{$station['station_number']})";
        }
        
        $pdo->commit();
        $message = "Stations created successfully! Created " . count($setup_log) . " stations.";
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw new Exception("Station creation failed: " . $e->getMessage());
    }
}

/**
 * Assign admin user to all stations for initial setup
 */
function assignAdminToAllStations() {
    global $pdo, $setup_log, $message;
    
    $admin_id = $_SESSION['employee_id'];
    $today = date('Y-m-d');
    
    // Get all active stations
    $stmt = $pdo->prepare("SELECT station_id, station_name FROM stations WHERE is_active = 1");
    $stmt->execute();
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($stations)) {
        throw new Exception("No stations found. Please create stations first.");
    }
    
    $pdo->beginTransaction();
    
    try {
        foreach ($stations as $station) {
            // Check if assignment already exists for today
            $check_stmt = $pdo->prepare("
                SELECT schedule_id FROM assignment_schedules 
                WHERE station_id = ? AND employee_id = ? AND start_date = ? AND is_active = 1
            ");
            $check_stmt->execute([$station['station_id'], $admin_id, $today]);
            
            if ($check_stmt->rowCount() > 0) {
                $setup_log[] = "Admin already assigned to {$station['station_name']} - skipped";
                continue;
            }
            
            // Create assignment
            $insert_stmt = $pdo->prepare("
                INSERT INTO assignment_schedules (station_id, employee_id, start_date, shift_start_time, shift_end_time, assigned_by, is_active)
                VALUES (?, ?, ?, '07:00:00', '17:00:00', ?, 1)
            ");
            
            $insert_stmt->execute([$station['station_id'], $admin_id, $today, $admin_id]);
            $setup_log[] = "Assigned admin to: {$station['station_name']}";
        }
        
        $pdo->commit();
        $message = "Admin assigned to all stations successfully! Assignments: " . count($setup_log);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw new Exception("Assignment failed: " . $e->getMessage());
    }
}

/**
 * Reset all stations (remove assignments and close stations)
 */
function resetAllStations() {
    global $pdo, $setup_log, $message;
    
    $pdo->beginTransaction();
    
    try {
        // Close all stations
        $close_stmt = $pdo->prepare("UPDATE stations SET is_open = 0");
        $close_stmt->execute();
        $setup_log[] = "Closed all stations";
        
        // Remove all active assignments for today
        $today = date('Y-m-d');
        $remove_stmt = $pdo->prepare("UPDATE assignment_schedules SET is_active = 0 WHERE start_date = ?");
        $remove_stmt->execute([$today]);
        $setup_log[] = "Removed all active assignments for today";
        
        $pdo->commit();
        $message = "All stations reset successfully!";
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw new Exception("Station reset failed: " . $e->getMessage());
    }
}

/**
 * Update station status (open/close)
 */
function updateStationStatus() {
    global $pdo, $message;
    
    $station_id = $_POST['station_id'] ?? 0;
    $status = $_POST['status'] ?? 'close';
    
    if (!$station_id) {
        throw new Exception("Station ID is required");
    }
    
    $is_open = ($status === 'open') ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE stations SET is_open = ? WHERE station_id = ?");
    $stmt->execute([$is_open, $station_id]);
    
    $action_text = $is_open ? 'opened' : 'closed';
    $message = "Station {$action_text} successfully!";
}

// Get current stations status
$stations_query = "
    SELECT s.*, 
           COALESCE(e.last_name, 'Unassigned') as assigned_employee,
           asg.shift_start_time, asg.shift_end_time
    FROM stations s
    LEFT JOIN assignment_schedules asg ON s.station_id = asg.station_id 
        AND asg.start_date <= CURDATE() 
        AND (asg.end_date IS NULL OR asg.end_date >= CURDATE()) 
        AND asg.is_active = 1
    LEFT JOIN employees e ON asg.employee_id = e.employee_id
    WHERE s.is_active = 1
    ORDER BY s.station_number
";

$stmt = $pdo->prepare($stations_query);
$stmt->execute();
$current_stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set active page for sidebar
$activePage = 'queue_management';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Setup - CHO Koronadal</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/edit.css">
    
    <style>
        .setup-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .setup-section {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            padding: 25px;
        }
        
        .setup-section h3 {
            color: #2c5282;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .setup-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary { 
            background: #3182ce; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #2c5282; 
        }
        
        .btn-success { 
            background: #38a169; 
            color: white; 
        }
        .btn-success:hover { 
            background: #2f855a; 
        }
        
        .btn-warning { 
            background: #d69e2e; 
            color: white; 
        }
        .btn-warning:hover { 
            background: #b7791f; 
        }
        
        .btn-danger { 
            background: #e53e3e; 
            color: white; 
        }
        .btn-danger:hover { 
            background: #c53030; 
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .station-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            background: #f7fafc;
        }
        
        .station-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .station-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-open {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-closed {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .setup-log {
            background: #1a202c;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 15px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #38a169;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #e53e3e;
        }
        
        @media (max-width: 768px) {
            .setup-container {
                padding: 15px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .setup-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <?php 
    $activePage = 'queue_management';
    include '../../includes/sidebar_admin.php'; 
    ?>

    <section class="homepage">
        <div class="setup-container">
            <!-- Breadcrumb -->
            <nav class="breadcrumb" style="margin-bottom: 20px;">
                <a href="../management/admin/dashboard.php">Home</a> / 
                <a href="dashboard.php">Queue Management</a> / 
                <span>Station Setup</span>
            </nav>

            <!-- Page Header -->
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1>Station Setup & Management</h1>
                <div>
                    <a href="dashboard.php" class="setup-btn btn-primary">Back to Dashboard</a>
                    <a href="logs.php" class="setup-btn btn-success">View Queue Logs</a>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Station Creation Section -->
            <div class="setup-section">
                <h3><i class="fas fa-plus-circle"></i> Initial Setup</h3>
                <p>Create default stations and assign initial permissions for the CHO queue management system.</p>
                
                <div class="button-group">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="create_stations">
                        <button type="submit" class="setup-btn btn-primary" 
                                onclick="return confirm('This will create all default stations. Continue?')">
                            <i class="fas fa-hospital"></i> Create Default Stations
                        </button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="assign_admin">
                        <button type="submit" class="setup-btn btn-success"
                                onclick="return confirm('This will assign admin to all stations for today. Continue?')">
                            <i class="fas fa-user-cog"></i> Assign Admin to All Stations
                        </button>
                    </form>
                </div>
            </div>

            <!-- Station Management Section -->
            <div class="setup-section">
                <h3><i class="fas fa-cogs"></i> Station Management</h3>
                <p>Manage individual station status and assignments.</p>
                
                <div class="button-group">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="reset_stations">
                        <button type="submit" class="setup-btn btn-warning"
                                onclick="return confirm('This will close all stations and remove assignments. Continue?')">
                            <i class="fas fa-undo"></i> Reset All Stations
                        </button>
                    </form>
                    
                    <a href="staff_assignments.php" class="setup-btn btn-primary">
                        <i class="fas fa-users"></i> Manage Staff Assignments
                    </a>
                </div>
            </div>

            <!-- Current Stations Status -->
            <div class="setup-section">
                <h3><i class="fas fa-list-alt"></i> Current Stations Status</h3>
                
                <?php if (empty($current_stations)): ?>
                    <p class="text-gray-600">No stations found. Please create default stations first.</p>
                <?php else: ?>
                    <div class="status-grid">
                        <?php foreach ($current_stations as $station): ?>
                            <div class="station-card">
                                <div class="station-header">
                                    <strong><?= htmlspecialchars($station['station_name']) ?></strong>
                                    <span class="station-status <?= $station['is_open'] ? 'status-open' : 'status-closed' ?>">
                                        <?= $station['is_open'] ? 'Open' : 'Closed' ?>
                                    </span>
                                </div>
                                
                                <div class="station-details">
                                    <p><strong>Type:</strong> <?= ucfirst(htmlspecialchars($station['station_type'])) ?></p>
                                    <p><strong>Number:</strong> #<?= htmlspecialchars($station['station_number']) ?></p>
                                    <p><strong>Assigned:</strong> <?= htmlspecialchars($station['assigned_employee']) ?></p>
                                    <?php if ($station['shift_start_time']): ?>
                                        <p><strong>Shift:</strong> <?= date('g:i A', strtotime($station['shift_start_time'])) ?> - <?= date('g:i A', strtotime($station['shift_end_time'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="station-actions" style="margin-top: 15px;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_station_status">
                                        <input type="hidden" name="station_id" value="<?= $station['station_id'] ?>">
                                        <input type="hidden" name="status" value="<?= $station['is_open'] ? 'close' : 'open' ?>">
                                        <button type="submit" class="setup-btn <?= $station['is_open'] ? 'btn-warning' : 'btn-success' ?>" style="font-size: 12px; padding: 6px 12px;">
                                            <?= $station['is_open'] ? 'Close Station' : 'Open Station' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Setup Log -->
            <?php if (!empty($setup_log)): ?>
                <div class="setup-section">
                    <h3><i class="fas fa-terminal"></i> Setup Log</h3>
                    <div class="setup-log">
                        <?php foreach ($setup_log as $log_entry): ?>
                            <div><?= date('H:i:s') ?> - <?= htmlspecialchars($log_entry) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js"></script>
</body>
</html>