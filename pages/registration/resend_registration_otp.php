<?php
// resend_registration_otp.php
session_start();
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../../vendor/autoload.php';

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

$email = $_SESSION['registration']['email'];
$first_name = $_SESSION['registration']['first_name'] ?? '';
$last_name = $_SESSION['registration']['last_name'] ?? '';

// Send OTP via email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'cityhealthofficeofkoronadal@gmail.com'; // SMTP username
    $mail->Password   = 'iclhoflunfkzmlie'; // SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

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
    file_put_contents('mail_error.log', $mail->ErrorInfo . PHP_EOL, FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to resend OTP. Please try again.'
    ]);
}
