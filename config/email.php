<?php
/**
 * Email Configuration for WBHSMS CHO Koronadal
 * Handles SMTP settings and email templates
 */

// Load environment variables
require_once __DIR__ . '/env.php';

// Include PHPMailer classes at the top level
require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Email configuration class
class EmailConfig {
    // SMTP Settings
    public static function getSMTPConfig() {
        return [
            'host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
            'port' => $_ENV['SMTP_PORT'] ?? 587,
            'username' => $_ENV['SMTP_USERNAME'] ?? 'chokoronadal.healthsystem@gmail.com',
            'password' => $_ENV['SMTP_PASSWORD'] ?? '', // Set this in your .env file
            'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls',
            'from_email' => $_ENV['FROM_EMAIL'] ?? 'noreply@chokoronadal.gov.ph',
            'from_name' => $_ENV['FROM_NAME'] ?? 'CHO Koronadal Health System',
        ];
    }

    // System settings
    public static function getSystemInfo() {
        return [
            'system_name' => 'CHO Koronadal Health Management System',
            'system_url' => $_ENV['SYSTEM_URL'] ?? 'http://localhost/wbhsms-cho-koronadal',
            'contact_phone' => $_ENV['CONTACT_PHONE'] ?? '(083) 228-8042',
            'contact_email' => $_ENV['CONTACT_EMAIL'] ?? 'info@chokoronadal.gov.ph',
            'facility_address' => $_ENV['FACILITY_ADDRESS'] ?? 'Koronadal City, South Cotabato',
        ];
    }

    // Email template for appointment confirmation
    public static function getAppointmentConfirmationTemplate($data) {
        $system_info = self::getSystemInfo();
        
        return [
            'subject' => 'Appointment Confirmation - CHO Koronadal [' . $data['appointment_num'] . ']',
            'html_body' => self::buildHTMLTemplate($data, $system_info),
            'text_body' => self::buildTextTemplate($data, $system_info)
        ];
    }

