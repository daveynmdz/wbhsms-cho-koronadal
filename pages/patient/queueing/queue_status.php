<?php

/**
 * Patient Queue Status Interface
 * Purpose: Individual patient queue tracking and real-time status updates
 * Integrates with existing public display system and queue management
 */

// Start output buffering at the very beginning
ob_start();

// Set error reporting for debugging but don't display errors in production
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Never show errors to users in production
ini_set('log_errors', '1');      // Log errors for debugging

// Include patient session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));

// Load configuration first
require_once $root_path . '/config/env.php';

// Then load session management
require_once $root_path . '/config/session/patient_session.php';

// Ensure session is properly started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, bounce to patient login
if (!isset($_SESSION['patient_id'])) {
    ob_clean(); // Clear output buffer before redirect
    header('Location: ../auth/patient_login.php');
    exit();
}

require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

// Include queue code formatter helper
require_once $root_path . '/pages/queueing/queue_code_formatter.php';

// Initialize queue management service
$queueService = new QueueManagementService($pdo);
$patient_id = $_SESSION['patient_id'];

// Get patient's current queue status
$current_queue = null;
$patient_info = null;
$wait_time_info = null;

try {
    // Get current queue entry for patient
    $queue_query = "
        SELECT 
            qe.*,
            s.station_name,
            s.station_type,
            v.visit_id,
            v.visit_type,
            CASE 
                WHEN pf.priority_level IS NOT NULL THEN pf.priority_level 
                ELSE 'regular' 
            END as priority_level,
            a.appointment_date,
            a.appointment_time,
            p.first_name,
            p.last_name
        FROM queue_entries qe
        JOIN stations s ON qe.station_id = s.station_id
        LEFT JOIN visits v ON qe.visit_id = v.visit_id
        LEFT JOIN appointments a ON v.appointment_id = a.appointment_id
        LEFT JOIN patient_flags pf ON qe.patient_id = pf.patient_id AND pf.is_active = 1
        LEFT JOIN patients p ON qe.patient_id = p.patient_id
        WHERE qe.patient_id = ? 
            AND qe.status IN ('waiting', 'called', 'in_progress')
            AND DATE(qe.time_in) = CURDATE()
        ORDER BY qe.time_in DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($queue_query);
    $stmt->execute([$patient_id]);
    $current_queue = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($current_queue) {
        // Get waiting ahead count
        $waiting_ahead_query = "
            SELECT COUNT(*) as waiting_ahead 
            FROM queue_entries 
            WHERE station_id = ? 
                AND status IN ('waiting', 'called') 
                AND time_in < ? 
                AND DATE(time_in) = CURDATE()
        ";
        $stmt = $pdo->prepare($waiting_ahead_query);
        $stmt->execute([$current_queue['station_id'], $current_queue['time_in']]);
        $wait_result = $stmt->fetch(PDO::FETCH_ASSOC);

        $wait_time_info = [
            'waiting_ahead' => $wait_result['waiting_ahead'],
            'estimated_minutes' => max(1, $wait_result['waiting_ahead'] * 5) // 5 min average per patient
        ];
    }

} catch (Exception $e) {
    error_log("Queue status error: " . $e->getMessage());
    $error_message = "Unable to retrieve queue status. Please try again.";
}

// Initialize latest CHO appointment variable outside try block
if (!isset($latest_cho_appointment)) {
    $latest_cho_appointment = null;
}

// Get latest CHO appointment with QR code (facility_id = 1)
try {
    $cho_query = "
        SELECT 
            a.appointment_id,
            a.scheduled_date,
            a.scheduled_time,
            a.status,
            a.qr_code_path,
            f.name as facility_name,
            s.name as service_name
        FROM appointments a
        JOIN facilities f ON a.facility_id = f.facility_id
        LEFT JOIN services s ON a.service_id = s.service_id
        WHERE a.patient_id = ? 
            AND a.facility_id = 1 
            AND a.status IN ('confirmed', 'completed', 'checked_in')
        ORDER BY a.created_at DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($cho_query);
    $stmt->execute([$patient_id]);
    $latest_cho_appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching CHO appointment: " . $e->getMessage());
    $latest_cho_appointment = null;
}

