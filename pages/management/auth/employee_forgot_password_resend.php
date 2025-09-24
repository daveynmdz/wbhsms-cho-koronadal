<?php
// Employee OTP resend handler
header('Content-Type: application/json');

$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
error_reporting(E_ALL);

// Hide errors in production
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Include employee session configuration
require_once __DIR__ . '/../../../config/session/employee_session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Only accept AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Check if we have reset session data
    if (empty($_SESSION['reset_otp']) || empty($_SESSION['reset_user_id']) || empty($_SESSION['reset_email'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please start the password reset process again.']);
        exit;
    }

    // Rate limiting for resend (max 3 resends per 15 minutes)
    $resend_key = 'employee_otp_resend_' . $_SESSION['reset_user_id'];
    if (!isset($_SESSION[$resend_key])) $_SESSION[$resend_key] = 0;
    if (!isset($_SESSION['last_resend_time'])) $_SESSION['last_resend_time'] = 0;

    // Check if too many resend attempts
    if ($_SESSION[$resend_key] >= 3 && (time() - $_SESSION['last_resend_time']) < 900) {
        $remaining = 900 - (time() - $_SESSION['last_resend_time']);
        echo json_encode([
            'success' => false, 
            'message' => 'Too many resend attempts. Please wait ' . ceil($remaining / 60) . ' minutes before trying again.'
        ]);
        exit;
    }

    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $posted_csrf = $input['csrf_token'] ?? '';

    // Validate CSRF token
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $posted_csrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid session token']);
        exit;
    }

    // Generate new OTP
    $new_otp = sprintf('%06d', mt_rand(100000, 999999));
    
    // Update session with new OTP
    $_SESSION['reset_otp'] = $new_otp;
    $_SESSION['reset_otp_time'] = time();

    // Send email
    $mail = new PHPMailer(true);
    
    try {
        // Email configuration (matching patient system)
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?? 'cityhealthofficeofkoronadal@gmail.com';
        $mail->Password   = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?? 587);

        $mail->setFrom('cityhealthofficeofkoronadal@gmail.com', 'City Health Office of Koronadal');
        $mail->addAddress($_SESSION['reset_email'], $_SESSION['reset_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - CHO Employee Portal (Resent)';
        $mail->Body = "
            <h2>Password Reset Request (Resent)</h2>
            <p>Dear " . htmlspecialchars($_SESSION['reset_name']) . ",</p>
            <p>You requested a new OTP for password reset. Use the following code to proceed:</p>
            <h3 style='color: #007bff; font-size: 24px; letter-spacing: 3px;'>{$new_otp}</h3>
            <p><strong>This OTP is valid for 15 minutes only.</strong></p>
            <p>If you did not request this, please ignore this email and contact IT support.</p>
            <hr>
            <p><small>CHO Koronadal Employee Portal</small></p>
        ";

        $result = $mail->send();
        
        if ($result) {
            // Update rate limiting
            $_SESSION[$resend_key]++;
            $_SESSION['last_resend_time'] = time();
            
            echo json_encode([
                'success' => true, 
                'message' => 'New OTP sent to ' . $_SESSION['reset_email'] . '. Please check your inbox.'
            ]);
        } else {
            throw new Exception('Mail send failed');
        }
        
    } catch (Exception $e) {
        error_log('[employee_forgot_password_resend] Email send failed: ' . $e->getMessage());
        
        // Still return success to avoid revealing email issues
        echo json_encode([
            'success' => true, 
            'message' => 'New OTP sent to ' . $_SESSION['reset_email'] . '. If you don\'t receive it, please contact IT support.'
        ]);
    }

} catch (Exception $e) {
    error_log('[employee_forgot_password_resend] Unexpected error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable']);
}
?>