    // Build HTML email template
    private static function buildHTMLTemplate($data, $system_info) {
        return "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Appointment Confirmation</title>
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            background-color: #f4f4f4; 
        }
        .email-container { 
            max-width: 600px; 
            margin: 20px auto; 
            background: white; 
            border-radius: 10px; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        }
        .header { 
            background: linear-gradient(135deg, #0077b6, #023e8a); 
            color: white; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .header h1 { 
            margin: 0 0 10px 0; 
            font-size: 28px; 
            font-weight: 700; 
        }
        .header p { 
            margin: 0; 
            font-size: 16px; 
            opacity: 0.9; 
        }
        .content { 
            padding: 30px 20px; 
        }
        .appointment-card { 
            background: #f8f9fa; 
            border-radius: 10px; 
            padding: 20px; 
            margin: 20px 0; 
            border-left: 5px solid #0077b6; 
        }
        .appointment-id { 
            background: #0077b6; 
            color: white; 
            padding: 10px 15px; 
            border-radius: 25px; 
            display: inline-block; 
            font-weight: bold; 
            font-size: 16px; 
            margin-bottom: 20px; 
        }
        .detail-grid { 
            display: table; 
            width: 100%; 
            border-collapse: collapse; 
        }
        .detail-row { 
            display: table-row; 
        }
        .detail-label, .detail-value { 
            display: table-cell; 
            padding: 12px 0; 
            border-bottom: 1px solid #e9ecef; 
            vertical-align: top; 
        }
        .detail-label { 
            font-weight: 600; 
            color: #0077b6; 
            width: 35%; 
            padding-right: 15px; 
        }
        .detail-value { 
            color: #333; 
            font-weight: 500; 
        }
        .highlight { 
            background: #fff3cd; 
            padding: 15px; 
            border-radius: 8px; 
            border-left: 4px solid #ffc107; 
            margin: 20px 0; 
        }
        .highlight h4 { 
            margin: 0 0 10px 0; 
            color: #856404; 
            font-size: 18px; 
        }
        .reminder-list { 
            margin: 0; 
            padding-left: 20px; 
        }
        .reminder-list li { 
            margin-bottom: 8px; 
            color: #6c757d; 
        }
        .important-note { 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            border-radius: 8px; 
            padding: 15px; 
            margin: 20px 0; 
        }
        .important-note h4 { 
            margin: 0 0 10px 0; 
            color: #155724; 
        }
        .footer { 
            background: #f8f9fa; 
            padding: 20px; 
            text-align: center; 
            border-top: 1px solid #e9ecef; 
        }
        .footer p { 
            margin: 5px 0; 
            color: #6c757d; 
            font-size: 14px; 
        }
        .contact-info { 
            background: #e3f2fd; 
            padding: 15px; 
            border-radius: 8px; 
            margin: 20px 0; 
        }
        .contact-info h4 { 
            margin: 0 0 10px 0; 
            color: #1976d2; 
        }
        .contact-item { 
            margin: 5px 0; 
            color: #333; 
        }
        .contact-item strong { 
            color: #1976d2; 
        }
        @media only screen and (max-width: 600px) {
            .email-container { 
                margin: 10px; 
                border-radius: 5px; 
            }
            .header { 
                padding: 20px 15px; 
            }
            .content { 
                padding: 20px 15px; 
            }
            .detail-label, .detail-value { 
                display: block; 
                width: 100%; 
                padding: 8px 0; 
            }
            .detail-label { 
                font-weight: bold; 
                color: #0077b6; 
                border-bottom: none; 
            }
            .detail-value { 
                border-bottom: 1px solid #e9ecef; 
                margin-bottom: 10px; 
            }
        }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>
            <h1>🏥 Appointment Confirmed</h1>
            <p>{$system_info['system_name']}</p>
        </div>
        
        <div class='content'>
            <p style='font-size: 16px; margin-bottom: 20px;'>
                Dear <strong>{$data['patient_name']}</strong>,
            </p>
            
            <p style='margin-bottom: 25px;'>
                Your appointment has been successfully scheduled. Please save this confirmation email for your records.
            </p>
            
            <div class='appointment-id'>
                📋 Appointment ID: {$data['appointment_num']}
            </div>
            
            <div class='appointment-card'>
                <h3 style='margin: 0 0 15px 0; color: #0077b6; font-size: 20px;'>Appointment Details</h3>
                <div class='detail-grid'>
                    <div class='detail-row'>
                        <div class='detail-label'>Patient Name:</div>
                        <div class='detail-value'>{$data['patient_name']}</div>
                    </div>
                    <div class='detail-row'>
                        <div class='detail-label'>Healthcare Facility:</div>
                        <div class='detail-value'>{$data['facility_name']}</div>
                    </div>
                    <div class='detail-row'>
                        <div class='detail-label'>Service Type:</div>
                        <div class='detail-value'>{$data['service']}</div>
                    </div>
                    <div class='detail-row'>
                        <div class='detail-label'>Appointment Date:</div>
                        <div class='detail-value'>{$data['formatted_date']}</div>
                    </div>
                    <div class='detail-row'>
                        <div class='detail-label'>Appointment Time:</div>
                        <div class='detail-value'>{$data['formatted_time']}</div>
                    </div>
                    " . (!empty($data['referral_num']) ? "
                    <div class='detail-row'>
                        <div class='detail-label'>Referral Number:</div>
                        <div class='detail-value'>#{$data['referral_num']}</div>
                    </div>" : "") . "
                </div>
            </div>
            
            <div class='highlight'>
                <h4>⏰ Please Arrive Early</h4>
                <p style='margin: 0; color: #856404;'>
                    <strong>Recommended arrival time: 15 minutes before your appointment.</strong><br>
                    This allows time for check-in procedures and ensures your appointment starts on time.
                </p>
            </div>
            
            <div class='important-note'>
                <h4>📋 Required Documents & Reminders</h4>
                <ul class='reminder-list'>
                    <li><strong>Valid Government-issued ID</strong> (Driver's License, SSS ID, PhilHealth ID, etc.)</li>
                    <li><strong>This appointment confirmation</strong> (printed or on your mobile device)</li>
                    " . (!empty($data['referral_num']) ? "<li><strong>Original referral document</strong> from your referring physician</li>" : "") . "
                    <li><strong>PhilHealth card</strong> (if applicable)</li>
                    <li>Any <strong>previous medical records</strong> related to your condition</li>
                    <li><strong>List of current medications</strong> you are taking</li>
                </ul>
            </div>
            
            <div class='contact-info'>
                <h4>📞 Need Help or Changes?</h4>
                <div class='contact-item'><strong>Phone:</strong> {$system_info['contact_phone']}</div>
                <div class='contact-item'><strong>Email:</strong> {$system_info['contact_email']}</div>
                <div class='contact-item'><strong>Address:</strong> {$system_info['facility_address']}</div>
                <p style='margin: 10px 0 0 0; font-size: 14px; color: #6c757d;'>
                    For cancellations or rescheduling, please contact us at least 24 hours in advance.
                </p>
            </div>
            
            <p style='margin-top: 25px; font-size: 16px;'>
                Thank you for choosing CHO Koronadal for your healthcare needs. We look forward to serving you!
            </p>
        </div>
        
        <div class='footer'>
            <p><strong>{$system_info['system_name']}</strong></p>
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>© " . date('Y') . " City Health Office - Koronadal. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";
    }

    // Build plain text email template
    private static function buildTextTemplate($data, $system_info) {
        return "APPOINTMENT CONFIRMATION - CHO KORONADAL

Dear {$data['patient_name']},

Your appointment has been successfully scheduled with {$system_info['system_name']}.

APPOINTMENT DETAILS:
===================
Appointment ID: {$data['appointment_num']}
Patient Name: {$data['patient_name']}
Healthcare Facility: {$data['facility_name']}
Service Type: {$data['service']}
Appointment Date: {$data['formatted_date']}
Appointment Time: {$data['formatted_time']}" .
(!empty($data['referral_num']) ? "\nReferral Number: #{$data['referral_num']}" : "") . "

IMPORTANT REMINDERS:
===================
• Please arrive 15 minutes before your appointment time
• Bring a valid government-issued ID
• Bring this appointment confirmation
• Present your referral document (if applicable)
• Bring your PhilHealth card (if applicable)
• Bring any previous medical records related to your condition
• Bring a list of current medications you are taking

CONTACT INFORMATION:
===================
Phone: {$system_info['contact_phone']}
Email: {$system_info['contact_email']}
Address: {$system_info['facility_address']}

For cancellations or rescheduling, please contact us at least 24 hours in advance.

Thank you for choosing CHO Koronadal for your healthcare needs.

---
{$system_info['system_name']}
This is an automated message. Please do not reply to this email.
© " . date('Y') . " City Health Office - Koronadal. All rights reserved.";
    }
}

// Email sending function
function sendEmail($to_email, $to_name, $subject, $html_body, $text_body = '') {
    // Validate input parameters
    if (empty($to_email) || empty($subject) || empty($html_body)) {
        error_log("sendEmail: Missing required parameters");
        return ['success' => false, 'message' => 'Missing required email parameters'];
    }

    // Validate email format
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("sendEmail: Invalid email format: " . $to_email);
        return ['success' => false, 'message' => 'Invalid email address format'];
    }

    try {
        $mail = new PHPMailer(true);
        
        // Check if EmailConfig class is available
        if (!class_exists('EmailConfig')) {
            error_log("sendEmail: EmailConfig class not found");
            return ['success' => false, 'message' => 'Email configuration class not available'];
        }
        
        $config = EmailConfig::getSMTPConfig();

        // Check if SMTP is configured
        if (empty($config['username']) || empty($config['password'])) {
            error_log("sendEmail: SMTP credentials not configured");
            return ['success' => false, 'message' => 'Email service not configured'];
        }

        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        
        if (strtolower($config['encryption']) === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $config['port'] ?: 465;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['port'] ?: 587;
        }

        // Enable verbose debug output (only in development)
        if (isset($_ENV['DEBUG_EMAIL']) && $_ENV['DEBUG_EMAIL'] === 'true') {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo($config['from_email'], $config['from_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        
        if (!empty($text_body)) {
            $mail->AltBody = $text_body;
        }

        $mail->send();
        error_log("Email sent successfully to: " . $to_email);
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        error_log("Email sending failed to " . $to_email . ": " . $e->getMessage());
        
        // Return user-friendly error message
        $error_message = "Failed to send email";
        if (strpos($e->getMessage(), 'SMTP connect()') !== false) {
            $error_message = "SMTP connection failed - please check email configuration";
        } elseif (strpos($e->getMessage(), 'SMTP Error: data not accepted') !== false) {
            $error_message = "Email rejected by server - please check recipient address";
        } elseif (strpos($e->getMessage(), 'Invalid address') !== false) {
            $error_message = "Invalid email address provided";
        } elseif (strpos($e->getMessage(), 'Authentication failed') !== false) {
            $error_message = "Email authentication failed - please check credentials";
        }
        
        return ['success' => false, 'message' => $error_message, 'technical_error' => $e->getMessage()];
    }
}

?>