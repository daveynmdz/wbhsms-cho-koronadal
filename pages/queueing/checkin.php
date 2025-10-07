<?php
/**
 * Patient Check-In Module
 * City Health Office of Koronadal
 * 
 * Purpose: Allow authorized staff to confirm patient arrivals and add them to active queue
 * Access: Admin, Records Officer, DHO, BHW
 */

// Include employee session configuration first
require_once '../../config/session/employee_session.php';

// Include necessary files
require_once '../../config/db.php';
require_once '../../includes/topbar.php';

// Access Control - Only allow specific roles
$allowed_roles = ['admin', 'records_officer', 'dho', 'bhw'];
$user_role = $_SESSION['role'] ?? '';

if (!isset($_SESSION['employee_id']) || !in_array($user_role, $allowed_roles)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - CHO Koronadal</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link rel="stylesheet" href="../../assets/css/dashboard.css">
        <link rel="stylesheet" href="../../assets/css/sidebar.css">
    </head>
    <body>
        <?php 
        $sidebar_file = "../../includes/sidebar_" . strtolower(str_replace(' ', '_', $_SESSION['role'] ?? 'guest')) . ".php";
        if (file_exists($sidebar_file)) {
            include $sidebar_file;
        }
        ?>
        
        <div class="main-content">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../../index.php">Home</a> > 
                <a href="../management/">Queue Management</a> > 
                <span>Patient Check-In</span>
            </div>
            
            <div class="access-denied-container">
                <div class="access-denied-card">
                    <i class="fas fa-lock fa-5x text-danger mb-4"></i>
                    <h2>Access Denied</h2>
                    <p>You don't have permission to access the Patient Check-In module.</p>
                    <p>This module is restricted to: admin, records_officer, dho, and bhw roles only.</p>
                    <a href="../../index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Return to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <style>
        .access-denied-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 60vh;
        }
        .access-denied-card {
            text-align: center;
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        </style>
    </body>
    </html>
    <?php
    exit();
}

// Initialize variables
$message = '';
$error = '';
$today = date('Y-m-d');
$stats = ['total' => 0, 'checked_in' => 0, 'completed' => 0];
$search_results = [];
$barangays = [];