// Generate simplified queue code display for patients (HHM-###)
function formatQueueCode($queue_data)
{
    if (!$queue_data) return '';

    // Use the actual queue_code from the database if available
    if (!empty($queue_data['queue_code'])) {
        return formatQueueCodeForPatient($queue_data['queue_code']);
    }

    // Fallback to generated format for legacy entries
    $time_prefix = date('H', strtotime($queue_data['time_in']));
    $time_suffix = date('H', strtotime($queue_data['time_in'])) < 12 ? 'A' : 'P';
    $sequence = str_pad($queue_data['queue_id'] ?? 1, 3, '0', STR_PAD_LEFT);

    return $time_prefix . $time_suffix . 'M-' . $sequence;
}

// Get patient flow steps based on visit type
function getPatientFlowSteps($queue_data)
{
    $basic_flow = [
        'checkin' => 'Check-in',
        'triage' => 'Triage/Vitals',
        'consultation' => 'Consultation'
    ];

    // Add conditional steps based on visit type
    if ($queue_data && $queue_data['visit_type'] !== 'consultation_only') {
        if ($queue_data['priority_level'] !== 'philhealth') {
            $basic_flow['billing'] = 'Billing';
        }
        $basic_flow['lab'] = 'Laboratory';
        $basic_flow['pharmacy'] = 'Pharmacy';
        $basic_flow['document'] = 'Documents';
    }

    return $basic_flow;
}

// Determine current step status
function getStepStatus($step_key, $current_station, $completed_stations = [])
{
    if (in_array($step_key, $completed_stations)) {
        return 'completed';
    } elseif ($step_key === $current_station) {
        return 'current';
    } else {
        return 'pending';
    }
}

