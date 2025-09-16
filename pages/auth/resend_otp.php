<?php
// /wbhsms-cho-koronadal/pages/auth/resend_otp.php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['reset_email'])) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'No reset email in session.']);
  exit;
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php'; // if not needed, you can remove
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

function generateOTP(int $len = 6): string
{
    return str_pad((string)random_int(0, 999999), $len, '0', STR_PAD_LEFT);
}

if (!isset($_SESSION['reset_user_id'], $_SESSION['reset_email'])) {
    // handled error → keep 200 so frontend shows snackbar nicely
    echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
    exit;
}

$cooldown = 30;
$now = time();
$last = (int)($_SESSION['last_resend_time'] ?? 0);
$wait = $cooldown - ($now - $last);
if ($wait > 0) {
    echo json_encode(['success' => false, 'message' => "Please wait {$wait}s before requesting another OTP."]);
    exit;
}

$otp = generateOTP();
$_SESSION['reset_otp']       = $otp;
$_SESSION['reset_otp_time']  = $now;
$_SESSION['last_resend_time'] = $now;

$toEmail = $_SESSION['reset_email'];
$toName  = $_SESSION['reset_name'] ?? 'Patient';

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug   = 2;          // verbose
    $mail->Debugoutput = 'error_log'; // goes to Coolify Logs
    error_log('[mailer] START ' . __FILE__);
    $mail->Host       = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?? 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?? 'cityhealthofficeofkoronadal@gmail.com';
    $mail->Password   = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?? 587);

    $mail->setFrom('cityhealthofficeofkoronadal@gmail.com', 'City Health Office of Koronadal');
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = 'Your New OTP for Password Reset';
    $mail->Body    = "<p>Your new One-Time Password (OTP) is: <strong>{$otp}</strong></p>";

    $mail->send();
    error_log('[mailer] SENT to ' . $toEmail);
    error_log('[resend_otp] Mail resent to ' . $toEmail);
    echo json_encode(['success' => true, 'message' => 'A new OTP has been sent to ' . $toEmail . '.']);
    exit;
} catch (Exception $e) {
    error_log('[mailer] ERROR ' . ($mail->ErrorInfo ?? $e->getMessage()));
    error_log('[resend_otp] Mailer Error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
    echo json_encode(['success' => false, 'message' => 'Could not resend OTP. Please try again in a moment.']);
    exit;
}
