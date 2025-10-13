<?php

/**
 * Check-In Station Module (CHO Main District)
 * City Health Office of Koronadal
 * 
 * Purpose: Check-In Station for appointment confirmation and queue entry
 * Access: Admin, Records Officer, DHO, BHW
 * 
 * Implementation based on station-checkin_Version2.md specification
 */

// Include employee session configuration first
require_once '../../config/session/employee_session.php';

// Include necessary files
require_once '../../config/db.php';
require_once '../../utils/queue_management_service.php';

// Access Control - Only allow check-in roles
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
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                max-width: 500px;
            }
        </style>
    </body>

    </html>
<?php
    exit();
}

// Initialize variables and services
$message = '';
$error = '';
$success = '';
$today = date('Y-m-d');
$current_time = date('H:i:s');
$stats = ['total' => 0, 'checked_in' => 0, 'completed' => 0, 'priority' => 0];
$search_results = [];
$barangays = [];
$services = [];

// Initialize Queue Management Service
try {
    $queueService = new QueueManagementService($pdo);
} catch (Exception $e) {
    error_log("Queue Service initialization error: " . $e->getMessage());
    $error = "System initialization failed. Please contact administrator.";
}

// Get today's statistics
try {
    // Total appointments today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_date) = ? AND facility_id = 1");
    $stmt->execute([$today]);
    $stats['total'] = $stmt->fetchColumn();

    // Checked-in patients today (via visits table)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE DATE(visit_date) = ? AND facility_id = 1");
    $stmt->execute([$today]);
    $stats['checked_in'] = $stmt->fetchColumn();

    // Completed appointments today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_date) = ? AND facility_id = 1 AND status = 'completed'");
    $stmt->execute([$today]);
    $stats['completed'] = $stmt->fetchColumn();

    // Priority patients in queue today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue_entries q 
                          JOIN appointments a ON q.appointment_id = a.appointment_id 
                          WHERE DATE(q.created_at) = ? AND q.priority_level IN ('priority', 'emergency') AND a.facility_id = 1");
    $stmt->execute([$today]);
    $stats['priority'] = $stmt->fetchColumn();
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

// Get available services
try {
    $stmt = $pdo->prepare("SELECT service_id, service_name FROM services WHERE status = 'active' ORDER BY service_name");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Services fetch error: " . $e->getMessage());
}

// Handle AJAX and form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle AJAX requests with JSON response
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');

        switch ($action) {
            case 'scan_qr':
                $qr_data = trim($_POST['qr_data'] ?? '');

                if (empty($qr_data)) {
                    echo json_encode(['success' => false, 'message' => 'QR code data is required']);
                    exit;
                }

                // Parse QR data to extract appointment_id and validate
                $appointment_id = null;
                $is_valid_qr = false;
                
                // Try to parse as JSON first (new QR format)
                $qr_json = json_decode($qr_data, true);
                if ($qr_json && isset($qr_json['type']) && $qr_json['type'] === 'appointment') {
                    $appointment_id = intval($qr_json['appointment_id'] ?? 0);
                    
                    // Validate QR code using verification code
                    if ($appointment_id > 0) {
                        require_once dirname(dirname(__DIR__)) . '/utils/qr_code_generator.php';
                        $is_valid_qr = QRCodeGenerator::validateQRData($qr_data, $appointment_id);
                    }
                } else {
                    // Fallback to legacy formats for backward compatibility
                    if (preg_match('/appointment_id[=:]\s*(\d+)/', $qr_data, $matches)) {
                        $appointment_id = intval($matches[1]);
                        $is_valid_qr = true; // Legacy QR codes are considered valid
                    } elseif (is_numeric($qr_data)) {
                        $appointment_id = intval($qr_data);
                        $is_valid_qr = true; // Legacy QR codes are considered valid
                    }
                }

                if (!$appointment_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid QR code format']);
                    exit;
                }

                if (!$is_valid_qr) {
                    echo json_encode(['success' => false, 'message' => 'Invalid QR code - verification failed']);
                    exit;
                }

                // Fetch appointment details
                try {
                    $stmt = $pdo->prepare("
                        SELECT a.appointment_id, a.patient_id, a.scheduled_date, a.scheduled_time, a.status, a.service_id,
                               a.referral_id, a.qr_code_path,
                               p.first_name, p.last_name, p.date_of_birth, p.isSenior, p.isPWD, p.philhealth_id_number,
                               b.barangay_name,
                               s.service_name,
                               r.referral_reason, r.referred_by
                        FROM appointments a
                        JOIN patients p ON a.patient_id = p.patient_id
                        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
                        LEFT JOIN services s ON a.service_id = s.service_id
                        LEFT JOIN referrals r ON a.referral_id = r.referral_id
                        WHERE a.appointment_id = ? AND a.facility_id = 1
                    ");
                    $stmt->execute([$appointment_id]);
                    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($appointment) {
                        // Check if already checked in
                        $stmt = $pdo->prepare("SELECT visit_id FROM visits WHERE appointment_id = ? AND facility_id = 1");
                        $stmt->execute([$appointment_id]);
                        $existing_visit = $stmt->fetch();

                        $appointment['already_checked_in'] = $existing_visit ? true : false;
                        $appointment['priority_status'] = $appointment['isSenior'] || $appointment['isPWD'] ? 'priority' : 'normal';

                        echo json_encode(['success' => true, 'appointment' => $appointment]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;

            case 'search_appointments':
                // Implement search functionality with enhanced filters
                $appointment_id = trim($_POST['appointment_id'] ?? '');
                $patient_id = trim($_POST['patient_id'] ?? '');
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $barangay = trim($_POST['barangay'] ?? '');
                $scheduled_date = $_POST['scheduled_date'] ?? $today;

                // Build search query
                $query = "
                    SELECT a.appointment_id, a.patient_id, a.scheduled_date, a.scheduled_time, a.status, a.service_id,
                           p.first_name, p.last_name, p.date_of_birth, p.isSenior, p.isPWD,
                           b.barangay_name,
                           s.name as service_name,
                           CASE 
                               WHEN p.isSenior = 1 OR p.isPWD = 1 THEN 'priority'
                               ELSE 'normal'
                           END as priority_status,
                           v.visit_id as already_checked_in
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.patient_id
                    LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
                    LEFT JOIN services s ON a.service_id = s.service_id
                    LEFT JOIN visits v ON a.appointment_id = v.appointment_id AND v.facility_id = 1
                    WHERE a.facility_id = 1
                ";

                $params = [];

                // Add search conditions
                if (!empty($appointment_id)) {
                    $clean_id = str_replace('APT-', '', $appointment_id);
                    $query .= " AND a.appointment_id = ?";
                    $params[] = $clean_id;
                }

                if (!empty($patient_id)) {
                    $query .= " AND p.patient_id = ?";
                    $params[] = $patient_id;
                }

                if (!empty($first_name)) {
                    $query .= " AND p.first_name LIKE ?";
                    $params[] = '%' . $first_name . '%';
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
                $params[] = $scheduled_date;

                try {
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'results' => $results]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Search failed: ' . $e->getMessage()]);
                }
                exit;

            case 'get_appointment_details':
                $appointment_id = $_POST['appointment_id'] ?? 0;
                $patient_id = $_POST['patient_id'] ?? 0;

                if (!$appointment_id || !$patient_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid appointment or patient ID']);
                    exit;
                }

                try {
                    // Get comprehensive appointment details
                    $stmt = $pdo->prepare("
                        SELECT a.*, 
                               p.first_name, p.last_name, p.middle_name, p.date_of_birth, 
                               p.sex as gender, p.isSenior, p.isPWD, p.email, p.contact_number as phone,
                               b.barangay_name,
                               s.name as service_name,
                               f.name as facility_name,
                               r.referral_reason, r.referred_by, r.referral_num,
                               v.visit_id as already_checked_in,
                               CASE 
                                   WHEN p.isSenior = 1 OR p.isPWD = 1 THEN 'priority'
                                   ELSE 'normal'
                               END as priority_status,
                               a.qr_code_path
                        FROM appointments a
                        JOIN patients p ON a.patient_id = p.patient_id
                        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
                        LEFT JOIN services s ON a.service_id = s.service_id
                        LEFT JOIN facilities f ON a.facility_id = f.facility_id
                        LEFT JOIN referrals r ON a.referral_id = r.referral_id
                        LEFT JOIN visits v ON a.appointment_id = v.appointment_id AND v.facility_id = a.facility_id
                        WHERE a.appointment_id = ? AND a.patient_id = ? AND a.facility_id = 1
                    ");

                    $stmt->execute([$appointment_id, $patient_id]);
                    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$appointment) {
                        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                        exit;
                    }

                    echo json_encode(['success' => true, 'appointment' => $appointment]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to load appointment details: ' . $e->getMessage()]);
                }
                exit;
        }
    }

    // Handle regular form submissions
    switch ($action) {
        case 'search':
            // Regular search for non-AJAX requests
            $appointment_id = trim($_POST['appointment_id'] ?? '');
            $patient_id = trim($_POST['patient_id'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $barangay = trim($_POST['barangay'] ?? '');
            $appointment_date = $_POST['appointment_date'] ?? $today;

            // Build search query (same as AJAX but for regular form)
            $query = "
                SELECT a.appointment_id, a.patient_id, a.scheduled_date as appointment_date, a.scheduled_time as appointment_time, 
                       a.status, a.service_id,
                       p.first_name, p.last_name, p.date_of_birth, p.isSenior, p.isPWD, p.philhealth_id_number as philhealth_id,
                       b.barangay_name as barangay,
                       s.name as service_name,
                       CASE 
                           WHEN p.isSenior = 1 OR p.isPWD = 1 THEN 'priority'
                           ELSE 'normal'
                       END as priority_status,
                       v.visit_id as already_checked_in
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN visits v ON a.appointment_id = v.appointment_id AND v.facility_id = 1
                WHERE a.facility_id = 1
            ";

            $params = [];

            // Add search conditions
            if (!empty($appointment_id)) {
                $clean_id = str_replace('APT-', '', $appointment_id);
                $query .= " AND a.appointment_id = ?";
                $params[] = $clean_id;
            }

            if (!empty($patient_id)) {
                $query .= " AND p.patient_id = ?";
                $params[] = $patient_id;
            }

            if (!empty($first_name)) {
                $query .= " AND p.first_name LIKE ?";
                $params[] = '%' . $first_name . '%';
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
            $priority_override = $_POST['priority_override'] ?? '';

            if ($appointment_id && $patient_id) {
                // Check if queue service is available
                if (!isset($queueService)) {
                    $error = "Queue service is not available. Please refresh the page or contact administrator.";
                    break;
                }

                try {
                    $pdo->beginTransaction();

                    // Get appointment and patient details
                    $stmt = $pdo->prepare("
                        SELECT a.appointment_id, a.patient_id, a.service_id, a.status, a.scheduled_date, a.scheduled_time,
                               p.first_name, p.last_name, p.isSenior, p.isPWD, p.date_of_birth
                        FROM appointments a
                        JOIN patients p ON a.patient_id = p.patient_id
                        WHERE a.appointment_id = ? AND a.patient_id = ? AND a.facility_id = 1
                    ");
                    $stmt->execute([$appointment_id, $patient_id]);
                    $appointment_data = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$appointment_data) {
                        throw new Exception("Appointment not found or invalid.");
                    }

                    // Check if already checked in
                    $stmt = $pdo->prepare("SELECT visit_id FROM visits WHERE appointment_id = ? AND facility_id = 1");
                    $stmt->execute([$appointment_id]);
                    if ($stmt->fetch()) {
                        throw new Exception("Patient has already been checked in for this appointment.");
                    }

                    // Validate employee permissions
                    $employee_id = $_SESSION['employee_id'] ?? $_SESSION['user_id'];

                    // Create visit entry
                    $stmt = $pdo->prepare("
                        INSERT INTO visits (patient_id, facility_id, appointment_id, visit_date, time_in, visit_status) 
                        VALUES (?, 1, ?, CURDATE(), NOW(), 'ongoing')
                    ");
                    $stmt->execute([$patient_id, $appointment_id]);
                    $visit_id = $pdo->lastInsertId();

                    // Determine priority level
                    $priority_level = 'normal';
                    if ($priority_override === 'priority' || $priority_override === 'emergency') {
                        $priority_level = $priority_override;
                    } elseif ($appointment_data['isSenior'] || $appointment_data['isPWD']) {
                        $priority_level = 'priority';
                    }

                    // Create queue entry using Queue Management Service
                    $queue_result = $queueService->createQueueEntry(
                        $appointment_id,
                        $patient_id,
                        $appointment_data['service_id'],
                        'triage', // First station after check-in
                        $priority_level,
                        $employee_id
                    );

                    if (!$queue_result['success']) {
                        $error_message = $queue_result['message'] ?? $queue_result['error'] ?? 'Unknown error occurred';
                        throw new Exception("Failed to create queue entry: " . $error_message);
                    }

                    // Update appointment status
                    $stmt = $pdo->prepare("UPDATE appointments SET status = 'checked_in' WHERE appointment_id = ?");
                    $stmt->execute([$appointment_id]);

                    // Log the check-in action
                    $stmt = $pdo->prepare("
                        INSERT INTO appointment_logs (appointment_id, patient_id, action, details, performed_by, created_at)
                        VALUES (?, ?, 'checked_in', ?, ?, NOW())
                    ");
                    $log_details = json_encode([
                        'queue_code' => $queue_result['queue_code'] ?? 'N/A',
                        'priority_level' => $priority_level,
                        'station' => 'triage'
                    ]);
                    $stmt->execute([$appointment_id, $patient_id, 'Patient checked in successfully', $employee_id]);

                    $pdo->commit();

                    $queue_code = $queue_result['queue_code'] ?? 'N/A';
                    $success = "Patient checked in successfully! Queue Code: " . $queue_code .
                        " | Priority: " . ucfirst($priority_level) . " | Next Station: Triage";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Check-in failed: " . $e->getMessage();
                }
            } else {
                $error = "Invalid appointment or patient information.";
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

                    $employee_id = $_SESSION['employee_id'] ?? $_SESSION['user_id'];

                    // Insert patient flag
                    $stmt = $pdo->prepare("
                        INSERT INTO patient_flags (patient_id, appointment_id, flag_type, remarks, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$patient_id, $appointment_id, $flag_type, $remarks, $employee_id]);

                    // Update appointment status based on flag type
                    $new_status = 'flagged';
                    if ($flag_type === 'no_show' || $flag_type === 'false_patient_booked' || $flag_type === 'duplicate_appointment') {
                        $new_status = 'cancelled';
                    }

                    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
                    $stmt->execute([$new_status, $appointment_id]);

                    // Log the flagging action
                    $stmt = $pdo->prepare("
                        INSERT INTO appointment_logs (appointment_id, patient_id, action, details, performed_by, created_at) 
                        VALUES (?, ?, 'flagged', ?, ?, NOW())
                    ");
                    $log_details = json_encode([
                        'flag_type' => $flag_type,
                        'remarks' => $remarks,
                        'status_changed_to' => $new_status
                    ]);
                    $stmt->execute([$appointment_id, $patient_id, $log_details, $employee_id]);

                    // Remove from queue if patient was already in queue
                    $stmt = $pdo->prepare("
                        UPDATE queue_entries 
                        SET status = 'cancelled', cancelled_at = NOW() 
                        WHERE appointment_id = ? AND status IN ('waiting', 'in_progress')
                    ");
                    $stmt->execute([$appointment_id]);

                    $pdo->commit();

                    $success = "Patient flagged successfully as: " . ucwords(str_replace('_', ' ', $flag_type));
                    if ($new_status === 'cancelled') {
                        $success .= " Appointment has been cancelled.";
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollback();
                    }
                    $error = "Flag operation failed: " . $e->getMessage();
                }
            } else {
                $error = "Invalid flag information provided.";
            }
            break;

        case 'cancel_appointment':
            $appointment_id = $_POST['appointment_id'] ?? 0;
            $patient_id = $_POST['patient_id'] ?? 0;
            $cancel_reason = trim($_POST['cancel_reason'] ?? '');

            if ($appointment_id && $patient_id && $cancel_reason) {
                try {
                    $pdo->beginTransaction();

                    $employee_id = $_SESSION['employee_id'] ?? $_SESSION['user_id'];

                    // Update appointment status
                    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
                    $stmt->execute([$appointment_id]);

                    // Log cancellation with detailed information
                    $stmt = $pdo->prepare("
                        INSERT INTO appointment_logs (appointment_id, patient_id, action, details, performed_by, created_at) 
                        VALUES (?, ?, 'cancelled', ?, ?, NOW())
                    ");
                    $log_details = json_encode([
                        'reason' => $cancel_reason,
                        'cancelled_by_role' => $user_role,
                        'cancellation_time' => date('Y-m-d H:i:s')
                    ]);
                    $stmt->execute([$appointment_id, $patient_id, $log_details, $employee_id]);

                    // Remove from queue if patient was in queue
                    $stmt = $pdo->prepare("
                        UPDATE queue_entries 
                        SET status = 'cancelled', cancelled_at = NOW() 
                        WHERE appointment_id = ? AND status IN ('waiting', 'in_progress')
                    ");
                    $stmt->execute([$appointment_id]);

                    $pdo->commit();
                    $success = "Appointment cancelled successfully. Reason: " . $cancel_reason;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollback();
                    }
                    $error = "Cancellation failed: " . $e->getMessage();
                }
            } else {
                $error = "Invalid cancellation information provided.";
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
            background: none;
            padding: 0;
            margin: 0 0 1rem 0;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
        }

        .checkin-container .qr-scanner-box {
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
            transition: var(--transition);
        }

        .checkin-container .qr-scanner-box:hover {
            border-color: var(--primary);
            color: var(--primary);
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

        /* Compact Instructions Card Styles */
        .compact-instructions {
            margin-bottom: 15px !important;
        }

        .compact-instructions .section-header {
            padding: 8px 15px !important;
            margin-bottom: 0 !important;
        }

        .compact-instructions .section-header h4 {
            font-size: 16px;
        }

        .toggle-instructions {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .toggle-instructions:hover {
            background: rgba(0, 119, 182, 0.1);
        }

        .toggle-instructions.rotated {
            transform: rotate(180deg);
        }

        .instructions-summary {
            padding: 10px 15px;
            background: linear-gradient(135deg, #e3f2fd, #f8f9fa);
            border-top: 1px solid #e9ecef;
        }

        .quick-steps {
            font-size: 13px;
            color: #495057;
            line-height: 1.4;
        }

        .detailed-instructions {
            padding: 15px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .instruction-grid {
            display: grid;
            gap: 8px;
        }

        .step-compact {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 10px;
            background: white;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
        }

        .step-num {
            background: var(--primary);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 11px;
            flex-shrink: 0;
        }

        .step-text {
            font-size: 12px;
            line-height: 1.4;
            color: #495057;
        }

        /* Two-Panel Input Layout */
        .checkin-container .input-panels {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-top: 1rem;
        }

        .checkin-container .panel {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: 1px solid #e9ecef;
        }

        .checkin-container .panel h5 {
            color: var(--primary-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .checkin-container .qr-scanner-area {
            text-align: center;
        }

        .checkin-container .qr-scanner-box {
            width: 200px;
            height: 200px;
            background: white;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #6c757d;
            transition: var(--transition);
        }

        .checkin-container .qr-scanner-box:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .checkin-container .qr-scanner-box.scanning {
            border-color: var(--success);
            background: rgba(45, 106, 79, 0.1);
        }

        .checkin-container .qr-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .checkin-container .search-panel .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .checkin-container .search-panel .form-group {
            margin-bottom: 0;
        }

        /* Results Table Enhancements */
        .checkin-container .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .checkin-container .results-count {
            font-size: 0.9rem;
            color: var(--secondary);
        }

        .checkin-container .service-badge {
            background: linear-gradient(135deg, #e7f3ff, #cce7ff);
            color: #0056b3;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .checkin-container .text-muted {
            color: #6c757d !important;
            font-size: 0.8rem;
        }

        .checkin-container .d-block {
            display: block !important;
        }

        .checkin-container .text-success {
            color: #2d6a4f !important;
        }

        .checkin-container .priority-indicators {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .checkin-container .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .checkin-container .action-buttons .btn-sm {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
        }

        /* Enhanced Modal Styles */
        .checkin-container .patient-summary-content,
        .checkin-container .confirmation-summary {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            margin-bottom: 1.5rem;
        }

        .checkin-container .patient-summary-content h6,
        .checkin-container .confirmation-summary h6 {
            color: var(--primary-dark);
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .checkin-container .priority-options {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .checkin-container .loading-placeholder {
            text-align: center;
            padding: 2rem;
            color: var(--secondary);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .checkin-container .input-panels {
                grid-template-columns: 1fr;
            }

            .checkin-container .search-panel .form-row {
                grid-template-columns: 1fr;
            }

            .checkin-container .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .checkin-container .action-buttons {
                flex-direction: column;
            }

            .checkin-container .action-buttons .btn-sm {
                width: 100%;
                margin-bottom: 0.25rem;
            }
        }

        @media (max-width: 480px) {
            .checkin-container .stats-grid {
                grid-template-columns: 1fr;
            }

            .checkin-container .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .checkin-container .total-count {
                width: 100%;
                justify-content: center;
            }
        }

        /* Modal Styles - Fixed positioning and centering */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            opacity: 0.8;
            transition: all 0.3s ease;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            opacity: 1;
            background: rgba(255, 255, 255, 0.1);
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
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.total {
            border-left-color: #667eea;
        }

        .stat-card.checked-in {
            border-left-color: #28a745;
        }

        .stat-card.completed {
            border-left-color: #17a2b8;
        }

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

        .stat-icon.total {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .stat-icon.checked-in {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .stat-icon.completed {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }

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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
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

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-checked_in {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #cce7ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .priority-senior {
            background: #fff3cd;
            color: #856404;
        }

        .priority-pwd {
            background: #d1ecf1;
            color: #0c5460;
        }

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
            background: rgba(0, 0, 0, 0.5);
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

        /* Modern Check-In Station Styling - Matching triage_station.php */
        /* Status Indicator */
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        .status-active {
            background: #27ae60;
            box-shadow: 0 0 8px rgba(39, 174, 96, 0.4);
        }

        .status-text {
            font-size: 12px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(39, 174, 96, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(39, 174, 96, 0);
            }
        }

        .action-section {
            margin-bottom: 25px;
        }

        .action-section:last-child {
            margin-bottom: 0;
        }

        .action-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 15px 0;
            padding: 8px 12px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-left: 4px solid #007bff;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            color: #495057;
        }

        .action-section-title i {
            color: #007bff;
            font-size: 16px;
        }

        .action-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .modern-btn {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .modern-btn:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
            transform: translateY(-2px);
            text-decoration: none;
            color: #495057;
        }

        .btn-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 10px;
            margin-right: 15px;
            font-size: 20px;
            flex-shrink: 0;
        }

        .btn-nav .btn-icon {
            background: linear-gradient(135deg, #48cae4, #0096c7);
            color: white;
        }

        .btn-qr-scan .btn-icon {
            background: linear-gradient(135deg, #9d4edd, #7209b7);
            color: white;
        }

        .btn-manual-search .btn-icon {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
            color: white;
        }

        /* Modern Table Action Buttons */
        .modern-action-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .modern-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .modern-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-icon-mini {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 14px;
            color: white;
        }

        /* Action Button Types */
        .btn-view {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #0096c7, #0077b6);
        }

        .btn-checkin {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }

        .btn-checkin:hover {
            background: linear-gradient(135deg, #40916c, #2d6a4f);
        }

        .btn-flag {
            background: linear-gradient(135deg, #ffd60a, #f77f00);
        }

        .btn-flag:hover {
            background: linear-gradient(135deg, #f77f00, #d62d20);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .btn-cancel:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
        }

        .btn-content {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .btn-title {
            font-weight: 600;
            font-size: 15px;
            color: #2c3e50;
        }

        .btn-subtitle {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 400;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .quick-stat {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border: 2px solid;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .quick-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .stat-total {
            border-color: #007bff;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        }

        .stat-checked {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
        }

        .stat-priority {
            border-color: #ffc107;
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            margin-right: 12px;
            font-size: 18px;
        }

        .stat-total .stat-icon {
            background: #007bff;
            color: white;
        }

        .stat-checked .stat-icon {
            background: #28a745;
            color: white;
        }

        .stat-priority .stat-icon {
            background: #ffc107;
            color: white;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }

        .stat-label {
            font-size: 12px;
            font-weight: 500;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .close-btn:hover {
            opacity: 1;
        }

        /* QR Scanner specific styles */
        .qr-scanner-area {
            text-align: center;
            padding: 20px;
        }

        .qr-scanner-box {
            background: #f8f9fa;
            border: 3px dashed #dee2e6;
            border-radius: 12px;
            padding: 40px 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .qr-scanner-box:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .qr-scanner-box.scanning {
            border-color: #28a745;
            background: #d4edda;
        }

        .qr-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .action-row {
                grid-template-columns: 1fr;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .modern-btn {
                padding: 14px 16px;
            }

            .btn-icon {
                width: 40px;
                height: 40px;
                margin-right: 12px;
                font-size: 18px;
            }

            .btn-title {
                font-size: 14px;
            }

            .btn-subtitle {
                font-size: 11px;
            }

            .quick-stat {
                padding: 12px;
            }

            .stat-icon {
                width: 35px;
                height: 35px;
                font-size: 16px;
                margin-right: 10px;
            }

            .stat-number {
                font-size: 18px;
            }

            .stat-label {
                font-size: 11px;
            }

            .action-section-title {
                font-size: 13px;
                padding: 6px 10px;
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
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Quick Instructions -->
                <div class="card-container compact-instructions">
                    <div class="section-header">
                        <h4><i class="fas fa-info-circle"></i> Quick Guide</h4>
                        <button class="toggle-instructions" onclick="toggleInstructions()" title="Toggle detailed instructions">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="instructions-summary">
                        <span class="quick-steps">
                            <strong>1.</strong> Verify ID → <strong>2.</strong> Scan/Search → <strong>3.</strong> Confirm → <strong>4.</strong> Check Priority → <strong>5.</strong> Check-In
                        </span>
                    </div>
                    <div class="detailed-instructions" id="detailedInstructions" style="display: none;">
                        <div class="instruction-grid">
                            <div class="step-compact">
                                <span class="step-num">1</span>
                                <span class="step-text"><strong>Verify Identity:</strong> Check patient's valid ID and appointment details</span>
                            </div>
                            <div class="step-compact">
                                <span class="step-num">2</span>
                                <span class="step-text"><strong>Scan/Search:</strong> Use QR code scanner or manual search to find appointment</span>
                            </div>
                            <div class="step-compact">
                                <span class="step-num">3</span>
                                <span class="step-text"><strong>Confirm Details:</strong> Verify appointment date, time, service, and patient information</span>
                            </div>
                            <div class="step-compact">
                                <span class="step-num">4</span>
                                <span class="step-text"><strong>Priority Check:</strong> Mark as priority if patient is Senior Citizen, PWD, or pregnant</span>
                            </div>
                            <div class="step-compact">
                                <span class="step-num">5</span>
                                <span class="step-text"><strong>Check-In:</strong> Accept booking to add patient to triage queue</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patient Lookup & Check-In Tools -->
                <div class="card-container">
                    <div class="section-header">
                        <h4><i class="fas fa-tools"></i> Check-In Tools & Navigation</h4>
                        <div class="station-status">
                            <span class="status-indicator status-active"></span>
                            <span class="status-text">Check-In Active</span>
                        </div>
                    </div>
                    <div class="section-body">

                        <!-- Navigation Actions -->
                        <div class="action-section">
                            <h5 class="action-section-title"><i class="fas fa-compass"></i> Navigation</h5>
                            <div class="action-row">
                                <a href="dashboard.php" class="modern-btn btn-nav">
                                    <div class="btn-icon">
                                        <i class="fas fa-tachometer-alt"></i>
                                    </div>
                                    <div class="btn-content">
                                        <span class="btn-title">Queue Dashboard</span>
                                        <span class="btn-subtitle">Main queue overview</span>
                                    </div>
                                </a>

                                <a href="station.php" class="modern-btn btn-nav">
                                    <div class="btn-icon">
                                        <i class="fas fa-desktop"></i>
                                    </div>
                                    <div class="btn-content">
                                        <span class="btn-title">Station Management</span>
                                        <span class="btn-subtitle">Multi-station interface</span>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Patient Lookup Methods -->
                        <div class="action-section">
                            <h5 class="action-section-title"><i class="fas fa-search"></i> Patient Lookup</h5>
                            <div class="action-row">
                                <div class="modern-btn btn-qr-scan" onclick="toggleQRScanner()">
                                    <div class="btn-icon">
                                        <i class="fas fa-qrcode"></i>
                                    </div>
                                    <div class="btn-content">
                                        <span class="btn-title">QR Code Scanner</span>
                                        <span class="btn-subtitle">Scan appointment QR code</span>
                                    </div>
                                </div>

                                <div class="modern-btn btn-manual-search" onclick="toggleManualSearch()">
                                    <div class="btn-icon">
                                        <i class="fas fa-keyboard"></i>
                                    </div>
                                    <div class="btn-content">
                                        <span class="btn-title">Manual Search</span>
                                        <span class="btn-subtitle">Find by name, ID, or details</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="action-section">
                            <h5 class="action-section-title"><i class="fas fa-chart-line"></i> Today's Stats</h5>
                            <div class="stats-row">
                                <div class="quick-stat stat-total">
                                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-number"><?php echo $stats['total']; ?></span>
                                        <span class="stat-label">Appointments</span>
                                    </div>
                                </div>
                                <div class="quick-stat stat-checked">
                                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-number"><?php echo $stats['checked_in']; ?></span>
                                        <span class="stat-label">Checked-In</span>
                                    </div>
                                </div>
                                <div class="quick-stat stat-priority">
                                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-number"><?php echo $stats['priority']; ?></span>
                                        <span class="stat-label">Priority</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- QR Scanner Section (Initially Hidden) -->
                <div class="card-container" id="qrScannerCard" style="display: none;">
                    <div class="section-header">
                        <h4><i class="fas fa-qrcode"></i> QR Code Scanner</h4>
                        <button class="close-btn" onclick="toggleQRScanner()" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="section-body">
                        <div class="qr-scanner-area">
                            <div class="qr-scanner-box" id="qrScannerBox">
                                <i class="fas fa-camera fa-3x"></i>
                                <p>Position QR code here</p>
                                <small>Scan appointment QR code</small>
                            </div>
                            <div class="qr-actions">
                                <button type="button" class="btn btn-primary" onclick="startQRScan()">
                                    <i class="fas fa-camera"></i> Start Scanner
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="simulateQRScan()">
                                    <i class="fas fa-qrcode"></i> Test Scan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manual Search Section (Initially Hidden) -->
                <div class="card-container" id="manualSearchCard" style="display: none;">
                    <div class="section-header">
                        <h4><i class="fas fa-search"></i> Manual Search & Filters</h4>
                        <button class="close-btn" onclick="toggleManualSearch()" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="section-body">
                        <form method="POST" class="search-form" id="searchForm">
                            <input type="hidden" name="action" value="search">

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Appointment ID</label>
                                    <input type="text" name="appointment_id" id="appointment_id" class="form-control"
                                        placeholder="APT-00000024 or 24" value="<?php echo $_POST['appointment_id'] ?? ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Patient ID</label>
                                    <input type="number" name="patient_id" id="patient_id" class="form-control"
                                        placeholder="Patient ID" value="<?php echo $_POST['patient_id'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" id="first_name" class="form-control"
                                        placeholder="Enter first name" value="<?php echo $_POST['first_name'] ?? ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" class="form-control"
                                        placeholder="Enter last name" value="<?php echo $_POST['last_name'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Barangay</label>
                                    <select name="barangay" id="barangay" class="form-control">
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
                                    <label class="form-label">Date</label>
                                    <input type="date" name="scheduled_date" id="scheduled_date" class="form-control"
                                        value="<?php echo $_POST['scheduled_date'] ?? $today; ?>">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card-container" id="resultsSection" style="<?php echo empty($search_results) ? 'display: none;' : ''; ?>">
                <div class="section-header">
                    <h4><i class="fas fa-list-alt"></i> Appointment Search Results</h4>
                    <div class="header-actions">
                        <span class="results-count">Found: <strong id="resultsCount"><?php echo count($search_results); ?></strong> appointment(s)</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="results-table" id="resultsTable">
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient Details</th>
                                <th>Scheduled</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="resultsBody">
                            <?php if (!empty($search_results)): ?>
                                <?php foreach ($search_results as $row): ?>
                                    <tr data-appointment-id="<?php echo $row['appointment_id']; ?>" data-patient-id="<?php echo $row['patient_id']; ?>">
                                        <td>
                                            <strong>APT-<?php echo str_pad($row['appointment_id'], 8, '0', STR_PAD_LEFT); ?></strong>
                                            <small class="text-muted d-block">ID: <?php echo $row['appointment_id']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></strong>
                                            <small class="text-muted d-block">
                                                Patient ID: <?php echo $row['patient_id']; ?>
                                                <?php if (!empty($row['barangay'])): ?>
                                                    | <?php echo htmlspecialchars($row['barangay']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></strong>
                                            <small class="text-muted d-block"><?php echo date('g:i A', strtotime($row['appointment_time'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="service-badge"><?php echo htmlspecialchars($row['service_name'] ?? 'General'); ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                            </span>
                                            <?php if (!empty($row['already_checked_in'])): ?>
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check-circle"></i> Checked-in
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="priority-indicators">
                                                <?php if ($row['priority_status'] === 'priority' || $row['isSenior'] || $row['isPWD']): ?>
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
                                                <?php else: ?>
                                                    <span class="priority-badge">Normal</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="modern-action-buttons">
                                                <button type="button" class="modern-action-btn btn-view" 
                                                    onclick="viewAppointment(<?php echo $row['appointment_id']; ?>, <?php echo $row['patient_id']; ?>)"
                                                    title="View Details">
                                                    <div class="btn-icon-mini">
                                                        <i class="fas fa-eye"></i>
                                                    </div>
                                                </button>

                                                <?php if (empty($row['already_checked_in']) && $row['status'] === 'confirmed'): ?>
                                                    <button type="button" class="modern-action-btn btn-checkin"
                                                        onclick="quickCheckin(<?php echo $row['appointment_id']; ?>, <?php echo $row['patient_id']; ?>)"
                                                        title="Quick Check-in">
                                                        <div class="btn-icon-mini">
                                                            <i class="fas fa-user-check"></i>
                                                        </div>
                                                    </button>
                                                <?php endif; ?>

                                                <button type="button" class="modern-action-btn btn-flag"
                                                    onclick="flagPatient(<?php echo $row['appointment_id']; ?>, <?php echo $row['patient_id']; ?>)"
                                                    title="Flag Patient">
                                                    <div class="btn-icon-mini">
                                                        <i class="fas fa-flag"></i>
                                                    </div>
                                                </button>

                                                <?php if (!empty($row['already_checked_in']) || $row['status'] === 'confirmed'): ?>
                                                <button type="button" class="modern-action-btn btn-cancel"
                                                    onclick="cancelAppointment(<?php echo $row['appointment_id']; ?>, <?php echo $row['patient_id']; ?>)"
                                                    title="Cancel Appointment">
                                                    <div class="btn-icon-mini">
                                                        <i class="fas fa-times-circle"></i>
                                                    </div>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($search_results) && $_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'search'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        No appointments found matching the search criteria. Please try different filters.
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

    <!-- View Appointment Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Appointment Details & Check-In</h3>
                <button type="button" class="modal-close" onclick="closeModal('appointmentModal')">&times;</button>
            </div>
            <div class="modal-body" id="appointmentModalBody">
                <!-- Content loaded via JavaScript -->
                <div id="appointmentDetails">
                    <div class="loading-placeholder">
                        <i class="fas fa-spinner fa-spin"></i> Loading appointment details...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('appointmentModal')">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Check-In Confirmation Modal -->
    <div id="checkinConfirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Patient Check-In</h3>
                <button type="button" class="modal-close" onclick="closeModal('checkinConfirmModal')">&times;</button>
            </div>
            <form method="POST" id="checkinConfirmForm">
                <input type="hidden" name="action" value="checkin">
                <input type="hidden" name="appointment_id" id="confirmAppointmentId">
                <input type="hidden" name="patient_id" id="confirmPatientId">

                <div class="modal-body">
                    <div class="confirmation-details" id="checkinConfirmDetails">
                        <!-- Details populated via JavaScript -->
                    </div>

                    <div class="priority-selection">
                        <label class="form-label">Priority Level Override</label>
                        <div class="priority-options">
                            <label class="priority-option">
                                <input type="radio" name="priority_override" value="normal" checked>
                                <span class="priority-label">Normal Priority</span>
                                <small>Standard queue processing</small>
                            </label>
                            <label class="priority-option">
                                <input type="radio" name="priority_override" value="priority">
                                <span class="priority-label">Priority Queue</span>
                                <small>For seniors, PWD, pregnant patients</small>
                            </label>
                            <label class="priority-option">
                                <input type="radio" name="priority_override" value="emergency">
                                <span class="priority-label">Emergency</span>
                                <small>Urgent medical attention required</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('checkinConfirmModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-check"></i> Confirm Check-In
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Flag Patient Modal -->
    <div id="flagModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Flag Patient / Issue Report</h3>
                <button type="button" class="modal-close" onclick="closeModal('flagModal')">&times;</button>
            </div>
            <form method="POST" id="flagForm">
                <input type="hidden" name="action" value="flag_patient">
                <input type="hidden" name="appointment_id" id="flagAppointmentId">
                <input type="hidden" name="patient_id" id="flagPatientId">

                <div class="modal-body">
                    <div class="patient-summary" id="flagPatientSummary">
                        <!-- Patient summary populated via JavaScript -->
                    </div>

                    <div class="form-group">
                        <label class="form-label">Issue/Flag Type</label>
                        <select name="flag_type" class="form-control" required>
                            <option value="">Select issue type...</option>
                            <optgroup label="Identity Issues">
                                <option value="false_senior">False Senior Citizen Claim</option>
                                <option value="false_pwd">False PWD Claim</option>
                                <option value="identity_mismatch">Identity Verification Failed</option>
                            </optgroup>
                            <optgroup label="Appointment Issues">
                                <option value="false_patient_booked">Wrong Patient Booking</option>
                                <option value="duplicate_appointment">Duplicate Appointment</option>
                                <option value="no_show">Patient No-Show</option>
                                <option value="late_arrival">Late Arrival (>30min)</option>
                            </optgroup>
                            <optgroup label="Documentation Issues">
                                <option value="false_philhealth">Invalid PhilHealth Documents</option>
                                <option value="missing_documents">Required Documents Missing</option>
                                <option value="expired_id">Expired Identification</option>
                            </optgroup>
                            <optgroup label="Other">
                                <option value="medical_emergency">Medical Emergency (Redirect)</option>
                                <option value="behavior_issue">Behavioral Concern</option>
                                <option value="other">Other Issue</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Detailed Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4"
                            placeholder="Provide detailed explanation of the issue, steps taken, and any recommendations..." required></textarea>
                        <small class="form-text text-muted">
                            Be specific and objective in your description. This will be part of the patient's record.
                        </small>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important:</strong> Flagging a patient will affect their appointment status and may prevent future bookings.
                        Ensure all information is accurate before submitting.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('flagModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
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
            <form method="POST" id="cancelForm">
                <input type="hidden" name="action" value="cancel_appointment">
                <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                <input type="hidden" name="patient_id" id="cancelPatientId">

                <div class="modal-body">
                    <div class="patient-summary" id="cancelPatientSummary">
                        <!-- Patient summary populated via JavaScript -->
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cancellation Reason</label>
                        <select name="cancel_reason" class="form-control" required>
                            <option value="">Select cancellation reason...</option>
                            <option value="Patient Request">Patient Request</option>
                            <option value="No Show">Patient No-Show</option>
                            <option value="Medical Emergency">Medical Emergency</option>
                            <option value="Facility Issue">Facility/Equipment Issue</option>
                            <option value="Staff Unavailable">Assigned Staff Unavailable</option>
                            <option value="Administrative Error">Administrative Error</option>
                            <option value="Patient Deceased">Patient Deceased</option>
                            <option value="Other">Other Reason</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Additional Details</label>
                        <textarea name="cancel_details" class="form-control" rows="3"
                            placeholder="Provide additional context for the cancellation..."></textarea>
                    </div>

                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Warning:</strong> This action cannot be undone. The appointment will be permanently cancelled
                        and removed from the queue system.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('cancelModal')">
                        <i class="fas fa-times"></i> Keep Appointment
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban"></i> Cancel Appointment
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
            <div class="modal-body">
                <div class="success-message" id="successMessage">
                    <!-- Success message populated via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeModal('successModal')">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
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

    <script>
        // Modal functions
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            document.body.style.overflow = '';
        }

        // Clear all modal content when closing
        function clearModalContent(modalId) {
            const modalBody = document.querySelector(`#${modalId} .modal-body`);
            if (modalBody) {
                // Reset dynamic content
                const dynamicElements = modalBody.querySelectorAll('[id$="Details"], [id$="Summary"]');
                dynamicElements.forEach(el => el.innerHTML = '');
            }
        }

        // Instructions Toggle Function
        function toggleInstructions() {
            const detailedInstructions = document.getElementById('detailedInstructions');
            const toggleBtn = document.querySelector('.toggle-instructions i');

            if (detailedInstructions.style.display === 'none' || !detailedInstructions.style.display) {
                detailedInstructions.style.display = 'block';
                toggleBtn.classList.remove('fa-chevron-down');
                toggleBtn.classList.add('fa-chevron-up');
            } else {
                detailedInstructions.style.display = 'none';
                toggleBtn.classList.remove('fa-chevron-up');
                toggleBtn.classList.add('fa-chevron-down');
            }
        }

        // Modern UI Toggle Functions
        function toggleQRScanner() {
            const qrCard = document.getElementById('qrScannerCard');
            const manualCard = document.getElementById('manualSearchCard');

            if (qrCard.style.display === 'none' || !qrCard.style.display) {
                // Show QR Scanner, hide Manual Search
                qrCard.style.display = 'block';
                manualCard.style.display = 'none';

                // Animate card appearance
                qrCard.style.opacity = '0';
                qrCard.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    qrCard.style.transition = 'all 0.3s ease';
                    qrCard.style.opacity = '1';
                    qrCard.style.transform = 'translateY(0)';
                }, 10);
            } else {
                // Hide QR Scanner
                qrCard.style.transition = 'all 0.3s ease';
                qrCard.style.opacity = '0';
                qrCard.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    qrCard.style.display = 'none';
                }, 300);
            }
        }

        function toggleManualSearch() {
            const qrCard = document.getElementById('qrScannerCard');
            const manualCard = document.getElementById('manualSearchCard');

            if (manualCard.style.display === 'none' || !manualCard.style.display) {
                // Show Manual Search, hide QR Scanner
                manualCard.style.display = 'block';
                qrCard.style.display = 'none';

                // Animate card appearance
                manualCard.style.opacity = '0';
                manualCard.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    manualCard.style.transition = 'all 0.3s ease';
                    manualCard.style.opacity = '1';
                    manualCard.style.transform = 'translateY(0)';
                }, 10);
            } else {
                // Hide Manual Search
                manualCard.style.transition = 'all 0.3s ease';
                manualCard.style.opacity = '0';
                manualCard.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    manualCard.style.display = 'none';
                }, 300);
            }
        }

        // Clear search filters
        function clearFilters() {
            const form = document.getElementById('searchForm');
            if (form) {
                form.reset();
                // Clear results
                const resultsSection = document.getElementById('resultsSection');
                if (resultsSection) {
                    resultsSection.style.display = 'none';
                }
            }
        }

        // QR Scanner Functions
        function startQRScan() {
            const scannerBox = document.getElementById('qrScannerBox');
            scannerBox.classList.add('scanning');
            scannerBox.innerHTML = `
                <i class="fas fa-camera fa-2x"></i>
                <p>Scanning...</p>
                <small>Position QR code in view</small>
            `;

            // Simulate scanner timeout
            setTimeout(() => {
                scannerBox.classList.remove('scanning');
                scannerBox.innerHTML = `
                    <i class="fas fa-camera fa-3x"></i>
                    <p>Position QR code here</p>
                    <small>Scan appointment QR code</small>
                `;
                alert('QR Scanner timeout. Please try manual search or contact IT support for scanner setup.');
            }, 10000);
        }

        function simulateQRScan() {
            // For testing - simulate a successful QR scan
            const testQRData = "appointment_id:24";
            processQRScan(testQRData);
        }

        function processQRScan(qrData) {
            if (!qrData) return;

            // Show loading
            showLoadingOverlay('Processing QR code...');

            // Send AJAX request to process QR scan
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=1&action=scan_qr&qr_data=${encodeURIComponent(qrData)}`
                })
                .then(response => response.json())
                .then(data => {
                    hideLoadingOverlay();
                    if (data.success) {
                        displayAppointmentDetails(data.appointment);
                    } else {
                        showError(data.message || 'QR scan failed');
                    }
                })
                .catch(error => {
                    hideLoadingOverlay();
                    showError('Network error: ' + error.message);
                });
        }

        // AJAX Search Function
        function performSearch() {
            const form = document.getElementById('searchForm');
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('action', 'search_appointments');

            showLoadingOverlay('Searching appointments...');

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoadingOverlay();
                    if (data.success) {
                        updateResultsTable(data.results);
                    } else {
                        showError(data.message || 'Search failed');
                    }
                })
                .catch(error => {
                    hideLoadingOverlay();
                    showError('Network error: ' + error.message);
                });
        }

        // Update results table with search data
        function updateResultsTable(results) {
            const resultsSection = document.getElementById('resultsSection');
            const resultsBody = document.getElementById('resultsBody');
            const resultsCount = document.getElementById('resultsCount');

            if (!results || results.length === 0) {
                resultsSection.style.display = 'none';
                showInfo('No appointments found matching your search criteria.');
                return;
            }

            resultsCount.textContent = results.length;
            resultsSection.style.display = 'block';

            // Clear existing results
            resultsBody.innerHTML = '';

            // Populate new results
            results.forEach(appointment => {
                const row = createAppointmentRow(appointment);
                resultsBody.appendChild(row);
            });
        }

        // Create table row for appointment
        function createAppointmentRow(appointment) {
            const row = document.createElement('tr');
            row.dataset.appointmentId = appointment.appointment_id;
            row.dataset.patientId = appointment.patient_id;

            // Determine priority status
            const isPriority = appointment.isSenior || appointment.isPWD || appointment.priority_status === 'priority';

            row.innerHTML = `
                <td>
                    <strong>APT-${appointment.appointment_id.toString().padStart(8, '0')}</strong>
                    <small class="text-muted d-block">ID: ${appointment.appointment_id}</small>
                </td>
                <td>
                    <strong>${appointment.last_name}, ${appointment.first_name}</strong>
                    <small class="text-muted d-block">
                        Patient ID: ${appointment.patient_id}
                        ${appointment.barangay_name ? ` | ${appointment.barangay_name}` : ''}
                    </small>
                </td>
                <td>
                    <strong>${formatDate(appointment.scheduled_date)}</strong>
                    <small class="text-muted d-block">${formatTime(appointment.scheduled_time)}</small>
                </td>
                <td>
                    <span class="service-badge">${appointment.service_name || 'General'}</span>
                </td>
                <td>
                    <span class="status-badge status-${appointment.status}">
                        ${capitalizeFirst(appointment.status.replace('_', ' '))}
                    </span>
                    ${appointment.already_checked_in ? '<small class="text-success d-block"><i class="fas fa-check-circle"></i> Checked-in</small>' : ''}
                </td>
                <td>
                    <div class="priority-indicators">
                        ${appointment.isSenior ? '<span class="priority-badge priority-senior"><i class="fas fa-user"></i> Senior</span>' : ''}
                        ${appointment.isPWD ? '<span class="priority-badge priority-pwd"><i class="fas fa-wheelchair"></i> PWD</span>' : ''}
                        ${!isPriority ? '<span class="priority-badge">Normal</span>' : ''}
                    </div>
                </td>
                <td>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-primary btn-sm" 
                                onclick="viewAppointment(${appointment.appointment_id}, ${appointment.patient_id})"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${!appointment.already_checked_in && appointment.status === 'confirmed' ? 
                            `<button type="button" class="btn btn-success btn-sm" 
                                    onclick="quickCheckin(${appointment.appointment_id}, ${appointment.patient_id})"
                                    title="Quick Check-in">
                                <i class="fas fa-user-check"></i>
                            </button>` : ''
                        }
                        <button type="button" class="btn btn-warning btn-sm" 
                                onclick="flagPatient(${appointment.appointment_id}, ${appointment.patient_id})"
                                title="Flag Patient">
                            <i class="fas fa-flag"></i>
                        </button>
                    </div>
                </td>
            `;

            return row;
        }

        // Utility functions
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function formatTime(timeStr) {
            const time = new Date(`2000-01-01 ${timeStr}`);
            return time.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // View appointment details (enhanced version)
        function viewAppointment(appointmentId, patientId) {
            showLoadingOverlay('Loading appointment details...');

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=1&action=get_appointment_details&appointment_id=${appointmentId}&patient_id=${patientId}`
                })
                .then(response => response.json())
                .then(data => {
                    hideLoadingOverlay();
                    if (data.success) {
                        displayAppointmentDetails(data.appointment);
                    } else {
                        showError(data.message || 'Failed to load appointment details');
                    }
                })
                .catch(error => {
                    hideLoadingOverlay();
                    showError('Network error: ' + error.message);
                });
        }

        function displayAppointmentDetails(appointment) {
            const modalBody = document.getElementById('appointmentModalBody');
            const isPriority = appointment.isSenior || appointment.isPWD;

            modalBody.innerHTML = `
                <div class="appointment-summary">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-calendar-alt"></i> Appointment Information</h5>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Appointment ID</span>
                                    <span class="info-value">APT-${appointment.appointment_id.toString().padStart(8, '0')}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Date & Time</span>
                                    <span class="info-value">${formatDate(appointment.scheduled_date)} at ${formatTime(appointment.scheduled_time)}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Service</span>
                                    <span class="info-value">${appointment.service_name || 'General Consultation'}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status</span>
                                    <span class="status-badge status-${appointment.status}">${capitalizeFirst(appointment.status.replace('_', ' '))}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5><i class="fas fa-user"></i> Patient Information</h5>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Full Name</span>
                                    <span class="info-value">${appointment.first_name} ${appointment.last_name}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Patient ID</span>
                                    <span class="info-value">${appointment.patient_id}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Date of Birth</span>
                                    <span class="info-value">${appointment.date_of_birth || 'Not recorded'}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Barangay</span>
                                    <span class="info-value">${appointment.barangay_name || 'Not specified'}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="priority-section">
                        <h5><i class="fas fa-star"></i> Priority Status</h5>
                        <div class="priority-indicators">
                            ${appointment.isSenior ? '<span class="priority-badge priority-senior"><i class="fas fa-user"></i> Senior Citizen</span>' : ''}
                            ${appointment.isPWD ? '<span class="priority-badge priority-pwd"><i class="fas fa-wheelchair"></i> PWD</span>' : ''}
                            ${!isPriority ? '<span class="priority-badge">Normal Priority</span>' : ''}
                        </div>
                    </div>

                    ${appointment.referral_reason ? `
                    <div class="referral-section">
                        <h5><i class="fas fa-share"></i> Referral Information</h5>
                        <div class="info-item">
                            <span class="info-label">Reason</span>
                            <span class="info-value">${appointment.referral_reason}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Referred By</span>
                            <span class="info-value">${appointment.referred_by || 'Not specified'}</span>
                        </div>
                    </div>
                    ` : ''}

                    ${appointment.qr_code_path ? `
                    <div class="qr-section">
                        <h5><i class="fas fa-qrcode"></i> QR Code Preview</h5>
                        <div class="qr-preview">
                            <img src="${appointment.qr_code_path}" alt="Appointment QR Code" style="max-width: 150px; height: auto;">
                        </div>
                    </div>
                    ` : ''}

                    <div class="action-section">
                        ${!appointment.already_checked_in && appointment.status === 'confirmed' ? `
                        <button type="button" class="btn btn-success" onclick="showCheckinConfirm(${appointment.appointment_id}, ${appointment.patient_id})">
                            <i class="fas fa-user-check"></i> Accept Booking / Check-In
                        </button>
                        ` : appointment.already_checked_in ? `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Patient has already been checked in.
                        </div>
                        ` : ''}
                        
                        <button type="button" class="btn btn-warning" onclick="showFlagPatient(${appointment.appointment_id}, ${appointment.patient_id})">
                            <i class="fas fa-flag"></i> Flag Patient
                        </button>
                    </div>
                </div>
            `;

            showModal('appointmentModal');
        }

        // Quick check-in function
        function quickCheckin(appointmentId, patientId) {
            showCheckinConfirm(appointmentId, patientId);
        }

        // Show check-in confirmation
        function showCheckinConfirm(appointmentId, patientId) {
            document.getElementById('confirmAppointmentId').value = appointmentId;
            document.getElementById('confirmPatientId').value = patientId;

            // Populate confirmation details
            const row = document.querySelector(`tr[data-appointment-id="${appointmentId}"]`);
            if (row) {
                const patientName = row.querySelector('td:nth-child(2) strong').textContent;
                const appointmentTime = row.querySelector('td:nth-child(3)').textContent;

                document.getElementById('checkinConfirmDetails').innerHTML = `
                    <div class="confirmation-summary">
                        <h6>Confirm check-in for:</h6>
                        <p><strong>Patient:</strong> ${patientName}</p>
                        <p><strong>Appointment:</strong> APT-${appointmentId.toString().padStart(8, '0')}</p>
                        <p><strong>Scheduled:</strong> ${appointmentTime}</p>
                    </div>
                `;
            }

            closeModal('appointmentModal');
            showModal('checkinConfirmModal');
        }

        // Show flag patient modal
        function showFlagPatient(appointmentId, patientId) {
            document.getElementById('flagAppointmentId').value = appointmentId;
            document.getElementById('flagPatientId').value = patientId;

            // Populate patient summary
            const row = document.querySelector(`tr[data-appointment-id="${appointmentId}"]`);
            if (row) {
                const patientName = row.querySelector('td:nth-child(2) strong').textContent;
                const appointmentTime = row.querySelector('td:nth-child(3)').textContent;

                document.getElementById('flagPatientSummary').innerHTML = `
                    <div class="patient-summary-content">
                        <h6>Patient to flag:</h6>
                        <p><strong>Name:</strong> ${patientName}</p>
                        <p><strong>Appointment:</strong> APT-${appointmentId.toString().padStart(8, '0')}</p>
                        <p><strong>Scheduled:</strong> ${appointmentTime}</p>
                    </div>
                `;
            }

            closeModal('appointmentModal');
            showModal('flagModal');
        }

        // Flag patient function (for action buttons in table)
        function flagPatient(appointmentId, patientId) {
            showFlagPatient(appointmentId, patientId);
        }

        // Loading overlay functions
        function showLoadingOverlay(message = 'Loading...') {
            const overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                color: white;
                font-size: 1.2rem;
            `;
            overlay.innerHTML = `
                <div style="text-align: center;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 1rem;">${message}</p>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        function hideLoadingOverlay() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.remove();
            }
        }

        // Alert functions
        function showSuccess(message) {
            showAlert(message, 'success');
        }

        function showError(message) {
            showAlert(message, 'danger');
        }

        function showInfo(message) {
            showAlert(message, 'info');
        }

        function showAlert(message, type = 'info') {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert-dynamic');
            existingAlerts.forEach(alert => alert.remove());

            // Create new alert
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dynamic`;
            alert.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 400px;
                min-width: 250px;
                animation: slideInRight 0.3s ease;
            `;

            const icon = type === 'success' ? 'check-circle' :
                type === 'danger' ? 'exclamation-triangle' :
                'info-circle';

            alert.innerHTML = `
                <i class="fas fa-${icon}"></i> ${message}
                <button type="button" style="background: none; border: none; float: right; font-size: 1.2rem; color: inherit; cursor: pointer;" onclick="this.parentElement.remove();">&times;</button>
            `;

            document.body.appendChild(alert);

            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 10000);
        }

        // Form submission with loading
        function submitFormWithLoading(formId, loadingMessage = 'Processing...') {
            const form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', function(e) {
                showLoadingOverlay(loadingMessage);
            });
        }

        // Initialize form handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading to form submissions
            submitFormWithLoading('checkinConfirmForm', 'Checking in patient...');
            submitFormWithLoading('flagForm', 'Flagging patient...');
            submitFormWithLoading('cancelForm', 'Cancelling appointment...');

            // Add AJAX search on form submit
            const searchForm = document.getElementById('searchForm');
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    performSearch();
                });
            }

            // Add CSS animation styles
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
                .info-grid {
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
                .row {
                    display: flex;
                    flex-wrap: wrap;
                    margin: -0.5rem;
                }
                .col-md-6 {
                    flex: 0 0 50%;
                    padding: 0.5rem;
                }
                @media (max-width: 768px) {
                    .col-md-6 { flex: 0 0 100%; }
                }
                .priority-option {
                    display: block;
                    padding: 0.75rem;
                    margin-bottom: 0.5rem;
                    border: 2px solid #e9ecef;
                    border-radius: 8px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .priority-option:hover {
                    border-color: #007bff;
                    background-color: #f8f9fa;
                }
                .priority-option input[type="radio"] {
                    margin-right: 0.5rem;
                }
                .priority-label {
                    font-weight: 600;
                    display: block;
                }
                .priority-option small {
                    display: block;
                    color: #6c757d;
                    margin-top: 0.25rem;
                }
                .form-text {
                    font-size: 0.875em;
                    color: #6c757d;
                }
            `;
            document.head.appendChild(style);
        });

        // Cancel Appointment Function
        async function cancelAppointment(appointmentId, patientId) {
            if (!confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('../../api/checkin/cancel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'cancel_appointment',
                        appointment_id: appointmentId,
                        patient_id: patientId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Appointment successfully cancelled.', 'success');
                    
                    // Remove the row from the table or refresh
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert('Error: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error cancelling appointment:', error);
                showAlert('An error occurred while cancelling the appointment.', 'error');
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
    </div>
</body>

</html>