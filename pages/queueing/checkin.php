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
require_once '../../utils/patient_flow_validator.php';

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

// Initialize Queue Management Service and Patient Flow Validator
try {
    $queueService = new QueueManagementService($pdo);
    $flowValidator = new PatientFlowValidator($pdo);
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
    $stmt = $pdo->prepare("SELECT service_id, name as service_name FROM services ORDER BY name");
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
                               s.name as service_name,
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
                header('Content-Type: application/json');
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
                header('Content-Type: application/json');
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
                               'CHO Koronadal' as facility_name,
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
            $is_philhealth = isset($_POST['is_philhealth']) ? (int)$_POST['is_philhealth'] : null;
            $philhealth_id = trim($_POST['philhealth_id'] ?? '');

            if ($appointment_id && $patient_id && $is_philhealth !== null) {
                // Check if queue service is available
                if (!isset($queueService)) {
                    $error = "Queue service is not available. Please refresh the page or contact administrator.";
                    break;
                }

                try {
                    // Ensure no open transactions before starting
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                        error_log("Warning: Found open transaction before check-in, rolling back");
                    }

                    // Get appointment and patient details first (no transaction yet)
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

                    // Determine priority level
                    $priority_level = 'normal';
                    if ($priority_override === 'priority' || $priority_override === 'emergency') {
                        $priority_level = $priority_override;
                    } elseif ($appointment_data['isSenior'] || $appointment_data['isPWD']) {
                        $priority_level = 'priority';
                    }

                    // **SINGLE TRANSACTION for all operations**
                    $pdo->beginTransaction();
                    
                    try {
                        // 1. Update patient PhilHealth status if provided
                        if ($is_philhealth !== null) {
                            $update_params = [$is_philhealth];
                            $philhealth_update_query = "UPDATE patients SET isPhilHealth = ?";
                            
                            if (!empty($philhealth_id) && $is_philhealth == 1) {
                                $philhealth_update_query .= ", philhealth_id_number = ?";
                                $update_params[] = $philhealth_id;
                            }
                            
                            $philhealth_update_query .= " WHERE patient_id = ?";
                            $update_params[] = $patient_id;
                            
                            $stmt = $pdo->prepare($philhealth_update_query);
                            $stmt->execute($update_params);
                        }

                        // 2. Create visit record
                        $stmt = $pdo->prepare("
                            INSERT INTO visits (
                                patient_id, facility_id, appointment_id, visit_date, 
                                visit_status, created_at, updated_at
                            ) VALUES (?, 1, ?, ?, 'ongoing', NOW(), NOW())
                        ");
                        $stmt->execute([$patient_id, $appointment_id, $appointment_data['scheduled_date']]);
                        $visit_id = $pdo->lastInsertId();

                        // 3. Generate queue code for CHO appointments using QueueManagementService
                        $queue_code = null;
                        $queue_number = null;
                        
                        // Use QueueManagementService to create proper queue entry with time-slot codes
                        $queue_result = $queueService->createQueueEntry(
                            $appointment_id, 
                            $patient_id, 
                            $appointment_data['service_id'], 
                            'triage', 
                            $priority_level, 
                            $employee_id
                        );
                        
                        if ($queue_result['success']) {
                            $queue_code = $queue_result['queue_code'];
                            $queue_entry_id = $queue_result['queue_entry_id'];
                            // Skip manual queue entry creation since QueueManagementService handled it
                            $skip_manual_queue_creation = true;
                        } else {
                            throw new Exception("Failed to create queue entry: " . $queue_result['message']);
                        }

                        // 4. Get station for this service
                        $stmt = $pdo->prepare("
                            SELECT station_id FROM stations 
                            WHERE service_id = ? AND station_type = 'triage' AND is_active = 1 AND is_open = 1
                            LIMIT 1
                        ");
                        $stmt->execute([$appointment_data['service_id']]);
                        $station_result = $stmt->fetch();
                        
                        if (!$station_result) {
                            // Fallback to any open triage station
                            $stmt = $pdo->prepare("
                                SELECT station_id FROM stations 
                                WHERE station_type = 'triage' AND is_active = 1 AND is_open = 1 
                                LIMIT 1
                            ");
                            $stmt->execute();
                            $station_result = $stmt->fetch();
                        }
                        
                        if (!$station_result) {
                            throw new Exception("No open triage stations available. Please contact administrator.");
                        }
                        
                        $station_id = $station_result['station_id'];

                        // 5. Create queue entry (skip if QueueManagementService already handled it)
                        if (!isset($skip_manual_queue_creation)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO queue_entries (
                                    visit_id, appointment_id, patient_id, service_id, station_id,
                                    queue_type, queue_number, queue_code, priority_level, status
                                ) VALUES (?, ?, ?, ?, ?, 'triage', ?, ?, ?, 'waiting')
                            ");
                            $stmt->execute([
                                $visit_id, $appointment_id, $patient_id, $appointment_data['service_id'], 
                                $station_id, $queue_number, $queue_code, $priority_level
                            ]);
                            $queue_entry_id = $pdo->lastInsertId();
                        } else {
                            // Update the existing queue entry with visit_id
                            $stmt = $pdo->prepare("UPDATE queue_entries SET visit_id = ? WHERE queue_entry_id = ?");
                            $stmt->execute([$visit_id, $queue_entry_id]);
                        }

                        // 6. Update appointment status
                        $stmt = $pdo->prepare("UPDATE appointments SET status = 'checked_in' WHERE appointment_id = ?");
                        $stmt->execute([$appointment_id]);

                        // 7. Log queue creation
                        $stmt = $pdo->prepare("
                            INSERT INTO queue_logs (queue_entry_id, action, old_status, new_status, remarks, performed_by, created_at)
                            VALUES (?, 'created', null, 'waiting', ?, ?, NOW())
                        ");
                        $queue_remarks = $queue_code ? "Queue created with code: {$queue_code}" : 'Queue entry created';
                        $stmt->execute([$queue_entry_id, $queue_remarks, $employee_id]);

                        // 8. Log the check-in action
                        $stmt = $pdo->prepare("
                            INSERT INTO appointment_logs (appointment_id, patient_id, action, reason, created_by_type, created_by_id, created_at)
                            VALUES (?, ?, 'updated', ?, 'employee', ?, NOW())
                        ");
                        $log_details = json_encode([
                            'queue_code' => $queue_code ?? 'N/A',
                            'priority_level' => $priority_level,
                            'station' => 'triage',
                            'philhealth_status' => $is_philhealth ? 'Member' : 'Non-member'
                        ]);
                        $stmt->execute([$appointment_id, $patient_id, 'Patient checked in successfully', $employee_id]);

                        // Commit all operations
                        $pdo->commit();

                        // Success message
                        $queue_code_display = $queue_code ?? 'N/A';
                        $success = "Patient checked in successfully! Queue Code: " . $queue_code_display .
                            " | Priority: " . ucfirst($priority_level) . " | Next Station: Triage" .
                            " | PhilHealth: " . ($is_philhealth ? 'Member' : 'Non-member');

                        // Log success
                        error_log("Check-in successful for appointment {$appointment_id}: Queue Code {$queue_code_display}");
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e; // Re-throw to be caught by outer catch
                    }
                        
                } catch (Exception $e) {
                    // Clean up any open transactions
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Check-in failed: " . $e->getMessage();
                    error_log("Check-in error for appointment {$appointment_id}: " . $e->getMessage());
                }
            } else {
                if ($is_philhealth === null) {
                    $error = "Please specify PhilHealth membership status before check-in.";
                } else {
                    $error = "Invalid appointment or patient information.";
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

                    $employee_id = $_SESSION['employee_id'] ?? $_SESSION['user_id'];

                    // Insert patient flag
                    $stmt = $pdo->prepare("
                        INSERT INTO patient_flags (patient_id, appointment_id, flag_type, remarks, created_by_type, created_by_id, created_at) 
                        VALUES (?, ?, ?, ?, 'employee', ?, NOW())
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
                        INSERT INTO appointment_logs (appointment_id, patient_id, action, reason, notes, created_by_type, created_by_id, created_at) 
                        VALUES (?, ?, 'updated', ?, ?, 'employee', ?, NOW())
                    ");
                    $log_reason = "Patient flagged: $flag_type";
                    $log_notes = json_encode([
                        'flag_type' => $flag_type,
                        'remarks' => $remarks,
                        'status_changed_to' => $new_status
                    ]);
                    $stmt->execute([$appointment_id, $patient_id, $log_reason, $log_notes, $employee_id]);

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
                        INSERT INTO appointment_logs (appointment_id, patient_id, action, reason, notes, created_by_type, created_by_id, created_at) 
                        VALUES (?, ?, 'cancelled', ?, ?, 'employee', ?, NOW())
                    ");
                    $log_notes = json_encode([
                        'cancelled_by_role' => $user_role,
                        'cancellation_time' => date('Y-m-d H:i:s')
                    ]);
                    $stmt->execute([$appointment_id, $patient_id, $cancel_reason, $log_notes, $employee_id]);

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
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Main Layout */
        .homepage {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .checkin-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .content-area {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        /* Breadcrumb Navigation */
        .breadcrumb {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px 25px;
            border-bottom: 1px solid #dee2e6;
        }

        .breadcrumb a {
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #007bff;
        }

        .breadcrumb-separator {
            margin: 0 10px;
            color: #6c757d;
            font-size: 0.8rem;
        }

        .breadcrumb-current {
            color: #007bff;
            font-weight: 600;
        }

        /* Page Header */
        .page-header {
            padding: 25px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #007bff;
        }

        .total-count {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .bg-primary { background: linear-gradient(135deg, #007bff, #0056b3); color: white; }
        .bg-success { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; }
        .bg-info { background: linear-gradient(135deg, #17a2b8, #117a8b); color: white; }

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            margin: 20px 25px;
            border-radius: 8px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-left-color: #17a2b8;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-left-color: #ffc107;
        }

        /* Card Container */
        .card-container {
            background: white;
            margin: 20px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        /* Section Header */
        .section-header {
            background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #007bff;
        }

        .section-header h4 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h4 i {
            color: #007bff;
        }

        .toggle-instructions {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .toggle-instructions:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }

        .station-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-active { background: #28a745; }
        .status-inactive { background: #dc3545; }
        .status-maintenance { background: #ffc107; }

        .status-text {
            font-weight: 500;
        }

        /* Section Body */
        .section-body {
            padding: 25px;
        }

        /* Instructions */
        .compact-instructions .section-body {
            padding: 0;
        }

        .detailed-instructions {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .instruction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .step-compact {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #007bff;
        }

        .step-num {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .step-text {
            flex: 1;
            line-height: 1.5;
        }

        .step-text strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
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

        /* Action Sections */
        .action-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .action-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .action-section-title {
            color: #495057;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-section-title i {
            color: #007bff;
        }

        .action-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }

        /* Modern Buttons */
        .modern-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 20px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .modern-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s ease;
        }

        .modern-btn:hover::before {
            left: 100%;
        }

        .modern-btn:hover {
            border-color: #007bff;
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.15);
            transform: translateY(-2px);
        }

        .btn-nav {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .btn-nav:hover {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }

        .btn-qr-scan {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            border-color: #28a745;
        }

        .btn-qr-scan:hover {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
        }

        .btn-manual-search {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-color: #ffc107;
        }

        .btn-manual-search:hover {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
        }

        .btn-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .btn-nav:hover .btn-icon {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            color: #007bff;
        }

        .btn-qr-scan .btn-icon {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }

        .btn-qr-scan:hover .btn-icon {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            color: #28a745;
        }

        .btn-manual-search .btn-icon {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
        }

        .btn-manual-search:hover .btn-icon {
            background: linear-gradient(135deg, #212529, #495057);
            color: #ffc107;
        }

        .btn-content {
            flex: 1;
            text-align: left;
        }

        .btn-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
        }

        .btn-subtitle {
            font-size: 0.9rem;
            color: #6c757d;
            display: block;
        }

        .btn-nav:hover .btn-title,
        .btn-nav:hover .btn-subtitle {
            color: white;
        }

        .btn-qr-scan:hover .btn-title,
        .btn-qr-scan:hover .btn-subtitle {
            color: white;
        }

        .btn-manual-search:hover .btn-title {
            color: #212529;
        }

        .btn-manual-search:hover .btn-subtitle {
            color: #495057;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .quick-stat {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px 15px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .quick-stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #28a745, #ffc107, #dc3545);
        }

        .quick-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-total { border-color: #007bff; }
        .stat-total::before { background: linear-gradient(135deg, #007bff, #0056b3); }

        .stat-checked { border-color: #28a745; }
        .stat-checked::before { background: linear-gradient(135deg, #28a745, #1e7e34); }

        .stat-priority { border-color: #ffc107; }
        .stat-priority::before { background: linear-gradient(135deg, #ffc107, #e0a800); }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
        }

        .stat-total .stat-icon { background: linear-gradient(135deg, #007bff, #0056b3); }
        .stat-checked .stat-icon { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .stat-priority .stat-icon { background: linear-gradient(135deg, #ffc107, #e0a800); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            display: block;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* QR Scanner */
        .qr-scanner-area {
            text-align: center;
        }

        .qr-scanner-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 3px dashed #007bff;
            border-radius: 12px;
            padding: 60px 20px;
            margin-bottom: 20px;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .qr-scanner-box.scanning {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .qr-scanner-box i {
            display: block;
            margin-bottom: 15px;
            color: #007bff;
        }

        .qr-scanner-box.scanning i {
            color: #28a745;
            animation: pulse 1.5s infinite;
        }

        .qr-scanner-box p {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .qr-scanner-box small {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .qr-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* Forms */
        .search-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .form-control:hover {
            border-color: #ced4da;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            flex-wrap: wrap;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        /* Standard Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057, #343a40);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #1e7e34, #155724);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800, #d39e00);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }

        .loading-placeholder {
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #6c757d;
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            margin: 20px 0;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .results-table thead {
            background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
            color: white;
        }

        .results-table th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 3px solid #007bff;
        }

        .results-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .results-table tbody tr {
            transition: all 0.3s ease;
        }

        .results-table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: scale(1.01);
        }

        .text-muted {
            color: #6c757d !important;
            font-size: 0.85rem;
        }

        .d-block {
            display: block !important;
        }

        .text-success {
            color: #28a745 !important;
        }

        /* Status and Service Badges */
        .status-badge, .service-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .service-badge {
            background: linear-gradient(135deg, #e9ecef, #ced4da);
            color: #495057;
            border: 1px solid #adb5bd;
        }

        .status-confirmed { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; }
        .status-pending { background: linear-gradient(135deg, #ffc107, #e0a800); color: #212529; }
        .status-cancelled { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
        .status-completed { background: linear-gradient(135deg, #17a2b8, #117a8b); color: white; }

        /* Priority Badges */
        .priority-indicators {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .priority-badge {
            background: linear-gradient(135deg, #e9ecef, #ced4da);
            color: #495057;
        }

        .priority-senior {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
        }

        .priority-pwd {
            background: linear-gradient(135deg, #4ecdc4, #44a08d);
            color: white;
        }

        /* Action Buttons */
        .modern-action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .modern-action-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .modern-action-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transition: all 0.3s ease;
            transform: translate(-50%, -50%);
        }

        .modern-action-btn:hover::before {
            width: 40px;
            height: 40px;
        }

        .modern-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-view {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .btn-checkin {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .btn-flag {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .btn-icon-mini {
            font-size: 0.9rem;
            z-index: 1;
            position: relative;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .results-count {
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Footer */
        .footer-info {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-size: 0.9rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            margin: 20px;
            border: 1px solid #e9ecef;
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
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: block;
            opacity: 1;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.7);
            transition: transform 0.3s ease;
            overflow: hidden;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #007bff;
        }

        .modal-title {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }

        .modal-body {
            padding: 25px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            border-top: 1px solid #dee2e6;
        }

        /* Loading and Success States */
        .loading-placeholder {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .loading-placeholder i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #007bff;
        }

        .appointment-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }

        .confirmation-details,
        .confirmation-summary,
        .patient-summary {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .patient-summary-content h6,
        .confirmation-summary h6 {
            color: #856404;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .patient-summary-content p,
        .confirmation-summary p {
            margin: 5px 0;
            color: #495057;
        }

        /* PhilHealth Verification Section */
        .philhealth-verification {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .philhealth-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 15px 0;
        }

        .philhealth-option {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .philhealth-option:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
        }

        .philhealth-option input[type="radio"] {
            margin-right: 12px;
            transform: scale(1.2);
        }

        .philhealth-option input[type="radio"]:checked + .philhealth-label {
            font-weight: 600;
            color: #007bff;
        }

        .philhealth-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            margin: 0;
        }

        .philhealth-label i {
            font-size: 18px;
        }

        .philhealth-option small {
            display: block;
            color: #6c757d;
            margin-top: 4px;
            font-size: 13px;
        }

        .philhealth-id-section {
            margin-top: 15px;
            padding: 15px;
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }

        .philhealth-id-section label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
            display: block;
        }

        .philhealth-id-section input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        /* PhilHealth Verification Section */
        .philhealth-verification {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .philhealth-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }

        .philhealth-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .philhealth-option:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }

        .philhealth-option input[type="radio"] {
            margin-right: 10px;
        }

        .philhealth-option input[type="radio"]:checked + .philhealth-label {
            color: #007bff;
            font-weight: 600;
        }

        .philhealth-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .philhealth-label i {
            font-size: 1.2em;
        }

        .philhealth-option small {
            display: block;
            color: #6c757d;
            margin-top: 5px;
            font-size: 0.85em;
        }

        .philhealth-id-section {
            margin-top: 15px;
            padding: 15px;
            background: #fff;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .philhealth-id-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }

        .philhealth-id-section input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        /* Priority Selection */
        .priority-selection {
            margin-top: 20px;
        }

        .priority-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }

        /* Stats Section */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
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
            text-align: left;
        }

        .btn-subtitle {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 400;
            text-align: left;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        /* Stats Section */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

        /* Close Button */
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

        /* QR Scanner Styles */
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

            .table-responsive {
                overflow-x: auto;
            }

            .results-table {
                min-width: 800px;
            }

            .modern-action-buttons {
                flex-direction: column;
                gap: 5px;
            }

            .modern-action-btn {
                width: 32px;
                height: 32px;
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

                    <!-- PhilHealth Verification Section -->
                    <div class="philhealth-verification">
                        <label class="form-label">
                            <i class="fas fa-id-card"></i> PhilHealth Membership Status
                        </label>
                        <div class="philhealth-options">
                            <label class="philhealth-option">
                                <input type="radio" name="is_philhealth" value="1" id="philhealth_yes">
                                <span class="philhealth-label">
                                    <i class="fas fa-check-circle text-success"></i> PhilHealth Member
                                </span>
                                <small>Patient has valid PhilHealth coverage</small>
                            </label>
                            <label class="philhealth-option">
                                <input type="radio" name="is_philhealth" value="0" id="philhealth_no">
                                <span class="philhealth-label">
                                    <i class="fas fa-times-circle text-danger"></i> Non-PhilHealth
                                </span>
                                <small>Patient will be charged for services</small>
                            </label>
                        </div>
                        
                        <div class="philhealth-id-section" id="philhealth_id_section" style="display: none;">
                            <label for="philhealth_id">PhilHealth ID Number (Optional)</label>
                            <input type="text" name="philhealth_id" id="philhealth_id" class="form-control" 
                                   placeholder="e.g., 12-345678901-2" maxlength="15">
                            <small class="text-muted">For record keeping purposes</small>
                        </div>
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
                .then(response => {
                    // Debug: Log response details
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers.get('content-type'));
                    
                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Response is not JSON. Content-Type: ' + contentType);
                    }
                    
                    return response.json();
                })
                .then(data => {
                    hideLoadingOverlay();
                    console.log('Parsed data:', data); // Debug log
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

        // PhilHealth handling functions
        function handlePhilHealthSelection() {
            const philhealthYes = document.getElementById('philhealth_yes');
            const philhealthNo = document.getElementById('philhealth_no');
            const philhealthIdSection = document.getElementById('philhealth_id_section');
            
            if (philhealthYes && philhealthNo && philhealthIdSection) {
                philhealthYes.addEventListener('change', function() {
                    if (this.checked) {
                        philhealthIdSection.style.display = 'block';
                    }
                });
                
                philhealthNo.addEventListener('change', function() {
                    if (this.checked) {
                        philhealthIdSection.style.display = 'none';
                        document.getElementById('philhealth_id').value = '';
                    }
                });
            }
        }

        function validatePhilHealthSelection() {
            const philhealthYes = document.getElementById('philhealth_yes');
            const philhealthNo = document.getElementById('philhealth_no');
            
            if (!philhealthYes || !philhealthNo) return true; // Skip validation if elements not found
            
            if (!philhealthYes.checked && !philhealthNo.checked) {
                showAlert('Please specify the patient\'s PhilHealth membership status before proceeding.', 'warning');
                return false;
            }
            
            return true;
        }

        // Enhanced check-in form validation
        function validateCheckinForm() {
            return validatePhilHealthSelection();
        }

        // Initialize form handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize PhilHealth handling
            handlePhilHealthSelection();
            
            // Add enhanced validation to check-in form
            const checkinForm = document.getElementById('checkinConfirmForm');
            if (checkinForm) {
                checkinForm.addEventListener('submit', function(e) {
                    if (!validateCheckinForm()) {
                        e.preventDefault();
                        return false;
                    }
                    showLoadingOverlay('Checking in patient...');
                });
            }
            
            // Add loading to other form submissions
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