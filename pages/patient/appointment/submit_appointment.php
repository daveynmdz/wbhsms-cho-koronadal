<?php
// submit_appointment.php - API endpoint for submitting appointment bookings
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

// Include PHPMailer classes at file level
require_once $root_path . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once $root_path . '/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once $root_path . '/vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
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
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
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

// Check if date is not in the past
$today = date('Y-m-d');
if ($appointment_date <= $today) {
    echo json_encode(['success' => false, 'message' => 'Cannot book appointments for today or past dates']);
    exit();
}

// Validate time slot (8 AM to 4 PM)
$hour = (int)explode(':', $appointment_time)[0];
if ($hour < 8 || $hour >= 16) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment time. Please select between 8 AM and 4 PM']);
    exit();
}

$conn->begin_transaction();

try {
    // Check slot availability (double-check to prevent overbooking)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as booking_count
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        WHERE scheduled_date = ? 
        AND scheduled_time = ? 
        AND s.name = ? 
        AND f.type LIKE CONCAT('%', ?, '%')
        AND status IN ('confirmed', 'pending')
        FOR UPDATE
    ");
    
    $stmt->bind_param("ssss", $appointment_date, $appointment_time, $service, $facility_type);
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
        AND status IN ('confirmed', 'pending')
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
    
    // Insert appointment
    $stmt = $conn->prepare("
        INSERT INTO appointments (
            patient_id, facility_id, referral_id, service_id, 
            scheduled_date, scheduled_time, status
        ) VALUES (?, ?, ?, ?, ?, ?, 'confirmed')
    ");
    
    $stmt->bind_param("iiiiss", 
        $patient_id, $facility_id, $referral_id, $service_id,
        $appointment_date, $appointment_time
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create appointment: ' . $stmt->error);
    }
    
    $appointment_id = $conn->insert_id;
    $stmt->close();
    
    // If referral was used, update referral status to 'accepted'
    if ($referral_id) {
        $stmt = $conn->prepare("UPDATE referrals SET status = 'accepted' WHERE referral_id = ?");
        $stmt->bind_param("i", $referral_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Send email notification
    $email_sent = false;
    $appointment_reference = 'APT-' . str_pad($appointment_id, 8, '0', STR_PAD_LEFT);
    try {
        $email_sent = sendAppointmentConfirmationEmail($patient_info, $appointment_reference, $facility_name, $service, $appointment_date, $appointment_time);
    } catch (Exception $e) {
        // Log email error but don't fail the appointment creation
        error_log("Failed to send appointment confirmation email: " . $e->getMessage());
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Appointment booked successfully',
        'appointment_id' => 'APT-' . str_pad($appointment_id, 8, '0', STR_PAD_LEFT),
        'appointment_db_id' => $appointment_id,
        'email_sent' => $email_sent
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();

// Function to send appointment confirmation email
function sendAppointmentConfirmationEmail($patient_info, $appointment_num, $facility_name, $service, $appointment_date, $appointment_time) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com'; // Configure your email
        $mail->Password = 'your-app-password';     // Configure your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('noreply@chokoronadal.gov.ph', 'CHO Koronadal');
        $mail->addAddress($patient_info['email'], $patient_info['first_name'] . ' ' . $patient_info['last_name']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation - CHO Koronadal';
        
        // Format date and time for display
        $formatted_date = date('F j, Y (l)', strtotime($appointment_date));
        $formatted_time = date('g:i A', strtotime($appointment_time . ':00'));
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #0077b6, #023e8a); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; }
                .appointment-details { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; padding: 8px 0; border-bottom: 1px solid #eee; }
                .label { font-weight: bold; color: #0077b6; }
                .value { color: #333; }
                .important { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🏥 Appointment Confirmed</h1>
                    <p>CHO Koronadal Health Management System</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$patient_info['first_name']} {$patient_info['last_name']},</p>
                    
                    <p>Your appointment has been successfully booked. Here are your appointment details:</p>
                    
                    <div class='appointment-details'>
                        <div class='detail-row'>
                            <span class='label'>Appointment ID:</span>
                            <span class='value'><strong>{$appointment_num}</strong></span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Patient Name:</span>
                            <span class='value'>{$patient_info['first_name']} {$patient_info['middle_name']} {$patient_info['last_name']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Facility:</span>
                            <span class='value'>{$facility_name}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Service:</span>
                            <span class='value'>{$service}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Date:</span>
                            <span class='value'>{$formatted_date}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Time:</span>
                            <span class='value'>{$formatted_time}</span>
                        </div>
                    </div>
                    
                    <div class='important'>
                        <h4>📋 Important Reminders:</h4>
                        <ul>
                            <li>Please arrive 15 minutes before your scheduled appointment time</li>
                            <li>Bring a valid government-issued ID</li>
                            <li>If you have a referral, please bring your referral document</li>
                            <li>Please present this email or your Appointment ID: <strong>{$appointment_num}</strong></li>
                            <li>If you need to cancel or reschedule, please contact us at least 24 hours in advance</li>
                        </ul>
                    </div>
                    
                    <p>If you have any questions or need to make changes to your appointment, please contact us through the patient portal or visit our facility.</p>
                    
                    <p>Thank you for choosing CHO Koronadal for your healthcare needs.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>© 2024 CHO Koronadal Health Management System</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->AltBody = "
        Appointment Confirmation - CHO Koronadal
        
        Dear {$patient_info['first_name']} {$patient_info['last_name']},
        
        Your appointment has been successfully booked.
        
        Appointment Details:
        - Appointment ID: {$appointment_num}
        - Patient: {$patient_info['first_name']} {$patient_info['middle_name']} {$patient_info['last_name']}
        - Facility: {$facility_name}
        - Service: {$service}
        - Date: {$formatted_date}
        - Time: {$formatted_time}
        
        Important Reminders:
        - Please arrive 15 minutes before your scheduled appointment time
        - Bring a valid government-issued ID
        - If you have a referral, please bring your referral document
        - Please present this email or your Appointment ID: {$appointment_num}
        
        Thank you for choosing CHO Koronadal for your healthcare needs.
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>