// Get today's statistics
try {
    // Total appointments today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_date) = ? AND facility_id = 1");
    $stmt->execute([$today]);
    $stats['total'] = $stmt->fetchColumn();
    
    // Checked-in patients today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE DATE(visit_date) = ? AND facility_id = 1");
    $stmt->execute([$today]);
    $stats['checked_in'] = $stmt->fetchColumn();
    
    // Completed appointments today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_date) = ? AND facility_id = 1 AND status = 'completed'");
    $stmt->execute([$today]);
    $stats['completed'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Statistics error: " . $e->getMessage());
}

// Get barangays for dropdown
try {
    $stmt = $pdo->prepare("SELECT DISTINCT b.barangay_name FROM barangay b 
                          INNER JOIN patients p ON b.barangay_id = p.barangay_id 
                          WHERE b.status = 'active' ORDER BY b.barangay_name");
    $stmt->execute();
    $barangays = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Barangays fetch error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'search':
            $appointment_id = trim($_POST['appointment_id'] ?? '');
            $patient_id = trim($_POST['patient_id'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $barangay = trim($_POST['barangay'] ?? '');
            $appointment_date = $_POST['appointment_date'] ?? $today;
            
            // Build search query
            $query = "SELECT a.appointment_id, a.scheduled_date as appointment_date, a.scheduled_time as appointment_time, a.status,
                             p.patient_id, p.first_name, p.last_name, b.barangay_name as barangay,
                             p.isSenior, p.isPWD, p.philhealth_id_number as philhealth_id
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
                      WHERE a.facility_id = 1";
            
            $params = [];
            
            // Add search conditions
            if (!empty($appointment_id)) {
                // Handle APT-00000024 format or numeric
                $clean_id = str_replace('APT-', '', $appointment_id);
                $query .= " AND a.appointment_id = ?";
                $params[] = $clean_id;
            }
            
            if (!empty($patient_id)) {
                $query .= " AND p.patient_id = ?";
                $params[] = $patient_id;
            }
            
            if (!empty($last_name)) {
                $query .= " AND p.last_name LIKE ?";
                $params[] = '%' . $last_name . '%';
            }
            
            if (!empty($barangay)) {
                $query .= " AND b.barangay_name = ?";
                $params[] = $barangay;
            }
            
            $query .= " AND DATE(a.scheduled_date) = ? ORDER BY a.scheduled_time ASC";
            $params[] = $appointment_date;
            
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $error = "Search failed: " . $e->getMessage();
            }
            break;
            
        case 'checkin':
            $appointment_id = $_POST['appointment_id'] ?? 0;
            $patient_id = $_POST['patient_id'] ?? 0;
            
            if ($appointment_id && $patient_id) {
                try {
                    $pdo->beginTransaction();
                    
                    // Validate employee station assignment for check-in
                    $employee_id = $_SESSION['employee_id'] ?? $_SESSION['user_id'];
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignment_schedules 
                                          WHERE employee_id = ? AND station_type = 'checkin' 
                                          AND DATE(?) BETWEEN start_date AND end_date");
                    $stmt->execute([$employee_id, $today]);
                    $is_assigned = $stmt->fetchColumn() > 0;
                    
                    if (!$is_assigned && $user_role !== 'admin') {
                        throw new Exception("You are not assigned to the Check-In station for today.");
                    }
                    
                    // Create/Update visit entry
                    $stmt = $pdo->prepare("INSERT INTO visits (patient_id, facility_id, appointment_id, visit_date, time_in) 
                                          VALUES (?, 1, ?, CURDATE(), NOW()) 
                                          ON DUPLICATE KEY UPDATE time_in = NOW()");
                    $stmt->execute([$patient_id, $appointment_id]);
                    
                    // Get patient details for queue priority
                    $stmt = $pdo->prepare("SELECT isSenior, isPWD FROM patients WHERE patient_id = ?");
                    $stmt->execute([$patient_id]);
                    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $priority = ($patient['isSenior'] || $patient['isPWD']) ? 'priority' : 'normal';
                    
                    // Generate queue code (format: 08A-001)
                    $queue_prefix = date('d') . 'A';
                    $stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM queue_entries WHERE DATE(created_at) = CURDATE()");
                    $stmt->execute();
                    $queue_number = str_pad($stmt->fetchColumn(), 3, '0', STR_PAD_LEFT);
                    $queue_code = $queue_prefix . '-' . $queue_number;
                    
                    // Find first open triage station
                    $stmt = $pdo->prepare("SELECT station_id FROM stations WHERE station_type = 'triage' AND is_open = 1 LIMIT 1");
                    $stmt->execute();
                    $station_id = $stmt->fetchColumn();
                    
                    if (!$station_id) {
                        throw new Exception("No open triage stations available.");
                    }
                    
                    // Insert queue entry
                    $stmt = $pdo->prepare("INSERT INTO queue_entries (patient_id, station_id, queue_code, status, priority, appointment_id, created_at) 
                                          VALUES (?, ?, ?, 'waiting', ?, ?, NOW())");
                    $stmt->execute([$patient_id, $station_id, $queue_code, $priority, $appointment_id]);
                    
                    // Update appointment status
                    $stmt = $pdo->prepare("UPDATE appointments SET status = 'checked_in' WHERE appointment_id = ?");
                    $stmt->execute([$appointment_id]);
                    
                    $pdo->commit();
                    $message = "Patient successfully checked in and added to Triage queue ($queue_code).";
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $error = "Check-in failed: " . $e->getMessage();
                }
            }
            break;
            
        case 'flag_patient':
            $appointment_id = $_POST['appointment_id'] ?? 0;
            $patient_id = $_POST['patient_id'] ?? 0;
            $flag_type = $_POST['flag_type'] ?? '';
            $remarks = trim($_POST['remarks'] ?? '');
            
            if ($appointment_id && $patient_id && $flag_type) {
                try {
                    $pdo->beginTransaction();
                    
                    // Insert patient flag
                    $stmt = $pdo->prepare("INSERT INTO patient_flags (patient_id, appointment_id, flag_type, remarks, created_by, created_at) 
                                          VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$patient_id, $appointment_id, $flag_type, $remarks, $_SESSION['employee_id'] ?? $_SESSION['user_id']]);
                    
                    // If false_patient_booked, auto-cancel appointment
                    if ($flag_type === 'false_patient_booked') {
                        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
                        $stmt->execute([$appointment_id]);
                        
                        // Log cancellation
                        $stmt = $pdo->prepare("INSERT INTO appointment_logs (appointment_id, patient_id, action, reason, created_by, created_at) 
                                              VALUES (?, ?, 'cancelled', 'Auto-cancelled due to false booking flag', ?, NOW())");
                        $stmt->execute([$appointment_id, $patient_id, $_SESSION['employee_id'] ?? $_SESSION['user_id']]);
                    }
                    
                    $pdo->commit();
                    $message = "Patient flag recorded successfully." . ($flag_type === 'false_patient_booked' ? " Appointment has been cancelled." : "");
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $error = "Flag operation failed: " . $e->getMessage();
                }
            }
            break;
            
        case 'cancel_appointment':
            $appointment_id = $_POST['appointment_id'] ?? 0;
            $patient_id = $_POST['patient_id'] ?? 0;
            $cancel_reason = trim($_POST['cancel_reason'] ?? '');
            
            if ($appointment_id && $patient_id && $cancel_reason) {
                try {
                    $pdo->beginTransaction();
                    
                    // Update appointment status
                    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
                    $stmt->execute([$appointment_id]);
                    
                    // Log cancellation
                    $stmt = $pdo->prepare("INSERT INTO appointment_logs (appointment_id, patient_id, action, reason, created_by, created_at) 
                                          VALUES (?, ?, 'cancelled', ?, ?, NOW())");
                    $stmt->execute([$appointment_id, $patient_id, $cancel_reason, $_SESSION['employee_id'] ?? $_SESSION['user_id']]);
                    
                    $pdo->commit();
                    $message = "Appointment successfully cancelled.";
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $error = "Cancellation failed: " . $e->getMessage();
                }
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Check-In - CHO Koronadal</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* CSS Variables for consistent styling */
        :root {
            --primary: #0077b6;
            --primary-dark: #03045e;
            --success: #28a745;
            --secondary: #6c757d;
            --dark: #212529;
            --border: #e0e0e0;
            --border-radius: 8px;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-lg: 0 4px 8px rgba(0,0,0,0.15);
            --transition: all 0.3s ease;
        }
        
        /* CHO Dashboard Framework - Matching dashboard.php styling */
        .checkin-container {
            /* CHO Theme Variables */
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

        .checkin-container .content-area {
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
        }

        /* Breadcrumb Navigation - matching dashboard */
        .checkin-container .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .checkin-container .breadcrumb a {
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

        .checkin-container .breadcrumb a:hover {
            background: rgba(0, 119, 182, 0.1);
            color: var(--primary-dark);
        }

        .checkin-container .breadcrumb-separator {
            color: var(--secondary);
            font-size: 0.7rem;
            opacity: 0.6;
        }

        .checkin-container .breadcrumb-current {
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

        /* Page header styling - matching dashboard */
        .checkin-container .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .checkin-container .page-header h1 {
            color: #0077b6;
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkin-container .page-header h1 i {
            color: #0077b6;
        }

        /* Total count badges styling - matching dashboard */
        .checkin-container .total-count {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
        }

        .checkin-container .total-count .badge {
            min-width: 120px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            border-radius: 25px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .checkin-container .total-count .badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Card container styling - matching dashboard */
        .checkin-container .card-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .checkin-container .section-header {
            display: flex;
            align-items: center;
            padding: 0 0 15px 0;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(0, 119, 182, 0.2);
        }
        
        .checkin-container .section-header h4 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 18px;
            font-weight: 600;
        }
        
        .checkin-container .section-header h4 i {
            color: var(--primary);
            margin-right: 8px;
        }

        /* Statistics Cards - matching dashboard */
        .checkin-container .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .checkin-container .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .checkin-container .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .checkin-container .stat-card.total {
            border-left-color: var(--primary);
        }

        .checkin-container .stat-card.checked-in {
            border-left-color: var(--success);
        }

        .checkin-container .stat-card.completed {
            border-left-color: var(--info);
        }

        .checkin-container .stat-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .checkin-container .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .checkin-container .stat-icon.total {
            background: linear-gradient(135deg, #0077b6, #023e8a);
        }

        .checkin-container .stat-icon.checked-in {
            background: linear-gradient(135deg, #20c997, #1a9471);
        }

        .checkin-container .stat-icon.completed {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }

        .checkin-container .stat-details h3 {
            margin: 0 0 0.25rem 0;
            color: var(--secondary);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .checkin-container .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .checkin-container .stat-subtitle {
            font-size: 0.8rem;
            color: var(--secondary);
            margin: 0.25rem 0 0 0;
        }

        /* Alert Messages - matching dashboard */
        .checkin-container .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left-width: 4px;
            border-left-style: solid;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .checkin-container .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
            border-left-color: #28a745;
        }
        
        .checkin-container .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            border-left-color: #dc3545;
        }

        .checkin-container .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
            border-left-color: #17a2b8;
        }
        
        .checkin-container .alert i {
            margin-right: 0;
        }
        /* Form Elements - matching dashboard */
        .checkin-container .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .checkin-container .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .checkin-container .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .checkin-container .form-control {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .checkin-container .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        /* Action buttons - matching dashboard style */
        .checkin-container .btn {
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
            gap: 0.5rem;
            text-decoration: none;
            font-weight: 600;
        }
        
        .checkin-container .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
        }

        .checkin-container .btn-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }

        .checkin-container .btn-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }

        .checkin-container .btn-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }

        .checkin-container .btn-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }

        .checkin-container .btn-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }

        .checkin-container .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .checkin-container .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
            grid-column: 1 / -1;
            margin-top: 1rem;
        }
        /* QR Scanner Section - matching dashboard card style */
        .checkin-container .qr-scanner-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .checkin-container .qr-reader-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            border: 2px solid #3498db;
            border-radius: 8px;
            overflow: hidden;
            background: #000;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkin-container .qr-reader-container video {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Results Table - matching dashboard table */
        .checkin-container .table-responsive {
            overflow-x: auto;
            border-radius: var(--border-radius);
            margin-top: 10px;
        }
        
        .checkin-container .results-table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: var(--shadow);
            background: white;
        }

        .checkin-container .results-table th {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .checkin-container .results-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .checkin-container .results-table tbody tr:hover {
            background-color: rgba(240, 247, 255, 0.6);
            transition: background-color 0.2s;
        }
        
        .checkin-container .results-table tr:last-child td {
            border-bottom: none;
        }

        /* Status and Priority Badges - matching dashboard */
        .checkin-container .badge,
        .checkin-container .status-badge {
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
        
        .checkin-container .bg-success,
        .checkin-container .status-confirmed {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }
        
        .checkin-container .bg-info,
        .checkin-container .status-checked_in {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }
        
        .checkin-container .bg-primary,
        .checkin-container .status-completed {
            background: linear-gradient(135deg, #0096c7, #0077b6);
        }
        
        .checkin-container .bg-danger,
        .checkin-container .status-cancelled {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }
        
        .checkin-container .bg-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }
        
        .checkin-container .bg-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }

        .checkin-container .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .checkin-container .priority-senior {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }

        .checkin-container .priority-pwd {
            background: linear-gradient(135deg, #d1ecf1, #74b9ff);
            color: #0c5460;
        }
        /* Modal Styles - matching dashboard framework */
        .checkin-container .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .checkin-container .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .checkin-container .modal-content {
            background: white;
            border-radius: var(--border-radius);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }
        
        .checkin-container .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        
        .checkin-container .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .checkin-container .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            opacity: 0.8;
            transition: var(--transition);
        }

        .checkin-container .modal-close:hover {
            opacity: 1;
        }
        
        .checkin-container .modal-body {
            padding: 1.5rem;
        }
        
        .checkin-container .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            background: #f8f9fa;
        }

        /* Patient Info Grid */
        .checkin-container .patient-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .checkin-container .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .checkin-container .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .checkin-container .info-value {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }

        /* Footer Info */
        .checkin-container .footer-info {
            margin-top: 2rem;
            text-align: center;
            color: #6c757d;
            font-size: 0.85rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        /* Mobile Responsive - matching dashboard */
        @media (max-width: 768px) {
            .checkin-container .content-area {
                padding: 1rem;
            }
            
            .checkin-container .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .checkin-container .total-count {
                width: 100%;
                justify-content: flex-start;
                gap: 0.75rem;
            }

            .checkin-container .total-count .badge {
                min-width: 100px;
                font-size: 0.8rem;
                padding: 6px 12px;
            }
            
            .checkin-container .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .checkin-container .search-form {
                grid-template-columns: 1fr;
            }
            
            .checkin-container .form-actions {
                flex-direction: column;
            }
            
            .checkin-container .results-table {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .checkin-container .total-count {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .checkin-container .total-count .badge {
                width: 100%;
                min-width: auto;
                text-align: center;
            }
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.total { border-left-color: #667eea; }
        .stat-card.checked-in { border-left-color: #28a745; }
        .stat-card.completed { border-left-color: #17a2b8; }
        
        .stat-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.total { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.checked-in { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-icon.completed { background: linear-gradient(135deg, #17a2b8, #138496); }
        
        .stat-details h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }
        
        .stat-details p {
            margin: 0.25rem 0 0 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .card-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }
        
        .qr-scanner-card {
            border: 2px dashed #667eea;
            background: linear-gradient(135deg, #f8f9ff, #fff);
            text-align: center;
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .qr-scanner-box {
            width: 200px;
            height: 200px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #6c757d;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8, #6a42a0);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .results-table th,
        .results-table td {
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .results-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-checked_in { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #cce7ff; color: #004085; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .priority-senior { background: #fff3cd; color: #856404; }
        .priority-pwd { background: #d1ecf1; color: #0c5460; }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
            margin-left: auto;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .patient-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }
        
        .footer-info {
            margin-top: 2rem;
            text-align: center;
            color: #6c757d;
            font-size: 0.85rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .results-table {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php 
    $sidebar_file = "../../includes/sidebar_" . strtolower(str_replace(' ', '_', $user_role)) . ".php";
    if (file_exists($sidebar_file)) {
        include $sidebar_file;
    } else {
        include "../../includes/sidebar_admin.php";
    }
    ?>
    
    <main class="homepage">
        <div class="checkin-container">
            <div class="content-area">
                <!-- Breadcrumb Navigation - matching dashboard -->
                <div class="breadcrumb" style="margin-top: 50px;">
                    <a href="../../index.php"><i class="fas fa-home"></i> Home</a>
                    <i class="fas fa-chevron-right breadcrumb-separator"></i>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Queue Dashboard</a>
                    <i class="fas fa-chevron-right breadcrumb-separator"></i>
                    <span class="breadcrumb-current"><i class="fas fa-user-check"></i> Patient Check-In</span>
                </div>

                <!-- Page Header with Status Badges - matching dashboard -->
                <div class="page-header">
                    <h1><i class="fas fa-user-check"></i> Patient Check-In</h1>
                    <div class="total-count">
                        <span class="badge bg-primary"><?php echo number_format($stats['total']); ?> Total Today</span>
                        <span class="badge bg-success"><?php echo number_format($stats['checked_in']); ?> Checked-In</span>
                        <span class="badge bg-info"><?php echo number_format($stats['completed']); ?> Completed</span>
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
                        <div class="stat-card total">
                            <div class="stat-content">
                                <div class="stat-icon total">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="stat-details">
                                    <h3>Total Appointments</h3>
                                    <p class="stat-value"><?php echo $stats['total']; ?></p>
                                    <p class="stat-subtitle">Scheduled for today</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card checked-in">
                            <div class="stat-content">
                                <div class="stat-icon checked-in">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <div class="stat-details">
                                    <h3>Patients Checked-In</h3>
                                    <p class="stat-value"><?php echo $stats['checked_in']; ?></p>
                                    <p class="stat-subtitle">Currently in system</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card completed">
                            <div class="stat-content">
                                <div class="stat-icon completed">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="stat-details">
                                    <h3>Appointments Completed</h3>
                                    <p class="stat-value"><?php echo $stats['completed']; ?></p>
                                    <p class="stat-subtitle">Finished today</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search & Filter Section -->
                <div class="card-container">
                    <div class="section-header">
                        <h4><i class="fas fa-search"></i> Search & Filter Appointments</h4>
                    </div>
            
            <form method="POST" class="search-form">
                <input type="hidden" name="action" value="search">
                
                <div class="form-group">
                    <label class="form-label">Appointment ID</label>
                    <input type="text" name="appointment_id" class="form-control" 
                           placeholder="APT-00000024 or 24" value="<?php echo $_POST['appointment_id'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Patient ID</label>
                    <input type="number" name="patient_id" class="form-control" 
                           placeholder="Patient ID" value="<?php echo $_POST['patient_id'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" 
                           placeholder="Enter last name" value="<?php echo $_POST['last_name'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Barangay</label>
                    <select name="barangay" class="form-control">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangays as $barangay): ?>
                        <option value="<?php echo htmlspecialchars($barangay); ?>" 
                                <?php echo ($_POST['barangay'] ?? '') === $barangay ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($barangay); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date of Appointment</label>
                    <input type="date" name="appointment_date" class="form-control" 
                           value="<?php echo $_POST['appointment_date'] ?? $today; ?>">
                </div>
                
                <div class="form-actions" style="grid-column: 1 / -1; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear Filter
                    </button>
                    <button type="button" class="btn btn-info" id="qr-scanner-toggle">
                        <i class="fas fa-qrcode"></i> Start QR Scanner
                    </button>
                </div>
            </form>
            
            <!-- QR Code Scanner Section -->
            <div id="qr-scanner-container" class="qr-scanner-section card-container">
                <div class="qr-scanner-header">
                    <h5><i class="fas fa-qrcode"></i> QR Code Scanner</h5>
                    <p class="qr-scanner-desc">Point your camera at the patient's QR code to automatically check them in</p>
                </div>
                
                <div class="qr-scanner-controls">
                    <div class="qr-scanner-buttons">
                        <button type="button" class="btn btn-success" id="start-scanner-btn">
                            <i class="fas fa-camera"></i> Start Camera
                        </button>
                        <button type="button" class="btn btn-danger" id="stop-scanner-btn" style="display: none;">
                            <i class="fas fa-stop"></i> Stop Camera
                        </button>
                        <select id="camera-select" class="form-control" style="display: none;">
                            <option value="">Select Camera...</option>
                        </select>
                    </div>
                </div>
                
                <div class="qr-scanner-preview">
                    <div id="qr-reader" class="qr-reader-container"></div>
                    <div id="qr-scanner-result" class="qr-scanner-result"></div>
                </div>
                
                <div class="qr-scanner-status">
                    <div id="qr-status-message" class="status-message">
                        <i class="fas fa-info-circle"></i> Click "Start Camera" to begin scanning
                    </div>
                </div>
            </div>
            
            <!-- Search Results -->
            <?php if (!empty($search_results)): ?>
            <div class="table-responsive">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Appointment ID</th>
                            <th>Patient ID</th>
                            <th>Last Name</th>
                            <th>First Name</th>
                            <th>Barangay</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results as $row): ?>
                        <tr>
                            <td><strong>APT-<?php echo str_pad($row['appointment_id'], 8, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['barangay']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($row['appointment_time'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['isSenior']): ?>
                                <span class="priority-badge priority-senior">
                                    <i class="fas fa-user"></i> Senior
                                </span>
                                <?php endif; ?>
                                <?php if ($row['isPWD']): ?>
                                <span class="priority-badge priority-pwd">
                                    <i class="fas fa-wheelchair"></i> PWD
                                </span>
                                <?php endif; ?>
                                <?php if (!$row['isSenior'] && !$row['isPWD']): ?>
                                <span class="priority-badge">Normal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm" 
                                        onclick="viewPatient(<?php echo $row['appointment_id']; ?>, <?php echo $row['patient_id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'search'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                No appointments found matching the search criteria.
            </div>
            <?php endif; ?>
        </div>
                
                <!-- Footer Info -->
                <div class="footer-info">
                    <p>Last updated: <?php echo date('F d, Y g:i A'); ?> | Total results displayed: <?php echo count($search_results); ?></p>
                </div>
            </div>
        </div>
    </main>
    
    <div class="checkin-container">
    <!-- Patient Details Modal -->
    <div id="patientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Patient Details</h3>
                <button type="button" class="modal-close" onclick="closeModal('patientModal')">&times;</button>
            </div>
            <div class="modal-body" id="patientModalBody">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Flag Patient Modal -->
    <div id="flagModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Flag Patient</h3>
                <button type="button" class="modal-close" onclick="closeModal('flagModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="flag_patient">
                <input type="hidden" name="appointment_id" id="flagAppointmentId">
                <input type="hidden" name="patient_id" id="flagPatientId">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Flag Type</label>
                        <select name="flag_type" class="form-control" required>
                            <option value="">Select flag type...</option>
                            <option value="false_senior">False Senior Citizen</option>
                            <option value="false_philhealth">False PhilHealth</option>
                            <option value="false_pwd">False PWD</option>
                            <option value="false_patient_booked">False Patient Booking</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4" 
                                  placeholder="Enter detailed remarks..." required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('flagModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-flag"></i> Submit Flag
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Cancel Appointment Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cancel Appointment</h3>
                <button type="button" class="modal-close" onclick="closeModal('cancelModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="cancel_appointment">
                <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                <input type="hidden" name="patient_id" id="cancelPatientId">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Cancellation Reason</label>
                        <select name="cancel_reason" class="form-control" required>
                            <option value="">Select reason...</option>
                            <option value="Patient no-show">Patient no-show</option>
                            <option value="Duplicate booking">Duplicate booking</option>
                            <option value="Patient requested cancellation">Patient requested cancellation</option>
                            <option value="Medical emergency">Medical emergency</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('cancelModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cancel Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Success</h3>
                <button type="button" class="modal-close" onclick="closeModal('successModal')">&times;</button>
            </div>
            <div class="modal-body" id="successModalBody">
                <!-- Content set via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeModal('successModal')">Done</button>
            </div>
        </div>
    </div>

    <!-- jQuery and SweetAlert2 Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Html5-QrCode Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <script>
        // ==========================================
        // PATIENT CHECK-IN MANAGEMENT SYSTEM
        // jQuery-based AJAX functionality for CHO
        // ==========================================
        
        // Current employee ID from session
        const currentEmployeeId = <?php echo $_SESSION['employee_id']; ?>;
        
        // Initialize when DOM is ready
        $(document).ready(function() {
            console.log('Patient Check-In System initialized');
            
            // Load initial summary data
            refreshSummaryCards();
            
            // Bind search functionality to existing search button
            $('.search-form').on('submit', function(e) {
                e.preventDefault();
                searchAppointments();
            });
            
            // Initialize QR Scanner
            initializeQRScanner();
            
            // Auto-refresh summary every 30 seconds
            setInterval(refreshSummaryCards, 30000);
        });

        // ==========================================
        // APPOINTMENT SEARCH FUNCTIONALITY
        // ==========================================
        
        function searchAppointments() {
            const form = $('.search-form');
            const filters = {
                appointment_id: $('input[name="appointment_id"]').val(),
                patient_id: $('input[name="patient_id"]').val(),
                last_name: $('input[name="last_name"]').val(),
                barangay: $('select[name="barangay"]').val(),
                appointment_date: $('input[name="appointment_date"]').val()
            };
            
            // Validate that at least one search criteria is provided
            if (!filters.appointment_id && !filters.patient_id && !filters.last_name && 
                !filters.barangay && !filters.appointment_date) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Search Required',
                    text: 'Please enter at least one search criteria'
                });
                return;
            }
            
            // Show loading indicator
            showLoadingOverlay('Searching appointments...');
            
            $.post('checkin_actions.php', {
                action: 'search_appointments',
                ...filters
            })
            .done(function(response) {
                hideLoadingOverlay();
                
                if (response.success) {
                    populateAppointmentsTable(response.data);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Search Failed',
                        text: response.message || 'Failed to search appointments'
                    });
                }
            })
            .fail(function(xhr, status, error) {
                hideLoadingOverlay();
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to connect to server: ' + error
                });
            });
        }

        function populateAppointmentsTable(appointments) {
            const tbody = $('.appointments-table tbody');
            tbody.empty();
            
            if (!appointments || appointments.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="8" class="no-results">
                            <i class="fas fa-search"></i>
                            <p>No appointments found matching your search criteria</p>
                        </td>
                    </tr>
                `);
                return;
            }
            
            appointments.forEach(function(appt) {
                const statusClass = getStatusClass(appt.status);
                const statusIcon = getStatusIcon(appt.status);
                
                const row = $(`
                    <tr data-appointment-id="${appt.appointment_id}">
                        <td>${appt.appointment_id}</td>
                        <td>${appt.patient_name}</td>
                        <td>${appt.contact_number || 'N/A'}</td>
                        <td>${appt.barangay || 'N/A'}</td>
                        <td>${appt.service_name || 'General'}</td>
                        <td>${formatDate(appt.scheduled_date)}</td>
                        <td>${formatTime(appt.scheduled_time)}</td>
                        <td>
                            <span class="status-badge ${statusClass}">
                                <i class="${statusIcon}"></i> ${appt.status}
                            </span>
                        </td>
                        <td class="actions">
                            <button type="button" class="btn btn-sm btn-primary" 
                                    onclick="showPatientDetails(${appt.appointment_id}, ${appt.patient_id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                            ${appt.status === 'confirmed' ? `
                                <button type="button" class="btn btn-sm btn-success" 
                                        onclick="checkinPatient(${appt.appointment_id})">
                                    <i class="fas fa-user-check"></i> Check-In
                                </button>
                            ` : ''}
                            <button type="button" class="btn btn-sm btn-warning" 
                                    onclick="flagPatient(${appt.patient_id}, ${appt.appointment_id})">
                                <i class="fas fa-flag"></i> Flag
                            </button>
                            ${appt.status !== 'cancelled' && appt.status !== 'completed' ? `
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="cancelAppointment(${appt.appointment_id})">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `);
                
                tbody.append(row);
            });
        }

        // ==========================================
        // PATIENT CHECK-IN OPERATIONS
        // ==========================================
        
        function checkinPatient(appointmentId) {
            Swal.fire({
                title: 'Check-In Patient',
                text: 'Are you sure you want to check in this patient? They will be added to the triage queue.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-user-check"></i> Yes, Check-In',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    performCheckin(appointmentId);
                }
            });
        }

        function performCheckin(appointmentId) {
            showLoadingOverlay('Checking in patient...');
            
            $.post('checkin_actions.php', {
                action: 'checkin_patient',
                appointment_id: appointmentId,
                employee_id: currentEmployeeId
            })
            .done(function(response) {
                hideLoadingOverlay();
                
                if (response.success) {
                    // Success notification with queue details
                    Swal.fire({
                        icon: 'success',
                        title: 'Patient Checked In Successfully!',
                        html: `
                            <div style="text-align: left; margin-top: 1rem;">
                                <p><strong>Queue Number:</strong> ${response.data.queue_code}</p>
                                <p><strong>Station:</strong> ${response.data.station_name}</p>
                                <p><strong>Patient:</strong> ${response.data.patient_name}</p>
                                <p><strong>Priority:</strong> ${response.data.priority}</p>
                            </div>
                        `,
                        showConfirmButton: true,
                        timer: 5000
                    });
                    
                    // Remove the row from table
                    $(`tr[data-appointment-id="${appointmentId}"]`).fadeOut(500, function() {
                        $(this).remove();
                    });
                    
                    // Refresh summary cards
                    refreshSummaryCards();
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Check-In Failed',
                        text: response.message || 'Unable to check in patient'
                    });
                }
            })
            .fail(function(xhr, status, error) {
                hideLoadingOverlay();
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to check in patient: ' + error
                });
            });
        }

        // ==========================================
        // PATIENT FLAGGING OPERATIONS
        // ==========================================
        
        function flagPatient(patientId, appointmentId = null) {
            Swal.fire({
                title: 'Flag Patient',
                html: `
                    <div style="text-align: left;">
                        <label for="flag-type" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Flag Type:</label>
                        <select id="flag-type" class="swal2-select" style="width: 100%; margin-bottom: 1rem;">
                            <option value="">Select flag type...</option>
                            <option value="false_senior">False Senior Citizen Claim</option>
                            <option value="false_philhealth">False PhilHealth Coverage</option>
                            <option value="false_patient_booked">False Patient Booking</option>
                            <option value="incomplete_documents">Incomplete Documents</option>
                            <option value="behavioral_issue">Behavioral Issue</option>
                            <option value="medical_alert">Medical Alert</option>
                            <option value="other">Other</option>
                        </select>
                        
                        <label for="flag-remarks" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Remarks:</label>
                        <textarea id="flag-remarks" class="swal2-textarea" 
                                placeholder="Please provide detailed remarks about this flag..." 
                                style="width: 100%; height: 100px; resize: vertical;"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-flag"></i> Flag Patient',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const flagType = $('#flag-type').val();
                    const remarks = $('#flag-remarks').val().trim();
                    
                    if (!flagType) {
                        Swal.showValidationMessage('Please select a flag type');
                        return false;
                    }
                    
                    if (!remarks) {
                        Swal.showValidationMessage('Please provide remarks');
                        return false;
                    }
                    
                    if (remarks.length < 10) {
                        Swal.showValidationMessage('Remarks must be at least 10 characters long');
                        return false;
                    }
                    
                    return { flagType, remarks };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    performPatientFlag(patientId, result.value.flagType, result.value.remarks, appointmentId);
                }
            });
        }

        function performPatientFlag(patientId, flagType, remarks, appointmentId = null) {
            showLoadingOverlay('Flagging patient...');
            
            const data = {
                action: 'flag_patient',
                patient_id: patientId,
                flag_type: flagType,
                remarks: remarks,
                employee_id: currentEmployeeId
            };
            
            if (appointmentId) {
                data.appointment_id = appointmentId;
            }
            
            $.post('checkin_actions.php', data)
            .done(function(response) {
                hideLoadingOverlay();
                
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Patient Flagged Successfully',
                        text: response.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    
                    // If this was a "false patient booking", remove from table
                    if (flagType === 'false_patient_booked' && appointmentId) {
                        $(`tr[data-appointment-id="${appointmentId}"]`).fadeOut(500, function() {
                            $(this).remove();
                        });
                        refreshSummaryCards();
                    }
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Flagging Failed',
                        text: response.message || 'Unable to flag patient'
                    });
                }
            })
            .fail(function(xhr, status, error) {
                hideLoadingOverlay();
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to flag patient: ' + error
                });
            });
        }

        // ==========================================
        // APPOINTMENT CANCELLATION
        // ==========================================
        
        function cancelAppointment(appointmentId) {
            Swal.fire({
                title: 'Cancel Appointment',
                input: 'textarea',
                inputLabel: 'Cancellation Reason',
                inputPlaceholder: 'Please provide a reason for cancelling this appointment...',
                inputAttributes: {
                    'aria-label': 'Cancellation reason',
                    'style': 'height: 100px; resize: vertical;'
                },
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-times"></i> Cancel Appointment',
                cancelButtonText: 'Keep Appointment',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please provide a cancellation reason';
                    }
                    if (value.trim().length < 5) {
                        return 'Reason must be at least 5 characters long';
                    }
                    if (value.length > 500) {
                        return 'Reason must not exceed 500 characters';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    performAppointmentCancellation(appointmentId, result.value);
                }
            });
        }

        function performAppointmentCancellation(appointmentId, reason) {
            showLoadingOverlay('Cancelling appointment...');
            
            $.post('checkin_actions.php', {
                action: 'cancel_appointment',
                appointment_id: appointmentId,
                reason: reason,
                employee_id: currentEmployeeId
            })
            .done(function(response) {
                hideLoadingOverlay();
                
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Appointment Cancelled',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>Patient:</strong> ${response.data.patient_name}</p>
                                <p><strong>Reason:</strong> ${response.data.reason}</p>
                            </div>
                        `,
                        timer: 4000,
                        showConfirmButton: false
                    });
                    
                    // Remove the row from table
                    $(`tr[data-appointment-id="${appointmentId}"]`).fadeOut(500, function() {
                        $(this).remove();
                    });
                    
                    // Refresh summary cards
                    refreshSummaryCards();
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Cancellation Failed',
                        text: response.message || 'Unable to cancel appointment'
                    });
                }
            })
            .fail(function(xhr, status, error) {
                hideLoadingOverlay();
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to cancel appointment: ' + error
                });
            });
        }

        // ==========================================
        // PATIENT DETAILS MODAL
        // ==========================================
        
        function showPatientDetails(appointmentId, patientId) {
            showLoadingOverlay('Loading patient details...');
            
            $.post('checkin_actions.php', {
                action: 'get_patient_details',
                patient_id: patientId,
                appointment_id: appointmentId
            })
            .done(function(response) {
                hideLoadingOverlay();
                
                if (response.success) {
                    displayPatientModal(response.data, appointmentId);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to Load Details',
                        text: response.message || 'Unable to load patient details'
                    });
                }
            })
            .fail(function(xhr, status, error) {
                hideLoadingOverlay();
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to load patient details: ' + error
                });
            });
        }

        function displayPatientModal(data, appointmentId) {
            const patient = data.patient;
            const appointment = data.appointment;
            const flags = data.flags || [];
            const visits = data.recent_visits || [];
            
            let flagsHtml = '';
            if (flags.length > 0) {
                flagsHtml = `
                    <div class="flags-section" style="margin-top: 1rem;">
                        <h4 style="color: #dc3545;"><i class="fas fa-flag"></i> Recent Flags</h4>
                        ${flags.map(flag => `
                            <div class="flag-item" style="background: #f8d7da; padding: 0.5rem; margin: 0.25rem 0; border-radius: 4px;">
                                <strong>${flag.flag_type.replace(/_/g, ' ').toUpperCase()}:</strong> ${flag.remarks}
                                <br><small>By: ${flag.flagged_by_name} on ${formatDateTime(flag.created_at)}</small>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            Swal.fire({
                title: `<i class="fas fa-user"></i> ${patient.first_name} ${patient.last_name}`,
                html: `
                    <div style="text-align: left; max-height: 500px; overflow-y: auto;">
                        <div class="patient-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div><strong>Age:</strong> ${patient.age || 'N/A'} years</div>
                            <div><strong>Sex:</strong> ${patient.sex || 'N/A'}</div>
                            <div><strong>Contact:</strong> ${patient.contact_number || 'N/A'}</div>
                            <div><strong>Barangay:</strong> ${patient.barangay || 'N/A'}</div>
                            <div><strong>PhilHealth:</strong> ${patient.philhealth_id || 'None'}</div>
                            <div><strong>Status:</strong> 
                                ${patient.isSenior ? '<span style="background: #007bff; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">SENIOR</span>' : ''}
                                ${patient.isPWD ? '<span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">PWD</span>' : ''}
                            </div>
                        </div>
                        
                        ${appointment ? `
                            <div class="appointment-info" style="background: #e9ecef; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                                <h4><i class="fas fa-calendar"></i> Appointment Details</h4>
                                <p><strong>Date:</strong> ${appointment.formatted_date || formatDate(appointment.scheduled_date)}</p>
                                <p><strong>Time:</strong> ${appointment.formatted_time || formatTime(appointment.scheduled_time)}</p>
                                <p><strong>Service:</strong> ${appointment.service_name || 'General Consultation'}</p>
                                <p><strong>Status:</strong> <span class="status-badge ${getStatusClass(appointment.status)}">${appointment.status}</span></p>
                            </div>
                        ` : ''}
                        
                        ${flagsHtml}
                    </div>
                `,
                width: '600px',
                showCancelButton: true,
                showConfirmButton: false,
                cancelButtonText: 'Close',
                showCloseButton: true,
                footer: `
                    <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                        ${appointment && appointment.status === 'confirmed' ? `
                            <button type="button" class="swal2-confirm swal2-styled" 
                                    onclick="Swal.close(); checkinPatient(${appointmentId});" 
                                    style="background-color: #28a745;">
                                <i class="fas fa-user-check"></i> Check-In
                            </button>
                        ` : ''}
                        <button type="button" class="swal2-confirm swal2-styled" 
                                onclick="Swal.close(); flagPatient(${patient.patient_id}, ${appointmentId || 'null'});" 
                                style="background-color: #ffc107;">
                            <i class="fas fa-flag"></i> Flag Patient
                        </button>
                        ${appointment && appointment.status !== 'cancelled' && appointment.status !== 'completed' ? `
                            <button type="button" class="swal2-confirm swal2-styled" 
                                    onclick="Swal.close(); cancelAppointment(${appointmentId});" 
                                    style="background-color: #dc3545;">
                                <i class="fas fa-times"></i> Cancel Appointment
                            </button>
                        ` : ''}
                    </div>
                `
            });
        }

        // ==========================================
        // QR CODE SCANNER INTEGRATION
        // ==========================================
        
        let html5QrCode = null;
        let qrScannerActive = false;
        
        function initializeQRScanner() {
            // Load camera options immediately since scanner is visible
            loadCameraOptions();
            
            // Update toggle button text since scanner is visible by default
            $('#qr-scanner-toggle').html('<i class="fas fa-qrcode"></i> Hide QR Scanner');
            
            // Toggle QR scanner visibility
            $('#qr-scanner-toggle').on('click', function() {
                const container = $('#qr-scanner-container');
                const button = $(this);
                
                if (container.is(':visible')) {
                    // Hide scanner
                    container.slideUp();
                    button.html('<i class="fas fa-qrcode"></i> Show QR Scanner');
                    stopQRScanner();
                } else {
                    // Show scanner
                    container.slideDown();
                    button.html('<i class="fas fa-qrcode"></i> Hide QR Scanner');
                    loadCameraOptions();
                }
            });
            
            // Start scanner button
            $('#start-scanner-btn').on('click', startQRScanner);
            
            // Stop scanner button
            $('#stop-scanner-btn').on('click', stopQRScanner);
            
            // Camera selection change
            $('#camera-select').on('change', function() {
                if (qrScannerActive) {
                    stopQRScanner();
                    setTimeout(startQRScanner, 500);
                }
            });
        }
        
        function loadCameraOptions() {
            Html5Qrcode.getCameras().then(devices => {
                const cameraSelect = $('#camera-select');
                cameraSelect.empty().append('<option value="">Select Camera...</option>');
                
                if (devices && devices.length > 0) {
                    devices.forEach((device, index) => {
                        const label = device.label || `Camera ${index + 1}`;
                        cameraSelect.append(`<option value="${device.id}">${label}</option>`);
                    });
                    
                    // Auto-select first camera if available
                    if (devices.length === 1) {
                        cameraSelect.val(devices[0].id);
                    }
                    
                    cameraSelect.show();
                    updateStatus('info', 'Camera options loaded. Select a camera and click "Start Camera".');
                } else {
                    updateStatus('warning', 'No cameras found. Please check camera permissions.');
                }
            }).catch(err => {
                console.error('Error getting cameras:', err);
                updateStatus('error', 'Failed to access camera devices. Please check permissions.');
            });
        }
        
        function startQRScanner() {
            const selectedCamera = $('#camera-select').val();
            
            if (!selectedCamera) {
                updateStatus('warning', 'Please select a camera first.');
                return;
            }
            
            if (qrScannerActive) {
                updateStatus('warning', 'Scanner is already active.');
                return;
            }
            
            try {
                html5QrCode = new Html5Qrcode('qr-reader');
                
                const qrConfig = {
                    fps: 10,
                    qrbox: {
                        width: 300,
                        height: 300
                    },
                    aspectRatio: 1.0,
                    disableFlip: false
                };
                
                html5QrCode.start(
                    selectedCamera,
                    qrConfig,
                    onQRScanSuccess,
                    onQRScanError
                ).then(() => {
                    qrScannerActive = true;
                    $('#start-scanner-btn').hide();
                    $('#stop-scanner-btn').show();
                    $('#camera-select').prop('disabled', true);
                    updateStatus('success', 'Camera started. Point at QR code to scan.');
                }).catch(err => {
                    console.error('Error starting scanner:', err);
                    updateStatus('error', 'Failed to start camera: ' + err.message);
                });
                
            } catch (err) {
                console.error('QR Scanner initialization error:', err);
                updateStatus('error', 'Scanner initialization failed. Please try again.');
            }
        }
        
        function stopQRScanner() {
            if (html5QrCode && qrScannerActive) {
                html5QrCode.stop().then(() => {
                    qrScannerActive = false;
                    html5QrCode = null;
                    $('#start-scanner-btn').show();
                    $('#stop-scanner-btn').hide();
                    $('#camera-select').prop('disabled', false);
                    updateStatus('info', 'Camera stopped. Click "Start Camera" to scan again.');
                }).catch(err => {
                    console.error('Error stopping scanner:', err);
                    qrScannerActive = false;
                    html5QrCode = null;
                    $('#start-scanner-btn').show();
                    $('#stop-scanner-btn').hide();
                    $('#camera-select').prop('disabled', false);
                    updateStatus('info', 'Scanner stopped.');
                });
            }
        }
        
        function onQRScanSuccess(decodedText, decodedResult) {
            console.log('QR Code detected:', decodedText);
            
            // Stop scanner immediately to prevent multiple scans
            stopQRScanner();
            
            updateStatus('success', 'QR Code detected! Processing...');
            
            // Process the QR code
            processQRCode(decodedText);
        }
        
        function onQRScanError(errorMessage) {
            // Don't log every scan attempt error to avoid console spam
            // Only log significant errors
        }
        
        function processQRCode(qrData) {
            showLoadingOverlay('Processing QR Code...');
            
            $.post('checkin_lookup.php', {
                qr_data: qrData
            })
            .done(function(response) {
                hideLoadingOverlay();
                
                if (response.success) {
                    updateStatus('success', 'QR Code processed successfully!');
                    
                    // Show check-in confirmation with patient details
                    showQRCheckInModal(response.data);
                    
                } else {
                    updateStatus('error', response.message || 'QR Code processing failed.');
                }
            })
            .fail(function(xhr, status, error) {
                hideLoadingOverlay();
                updateStatus('error', 'Network error: Failed to process QR code.');
                console.error('QR Lookup failed:', error);
            });
        }
        
        function showQRCheckInModal(data) {
            const patient = data.patient;
            const appointment = data.appointment;
            
            Swal.fire({
                title: `<i class="fas fa-qrcode"></i> QR Check-In Confirmation`,
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <h4 style="color: #2c3e50; margin-bottom: 1rem;">Patient Information</h4>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                            <p><strong>Name:</strong> ${patient.full_name}</p>
                            <p><strong>Patient ID:</strong> ${patient.patient_id}</p>
                            <p><strong>Age:</strong> ${patient.age} years</p>
                            <p><strong>Contact:</strong> ${patient.contact_number}</p>
                            <p><strong>Barangay:</strong> ${patient.barangay}</p>
                            ${patient.isSenior ? '<p><span style="background: #007bff; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">SENIOR CITIZEN</span></p>' : ''}
                            ${patient.isPWD ? '<p><span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">PWD</span></p>' : ''}
                        </div>
                        
                        <h4 style="color: #2c3e50; margin-bottom: 1rem;">Appointment Details</h4>
                        <div style="background: #e9ecef; padding: 1rem; border-radius: 4px;">
                            <p><strong>Appointment ID:</strong> APT-${String(appointment.appointment_id).padStart(8, '0')}</p>
                            <p><strong>Date:</strong> ${appointment.formatted_date}</p>
                            <p><strong>Time:</strong> ${appointment.formatted_time}</p>
                            <p><strong>Service:</strong> ${appointment.service_name}</p>
                            <p><strong>Status:</strong> ${appointment.status}</p>
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-user-check"></i> Check-In Patient',
                cancelButtonText: 'Cancel',
                width: '600px'
            }).then((result) => {
                if (result.isConfirmed) {
                    performCheckin(appointment.appointment_id);
                } else {
                    updateStatus('info', 'Check-in cancelled. Ready for next scan.');
                }
            });
        }
        
        function updateStatus(type, message) {
            const statusElement = $('#qr-status-message');
            const iconMap = {
                'info': 'fas fa-info-circle',
                'success': 'fas fa-check-circle',
                'error': 'fas fa-exclamation-circle',
                'warning': 'fas fa-exclamation-triangle'
            };
            
            statusElement
                .removeClass('info success error warning')
                .addClass(type)
                .html(`<i class="${iconMap[type]}"></i> ${message}`);
        }

        // ==========================================
        // SUMMARY CARDS REFRESH
        // ==========================================
        
        function refreshSummaryCards() {
            $.get('checkin_summary.php')
            .done(function(data) {
                if (data && typeof data === 'object') {
                    $('#summary-today').text(data.today || '0');
                    $('#summary-checkedin').text(data.checkedIn || '0');
                    $('#summary-completed').text(data.completed || '0');
                }
            })
            .fail(function() {
                console.warn('Failed to refresh summary cards');
            });
        }

        // ==========================================
        // UTILITY FUNCTIONS
        // ==========================================
        
        function showLoadingOverlay(message = 'Loading...') {
            Swal.fire({
                title: message,
                html: '<div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div>',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
        }

        function hideLoadingOverlay() {
            Swal.close();
        }

        function getStatusClass(status) {
            const statusClasses = {
                'confirmed': 'status-confirmed',
                'checked_in': 'status-checked-in',
                'completed': 'status-completed',
                'cancelled': 'status-cancelled',
                'pending': 'status-pending'
            };
            return statusClasses[status] || 'status-default';
        }

        function getStatusIcon(status) {
            const statusIcons = {
                'confirmed': 'fas fa-check-circle',
                'checked_in': 'fas fa-user-check',
                'completed': 'fas fa-check-double',
                'cancelled': 'fas fa-times-circle',
                'pending': 'fas fa-clock'
            };
            return statusIcons[status] || 'fas fa-circle';
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }

        function formatTime(timeString) {
            if (!timeString) return 'N/A';
            const time = new Date('1970-01-01T' + timeString + 'Z');
            return time.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true 
            });
        }

        function formatDateTime(dateTimeString) {
            if (!dateTimeString) return 'N/A';
            const date = new Date(dateTimeString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        // ==========================================
        // LEGACY COMPATIBILITY FUNCTIONS
        // ==========================================

        // Clear filters
        function clearFilters() {
            const form = document.querySelector('.search-form');
            form.reset();
            document.querySelector('input[name="appointment_date"]').value = '<?php echo $today; ?>';
        }
        
        // Simulate QR scan (legacy function)
        function simulateQRScan() {
            activateQRScanner();
        }
        
        // View patient details (legacy function)
        function viewPatient(appointmentId, patientId) {
            showPatientDetails(appointmentId, patientId);
        }

        // Modal functions for backward compatibility
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        };

        console.log('✅ Patient Check-In System fully initialized with AJAX functionality');
    </script>
    </div>
</body>
</html>