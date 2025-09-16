<?php
// /wbhsms-cho-koronadal/pages/auth/resend_otp.php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
header('Content-Type: application/json; charset=UTF-8');

// ---- runtime logging (to Coolify Logs via PHP error_log) ----
ini_set('log_errors', '1');
ini_set('display_errors', '0');        // keep OFF in prod
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// ---- require files (fixed paths) ----
require_once dirname(__DIR__, 2) . '/config/db.php';         // FIXED: up 2 dirs to /config
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';   // correct

// ---- only allow POST ----
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- session guards ----
if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_user_id'])) {
    error_log('[resend_otp] Missing session values: email=' . (isset($_SESSION['reset_email']) ? 'yes' : 'no') . ' user_id=' . (isset($_SESSION['reset_user_id']) ? 'yes' : 'no'));
    echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
    exit;
}

// ---- simple cooldown ----
$cooldown = 30;
$now      = time();
$last     = (int)($_SESSION['last_resend_time'] ?? 0);
$wait     = $cooldown - ($now - $last);
if ($wait > 0) {
    echo json_encode(['success' => false, 'message' => "Please wait {$wait}s before requesting another OTP."]);
    exit;
}

// ---- OTP ----
function generateOTP(int $len = 6): string {
    $max = (10 ** $len) - 1;                // e.g. 999999
    return str_pad((string)random_int(0, $max), $len, '0', STR_PAD_LEFT);
}
$otp = generateOTP();

$_SESSION['reset_otp']        = $otp;
$_SESSION['reset_otp_time']   = $now;
$_SESSION['last_resend_time'] = $now;

// ---- mail ----
$toEmail = $_SESSION['reset_email'];
$toName  = $_SESSION['reset_name'] ?? 'Patient';

// gather envs (don’t log secrets)
$SMTP_HOST = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?? 'smtp.gmail.com';
$SMTP_PORT = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?? 587);
$SMTP_USER = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?? '';
$SMTP_PASS = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?? '';

error_log(sprintf('[resend_otp] START to=%s host=%s port=%d user_present=%s pass_present=%s',
    $toEmail, $SMTP_HOST, $SMTP_PORT, $SMTP_USER !== '' ? 'yes' : 'no', $SMTP_PASS !== '' ? 'yes' : 'no'));

try {
    if ($SMTP_USER === '' || $SMTP_PASS === '') {
        throw new Exception('SMTP credentials missing (set SMTP_USER/SMTP_PASS env vars).');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug   = 2;                 // SMTP transcript → Logs
    $mail->Debugoutput = 'error_log';
    $mail->Host       = $SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USER;
    $mail->Password   = $SMTP_PASS;         // Use a Gmail App Password if using Gmail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $SMTP_PORT;

    $mail->setFrom($SMTP_USER, 'City Health Office of Koronadal');
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = 'Your New OTP for Password Reset';
    $mail->Body    = "<p>Your new One-Time Password (OTP) is: <strong>{$otp}</strong></p>";

    error_log('[resend_otp] SENDING…');
    $mail->send();
    error_log('[resend_otp] SENT to ' . $toEmail);

    echo json_encode(['success' => true, 'message' => 'A new OTP has been sent to ' . $toEmail . '.']);
} catch (Throwable $e) {
    $err = isset($mail) && !empty($mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
    error_log('[resend_otp] ERROR ' . $err);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not resend OTP. Please try again in a moment.']);
}
