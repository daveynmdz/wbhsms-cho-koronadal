<?php
// submit_appointment.php - API endpoint for submitting appointment bookings
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include patient session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// If user is not logged in, return error
if (!isset($_SESSION['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Include queue management service
require_once $root_path . '/utils/queue_management_service.php';

// Include appointment logger
require_once $root_path . '/utils/appointment_logger.php';

// Include QR code generator
require_once $root_path . '/utils/qr_code_generator.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    error_log("submit_appointment.php: Invalid input data received");
    echo json_encode(['success' => false, 'message' => 'Invalid input data received']);
    exit();
}

$patient_id = $_SESSION['patient_id'];
$facility_type = $input['facility_type'] ?? '';
$referral_id = $input['referral_id'] ?? null;
$service = $input['service'] ?? '';
$appointment_date = $input['appointment_date'] ?? '';
$appointment_time = $input['appointment_time'] ?? '';

// Validate required fields
if (empty($facility_type) || empty($service) || empty($appointment_date) || empty($appointment_time)) {
    $missing_fields = [];
    if (empty($facility_type)) $missing_fields[] = 'facility_type';
    if (empty($service)) $missing_fields[] = 'service';
    if (empty($appointment_date)) $missing_fields[] = 'appointment_date';
    if (empty($appointment_time)) $missing_fields[] = 'appointment_time';
    
    error_log("submit_appointment.php: Missing required fields: " . implode(', ', $missing_fields));
    echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
    exit();
}

// Validate facility type
if (!in_array($facility_type, ['bhc', 'dho', 'cho'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid facility type']);
    exit();
}

// Validate that referral is provided for DHO/CHO
if (($facility_type === 'dho' || $facility_type === 'cho') && empty($referral_id)) {
    echo json_encode(['success' => false, 'message' => 'Referral is required for DHO/CHO appointments']);
    exit();
}

// Validate date and time format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

if (!preg_match('/^\d{2}:\d{2}$/', $appointment_time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format']);
    exit();
}

// Check if date is not in the past (allow same-day booking)
$today = date('Y-m-d');
if ($appointment_date < $today) {
    echo json_encode(['success' => false, 'message' => 'Cannot book appointments for past dates']);
    exit();
}

// For same-day appointments, check if it's past 4 PM
if ($appointment_date === $today) {
    $current_hour = (int)date('H');
    if ($current_hour >= 16) {
        echo json_encode(['success' => false, 'message' => 'Same-day appointments are only available until 4:00 PM']);
        exit();
    }
    
    // Also check if the selected time slot has already passed
    $current_time = date('H:i');
    if ($appointment_time <= $current_time) {
        echo json_encode(['success' => false, 'message' => 'Selected time slot has already passed. Please choose a later time.']);
        exit();
    }
}

// Validate time slot (8 AM to 4 PM)
$hour = (int)explode(':', $appointment_time)[0];
if ($hour < 8 || $hour > 16) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment time. Please select between 8 AM and 4 PM']);
    exit();
}

// Debug: Check database connection before starting transaction
error_log("DEBUG: Starting appointment creation process for patient_id: $patient_id");
error_log("DEBUG: Database connection status: " . (($conn && !$conn->connect_error) ? 'OK' : 'ERROR'));

$conn->begin_transaction();

try {
    // Map frontend facility types to database facility types
    $facility_type_map = [
        'bhc' => 'Barangay Health Center',
        'dho' => 'District Health Office', 
        'cho' => 'City Health Office'
    ];
    
    $db_facility_type = $facility_type_map[$facility_type] ?? '';
    if (empty($db_facility_type)) {
        throw new Exception('Invalid facility type selected');
    }
    
    // Check slot availability (double-check to prevent overbooking)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as booking_count
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        WHERE a.scheduled_date = ? 
        AND a.scheduled_time = ? 
        AND s.name = ? 
        AND f.type = ?
        AND a.status = 'confirmed'
        FOR UPDATE
    ");
    
    $stmt->bind_param("ssss", $appointment_date, $appointment_time, $service, $db_facility_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $current_bookings = (int)$row['booking_count'];
    $stmt->close();
    
    if ($current_bookings >= 20) {
        throw new Exception('This time slot is fully booked. Please select another time.');
    }
    
    // Check for duplicate appointments
    $stmt = $conn->prepare("
        SELECT COUNT(*) as existing_count
        FROM appointments 
        WHERE patient_id = ? 
        AND scheduled_date = ? 
        AND status = 'confirmed'
    ");
    
    $stmt->bind_param("is", $patient_id, $appointment_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $existing_appointments = (int)$row['existing_count'];
    $stmt->close();
    
    if ($existing_appointments > 0) {
        throw new Exception('You already have an appointment on this date. Please select a different date.');
    }
    
    // If referral is provided, validate it belongs to the patient and is active
    if ($referral_id) {
        $stmt = $conn->prepare("
            SELECT referral_id, status 
            FROM referrals 
            WHERE referral_id = ? AND patient_id = ?
        ");
        
        $stmt->bind_param("ii", $referral_id, $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $referral = $result->fetch_assoc();
        $stmt->close();
        
        if (!$referral) {
            throw new Exception('Invalid referral selected');
        }
        
        if ($referral['status'] !== 'active') {
            throw new Exception('Selected referral is not active');
        }
    }
    
    // Get patient information for appointment creation and email
    $stmt = $conn->prepare("
        SELECT p.*, b.barangay_name
        FROM patients p
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        WHERE p.patient_id = ?
    ");
    
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_info = $result->fetch_assoc();
    $stmt->close();
    
    if (!$patient_info) {
        throw new Exception('Patient information not found');
    }
    
    // Get service_id and facility_id based on the selections
    $service_id = null;
    $facility_id = null;
    
    // Get service_id
    $stmt = $conn->prepare("SELECT service_id FROM services WHERE name = ?");
    $stmt->bind_param("s", $service);
    $stmt->execute();
    $result = $stmt->get_result();
    $service_row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$service_row) {
        throw new Exception('Invalid service selected');
    }
    $service_id = $service_row['service_id'];
    
    // Get facility_id based on facility_type and patient's barangay
    if ($facility_type === 'bhc') {
        // For BHC, find the health center in the patient's barangay
        $stmt = $conn->prepare("
            SELECT facility_id, name
            FROM facilities 
            WHERE barangay_id = ? AND type = 'Barangay Health Center'
            LIMIT 1
        ");
        $stmt->bind_param("i", $patient_info['barangay_id']);
    } else if ($facility_type === 'dho') {
        // For DHO, find the district health office for the patient's district
        $stmt = $conn->prepare("
            SELECT f.facility_id, f.name
            FROM facilities f
            JOIN barangay b ON f.district_id = b.district_id
            WHERE b.barangay_id = ? AND f.type = 'District Health Office'
            LIMIT 1
        ");
        $stmt->bind_param("i", $patient_info['barangay_id']);
    } else { // cho
        // For CHO, get the main city health office
        $stmt = $conn->prepare("
            SELECT facility_id, name
            FROM facilities 
            WHERE type = 'City Health Office' AND is_main = 1
            LIMIT 1
        ");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $facility_row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$facility_row) {
        throw new Exception('No suitable facility found for your location and selected facility type');
    }
    $facility_id = $facility_row['facility_id'];
    $facility_name = $facility_row['name'];
    
    // Debug: Log appointment insertion details
    error_log("DEBUG: About to insert appointment - patient_id: $patient_id, facility_id: $facility_id, service_id: $service_id, date: $appointment_date, time: $appointment_time");
    
    // Insert appointment with confirmed status (auto-confirm for better UX)
    $stmt = $conn->prepare("
        INSERT INTO appointments (
            patient_id, facility_id, referral_id, service_id, 
            scheduled_date, scheduled_time, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare appointment statement: ' . $conn->error);
    }
    
    $stmt->bind_param("iiiiss", 
        $patient_id, $facility_id, $referral_id, $service_id,
        $appointment_date, $appointment_time
    );
    
    if (!$stmt->execute()) {
        error_log("DEBUG: Failed to execute appointment insert: " . $stmt->error);
        throw new Exception('Failed to create appointment: ' . $stmt->error);
    }
    
    $appointment_id = $conn->insert_id;
    error_log("DEBUG: Successfully created appointment with ID: $appointment_id");
    $stmt->close();
    
    // Log appointment creation using appointment logger
    try {
        $appointment_logger = new AppointmentLogger($conn);
        $log_success = $appointment_logger->logAppointmentCreation(
            $appointment_id, 
            $patient_id, 
            $appointment_date, 
            $appointment_time, 
            'patient', 
            $patient_id
        );
        
        if (!$log_success) {
            // Log the issue but don't fail the appointment creation
            error_log("Warning: Failed to log appointment creation for appointment_id: $appointment_id");
        }
    } catch (Exception $log_exception) {
        // Log the exception but don't fail the appointment creation
        error_log("Warning: Exception in appointment logging: " . $log_exception->getMessage());
    }
    
    // Update referral status based on business rules  
    if ($referral_id) {
        // Check if notes column exists in referrals table
        $check_notes_query = "SHOW COLUMNS FROM referrals LIKE 'notes'";
        $notes_result = $conn->query($check_notes_query);
        $has_notes_column = ($notes_result->num_rows > 0);
        
        if ($has_notes_column) {
            // Update referral status with notes (for databases that have the column)
            $stmt = $conn->prepare("
                UPDATE referrals 
                SET status = 'accepted', 
                    updated_at = NOW(),
                    notes = CONCAT(COALESCE(notes, ''), 'Used for appointment #', ?)
                WHERE referral_id = ?
            ");
            $appointment_reference = 'APT-' . str_pad($appointment_id, 8, '0', STR_PAD_LEFT);
            $stmt->bind_param("si", $appointment_reference, $referral_id);
        } else {
            // Update referral status without notes (for production databases without notes column)
            $stmt = $conn->prepare("
                UPDATE referrals 
                SET status = 'accepted', 
                    updated_at = NOW()
                WHERE referral_id = ?
            ");
            $stmt->bind_param("i", $referral_id);
        }
        
        $stmt->execute();
        $stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Generate QR code for appointment
    error_log("DEBUG: Generating QR code for appointment $appointment_id");
    $qr_result = QRCodeGenerator::generateAndSaveQR(
        $appointment_id,
        [
            'patient_id' => $patient_id,
            'scheduled_date' => $appointment_date,
            'scheduled_time' => $appointment_time,
            'facility_id' => $facility_id,
            'service_id' => $service_id
        ],
        $conn // Use MySQLi connection for consistency
    );
    
    if ($qr_result['success']) {
        error_log("DEBUG: QR code generated successfully for appointment $appointment_id");
    } else {
        error_log("WARNING: QR code generation failed for appointment $appointment_id: " . $qr_result['error']);
    }
    
    // Send email notification
    $email_result = ['success' => false, 'message' => 'Email not configured'];
    $appointment_reference = 'APT-' . str_pad($appointment_id, 8, '0', STR_PAD_LEFT);
    
    try {
        // Check if patient has a valid email address
        if (empty($patient_info['email']) || !filter_var($patient_info['email'], FILTER_VALIDATE_EMAIL)) {
            error_log("Patient email invalid or missing: " . ($patient_info['email'] ?? 'null'));
            $email_result = ['success' => false, 'message' => 'Patient email address not available'];
        } else {
            // Send email using the same pattern as OTP emails
            $email_result = sendAppointmentConfirmationEmail(
                $patient_info, 
                $appointment_reference, 
                $facility_name, 
                $service, 
                $appointment_date, 
                $appointment_time, 
                $referral_id,
                $qr_result      // Pass QR information to email function
            );
        }
    } catch (Exception $e) {
        // Log email error but don't fail the appointment creation
        error_log("Failed to send appointment confirmation email: " . $e->getMessage());
        $email_result = ['success' => false, 'message' => 'Email sending failed: ' . $e->getMessage()];
    }
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'Appointment booked successfully',
        'appointment_id' => 'APT-' . str_pad($appointment_id, 8, '0', STR_PAD_LEFT),
        'appointment_db_id' => $appointment_id,
        'facility_type' => $facility_type,
        'email_sent' => $email_result['success'],
        'email_message' => $email_result['message'],
        'patient_email' => $patient_info['email']
    ];
    
    // Add QR code information
    if (isset($qr_result) && $qr_result['success']) {
        $response['qr_generated'] = true;
        $response['qr_verification_code'] = $qr_result['verification_code'];
        $response['qr_message'] = 'QR code generated successfully for seamless check-in';
    } else {
        $response['qr_generated'] = false;
        $response['qr_message'] = isset($qr_result) ? $qr_result['error'] : 'QR generation not attempted';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("submit_appointment.php: Exception caught: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

$conn->close();

// Function to send appointment confirmation email using the same pattern as OTP emails
function sendAppointmentConfirmationEmail($patient_info, $appointment_num, $facility_name, $service, $appointment_date, $appointment_time, $referral_id = null, $qr_result = null) {
    try {
        // Get referral information if available
        $referral_num = '';
        if ($referral_id) {
            global $conn;
            $stmt = $conn->prepare("SELECT referral_num FROM referrals WHERE referral_id = ?");
            $stmt->bind_param("i", $referral_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $referral = $result->fetch_assoc();
            $stmt->close();
            if ($referral) {
                $referral_num = $referral['referral_num'];
            }
        }

        $patient_name = trim($patient_info['first_name'] . ' ' . ($patient_info['middle_name'] ?? '') . ' ' . $patient_info['last_name']);
        $formatted_date = date('F j, Y (l)', strtotime($appointment_date));
        $formatted_time = date('g:i A', strtotime($appointment_time . ':00'));

        // Note: Queue information is NOT included in appointment confirmation emails
        // Queue codes are only assigned during check-in process

        // Prepare QR code information for email
        $qr_info_html = '';
        $qr_info_text = '';
        $has_qr_image = false;
        
        if ($qr_result && $qr_result['success']) {
            // Get QR code from database
            global $conn;
            $stmt = $conn->prepare("SELECT qr_code_path FROM appointments WHERE appointment_id = ?");
            $appointment_id = substr($appointment_num, 4); // Remove 'APT-' prefix and leading zeros
            $appointment_id = (int)$appointment_id;
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment_data = $result->fetch_assoc();
            $stmt->close();
            
            if ($appointment_data && $appointment_data['qr_code_path']) {
                $has_qr_image = true;
                $qr_verification_code = $qr_result['verification_code'] ?? 'N/A';
                
                $qr_info_html = '
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">QR Code:</td>
                            <td style="padding: 12px 0; color: #333;">Available for seamless check-in</td>
                        </tr>
                        <tr' . (!empty($referral_num) ? ' style="border-bottom: 1px solid #e9ecef;"' : '') . '>
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">Verification Code:</td>
                            <td style="padding: 12px 0; color: #333; font-family: monospace; background: #f8f9fa; padding: 8px; border-radius: 4px;">' . htmlspecialchars($qr_verification_code, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>';
                
                $qr_info_text = "\nQR Code: Available for seamless check-in\nVerification Code: {$qr_verification_code}";
            }
        }

        // For development: bypass email if SMTP_PASS is empty or 'disabled'
        $bypassEmail = empty($_ENV['SMTP_PASS']) || $_ENV['SMTP_PASS'] === 'disabled';
        
        if ($bypassEmail) {
            // Development mode: log instead of sending
            error_log("DEVELOPMENT MODE: Appointment confirmation for {$patient_info['email']} - {$appointment_num}");
            return ['success' => false, 'message' => 'DEVELOPMENT MODE: Email sending disabled. Appointment confirmation logged.'];
        }

        // Load PHPMailer classes the same way as OTP system
        if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            $root_path = dirname(dirname(dirname(__DIR__)));
            $vendorAutoload = $root_path . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            } else {
                require_once $root_path . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
                require_once $root_path . '/vendor/phpmailer/phpmailer/src/SMTP.php';
                require_once $root_path . '/vendor/phpmailer/phpmailer/src/Exception.php';
            }
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Use the same SMTP configuration as the working OTP system
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Username = $_ENV['SMTP_USER'] ?? 'cityhealthofficeofkoronadal@gmail.com';
        $mail->Password = $_ENV['SMTP_PASS'] ?? '';
        $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
        $fromEmail = $_ENV['SMTP_FROM'] ?? 'cityhealthofficeofkoronadal@gmail.com';
        $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'City Health Office of Koronadal';

        // Add debugging for development
        $debug = ($_ENV['APP_DEBUG'] ?? '0') === '1';
        if ($debug) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = 'error_log';
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($patient_info['email'], $patient_name);

        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation - CHO Koronadal [' . $appointment_num . ']';
        
        // Create HTML email body
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
            <div style="background: linear-gradient(135deg, #0077b6, #023e8a); color: white; padding: 30px 20px; text-align: center;">
                <h1 style="margin: 0 0 10px 0; font-size: 28px;">🏥 Appointment Confirmed</h1>
                <p style="margin: 0; font-size: 16px; opacity: 0.9;">City Health Office of Koronadal</p>
            </div>
            
            <div style="padding: 30px 20px;">
                <p style="font-size: 16px; margin-bottom: 20px;">
                    Dear <strong>' . htmlspecialchars($patient_name, ENT_QUOTES, 'UTF-8') . '</strong>,
                </p>
                
                <p style="margin-bottom: 25px;">
                    Your appointment has been successfully scheduled. Please save this confirmation for your records.
                </p>
                
                <div style="background: #0077b6; color: white; padding: 10px 15px; border-radius: 25px; display: inline-block; font-weight: bold; font-size: 16px; margin-bottom: 20px;">
                    📋 Appointment ID: ' . htmlspecialchars($appointment_num, ENT_QUOTES, 'UTF-8') . '
                </div>
                
                <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin: 20px 0; border-left: 5px solid #0077b6;">
                    <h3 style="margin: 0 0 15px 0; color: #0077b6; font-size: 20px;">Appointment Details</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6; width: 35%;">Patient Name:</td>
                            <td style="padding: 12px 0; color: #333;">' . htmlspecialchars($patient_name, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">Healthcare Facility:</td>
                            <td style="padding: 12px 0; color: #333;">' . htmlspecialchars($facility_name, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">Service Type:</td>
                            <td style="padding: 12px 0; color: #333;">' . htmlspecialchars($service, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">Appointment Date:</td>
                            <td style="padding: 12px 0; color: #333;">' . htmlspecialchars($formatted_date, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">Appointment Time:</td>
                            <td style="padding: 12px 0; color: #333;">' . htmlspecialchars($formatted_time, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>' . 
                        $qr_info_html .
                        (!empty($referral_num) ? '
                        <tr>
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">Referral Number:</td>
                            <td style="padding: 12px 0; color: #333;">#' . htmlspecialchars($referral_num, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>' : '') . '
                    </table>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;">⏰ Please Arrive Early</h4>
                    <p style="margin: 0; color: #856404;">
                        <strong>Recommended arrival time: 15 minutes before your appointment.</strong><br>
                        This allows time for check-in procedures.
                    </p>
                </div>
                
                <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #155724;">📋 Required Documents</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #155724;">
                        <li><strong>Valid Government-issued ID</strong></li>
                        <li><strong>This appointment confirmation</strong></li>' .
                        (!empty($referral_num) ? '<li><strong>Original referral document</strong></li>' : '') . '
                        <li><strong>PhilHealth card</strong> (if applicable)</li>
                        <li><strong>Previous medical records</strong> (if relevant)</li>
                    </ul>
                </div>
                
                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #1976d2;">📞 Contact Information</h4>
                    <p style="margin: 5px 0; color: #333;"><strong>Phone:</strong> (083) 228-8042</p>
                    <p style="margin: 5px 0; color: #333;"><strong>Email:</strong> info@chokoronadal.gov.ph</p>
                    <p style="margin: 10px 0 0 0; font-size: 14px; color: #6c757d;">
                        For cancellations or rescheduling, please contact us at least 24 hours in advance.
                    </p>
                </div>
                
                <p style="margin-top: 25px; font-size: 16px;">
                    Thank you for choosing CHO Koronadal for your healthcare needs!
                </p>
            </div>
            
            <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef;">
                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;"><strong>City Health Office of Koronadal</strong></p>
                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">This is an automated message. Please do not reply to this email.</p>
                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">© ' . date('Y') . ' CHO Koronadal. All rights reserved.</p>
            </div>
        </div>';

        // Create plain text version
        $mail->AltBody = "APPOINTMENT CONFIRMATION - CHO KORONADAL

Dear {$patient_name},

Your appointment has been successfully scheduled.

APPOINTMENT DETAILS:
Appointment ID: {$appointment_num}
Patient Name: {$patient_name}
Healthcare Facility: {$facility_name}
Service Type: {$service}
Appointment Date: {$formatted_date}
Appointment Time: {$formatted_time}" .
$qr_info_text .
(!empty($referral_num) ? "\nReferral Number: #{$referral_num}" : "") . "

IMPORTANT REMINDERS:
• Please arrive 15 minutes before your appointment time
• Bring a valid government-issued ID
• Bring this appointment confirmation" .
"• Present your referral document (if applicable)
• Bring your PhilHealth card (if applicable)

CONTACT INFORMATION:
Phone: (083) 228-8042
Email: info@chokoronadal.gov.ph

For cancellations or rescheduling, please contact us at least 24 hours in advance.

Thank you for choosing CHO Koronadal for your healthcare needs.

City Health Office of Koronadal
This is an automated message. Please do not reply to this email.
© " . date('Y') . " CHO Koronadal. All rights reserved.";

        // Embed QR code if available
        if ($has_qr_image && isset($appointment_data['qr_code_path'])) {
            try {
                // Create temporary file for QR code
                $temp_qr_file = tempnam(sys_get_temp_dir(), 'qr_appointment_');
                file_put_contents($temp_qr_file, $appointment_data['qr_code_path']);
                
                // Add QR code as embedded image
                $mail->addEmbeddedImage($temp_qr_file, 'qr_code', 'appointment_qr.png', 'base64', 'image/png');
                
                // Update email body to include QR code display
                $qr_section = '
                <div style="background: #f8f9fa; border: 2px dashed #6c757d; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
                    <h4 style="margin: 0 0 15px 0; color: #495057;">📱 Your QR Code for Check-in</h4>
                    <img src="cid:qr_code" alt="Appointment QR Code" style="max-width: 200px; height: auto; border: 1px solid #dee2e6; border-radius: 4px;">
                    <p style="margin: 15px 0 5px 0; color: #6c757d; font-size: 14px;">
                        <strong>Scan this QR code at the check-in station for instant verification</strong>
                    </p>
                    <p style="margin: 0; color: #6c757d; font-size: 12px;">
                        Verification Code: <span style="font-family: monospace; background: #e9ecef; padding: 2px 6px; border-radius: 3px;">' . htmlspecialchars($qr_verification_code, ENT_QUOTES, 'UTF-8') . '</span>
                    </p>
                </div>';
                
                // Insert QR section before the contact information
                $mail->Body = str_replace(
                    '<div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;">',
                    $qr_section . '<div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;">',
                    $mail->Body
                );
                
                // Clean up temp file after sending
                register_shutdown_function(function() use ($temp_qr_file) {
                    if (file_exists($temp_qr_file)) {
                        unlink($temp_qr_file);
                    }
                });
                
            } catch (Exception $qr_e) {
                error_log("Failed to embed QR code in email: " . $qr_e->getMessage());
                // Continue without QR code if embedding fails
            }
        }

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        // Log detailed error information
        $errorDetails = 'PHPMailer appointment email error: ' . ($mail->ErrorInfo ?? '') . ' Exception: ' . $e->getMessage();
        error_log($errorDetails);
        
        // More specific error message
        if (strpos($e->getMessage(), 'authenticate') !== false) {
            $errorMsg = 'Email service authentication failed';
        } else {
            $errorMsg = 'Failed to send appointment confirmation email';
        }
        
        return ['success' => false, 'message' => $errorMsg, 'technical_error' => $e->getMessage()];
    } catch (Exception $e) {
        error_log("Error in sendAppointmentConfirmationEmail: " . $e->getMessage());
        return ['success' => false, 'message' => 'Email preparation failed: ' . $e->getMessage()];
    }
}
?>