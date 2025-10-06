<?php
/**
 * Queue Management Dashboard - Admin Interface
 * Modern, intuitive dashboard for comprehensive queue oversight
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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

// DB connection
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Initialize queue management service
$queueService = new QueueManagementService($conn);

$today = date('Y-m-d');
$message = '';
$error = '';

// Handle station toggle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_station'])) {
    $station_id = intval($_POST['station_id']);
    $is_open = intval($_POST['is_open']);
    
    $stmt = $conn->prepare("UPDATE stations SET is_open = ? WHERE station_id = ?");
    $stmt->bind_param("ii", $is_open, $station_id);
    
    if ($stmt->execute()) {
        $message = $is_open ? "Station opened successfully" : "Station closed successfully";
    } else {
        $error = "Failed to update station status";
    }
    $stmt->close();
}

// Get comprehensive queue statistics for today
$stats_query = "
    SELECT 
        COUNT(*) as total_queues,
        SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting_count,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_count,
        SUM(CASE WHEN priority_level = 'normal' THEN 1 ELSE 0 END) as normal_priority,
        SUM(CASE WHEN priority_level = 'priority' THEN 1 ELSE 0 END) as priority_priority,
        SUM(CASE WHEN priority_level = 'emergency' THEN 1 ELSE 0 END) as emergency_priority,
        AVG(waiting_time) as avg_waiting_time,
        AVG(turnaround_time) as avg_turnaround_time
    FROM queue_entries 
    WHERE DATE(created_at) = ?
";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get queue type breakdown for today
$type_stats_query = "
    SELECT 
        queue_type,
        COUNT(*) as count,
        SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed
    FROM queue_entries 
    WHERE DATE(created_at) = ?
    GROUP BY queue_type
    ORDER BY count DESC
";

$stmt = $conn->prepare($type_stats_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$type_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all stations with current assignments and status
$stations = $queueService->getAllStationsWithAssignments($today);

// Get station queue counts for today (limited to top 5)
$station_counts_query = "
    SELECT 
        s.station_id,
        s.station_name,
        COUNT(qe.queue_entry_id) as total_served,
        SUM(CASE WHEN qe.status = 'waiting' THEN 1 ELSE 0 END) as current_waiting,
        AVG(qe.turnaround_time) as avg_service_time
    FROM stations s
    LEFT JOIN queue_entries qe ON s.station_id = qe.station_id AND DATE(qe.created_at) = ?
    WHERE s.is_active = 1
    GROUP BY s.station_id, s.station_name
    ORDER BY total_served DESC
    LIMIT 5
";

$stmt = $conn->prepare($station_counts_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$busiest_stations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Set active page for sidebar highlighting
$activePage = 'queueing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Management Dashboard | CHO Koronadal</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Dashboard-specific styles - MATCHING STAFF ASSIGNMENTS TEMPLATE */
        .queue-dashboard-container {
            /* CHO Theme Variables - Matching staff_assignments.php */
            --primary: #0077b6;
            --primary-dark: #03045e;
            --secondary: #6c757d;
            --success: #2d6a4f;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #d00000;
            --light: #f8f9fa;
            --border: #dee2e6;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
        }

        .queue-dashboard-container .content-area {
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
        }

        /* Page header styling - matching staff assignments */
        .queue-dashboard-container .page-title {
            color: var(--primary-dark);
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        /* Card container styling - matching staff assignments */
        .queue-dashboard-container .card-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .queue-dashboard-container .section-header {
            display: flex;
            align-items: center;
            padding: 0 0 15px 0;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(0, 119, 182, 0.2);
        }
        
        .queue-dashboard-container .section-header h4 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 18px;
            font-weight: 600;
        }
        
        .queue-dashboard-container .section-header h4 i {
            color: var(--primary);
            margin-right: 8px;
        }

        /* Breadcrumb Navigation - exactly matching staff assignments */
        .queue-dashboard-container .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .queue-dashboard-container .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .queue-dashboard-container .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Page header styling - exactly matching staff assignments */
        .queue-dashboard-container .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .queue-dashboard-container .page-header h1 {
            color: #0077b6;
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .queue-dashboard-container .page-header h1 i {
            color: #0077b6;
        }

        /* Total count badges styling - exactly matching staff assignments */
        .queue-dashboard-container .total-count {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
        }

        .queue-dashboard-container .total-count .badge {
            min-width: 120px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            border-radius: 25px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .queue-dashboard-container .total-count .badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Mobile responsive for page header - matching staff assignments */
        @media (max-width: 768px) {
            .queue-dashboard-container .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .queue-dashboard-container .total-count {
                width: 100%;
                justify-content: flex-start;
                gap: 0.75rem;
            }

            .queue-dashboard-container .total-count .badge {
                min-width: 100px;
                font-size: 0.8rem;
                padding: 6px 12px;
            }
        }

        @media (max-width: 480px) {
            .queue-dashboard-container .total-count {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .queue-dashboard-container .total-count .badge {
                width: 100%;
                min-width: auto;
                text-align: center;
            }
        }

        .queue-dashboard-container .breadcrumb-nav {
            margin-bottom: 1.5rem;
        }

        .queue-dashboard-container .breadcrumb-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            font-size: 0.85rem;
        }

        .queue-dashboard-container .breadcrumb-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .queue-dashboard-container .breadcrumb-link:hover {
            background: rgba(0, 119, 182, 0.1);
            color: var(--primary-dark);
        }

        .queue-dashboard-container .breadcrumb-separator {
            color: var(--secondary);
            font-size: 0.7rem;
            opacity: 0.6;
        }

        .queue-dashboard-container .breadcrumb-current {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--dark);
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .queue-dashboard-container .breadcrumb-current i {
            color: var(--primary);
        }

        /* Header Section - matching staff assignments card style */
        .queue-dashboard-container .dashboard-header {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .queue-dashboard-container .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .queue-dashboard-container .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .queue-dashboard-container .header-icon {
            width: 50px;
            height: 50px;
            background: var(--gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
        }

        .queue-dashboard-container .header-text h1 {
            margin: 0;
            color: var(--dark);
            font-size: 1.8rem;
            font-weight: 700;
        }

        .queue-dashboard-container .header-text p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .queue-dashboard-container .quick-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Action buttons - matching staff assignments style */
        .queue-dashboard-container .btn,
        .queue-dashboard-container .action-btn {
            margin-right: 5px;
            padding: 8px 15px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            color: white;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            text-decoration: none;
        }
        
        .queue-dashboard-container .btn:hover,
        .queue-dashboard-container .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
        }

        .queue-dashboard-container .btn-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }

        .queue-dashboard-container .btn-outline {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }

        .queue-dashboard-container .btn-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }

        .queue-dashboard-container .btn-info {
            background: linear-gradient(135deg, #0096c7, #0077b6);
        }

        /* Alert Messages - matching staff assignments */
        .queue-dashboard-container .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left-width: 4px;
            border-left-style: solid;
        }
        
        .queue-dashboard-container .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
            border-left-color: #28a745;
        }
        
        .queue-dashboard-container .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            border-left-color: #dc3545;
        }
        
        .queue-dashboard-container .alert i {
            margin-right: 5px;
        }

        /* Statistics Cards - matching staff assignments card style */
        .queue-dashboard-container .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .queue-dashboard-container .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .queue-dashboard-container .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .queue-dashboard-container .stat-card.waiting {
            border-left-color: var(--warning);
        }

        .queue-dashboard-container .stat-card.in-progress {
            border-left-color: var(--info);
        }

        .queue-dashboard-container .stat-card.completed {
            border-left-color: var(--success);
        }

        .queue-dashboard-container .stat-card.time {
            border-left-color: var(--secondary);
        }

        .queue-dashboard-container .stat-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .queue-dashboard-container .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--white);
        }

        .queue-dashboard-container .stat-icon.total {
            background: linear-gradient(135deg, #0077b6, #023e8a);
        }

        .queue-dashboard-container .stat-icon.waiting {
            background: linear-gradient(135deg, #ffc107, #f39c12);
        }

        .queue-dashboard-container .stat-icon.in-progress {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }

        .queue-dashboard-container .stat-icon.completed {
            background: linear-gradient(135deg, #20c997, #1a9471);
        }

        .queue-dashboard-container .stat-icon.time {
            background: linear-gradient(135deg, #6c757d, #495057);
        }

        .queue-dashboard-container .stat-details h3 {
            margin: 0 0 0.25rem 0;
            color: var(--secondary);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .queue-dashboard-container .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .queue-dashboard-container .stat-subtitle {
            font-size: 0.8rem;
            color: var(--secondary);
            margin: 0.25rem 0 0 0;
        }

        /* Main Content Grid */
        .queue-dashboard-container .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Right Panel with Multiple Sections */
        .queue-dashboard-container .right-panel {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .queue-dashboard-container .compact-section {
            margin-bottom: 0;
        }

        .queue-dashboard-container .compact-body {
            padding: 1rem 1.5rem;
        }

        .queue-dashboard-container .compact-empty {
            padding: 1.5rem;
        }

        /* Compact Queue Types */
        .queue-dashboard-container .compact-queue-types {
            display: grid;
            gap: 0.75rem;
        }

        .queue-dashboard-container .compact-card {
            padding: 0.75rem;
            margin-bottom: 0;
        }

        .queue-dashboard-container .compact-stats {
            gap: 0.75rem;
        }

        .queue-dashboard-container .compact-stats .queue-stat-label {
            font-size: 0.65rem;
        }

        /* Summary Grid */
        .queue-dashboard-container .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .queue-dashboard-container .summary-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: var(--transition);
        }

        .queue-dashboard-container .summary-item:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }

        .queue-dashboard-container .summary-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 14px;
            flex-shrink: 0;
        }

        .queue-dashboard-container .summary-icon.waiting-bg {
            background: linear-gradient(135deg, var(--warning), #e0a800);
        }

        .queue-dashboard-container .summary-icon.success-bg {
            background: linear-gradient(135deg, var(--success), #1a9471);
        }

        .queue-dashboard-container .summary-icon.info-bg {
            background: linear-gradient(135deg, var(--info), #138496);
        }

        .queue-dashboard-container .summary-icon.primary-bg {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .queue-dashboard-container .summary-details {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .queue-dashboard-container .summary-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.2;
        }

        .queue-dashboard-container .summary-label {
            font-size: 0.7rem;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Status Indicators */
        .queue-dashboard-container .status-indicators {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .queue-dashboard-container .status-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
        }

        .queue-dashboard-container .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
            position: relative;
        }

        .queue-dashboard-container .status-indicator.active {
            background: var(--success);
            box-shadow: 0 0 0 2px rgba(32, 201, 151, 0.2);
        }

        .queue-dashboard-container .status-indicator.active::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s infinite;
        }

        .queue-dashboard-container .status-indicator.inactive {
            background: var(--secondary);
        }

        .queue-dashboard-container .status-indicator.warning {
            background: var(--warning);
            box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.2);
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.2);
                opacity: 0.7;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .queue-dashboard-container .status-text {
            font-size: 0.85rem;
            color: var(--dark);
            font-weight: 500;
        }

        .queue-dashboard-container .system-uptime {
            padding-top: 0.75rem;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }

        .queue-dashboard-container .system-uptime small {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.75rem;
        }

        /* Badge styling - matching staff assignments */
        .queue-dashboard-container .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .queue-dashboard-container .bg-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }
        
        .queue-dashboard-container .bg-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }
        
        .queue-dashboard-container .bg-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }
        
        .queue-dashboard-container .bg-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }
        
        .queue-dashboard-container .bg-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }

        /* Action buttons grid - full width layout */
        .queue-dashboard-container .action-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            width: 100%;
        }

        .queue-dashboard-container .action-buttons-grid .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 15px;
            text-align: center;
            min-height: 80px;
            margin-right: 0;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .queue-dashboard-container .action-buttons-grid .action-btn i {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .queue-dashboard-container .action-buttons-grid .action-btn span {
            font-size: 14px;
            font-weight: 600;
        }

        .queue-dashboard-container .action-buttons-grid .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        /* Secondary button styling for Staff Assignments */
        .queue-dashboard-container .btn-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }

        @media (max-width: 768px) {
            .queue-dashboard-container .action-buttons-grid {
                grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
                gap: 10px;
            }

            .queue-dashboard-container .action-buttons-grid .action-btn {
                padding: 15px 10px;
                min-height: 70px;
            }

            .queue-dashboard-container .action-buttons-grid .action-btn i {
                font-size: 20px;
                margin-bottom: 6px;
            }

            .queue-dashboard-container .action-buttons-grid .action-btn span {
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .queue-dashboard-container .action-buttons-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* Dashboard Sections - matching staff assignments card style */
        .queue-dashboard-container .dashboard-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .queue-dashboard-container .dashboard-section .section-header {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
            border-bottom: none;
        }

        .queue-dashboard-container .dashboard-section .section-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 600;
        }

        .queue-dashboard-container .dashboard-section .section-title i {
            color: white;
            margin-right: 0;
        }

        .queue-dashboard-container .dashboard-section .section-meta {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .queue-dashboard-container .dashboard-section .section-body {
            padding: 20px;
        }

        /* Station Management Table - matching staff assignments table */
        .queue-dashboard-container .table-responsive {
            overflow-x: auto;
            border-radius: var(--border-radius);
            margin-top: 10px;
        }
        
        .queue-dashboard-container .stations-wrapper {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }

        .queue-dashboard-container .stations-table,
        .queue-dashboard-container table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: var(--shadow);
        }

        .queue-dashboard-container .stations-table th,
        .queue-dashboard-container table th {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
            z-index: 10;
            cursor: pointer;
            user-select: none;
        }

        .queue-dashboard-container .stations-table td,
        .queue-dashboard-container table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .queue-dashboard-container .stations-table tbody tr:hover,
        .queue-dashboard-container table tr:hover {
            background-color: rgba(240, 247, 255, 0.6);
            transition: background-color 0.2s;
        }
        
        .queue-dashboard-container .stations-table tr:last-child td,
        .queue-dashboard-container table tr:last-child td {
            border-bottom: none;
        }

        .queue-dashboard-container .station-name {
            font-weight: 600;
            color: var(--dark);
        }

        .queue-dashboard-container .station-type {
            font-size: 0.75rem;
            color: var(--secondary);
            text-transform: capitalize;
        }

        .queue-dashboard-container .employee-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .queue-dashboard-container .employee-name {
            font-weight: 500;
            color: var(--dark);
        }

        .queue-dashboard-container .employee-role {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            background: var(--primary-light);
            color: var(--primary-dark);
            border-radius: 4px;
            display: inline-block;
            text-transform: capitalize;
        }

        .queue-dashboard-container .status-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .queue-dashboard-container .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .queue-dashboard-container .status-dot.active {
            background: var(--success);
        }

        .queue-dashboard-container .status-dot.inactive {
            background: var(--secondary);
        }

        .queue-dashboard-container .status-dot.open {
            background: var(--info);
        }

        .queue-dashboard-container .status-dot.closed {
            background: var(--danger);
        }

        /* Modern Toggle Switch */
        .queue-dashboard-container .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .queue-dashboard-container .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .queue-dashboard-container .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            transition: 0.3s;
            border-radius: 24px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .queue-dashboard-container .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: var(--white);
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        input:checked + .toggle-slider {
            background: var(--success);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }

        /* Queue Type Cards */
        .queue-dashboard-container .queue-types {
            display: grid;
            gap: 1rem;
        }

        .queue-dashboard-container .queue-type-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }

        .queue-dashboard-container .queue-type-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .queue-dashboard-container .queue-type-name {
            font-weight: 600;
            color: var(--dark);
            text-transform: capitalize;
            font-size: 0.9rem;
        }

        .queue-dashboard-container .queue-type-total {
            background: var(--primary);
            color: var(--white);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .queue-dashboard-container .queue-type-stats {
            display: flex;
            gap: 1rem;
        }

        .queue-dashboard-container .queue-stat {
            text-align: center;
            flex: 1;
        }

        .queue-dashboard-container .queue-stat-value {
            display: block;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .queue-dashboard-container .queue-stat-label {
            font-size: 0.7rem;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .queue-dashboard-container .queue-stat.waiting .queue-stat-value {
            color: var(--warning);
        }

        .queue-dashboard-container .queue-stat.progress .queue-stat-value {
            color: var(--info);
        }

        .queue-dashboard-container .queue-stat.done .queue-stat-value {
            color: var(--success);
        }

        /* Chart Container */
        .queue-dashboard-container .chart-container {
            position: relative;
            height: 250px;
            padding: 1rem 0;
        }

        /* Priority Section */
        .queue-dashboard-container .priority-section {
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .queue-dashboard-container .priority-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0;
        }

        .queue-dashboard-container .priority-item {
            padding: 1.5rem;
            text-align: center;
            border-right: 1px solid #e9ecef;
            transition: var(--transition);
        }

        .queue-dashboard-container .priority-item:last-child {
            border-right: none;
        }

        .queue-dashboard-container .priority-item:hover {
            background: #f8f9fa;
        }

        .queue-dashboard-container .priority-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem auto;
            font-size: 18px;
            color: var(--white);
        }

        .queue-dashboard-container .priority-icon.emergency {
            background: linear-gradient(135deg, var(--danger), #c82333);
        }

        .queue-dashboard-container .priority-icon.priority {
            background: linear-gradient(135deg, var(--warning), #e0a800);
        }

        .queue-dashboard-container .priority-icon.normal {
            background: linear-gradient(135deg, var(--info), #138496);
        }

        .queue-dashboard-container .priority-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .queue-dashboard-container .priority-label {
            font-size: 0.8rem;
            color: var(--secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .queue-dashboard-container .main-grid {
                grid-template-columns: 1fr;
            }

            .queue-dashboard-container .right-panel {
                grid-row: 1;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                display: grid;
                gap: 1.5rem;
            }
        }

        @media (max-width: 900px) {
            .queue-dashboard-container .right-panel {
                grid-template-columns: 1fr;
                display: flex;
                flex-direction: column;
            }

            .queue-dashboard-container .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .queue-dashboard-container .content-area {
                padding: 1rem;
            }

            .queue-dashboard-container .header-content {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .queue-dashboard-container .stats-grid {
                grid-template-columns: 1fr;
            }

            .queue-dashboard-container .quick-actions {
                justify-content: center;
            }

            .queue-dashboard-container .stations-wrapper {
                font-size: 0.85rem;
            }

            .queue-dashboard-container .stations-table th,
            .queue-dashboard-container .stations-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .queue-dashboard-container .header-text h1 {
                font-size: 1.5rem;
            }

            .queue-dashboard-container .stat-value {
                font-size: 1.75rem;
            }

            .queue-dashboard-container .priority-grid {
                grid-template-columns: 1fr;
            }

            .queue-dashboard-container .priority-item {
                border-right: none;
                border-bottom: 1px solid #e9ecef;
            }

            .queue-dashboard-container .priority-item:last-child {
                border-bottom: none;
            }
        }

        /* Loading States */
        .queue-dashboard-container .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Empty States */
        .queue-dashboard-container .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--secondary);
        }

        .queue-dashboard-container .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Utilities */
        .queue-dashboard-container .text-muted {
            color: var(--secondary) !important;
        }

        .queue-dashboard-container .text-center {
            text-align: center;
        }

        .queue-dashboard-container .mb-0 {
            margin-bottom: 0 !important;
        }
    </style>
</head>

<body>
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'queueing';
    include '../../includes/sidebar_admin.php';
    ?>

    <main class="homepage">
        <div class="queue-dashboard-container">
        <div class="content-area">
            <!-- Breadcrumb Navigation - matching staff assignments -->
            <div class="breadcrumb" style="margin-top: 50px;">
                <a href="../management/admin/dashboard.php"><i class="fas fa-home"></i> Admin Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="."><i class="fas fa-users"></i> Queue Management</a>
                <i class="fas fa-chevron-right"></i>
                <span>Dashboard</span>
            </div>

            <!-- Page Header with Status Badges - matching staff assignments -->
            <div class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> Queue Management Dashboard</h1>
                <div class="total-count">
                    <span class="badge bg-info"><?php echo number_format($stats['total_queues'] ?? 0); ?> Total Queues</span>
                    <span class="badge bg-warning"><?php echo number_format($stats['waiting_count'] ?? 0); ?> Waiting</span>
                    <span class="badge bg-success"><?php echo number_format($stats['done_count'] ?? 0); ?> Completed</span>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card-container">
                <div class="section-header">
                    <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
                </div>
                <div class="action-buttons-grid">
                    <a href="logs.php" class="action-btn btn-info">
                        <i class="fas fa-history"></i>
                        <span>Queue Logs</span>
                    </a>
                    <a href="staff_assignments.php" class="action-btn btn-secondary">
                        <i class="fas fa-users-cog"></i>
                        <span>Staff Assignments</span>
                    </a>
                    <a href="checkin.php" class="action-btn btn-success">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Check-in</span>
                    </a>
                    <a href="station.php" class="action-btn btn-primary">
                        <i class="fas fa-desktop"></i>
                        <span>Station View</span>
                    </a>
                    <a href="admin_monitor.php" class="action-btn btn-info">
                        <i class="fas fa-tv"></i>
                        <span>Master View</span>
                    </a>
                    <a href="public_display_selector.php" class="action-btn btn-outline">
                        <i class="fas fa-display"></i>
                        <span>Display Launcher</span>
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <div class="card-container">
                <div class="section-header">
                    <h4><i class="fas fa-chart-line"></i> Today's Statistics</h4>
                </div>
                <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-icon total">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Total Queues</h3>
                            <p class="stat-value"><?php echo number_format($stats['total_queues'] ?? 0); ?></p>
                            <p class="stat-subtitle">All entries today</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card waiting">
                    <div class="stat-content">
                        <div class="stat-icon waiting">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Currently Waiting</h3>
                            <p class="stat-value"><?php echo number_format($stats['waiting_count'] ?? 0); ?></p>
                            <p class="stat-subtitle"><?php echo number_format($stats['in_progress_count'] ?? 0); ?> being served</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card completed">
                    <div class="stat-content">
                        <div class="stat-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Completed Today</h3>
                            <p class="stat-value"><?php echo number_format($stats['done_count'] ?? 0); ?></p>
                            <p class="stat-subtitle"><?php echo number_format($stats['cancelled_count'] ?? 0); ?> cancelled</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card time">
                    <div class="stat-content">
                        <div class="stat-icon time">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Average Wait</h3>
                            <p class="stat-value"><?php echo $stats['avg_waiting_time'] ? number_format($stats['avg_waiting_time'], 0) : '0'; ?><span style="font-size: 0.7rem; font-weight: 400;">min</span></p>
                            <p class="stat-subtitle">Service: <?php echo $stats['avg_turnaround_time'] ? number_format($stats['avg_turnaround_time'], 0) : '0'; ?> min</p>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="main-grid">
                <!-- Station Management -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-hospital"></i>
                            Station Management
                        </h3>
                        <span class="section-meta"><?php echo count($stations); ?> stations</span>
                    </div>
                    <div class="section-body">
                        <?php if (empty($stations)): ?>
                            <div class="empty-state">
                                <i class="fas fa-hospital"></i>
                                <h4>No Stations Configured</h4>
                                <p>Set up stations to begin queue management.</p>
                            </div>
                        <?php else: ?>
                            <div class="stations-wrapper">
                                <table class="stations-table">
                                    <thead>
                                        <tr>
                                            <th>Station</th>
                                            <th>Assigned Staff</th>
                                            <th>Status</th>
                                            <th>Open/Close</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stations as $station): ?>
                                            <tr>
                                                <td>
                                                    <div class="station-name"><?php echo htmlspecialchars($station['station_name']); ?></div>
                                                    <div class="station-type"><?php echo htmlspecialchars(ucfirst($station['station_type'])); ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($station['employee_name']): ?>
                                                        <div class="employee-info">
                                                            <div class="employee-name"><?php echo htmlspecialchars($station['employee_name']); ?></div>
                                                            <div class="employee-role"><?php echo htmlspecialchars(ucfirst($station['employee_role'] ?? '')); ?></div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unassigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="status-badge">
                                                        <span class="status-dot <?php echo $station['is_active'] ? 'active' : 'inactive'; ?>"></span>
                                                        <?php echo $station['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($station['is_active']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="station_id" value="<?php echo $station['station_id']; ?>">
                                                            <input type="hidden" name="is_open" value="<?php echo (isset($station['is_open']) && $station['is_open']) ? 0 : 1; ?>">
                                                            <label class="toggle-switch">
                                                                <input type="checkbox" name="toggle_station" 
                                                                       <?php echo (isset($station['is_open']) && $station['is_open']) ? 'checked' : ''; ?>
                                                                       onchange="this.form.submit()">
                                                                <span class="toggle-slider"></span>
                                                            </label>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Queue Types Overview & Quick Actions -->
                <div class="right-panel">
                    <!-- Queue Types Section -->
                    <div class="dashboard-section compact-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-chart-pie"></i>
                                Queue Types
                            </h3>
                            <span class="section-meta">Today's breakdown</span>
                        </div>
                        <div class="section-body compact-body">
                            <?php if (empty($type_stats)): ?>
                                <div class="empty-state compact-empty">
                                    <i class="fas fa-chart-pie"></i>
                                    <h4>No Queue Data</h4>
                                    <p>No queue entries found for today.</p>
                                </div>
                            <?php else: ?>
                                <div class="queue-types compact-queue-types">
                                    <?php foreach ($type_stats as $type): ?>
                                        <div class="queue-type-card compact-card">
                                            <div class="queue-type-header">
                                                <div class="queue-type-name"><?php echo htmlspecialchars(ucfirst($type['queue_type'])); ?></div>
                                                <div class="queue-type-total"><?php echo $type['count']; ?></div>
                                            </div>
                                            <div class="queue-type-stats compact-stats">
                                                <div class="queue-stat waiting">
                                                    <span class="queue-stat-value"><?php echo $type['waiting']; ?></span>
                                                    <span class="queue-stat-label">Wait</span>
                                                </div>
                                                <div class="queue-stat progress">
                                                    <span class="queue-stat-value"><?php echo $type['in_progress']; ?></span>
                                                    <span class="queue-stat-label">Active</span>
                                                </div>
                                                <div class="queue-stat done">
                                                    <span class="queue-stat-value"><?php echo $type['completed']; ?></span>
                                                    <span class="queue-stat-label">Done</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Stats Section -->
                    <div class="dashboard-section compact-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-clock"></i>
                                Today's Summary
                            </h3>
                            <span class="section-meta">Real-time</span>
                        </div>
                        <div class="section-body compact-body">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <div class="summary-icon waiting-bg">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                    <div class="summary-details">
                                        <span class="summary-value"><?php echo $stats['avg_waiting_time'] ? number_format($stats['avg_waiting_time'], 0) : '0'; ?>min</span>
                                        <span class="summary-label">Avg Wait</span>
                                    </div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-icon success-bg">
                                        <i class="fas fa-check-double"></i>
                                    </div>
                                    <div class="summary-details">
                                        <span class="summary-value"><?php echo number_format(($stats['done_count'] ?? 0) + ($stats['cancelled_count'] ?? 0)); ?></span>
                                        <span class="summary-label">Processed</span>
                                    </div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-icon info-bg">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="summary-details">
                                        <span class="summary-value"><?php echo number_format(($stats['waiting_count'] ?? 0) + ($stats['in_progress_count'] ?? 0)); ?></span>
                                        <span class="summary-label">Active</span>
                                    </div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-icon primary-bg">
                                        <i class="fas fa-hospital"></i>
                                    </div>
                                    <div class="summary-details">
                                        <span class="summary-value"><?php echo count(array_filter($stations, function($s) { return $s['is_active'] && isset($s['is_open']) && $s['is_open']; })); ?></span>
                                        <span class="summary-label">Open</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Status Section -->
                    <div class="dashboard-section compact-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-heartbeat"></i>
                                System Status
                            </h3>
                            <span class="section-meta">Live</span>
                        </div>
                        <div class="section-body compact-body">
                            <div class="status-indicators">
                                <div class="status-item">
                                    <div class="status-indicator active"></div>
                                    <span class="status-text">Queue System Online</span>
                                </div>
                                <div class="status-item">
                                    <div class="status-indicator <?php echo !empty($stations) ? 'active' : 'inactive'; ?>"></div>
                                    <span class="status-text">Stations <?php echo !empty($stations) ? 'Active' : 'Offline'; ?></span>
                                </div>
                                <div class="status-item">
                                    <div class="status-indicator <?php echo ($stats['total_queues'] ?? 0) > 0 ? 'active' : 'warning'; ?>"></div>
                                    <span class="status-text">Queue Processing</span>
                                </div>
                            </div>
                            <div class="system-uptime">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i>
                                    Last updated: <?php echo date('H:i:s'); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Priority Level Overview -->
            <div class="card-container">
                <div class="section-header">
                    <h4><i class="fas fa-exclamation-triangle"></i> Priority Distribution</h4>
                </div>
                <div class="priority-grid">
                    <div class="priority-item">
                        <div class="priority-icon emergency">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <p class="priority-value"><?php echo $stats['emergency_priority'] ?? 0; ?></p>
                        <p class="priority-label">Emergency</p>
                    </div>
                    <div class="priority-item">
                        <div class="priority-icon priority">
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="priority-value"><?php echo $stats['priority_priority'] ?? 0; ?></p>
                        <p class="priority-label">Priority</p>
                    </div>
                    <div class="priority-item">
                        <div class="priority-icon normal">
                            <i class="fas fa-user"></i>
                        </div>
                        <p class="priority-value"><?php echo $stats['normal_priority'] ?? 0; ?></p>
                        <p class="priority-label">Normal</p>
                    </div>
                </div>
            </div>

            <!-- Performance Chart (if data available) -->
            <?php if (!empty($busiest_stations)): ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-chart-bar"></i>
                        Station Performance
                    </h3>
                    <span class="section-meta">Top performers today</span>
                </div>
                <div class="section-body">
                    <div class="chart-container">
                        <canvas id="stationChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        </div>
    </main>

    <!-- Chart.js Implementation -->
    <script>
        <?php if (!empty($busiest_stations)): ?>
        // Station Performance Chart
        const ctx = document.getElementById('stationChart').getContext('2d');
        const stationChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($busiest_stations, 'station_name')); ?>,
                datasets: [{
                    label: 'Patients Served',
                    data: <?php echo json_encode(array_column($busiest_stations, 'total_served')); ?>,
                    backgroundColor: 'rgba(0, 119, 182, 0.8)',
                    borderColor: 'rgba(0, 119, 182, 1)',
                    borderWidth: 2,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                elements: {
                    bar: {
                        borderSkipped: false,
                    }
                }
            }
        });
        <?php endif; ?>

        // Auto-refresh functionality (every 3 minutes) - silent refresh
        function refreshDashboard() {
            // Add a subtle loading indicator
            const refreshIndicator = document.createElement('div');
            refreshIndicator.style.cssText = `
                position: fixed;
                top: 10px;
                right: 10px;
                background: rgba(0, 119, 182, 0.9);
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 1000;
                transition: opacity 0.3s ease;
            `;
            refreshIndicator.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Updating data...';
            document.body.appendChild(refreshIndicator);
            
            // Refresh the page after a short delay to show the indicator
            setTimeout(() => {
                window.location.reload();
            }, 800);
        }
        
        // Set up auto-refresh timer (3 minutes = 180000ms)
        let refreshTimer = setInterval(refreshDashboard, 180000);
        
        // Add manual refresh button functionality
        function addManualRefreshButton() {
            const refreshBtn = document.createElement('button');
            refreshBtn.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: linear-gradient(135deg, #0077b6, #023e8a);
                color: white;
                border: none;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                font-size: 16px;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                transition: all 0.3s ease;
                z-index: 999;
            `;
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
            refreshBtn.title = 'Refresh Dashboard Data';
            
            refreshBtn.addEventListener('click', () => {
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>';
                setTimeout(() => window.location.reload(), 300);
            });
            
            refreshBtn.addEventListener('mouseenter', () => {
                refreshBtn.style.transform = 'translateY(-2px) scale(1.05)';
                refreshBtn.style.boxShadow = '0 6px 20px rgba(0, 0, 0, 0.25)';
            });
            
            refreshBtn.addEventListener('mouseleave', () => {
                refreshBtn.style.transform = 'translateY(0) scale(1)';
                refreshBtn.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            });
            
            document.body.appendChild(refreshBtn);
        }
        
        // Add the manual refresh button
        addManualRefreshButton();

        // Clear timer if user navigates away
        window.addEventListener('beforeunload', () => {
            clearInterval(refreshTimer);
        });
        
        // Optional: Add visibility API to pause refresh when tab is not visible
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(refreshTimer);
            } else {
                // Resume auto-refresh when tab becomes visible again
                refreshTimer = setInterval(refreshDashboard, 180000);
            }
        });

        console.log('Queue Management Dashboard loaded successfully');
    </script>
</body>
</html>