// Set active page for sidebar highlighting
$activePage = 'queue_status';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Queue Status | CHO Koronadal Patient Portal</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Patient Queue Status Styles - Matching Dashboard.php UI Structure */
        .queue-dashboard-container {
            --cho-primary: #0077b6;
            --cho-primary-dark: #03045e;
            --cho-secondary: #6c757d;
            --cho-success: #2d6a4f;
            --cho-info: #17a2b8;
            --cho-warning: #ffc107;
            --cho-danger: #d00000;
            --cho-light: #f8f9fa;
            --cho-border: #dee2e6;
            --cho-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --cho-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --cho-border-radius: 0.5rem;
            --cho-border-radius-lg: 1rem;
            --cho-transition: all 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .queue-dashboard-container .content-area {
            padding: 1.5rem;
        }

        /* Breadcrumb and Page Header Styles - matching appointments.php */
        .breadcrumb {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        .breadcrumb a {
            color: #0077b6;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #023e8a;
            text-decoration: underline;
        }

        .breadcrumb span {
            margin: 0 0.5rem;
            color: #6c757d;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 0rem;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--cho-primary);
            margin: 0;
            display: flex;
            align-items: center;
            flex: 1;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .action-buttons .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--cho-border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: var(--cho-transition);
            cursor: pointer;
            font-size: 0.9rem;
        }

        .action-buttons .btn-primary {
            background: var(--cho-primary);
            color: white;
        }

        .action-buttons .btn-primary:hover {
            background: var(--cho-primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--cho-shadow);
        }

        .action-buttons .btn-secondary {
            background: var(--cho-secondary);
            color: white;
        }

        .action-buttons .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
            box-shadow: var(--cho-shadow);
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.5rem;
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .action-buttons {
                width: 100%;
                justify-content: stretch;
            }

            .action-buttons .btn {
                flex: 1;
                justify-content: center;
            }

            .hide-on-mobile {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        /* Breadcrumb Navigation - matching dashboard.php */
        .queue-dashboard-container .breadcrumb {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            font-size: 0.95rem;
            color: var(--cho-secondary);
        }

        .queue-dashboard-container .breadcrumb a {
            color: var(--cho-primary);
            text-decoration: none;
        }

        .queue-dashboard-container .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Page header styling - matching dashboard.php */
        .queue-dashboard-container .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 0rem;
            gap: 1rem;
        }

        .queue-dashboard-container .page-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--cho-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .queue-dashboard-container .page-header h1 i {
            font-size: 1.8rem;
        }

        .queue-dashboard-container .page-header p {
            color: var(--cho-secondary);
            font-size: 1.1rem;
            margin: 0;
        }

        /* Dashboard Section - matching dashboard.php card style */
        .queue-dashboard-container .dashboard-section {
            background: white;
            border-radius: var(--cho-border-radius);
            box-shadow: var(--cho-shadow);
            margin-bottom: 2rem;
        }

        .queue-dashboard-container .dashboard-section .section-header {
            padding: 1.5rem 2rem 1rem 2rem;
            border-bottom: 1px solid var(--cho-border);
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: var(--cho-border-radius) var(--cho-border-radius) 0 0;
        }

        .queue-dashboard-container .dashboard-section .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--cho-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .queue-dashboard-container .dashboard-section .section-title i {
            font-size: 1.1rem;
        }

        .queue-dashboard-container .dashboard-section .section-body {
            padding: 0.5rem 2rem 2rem 4rem;
        }

        /* Collapsible Section Styles */
        .collapsible-header {
            cursor: pointer;
            transition: var(--cho-transition);
            user-select: none;
        }

        .collapsible-header:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        }

        .collapsible-header .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .toggle-icon {
            transition: transform 0.3s ease;
            margin-left: auto;
            font-size: 0.9rem;
        }

        .toggle-icon.rotated {
            transform: rotate(180deg);
        }

        .collapsible-content {
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .collapsible-content.expanded {
            display: block !important;
            animation: slideDown 0.3s ease-out;
        }

        .collapsible-content.collapsed {
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
                padding-top: 0;
                padding-bottom: 0;
            }

            to {
                opacity: 1;
                max-height: 200px;
                padding-top: 0.5rem;
                padding-bottom: 2rem;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 1;
                max-height: 200px;
                padding-top: 0.5rem;
                padding-bottom: 2rem;
            }

            to {
                opacity: 0;
                max-height: 0;
                padding-top: 0;
                padding-bottom: 0;
            }
        }

        /* Queue Status Hero Section */
        .queue-dashboard-container .queue-status-hero {
            background: linear-gradient(135deg, var(--cho-primary) 0%, var(--cho-primary-dark) 100%);
            color: white;
            padding: 2.5rem;
            border-radius: var(--cho-border-radius-lg);
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: var(--cho-shadow-lg);
        }

        .queue-dashboard-container .queue-code-display {
            font-size: 3.5rem;
            font-weight: 900;
            margin: 1.5rem 0;
            letter-spacing: 4px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            font-family: 'Courier New', monospace;
        }

        .queue-dashboard-container .status-badge {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 1rem 0;
            font-size: 1.1rem;
        }

        .queue-dashboard-container .status-waiting {
            background: rgba(255, 193, 7, 0.2);
            color: #856404;
            border: 2px solid rgba(255, 193, 7, 0.5);
        }

        .queue-dashboard-container .status-called {
            background: rgba(40, 167, 69, 0.2);
            color: #155724;
            border: 2px solid rgba(40, 167, 69, 0.5);
            animation: pulse 2s infinite;
        }

        .queue-dashboard-container .status-in_progress {
            background: rgba(23, 162, 184, 0.2);
            color: #0c5460;
            border: 2px solid rgba(23, 162, 184, 0.5);
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Alert Messages - matching dashboard.php */
        .queue-dashboard-container .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--cho-border-radius);
            margin-bottom: 1.5rem;
        }

        .queue-dashboard-container .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
            color: #155724;
        }

        .queue-dashboard-container .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 2px solid #dc3545;
            color: #721c24;
        }

        .queue-dashboard-container .alert i {
            margin-right: 0.5rem;
        }

        .queue-dashboard-container .alert-called {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
            color: #155724;
            padding: 1.5rem;
            border-radius: var(--cho-border-radius);
            margin-bottom: 1rem;
            animation: alertPulse 1.5s infinite;
        }

        @keyframes alertPulse {

            0%,
            100% {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            }

            50% {
                background: linear-gradient(135deg, #b8dabc 0%, #a7d4a7 100%);
            }
        }

        /* Progress Flow Section - matching dashboard.php card structure */
        .queue-dashboard-container .progress-flow-container {
            background: white;
            padding: 2rem;
            border-radius: var(--cho-border-radius-lg);
            box-shadow: var(--cho-shadow);
            margin-bottom: 2rem;
        }

        .queue-dashboard-container .progress-flow {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 2rem 0;
        }

        .queue-dashboard-container .flow-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
            max-width: 120px;
        }

        .queue-dashboard-container .step-indicator {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            transition: var(--cho-transition);
            border: 3px solid transparent;
        }

        .queue-dashboard-container .step-indicator.completed {
            background: var(--cho-success);
            color: white;
            border-color: var(--cho-success);
        }

        .queue-dashboard-container .step-indicator.current {
            background: var(--cho-primary);
            color: white;
            border-color: var(--cho-primary);
            animation: currentPulse 2s infinite;
        }

        .queue-dashboard-container .step-indicator.pending {
            background: var(--cho-light);
            color: var(--cho-secondary);
            border-color: var(--cho-border);
        }

        @keyframes currentPulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(0, 119, 182, 0.7);
            }

            50% {
                box-shadow: 0 0 0 20px rgba(0, 119, 182, 0);
            }
        }

        .queue-dashboard-container .step-label {
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            color: var(--cho-secondary);
            line-height: 1.2;
        }

        .queue-dashboard-container .step-label.current {
            color: var(--cho-primary);
            font-weight: 700;
        }

        .queue-dashboard-container .progress-line {
            position: absolute;
            top: 30px;
            left: 60px;
            right: 60px;
            height: 4px;
            background: var(--cho-light);
            z-index: 1;
        }

        /* Information Cards Grid - matching dashboard.php stats grid */
        .queue-dashboard-container .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .queue-dashboard-container .info-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--cho-border-radius);
            box-shadow: var(--cho-shadow);
            border-left: 4px solid var(--cho-primary);
            transition: var(--cho-transition);
        }

        .queue-dashboard-container .info-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--cho-shadow-lg);
        }

        .queue-dashboard-container .info-card h4 {
            margin: 0 0 1rem 0;
            color: var(--cho-primary);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .queue-dashboard-container .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .queue-dashboard-container .summary-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .queue-dashboard-container .summary-label {
            font-size: 0.9rem;
            color: var(--cho-secondary);
            font-weight: 600;
        }

        .queue-dashboard-container .summary-value {
            font-size: 1rem;
            color: var(--cho-primary-dark);
            font-weight: 500;
        }

        .queue-dashboard-container .summary-value.highlight {
            font-weight: 700;
            color: var(--cho-primary);
            font-size: 1.1rem;
        }

        .queue-dashboard-container .wait-time-display {
            font-size: 2rem;
            font-weight: bold;
            color: var(--cho-primary);
            margin: 0.5rem 0;
        }

        .queue-dashboard-container .next-steps-card {
            background: linear-gradient(135deg, #e8f4f8 0%, #f0f8ff 100%);
            border: 2px solid rgba(23, 162, 184, 0.2);
            padding: 1.5rem;
            border-radius: var(--cho-border-radius);
            margin-bottom: 1rem;
        }

        .queue-dashboard-container .no-queue-message {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--cho-border-radius-lg);
            box-shadow: var(--cho-shadow);
        }

        .queue-dashboard-container .refresh-notice {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--cho-primary);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: var(--cho-border-radius);
            font-size: 0.9rem;
            box-shadow: var(--cho-shadow-lg);
            z-index: 1000;
        }

        /* Main Grid Layout - matching dashboard.php */
        .queue-dashboard-container .main-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Button styling - matching dashboard.php */
        .queue-dashboard-container .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--cho-border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: var(--cho-transition);
            cursor: pointer;
        }

        .queue-dashboard-container .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--cho-shadow);
        }

        .queue-dashboard-container .btn-primary {
            background: var(--cho-primary);
            color: white;
        }

        /* Mobile Responsiveness - matching dashboard.php */
        @media (max-width: 768px) {
            .queue-dashboard-container .content-area {
                padding: 1rem;
            }

            .queue-dashboard-container .queue-code-display {
                font-size: 2.5rem;
                letter-spacing: 2px;
            }

            .queue-dashboard-container .progress-flow {
                flex-wrap: wrap;
                justify-content: center;
            }

            .queue-dashboard-container .flow-step {
                margin: 0.5rem;
                flex: 0 0 auto;
            }

            .queue-dashboard-container .progress-line {
                display: none;
            }

            .queue-dashboard-container .info-cards {
                grid-template-columns: 1fr;
            }

            .queue-dashboard-container .page-header {
                padding: 1.5rem;
            }

            .queue-dashboard-container .page-header h1 {
                font-size: 1.8rem;
            }

            .queue-dashboard-container .dashboard-section .section-header {
                padding: 1rem 1.5rem 0.75rem 1.5rem;
            }

            .queue-dashboard-container .dashboard-section .section-body {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .queue-dashboard-container .queue-code-display {
                font-size: 2rem;
                letter-spacing: 1px;
            }

            .queue-dashboard-container .page-header h1 {
                font-size: 1.5rem;
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../../../includes/sidebar_patient.php'; ?>

    <div class="homepage">
        <div class="queue-dashboard-container">
            <div class="content-area">
                <!-- Breadcrumb Navigation -->
                <div class="breadcrumb" style="margin-top: 50px;">
                    <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <span> > </span>
                    <span style="color: #0077b6; font-weight: 600;">My Queue Status</span>
                </div>

                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-ticket-alt" style="margin-right: 0.5rem;"></i>My Queue Status</h1>
                    <div class="action-buttons">
                                <a href="../appointment/appointments/" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i>
                            <span class="hide-on-mobile">View Appointments</span>
                        </a>
                        <button class="btn btn-secondary" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt"></i>
                            <span class="hide-on-mobile">Refresh Status</span>
                        </button>
                    </div>
                </div>

                <!-- Instructions Section - Collapsible -->
                <div class="dashboard-section">
                    <div class="section-header collapsible-header" onclick="toggleInstructions()">
                        <h4 class="section-title">
                            <i class="fas fa-info-circle"></i> How Queueing Works
                            <i class="fas fa-chevron-down toggle-icon" id="instructions-toggle"></i>
                        </h4>
                    </div>
                    <div class="section-body collapsible-content" id="instructions-content" style="display: none;">
                        <ul style="margin: 0; color: var(--cho-secondary); font-size: 1rem; line-height: 1.6;">
                            <li>After check-in, you will receive a queue number for each station you need to visit.</li>
                            <li>Wait for your queue number to be called. You can monitor your status here or on the public display screens.</li>
                            <li>When your number is called, proceed to the station promptly and bring your queue ticket and valid ID.</li>
                            <li>If you miss your turn, notify the staff at the station for assistance.</li>
                            <li>Follow the progress bar below to see which steps you have completed and what's next.</li>
                        </ul>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($current_queue): ?>

                    <!-- Queue Status Hero Section -->
                    <div class="dashboard-section">
                        <div class="section-body">
                            <div class="queue-status-hero">
                                <h1><i class="fas fa-ticket-alt"></i> Your Queue Number</h1>
                                <div class="queue-code-display" id="queueCode">
                                    <?php echo formatQueueCode($current_queue); ?>
                                </div>
                                <div class="status-badge status-<?php echo $current_queue['status']; ?>">
                                    <?php
                                    switch ($current_queue['status']) {
                                        case 'waiting':
                                            echo '⏰ Waiting in Line';
                                            break;
                                        case 'called':
                                            echo '📢 NOW SERVING - Please Proceed!';
                                            break;
                                        case 'in_progress':
                                            echo '🏥 Currently Being Served';
                                            break;
                                        default:
                                            echo ucfirst($current_queue['status']);
                                    }
                                    ?>
                                </div>
                                <div style="font-size: 1.2rem; margin-top: 1rem;">
                                    <strong><?php echo htmlspecialchars($current_queue['station_name']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Special Alert for Called Status -->
                    <?php if ($current_queue['status'] === 'called'): ?>
                        <div class="alert-called">
                            <h3><i class="fas fa-bullhorn"></i> Your Number Has Been Called!</h3>
                            <p style="font-size: 1.1rem; margin: 0;">
                                Please proceed to <strong><?php echo htmlspecialchars($current_queue['station_name']); ?></strong> immediately.
                                Bring your queue ticket and valid ID.
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Progress Section -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h4 class="section-title"><i class="fas fa-route"></i> Your Visit Progress</h4>
                        </div>
                        <div class="section-body">
                            <div class="progress-flow">
                                <div class="progress-line"></div>
                                <?php
                                $flow_steps = getPatientFlowSteps($current_queue);
                                $current_station = $current_queue['station_type'];
                                foreach ($flow_steps as $step_key => $step_label):
                                    $status = getStepStatus($step_key, $current_station);
                                ?>
                                    <div class="flow-step">
                                        <div class="step-indicator <?php echo $status; ?>">
                                            <?php if ($status === 'completed'): ?>
                                                <i class="fas fa-check"></i>
                                            <?php elseif ($status === 'current'): ?>
                                                <i class="fas fa-user-clock"></i>
                                            <?php else: ?>
                                                <i class="fas fa-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="step-label <?php echo $status; ?>">
                                            <?php echo htmlspecialchars($step_label); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Information Cards Section -->
                    <div class="main-grid">
                        <div class="info-cards">
                            <!-- Wait Time Card -->
                            <div class="info-card">
                                <h4><i class="fas fa-hourglass-half"></i> Estimated Wait Time</h4>
                                <div class="wait-time-display">
                                    <?php echo $wait_time_info['estimated_minutes']; ?> minutes
                                </div>
                                <p style="color: var(--cho-secondary); margin: 0;">
                                    <?php echo $wait_time_info['waiting_ahead']; ?> patients ahead of you
                                </p>
                            </div>

                            <!-- Current Station Info -->
                            <div class="info-card">
                                <h4><i class="fas fa-map-marker-alt"></i> Current Station</h4>
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--cho-primary); margin: 0.5rem 0;">
                                    <?php echo htmlspecialchars($current_queue['station_name']); ?>
                                </div>
                                <p style="color: var(--cho-secondary); margin: 0;">
                                    Status: <?php echo ucfirst($current_queue['status']); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Next Steps Section -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h4 class="section-title"><i class="fas fa-info-circle"></i> What to Do Next</h4>
                        </div>
                        <div class="section-body">
                            <div class="next-steps-card">
                                <p id="nextStepsText" style="margin: 0; font-size: 1.1rem;">
                                    <?php
                                    switch ($current_queue['status']) {
                                        case 'waiting':
                                            echo "Please remain in the <strong>{$current_queue['station_name']}</strong> waiting area. Listen carefully for your queue number <strong>" . formatQueueCode($current_queue) . "</strong> to be announced.";
                                            break;
                                        case 'called':
                                            echo "<strong>YOUR NUMBER HAS BEEN CALLED!</strong> Please proceed to <strong>{$current_queue['station_name']}</strong> immediately. Bring your queue ticket and valid ID.";
                                            break;
                                        case 'in_progress':
                                            echo "You are currently being served at <strong>{$current_queue['station_name']}</strong>. Please follow the staff's instructions.";
                                            break;
                                        default:
                                            echo "Please check with the staff at the information desk for further instructions.";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- No Active Queue Section -->
                    <div class="dashboard-section">
                        <div class="section-body">
                            <div class="no-queue-message">
                                <i class="fas fa-info-circle" style="font-size: 4rem; color: var(--cho-info); margin-bottom: 1rem;"></i>
                                <h2>No Active Queue</h2>
                                <p style="font-size: 1.1rem; color: var(--cho-secondary); margin: 2rem 0;">
                                    You are not currently in any queue. If you have an appointment today in the City Health Office,<br>
                                    please proceed to the reception desk for check-in.
                                </p>
                                <a href="../appointment/appointments.php/" class="btn btn-primary">
                                    <i class="fas fa-calendar-alt"></i> View My Appointments
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Latest CHO Appointment Section -->
                <?php if ($latest_cho_appointment): ?>
                    <div class="dashboard-section">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-hospital"></i>
                                Latest CHO Appointment
                            </div>
                        </div>
                        <div class="section-body">
                            <div class="info-cards">
                                <div class="info-card">
                                    <h4><i class="fas fa-ticket-alt"></i> Appointment Details</h4>
                                    <div class="summary-grid">
                                        <div class="summary-item">
                                            <div class="summary-label">Appointment ID</div>
                                            <div class="summary-value highlight">APT-<?php echo str_pad($latest_cho_appointment['appointment_id'], 8, '0', STR_PAD_LEFT); ?></div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="summary-label">Facility</div>
                                            <div class="summary-value"><?php echo htmlspecialchars($latest_cho_appointment['facility_name']); ?></div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="summary-label">Service</div>
                                            <div class="summary-value"><?php echo htmlspecialchars($latest_cho_appointment['service_name'] ?? 'General Consultation'); ?></div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="summary-label">Scheduled Date</div>
                                            <div class="summary-value"><?php echo date('F j, Y (l)', strtotime($latest_cho_appointment['scheduled_date'])); ?></div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="summary-label">Scheduled Time</div>
                                            <div class="summary-value"><?php echo date('g:i A', strtotime($latest_cho_appointment['scheduled_time'])); ?></div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="summary-label">Status</div>
                                            <div class="summary-value" style="color: <?php echo $latest_cho_appointment['status'] === 'confirmed' ? '#28a745' : '#17a2b8'; ?>;">
                                                <i class="fas fa-<?php echo $latest_cho_appointment['status'] === 'confirmed' ? 'check-circle' : 'clock'; ?>"></i>
                                                <?php echo ucfirst($latest_cho_appointment['status']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($latest_cho_appointment['qr_code_path']): ?>
                                    <div class="info-card">
                                        <h4><i class="fas fa-qrcode"></i> QR Code for Check-in</h4>
                                        <div style="text-align: center; padding: 1rem;">
                                            <div class="qr-code-container" style="background: white; padding: 1rem; border-radius: 10px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                                <img src="data:image/png;base64,<?php echo base64_encode($latest_cho_appointment['qr_code_path']); ?>" 
                                                     alt="Appointment QR Code" 
                                                     style="width: 180px; height: 180px; border: 1px solid #dee2e6; border-radius: 4px;">
                                            </div>
                                            <?php if ($latest_cho_appointment['qr_verification_code']): ?>
                                                <div style="margin-top: 1rem;">
                                                    <div class="summary-item">
                                                        <div class="summary-label">Verification Code</div>
                                                        <div class="summary-value" style="font-family: monospace; background: #f8f9fa; padding: 0.5rem; border-radius: 4px; font-weight: bold;">
                                                            <?php echo htmlspecialchars($latest_cho_appointment['qr_verification_code']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div style="margin-top: 1rem; font-size: 0.9rem; color: #6c757d;">
                                                <i class="fas fa-info-circle"></i> 
                                                Present this QR code at check-in for instant verification
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="info-card">
                                        <h4><i class="fas fa-qrcode"></i> QR Code Status</h4>
                                        <div class="alert alert-warning" style="margin: 0;">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            QR code not available for this appointment
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Auto-refresh Notice -->
                <div class="refresh-notice">
                    <i class="fas fa-sync-alt"></i> Auto-updating every 30 seconds
                </div>

            </div>
        </div>
    </div>

    <script>
        // Collapsible instructions functionality
        function toggleInstructions() {
            const content = document.getElementById('instructions-content');
            const toggleIcon = document.getElementById('instructions-toggle');

            if (content.style.display === 'none') {
                // Expand
                content.style.display = 'block';
                content.classList.add('expanded');
                content.classList.remove('collapsed');
                toggleIcon.classList.add('rotated');

                // Store expanded state
                localStorage.setItem('instructionsExpanded', 'true');
            } else {
                // Collapse
                content.classList.add('collapsed');
                content.classList.remove('expanded');
                toggleIcon.classList.remove('rotated');

                // Wait for animation to complete before hiding
                setTimeout(() => {
                    content.style.display = 'none';
                }, 300);

                // Store collapsed state
                localStorage.setItem('instructionsExpanded', 'false');
            }
        }

        // Initialize instructions state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const content = document.getElementById('instructions-content');
            const toggleIcon = document.getElementById('instructions-toggle');
            const isExpanded = localStorage.getItem('instructionsExpanded') === 'true';

            if (isExpanded) {
                content.style.display = 'block';
                content.classList.add('expanded');
                toggleIcon.classList.add('rotated');
            }
        });

        // Auto-refresh functionality
        let refreshInterval;
        let lastStatus = '<?php echo $current_queue ? $current_queue['status'] : ''; ?>';

        function refreshQueueStatus() {
            fetch('../../../api/patient_queue_status.php', {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.queue) {
                        // Check if status changed
                        if (data.queue.status !== lastStatus) {
                            // Status changed - reload page for full update
                            window.location.reload();
                        } else {
                            // Update specific elements without full reload
                            updateQueueDisplay(data.queue, data.wait_info);
                        }
                        lastStatus = data.queue.status;
                    } else if (!data.queue && lastStatus) {
                        // Queue ended - reload to show no queue message
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.log('Queue status refresh error:', error);
                    // Continue with current display on error
                });
        }

        function updateQueueDisplay(queue, waitInfo) {
            // Update wait time if elements exist
            const waitTimeDisplay = document.querySelector('.wait-time-display');
            const waitingAheadText = document.querySelector('.info-card p');

            if (waitTimeDisplay && waitInfo) {
                waitTimeDisplay.textContent = waitInfo.estimated_minutes + ' minutes';
            }

            if (waitingAheadText && waitInfo) {
                waitingAheadText.textContent = waitInfo.waiting_ahead + ' patients ahead of you';
            }
        }

        // Start auto-refresh every 30 seconds
        refreshInterval = setInterval(refreshQueueStatus, 30000);

        // Refresh on page focus (when user returns to tab)
        window.addEventListener('focus', function() {
            refreshQueueStatus();
        });

        // Add visual feedback for called status
        <?php if ($current_queue && $current_queue['status'] === 'called'): ?>
            // Play notification sound if available
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmMeCSGH0fPTgjMGHm7A7+OZRA0PVqzn77BdGAg+ltryxnkpBSl+zPLZjDoIGGS57+OWT');
                audio.volume = 0.3;
                audio.play().catch(e => console.log('Audio play failed:', e));
            } catch (e) {
                console.log('Audio notification not available:', e);
            }
        <?php endif; ?>

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>

</html>