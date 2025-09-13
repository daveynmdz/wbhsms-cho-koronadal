<?php
// resend_registration_otp.php

session_start();
header('Content-Type: application/json');
require_once '../../config/db.php'; // Loads env.php and .env/.env.local

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../vendor/autoload.php';

// Quick check: do we even have a registration pending?
if (empty($_SESSION['registration']) || empty($_SESSION['registration']['email'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No pending registration found.'
    ]);
    exit;
}

// Generate a new OTP
function generateOTP($length = 6) {
    return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}


$newOtp = generateOTP();
$_SESSION['otp'] = $newOtp;
$_SESSION['otp_expiry'] = time() + 300; // 5 minutes expiry

$email = $_SESSION['registration']['email'];
$first_name = $_SESSION['registration']['first_name'] ?? '';
$last_name = $_SESSION['registration']['last_name'] ?? '';

// Send OTP via email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    // Load SMTP config from environment variables
    $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'] ?? '';
    $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

    $mail->setFrom('cityhealthofficeofkoronadal@gmail.com', 'City Health Office of Koronadal');
    $mail->addAddress($email, $first_name . ' ' . $last_name);

    $mail->isHTML(true);
    $mail->Subject = 'Your OTP for Registration (Resent)';
    $mail->Body    = "<p>Your new One-Time Password (OTP) is: <strong>$newOtp</strong></p>";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'A new OTP has been sent to your email.'
    ]);
} catch (Exception $e) {
    $logMsg = date('Y-m-d H:i:s') . " | PHPMailer Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage() . "\n";
    file_put_contents('mail_error.log', $logMsg, FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to resend OTP. Please try again.'
    ]);
}
