<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../config/env.php';
require_once '../../../utils/appointment_qr_generator.php';
require_once '../../../utils/queue_management_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Handle both JSON and form data input
    $input_data = [];
    
    // Check if data is sent as JSON
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($content_type, 'application/json') !== false) {
        $json_input = file_get_contents('php://input');
        $input_data = json_decode($json_input, true) ?? [];
        
        // Add patient_id from session if not provided
        if (!isset($input_data['patient_id']) && isset($_SESSION['patient_id'])) {
            $input_data['patient_id'] = $_SESSION['patient_id'];
        }
    } else {
        // Traditional form data
        $input_data = $_POST;
    }
    
    // Debug logging
    error_log("Appointment submission - Input data: " . json_encode($input_data));
    error_log("Session data: " . json_encode($_SESSION ?? []));
    
    // Collect and validate appointment data
    $patient_id = $input_data['patient_id'] ?? $_SESSION['patient_id'] ?? null;
    $service = $input_data['service'] ?? '';
    $facility_type = $input_data['facility_type'] ?? '';
    $appointment_date = $input_data['appointment_date'] ?? '';
    $appointment_time = $input_data['appointment_time'] ?? '';
    $referral_id = $input_data['referral_id'] ?? null;

    // Validation with detailed error information
    $missing_fields = [];
    if (!$patient_id) $missing_fields[] = 'patient_id';
    if (!$service) $missing_fields[] = 'service';
    if (!$facility_type) $missing_fields[] = 'facility_type';
    if (!$appointment_date) $missing_fields[] = 'appointment_date';
    if (!$appointment_time) $missing_fields[] = 'appointment_time';
    
    if (!empty($missing_fields)) {
        $error_message = 'Missing required fields: ' . implode(', ', $missing_fields);
        error_log("Appointment validation failed: $error_message");
        error_log("Received values - patient_id: $patient_id, service: $service, facility_type: $facility_type, appointment_date: $appointment_date, appointment_time: $appointment_time");
        throw new Exception($error_message);
    }

    // Validate date format and future date
    $date_obj = DateTime::createFromFormat('Y-m-d', $appointment_date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $appointment_date) {
        throw new Exception('Invalid appointment date format');
    }

    if ($date_obj < new DateTime('today')) {
        throw new Exception('Appointment date cannot be in the past');
    }

    // Validate time format
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $appointment_time)) {
        throw new Exception('Invalid appointment time format');
    }

    $conn->begin_transaction();

    // Check for existing appointments on the same date/time
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE patient_id = ? 
        AND scheduled_date = ? 
        AND scheduled_time = ? 
        AND status NOT IN ('cancelled', 'completed')
    ");
    $stmt->bind_param("iss", $patient_id, $appointment_date, $appointment_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing['count'] > 0) {
        throw new Exception('You already have an appointment scheduled for this date and time');
    }

    // Validate and get referral information if provided
    if ($referral_id && $referral_id !== '' && $referral_id !== 'null') {
        error_log("Validating referral_id: $referral_id for patient_id: $patient_id");
        
        $stmt = $conn->prepare("
            SELECT status, referring_facility_id 
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
        
        error_log("Referral validation passed for referral_id: $referral_id");
    } else {
        // No referral provided - set to null for consistency
        $referral_id = null;
        error_log("No referral provided, setting referral_id to null");
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
    
    // Get service_id with better error handling
    error_log("Looking up service: '$service'");
    $stmt = $conn->prepare("SELECT service_id FROM services WHERE name = ?");
    $stmt->bind_param("s", $service);
    $stmt->execute();
    $result = $stmt->get_result();
    $service_row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$service_row) {
        // Log available services for debugging
        $stmt = $conn->prepare("SELECT name FROM services LIMIT 10");
        $stmt->execute();
        $result = $stmt->get_result();
        $available_services = [];
        while ($row = $result->fetch_assoc()) {
            $available_services[] = $row['name'];
        }
        $stmt->close();
        
        error_log("Service '$service' not found. Available services: " . implode(', ', $available_services));
        throw new Exception("Invalid service selected: '$service'. Available services: " . implode(', ', $available_services));
    }
    $service_id = $service_row['service_id'];
    error_log("Service '$service' found with ID: $service_id");
    
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
        // For DHO, find the district health office for the patient's barangay
        $stmt = $conn->prepare("
            SELECT f.facility_id, f.name
            FROM facilities f
            JOIN barangay b ON f.district_id = b.district_id
            WHERE b.barangay_id = ? AND f.type = 'District Health Office'
            LIMIT 1
        ");
        $stmt->bind_param("i", $patient_info['barangay_id']);
    } else {
        // Default to City Health Office (CHO)
        $stmt = $conn->prepare("
            SELECT facility_id, name
            FROM facilities 
            WHERE type = 'City Health Office' AND name LIKE '%Main%'
            LIMIT 1
        ");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $facility_info = $result->fetch_assoc();
    $stmt->close();
    
    if (!$facility_info) {
        throw new Exception('No suitable healthcare facility found for your location');
    }
    
    $facility_id = $facility_info['facility_id'];
    $facility_name = $facility_info['name'];

    // Insert appointment record (appointment_num will be generated based on appointment_id)
    $stmt = $conn->prepare("
        INSERT INTO appointments (
            patient_id, service_id, facility_id, 
            scheduled_date, scheduled_time, status, referral_id, 
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, 'confirmed', ?, NOW(), NOW())
    ");
    
    $stmt->bind_param("iiissi", 
        $patient_id, $service_id, $facility_id, 
        $appointment_date, $appointment_time, $referral_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create appointment: ' . $stmt->error);
    }
    
    $appointment_id = $conn->insert_id;
    $stmt->close();
    
    // Generate appointment number based on appointment_id
    $appointment_num = 'APT-' . date('Ymd') . '-' . str_pad($appointment_id, 5, '0', STR_PAD_LEFT);

    // Generate QR code for ALL appointments (CHO, BHC, and DHO)
    $qr_result = null;
    error_log("Starting QR generation process for appointment {$appointment_id}");
    try {
        error_log("Loading AppointmentQRGenerator class...");
        require_once dirname(dirname(dirname(__DIR__))) . '/utils/appointment_qr_generator.php';
        
        if (!class_exists('AppointmentQRGenerator')) {
            error_log("CRITICAL: AppointmentQRGenerator class not found after include");
            throw new Exception('AppointmentQRGenerator class not found');
        }
        
        $qr_generator = new AppointmentQRGenerator();
        error_log("QR Generator instance created successfully");
        error_log("QR Generation - Calling with parameters: appointment_id={$appointment_id}, patient_id={$patient_id}, referral_id={$referral_id}, facility_id={$facility_id}, facility_type={$facility_type}");
        
        $qr_result = $qr_generator->generateAppointmentQR(
            $appointment_id,
            $patient_id,
            $referral_id,
            $facility_id,
            $facility_type
        );
        
        error_log("QR Generation Result: " . ($qr_result['success'] ? 'SUCCESS' : 'FAILED - ' . $qr_result['message']));
        if ($qr_result['success']) {
            error_log("QR File created: " . $qr_result['qr_filepath']);
            error_log("QR File size: " . $qr_result['file_size'] . " bytes");
        }
        
        if ($qr_result['success']) {
            // Update appointment record with QR code BLOB data
            $qr_update_success = $qr_generator->updateAppointmentQRBlob($conn, $appointment_id, $qr_result['qr_filepath']);
            if ($qr_update_success) {
                error_log("QR BLOB data stored successfully for appointment {$appointment_id}");
            } else {
                error_log("Failed to store QR BLOB data for appointment {$appointment_id}");
            }
        }
    } catch (Exception $e) {
        error_log("QR generation failed for appointment {$appointment_id}: " . $e->getMessage());
        // Continue without QR code - don't fail the appointment
    }

    // Handle queue creation - ONLY for CHO facilities (facility_id = 1)
    $queue_result = null;
    $is_cho_facility = ($facility_id == 1); // City Health Office Main District
    
    if ($is_cho_facility) {
        try {
            $queueService = new QueueManagementService($conn);
            
            // Determine priority level
            $priority_level = ($patient_info['isSenior'] || $patient_info['isPWD']) ? 'priority' : 'normal';
            
            $queue_result = $queueService->createQueueEntry(
                $appointment_id,
                $patient_id,
                $service_id,
                'consultation',
                $priority_level,
                1  // Default employee_id for system-generated entries
            );
            
            if (!$queue_result['success']) {
                error_log("Queue creation failed for CHO appointment {$appointment_id}: " . ($queue_result['error'] ?? 'Unknown error'));
                // Continue without queue - appointment is still valid
            }
        } catch (Exception $e) {
            error_log("Queue service error for CHO appointment {$appointment_id}: " . $e->getMessage());
            // Continue without queue - appointment is still valid
        }
    }

    $conn->commit();

    // Prepare success response
    $success_message = "Appointment scheduled successfully!";
    $facility_message = "";
    
    if ($is_cho_facility) {
        if ($queue_result && $queue_result['success']) {
            $facility_message = "Queue number assigned: #{$queue_result['queue_number']}. Please arrive 15 minutes early.";
        } else {
            $facility_message = "Please arrive 15 minutes early for check-in at the City Health Office.";
        }
    } else {
        $facility_message = "Please arrive 15 minutes early for your appointment at {$facility_name}.";
    }

    // Send appointment confirmation email
    $email_result = null;
    try {
        if (!empty($patient_info['email'])) {
            $email_result = sendAppointmentConfirmationEmail(
                $patient_info,
                $appointment_num,
                $facility_name,
                $service,
                $appointment_date,
                $appointment_time,
                $referral_id,
                $queue_result,
                $qr_result
            );
            
            if (!$email_result['success']) {
                error_log("Email sending failed for appointment {$appointment_id}: " . $email_result['message']);
            } else {
                error_log("Email sent successfully for appointment {$appointment_id}");
            }
        } else {
            $email_result = ['success' => false, 'message' => 'No email address provided'];
            error_log("No email address provided for appointment {$appointment_id}");
        }
    } catch (Exception $e) {
        $email_result = ['success' => false, 'message' => $e->getMessage()];
        error_log("Failed to send appointment confirmation email: " . $e->getMessage());
        // Continue - appointment creation succeeded
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $success_message,
        'facility_message' => $facility_message,
        'appointment_id' => $appointment_id,
        'appointment_num' => $appointment_num,
        'facility_name' => $facility_name,
        'facility_type' => $facility_type,
        'is_cho_facility' => $is_cho_facility,
        'has_queue' => $queue_result && $queue_result['success'],
        'queue_number' => $queue_result['queue_number'] ?? null,
        'qr_generated' => $qr_result && $qr_result['success'],
        'email_sent' => $email_result && $email_result['success'],
        'email_message' => $email_result ? $email_result['message'] : 'No email attempted'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Appointment creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();

// Helper function to extract appointment ID from appointment number (APT-YYYYMMDD-XXXXX format)
function extractAppointmentIdFromNum($appointment_num) {
    // Extract the last part after the last dash (the padded ID)
    $parts = explode('-', $appointment_num);
    if (count($parts) >= 3) {
        $padded_id = end($parts);
        $appointment_id = intval($padded_id); // Convert back to integer
        error_log("Extracting appointment ID from '$appointment_num': parts=" . json_encode($parts) . ", padded_id='$padded_id', appointment_id=$appointment_id");
        return $appointment_id;
    }
    error_log("Failed to extract appointment ID from '$appointment_num': parts=" . json_encode($parts));
    return null;
}

// Helper function to get QR BLOB data from database
function getQRBlobFromDatabase($appointment_id) {
    global $conn;
    try {
        error_log("Looking for QR BLOB data for appointment_id: $appointment_id");
        
        $stmt = $conn->prepare("SELECT qr_code_path, LENGTH(qr_code_path) as blob_size FROM appointments WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            error_log("Found appointment record - BLOB size: " . ($row['blob_size'] ?? 0));
            if ($row['qr_code_path'] && $row['blob_size'] > 0) {
                error_log("Returning QR BLOB data of size: " . $row['blob_size']);
                return $row['qr_code_path']; // This is now BLOB data
            } else {
                error_log("No QR BLOB data found (NULL or empty)");
            }
        } else {
            error_log("No appointment found with appointment_id: $appointment_id");
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Failed to get QR BLOB from database: " . $e->getMessage());
        return null;
    }
}

// Function to send appointment confirmation email with QR code
function sendAppointmentConfirmationEmail($patient_info, $appointment_num, $facility_name, $service, $appointment_date, $appointment_time, $referral_id = null, $queue_result = null, $qr_result = null) {
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

        // Prepare queue information for email
        $queue_info_html = '';
        $queue_info_text = '';
        
        if ($queue_result && $queue_result['success'] && isset($queue_result['queue_number'])) {
            $priority_display = ($queue_result['priority_level'] === 'priority') ? 'Priority' : 'Regular';
            $queue_info_html = '
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">Queue Number:</td>
                            <td style="padding: 12px 0; color: #333; font-weight: bold; font-size: 16px; color: #0077b6;">#' . htmlspecialchars($queue_result['queue_number'], ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">Queue Type:</td>
                            <td style="padding: 12px 0; color: #333;">' . htmlspecialchars(ucfirst($queue_result['queue_type']), ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>
                        <tr' . (!empty($referral_num) ? ' style="border-bottom: 1px solid #e9ecef;"' : '') . '>
                            <td style="padding: 12px 0; font-weight: 600; color: #0077b6;">Priority Level:</td>
                            <td style="padding: 12px 0; color: #333;">' . htmlspecialchars($priority_display, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>';
            
            $queue_info_text = "\nQueue Number: #{$queue_result['queue_number']}\nQueue Type: " . ucfirst($queue_result['queue_type']) . "\nPriority Level: {$priority_display}";
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

        // QR Code handling - Get QR data from database BLOB
        $qr_embedded = false;
        $qr_base64_inline = '';
        $qr_image_tag = '';
        
        error_log("Email QR Check - QR Result exists: " . ($qr_result ? 'YES' : 'NO'));
        if ($qr_result && $qr_result['success']) {
            error_log("Email QR Check - QR Success: YES, fetching from database...");
            
            try {
                // Get QR BLOB data from database using the appointment number
                $appointment_id_for_qr = extractAppointmentIdFromNum($appointment_num);
                $qr_blob_data = getQRBlobFromDatabase($appointment_id_for_qr);
                
                if ($qr_blob_data) {
                    // Method 1: Create inline base64 image (best compatibility)
                    $qr_base64_inline = base64_encode($qr_blob_data);
                    $qr_image_tag = '<img src="data:image/png;base64,' . $qr_base64_inline . '" alt="Appointment QR Code" style="width:200px;height:200px;border:2px solid #0077b6;border-radius:10px;display:block;margin:0 auto;" />';
                    
                    // Method 2: Add embedded image (CID) as fallback
                    $qr_filename = 'QR-' . $appointment_num . '.png';
                    $mail->addStringEmbeddedImage($qr_blob_data, 'appointment_qr_code', $qr_filename, 'base64', 'image/png');
                    
                    // Method 3: Also attach the QR image for saving/redundancy
                    $mail->addStringAttachment($qr_blob_data, $qr_filename, 'base64', 'image/png');
                    
                    $qr_embedded = true;
                    
                    error_log("QR Code prepared for email from database - Base64 size: " . strlen($qr_base64_inline) . " bytes");
                } else {
                    error_log("No QR BLOB data found in database for appointment: " . $appointment_num);
                }
                
            } catch (Exception $e) {
                error_log("Failed to prepare QR code for email from database: " . $e->getMessage());
            }
        }
        
        if (!$qr_embedded) {
            // Fallback to no QR code display
            $qr_image_tag = '<p style="color: #dc3545; font-size: 14px; text-align: center; padding: 15px; background: #f8d7da; border-radius: 5px;">QR Code temporarily unavailable. Please use your Appointment ID for check-in.</p>';
        }

        $mail->isHTML(true);
        $mail->Subject = 'Your Appointment Confirmation – City Health Office of Koronadal [' . $appointment_num . ']';
        
        // Build QR code section for email
        $qr_section = '';
        if ($qr_embedded && !empty($qr_image_tag)) {
            $qr_section = '
                <div style="background: #f8f9fa; border-radius: 15px; padding: 25px; margin: 25px 0; text-align: center; border: 2px solid #0077b6;">
                    <h3 style="margin: 0 0 15px 0; color: #0077b6; font-size: 20px;">📱 Your Appointment QR Code</h3>
                    <p style="margin: 0 0 15px 0; color: #6c757d; font-size: 14px;">
                        Present this QR code at the check-in counter for fast service
                    </p>
                    <div style="margin: 15px 0;">
                        ' . $qr_image_tag . '
                    </div>
                    <p style="margin: 15px 0 5px 0; color: #6c757d; font-size: 12px;">
                        <strong>Alternative check-in:</strong> Present your Appointment ID: <strong>' . htmlspecialchars($appointment_num, ENT_QUOTES, 'UTF-8') . '</strong>
                    </p>
                    <p style="margin: 5px 0 0 0; color: #28a745; font-size: 11px;">
                        💡 <em>QR code also attached as a separate file for saving to your device</em>
                    </p>
                </div>';
        } else {
            $qr_section = '
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 10px; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; color: #856404; font-size: 14px;">
                        <strong>📋 Manual Check-in:</strong> Please present your Appointment ID <strong>' . htmlspecialchars($appointment_num, ENT_QUOTES, 'UTF-8') . '</strong> at the facility.
                    </p>
                </div>';
        }

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
                </div>' . 
                ($queue_result && $queue_result['success'] ? '
                
                <div style="background: #28a745; color: white; padding: 10px 15px; border-radius: 25px; display: inline-block; font-weight: bold; font-size: 16px; margin-bottom: 20px; margin-left: 10px;">
                    🎫 Queue Number: #' . htmlspecialchars($queue_result['queue_number'], ENT_QUOTES, 'UTF-8') . '
                </div>' : '') . '
                
                ' . $qr_section . '
                
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
                        $queue_info_html .
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
                        ($queue_result && $queue_result['success'] ? '<li><strong>Queue Number: #' . htmlspecialchars($queue_result['queue_number'], ENT_QUOTES, 'UTF-8') . '</strong> (show this when you arrive)</li>' : '') .
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
$queue_info_text .
(!empty($referral_num) ? "\nReferral Number: #{$referral_num}" : "") . "

" . ($qr_embedded ? "QR CODE INCLUDED:
Your appointment QR code is displayed inline in this email and attached as a file for saving to your device.
Present the QR code at check-in for fastest service.

" : "") . "IMPORTANT REMINDERS:
• Please arrive 15 minutes before your appointment time
• Bring a valid government-issued ID
• Bring this appointment confirmation" .
($qr_embedded ? "\n• Present your QR code (inline or attached file) for fast check-in" : "") .
($queue_result && $queue_result['success'] ? "\n• Present your queue number: #{$queue_result['queue_number']}" : "") . "
• Present your referral document (if applicable)
• Bring your PhilHealth card (if applicable)

CHECK-IN OPTIONS:
" . ($qr_embedded ? "• Scan your inline QR code at the check-in counter (recommended)
• Save and use the attached QR code file
• Or present your Appointment ID: {$appointment_num}" : "• Present your Appointment ID: {$appointment_num}") . "

CONTACT INFORMATION:
Phone: (083) 228-8042
Email: info@chokoronadal.gov.ph

For cancellations or rescheduling, please contact us at least 24 hours in advance.

Thank you for choosing CHO Koronadal for your healthcare needs.

City Health Office of Koronadal
This is an automated message. Please do not reply to this email.
© " . date('Y') . " CHO Koronadal. All rights reserved.";

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