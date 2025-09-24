<?php
// resend_registration_otp.php
session_start();
header('Content-Type: application/json');

// Set error handling
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

// Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/env.php'; // Loads env.php and .env/.env.local

// Load PHPMailer classes
if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
    $vendorAutoload = $root_path . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        require_once $root_path . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once $root_path . '/vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once $root_path . '/vendor/phpmailer/phpmailer/src/Exception.php';
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Quick check: do we even have a registration pending?
if (empty($_SESSION['registration']) || empty($_SESSION['registration']['email'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No pending registration found. Please restart registration.'
    ]);
    exit;
}

// Check if there's an existing OTP that hasn't expired yet (rate limiting)
if (isset($_SESSION['otp_expiry']) && time() < $_SESSION['otp_expiry']) {
    $remainingTime = $_SESSION['otp_expiry'] - time();
    if ($remainingTime > 240) { // If more than 4 minutes remaining, don't allow resend
        echo json_encode([
            'success' => false,
            'message' => 'Please wait before requesting a new OTP. Current OTP is still valid.'
        ]);
        exit;
    }
}

// Generate a new OTP
function generateOTP($length = 6) {
    return str_pad((string)random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}

try {
    $newOtp = generateOTP();
    $_SESSION['otp'] = $newOtp;
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes expiry

    $email = $_SESSION['registration']['email'];
    $first_name = $_SESSION['registration']['first_name'] ?? '';
    $last_name = $_SESSION['registration']['last_name'] ?? '';

    // For development: bypass email if SMTP_PASS is empty or 'disabled'
    $bypassEmail = empty($_ENV['SMTP_PASS']) || $_ENV['SMTP_PASS'] === 'disabled';
    
    if ($bypassEmail) {
        // Development mode: show OTP directly
        error_log("DEVELOPMENT MODE: Resent OTP for {$email} is: {$newOtp}");
        $_SESSION['dev_message'] = "DEVELOPMENT MODE: Your new OTP is {$newOtp}";
        echo json_encode([
            'success' => true,
            'message' => "DEVELOPMENT MODE: Your new OTP is {$newOtp}"
        ]);
        exit;
    }

    // Send OTP via email
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    
    // Load SMTP config from environment variables
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'] ?? '';
    $mail->Password = $_ENV['SMTP_PASS'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
    
    $fromEmail = $_ENV['SMTP_FROM'] ?? 'cityhealthofficeofkoronadal@gmail.com';
    $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'City Health Office of Koronadal';

    // Add debugging for development
    if ($debug) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'error_log';
    }

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($email, trim($first_name . ' ' . $last_name));

    $mail->isHTML(true);
    $mail->Subject = 'Your New OTP for CHO Koronadal Registration';
    $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #007bff;">CHO Koronadal - New OTP</h2>
            <p>Hello ' . htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') . ',</p>
            <p>You requested a new One-Time Password (OTP) for your registration. Your new OTP is:</p>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;">
                <h1 style="color: #007bff; letter-spacing: 3px; margin: 0;">' . htmlspecialchars($newOtp, ENT_QUOTES, 'UTF-8') . '</h1>
            </div>
            <p><strong>This code will expire in 5 minutes.</strong></p>
            <p>If you did not request this OTP, please ignore this email.</p>
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
            <p style="color: #666; font-size: 12px;">
                This is an automated message from City Health Office of Koronadal. 
                Please do not reply to this email.
            </p>
        </div>
    ';
    $mail->AltBody = "Your new OTP is: {$newOtp} (expires in 5 minutes). If you did not request this, please ignore this message.";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'A new OTP has been sent to your email address.'
    ]);

} catch (Exception $e) {
    // Log detailed error information
    $errorDetails = 'PHPMailer resend error: ' . $mail->ErrorInfo . ' Exception: ' . $e->getMessage();
    error_log($errorDetails);
    
    // Also log to mail_error.log
    $logEntry = date('Y-m-d H:i:s') . ' | RESEND | ' . $errorDetails . PHP_EOL;
    file_put_contents(__DIR__ . '/tools/mail_error.log', $logEntry, FILE_APPEND | LOCK_EX);
    
    // More specific error message
    if (strpos($e->getMessage(), 'authenticate') !== false) {
        $errorMsg = 'Email service is currently unavailable. Please contact the administrator.';
    } else {
        $errorMsg = 'Failed to resend OTP. Please check your email address and try again.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $errorMsg
    ]);
} catch (Throwable $e) {
    // Catch any other errors
    error_log('Unexpected error in resend OTP: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.'
    ]);
}
?>