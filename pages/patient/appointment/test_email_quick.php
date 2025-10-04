<?php
/**
 * Email Test Script - Quick Email Testing
 * Use this to test email functionality without booking appointments
 */

// Set error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include required files
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';
require_once $root_path . '/config/email.php';

// Check if user is logged in
if (!isset($_SESSION['patient_id'])) {
    echo "<h2>❌ Please Log In</h2>";
    echo "You need to be logged in as a patient to test email functionality.<br>";
    echo "<a href='../auth/login.php'>Go to Login</a>";
    exit;
}

echo "<h2>📧 Email Function Test</h2>";

// Get patient info for testing
require_once $root_path . '/config/db.php';
$patient_id = $_SESSION['patient_id'];

$stmt = $conn->prepare("SELECT first_name, last_name, email FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$stmt->close();

if (!$patient) {
    echo "❌ Patient not found";
    exit;
}

echo "<p><strong>Testing email for:</strong> " . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</p>";
echo "<p><strong>Email address:</strong> " . htmlspecialchars($patient['email']) . "</p>";

// Check if email should be sent
if (isset($_GET['send']) && $_GET['send'] === '1') {
    echo "<h3>🚀 Sending Test Email...</h3>";
    
    // Create test appointment data
    $test_data = [
        'appointment_num' => 'TEST-' . date('Ymd-His'),
        'patient_name' => trim($patient['first_name'] . ' ' . $patient['last_name']),
        'facility_name' => 'Test Health Center',
        'service' => 'Primary Care',
        'formatted_date' => date('F j, Y (l)', strtotime('+1 day')),
        'formatted_time' => '10:00 AM',
        'referral_num' => 'TEST-REF-001'
    ];
    
    echo "<h4>📋 Test Appointment Details:</h4>";
    echo "<ul>";
    echo "<li><strong>Appointment ID:</strong> " . htmlspecialchars($test_data['appointment_num']) . "</li>";
    echo "<li><strong>Patient:</strong> " . htmlspecialchars($test_data['patient_name']) . "</li>";
    echo "<li><strong>Facility:</strong> " . htmlspecialchars($test_data['facility_name']) . "</li>";
    echo "<li><strong>Service:</strong> " . htmlspecialchars($test_data['service']) . "</li>";
    echo "<li><strong>Date:</strong> " . htmlspecialchars($test_data['formatted_date']) . "</li>";
    echo "<li><strong>Time:</strong> " . htmlspecialchars($test_data['formatted_time']) . "</li>";
    echo "</ul>";
    
    try {
        // Get email template
        $template = EmailConfig::getAppointmentConfirmationTemplate($test_data);
        
        echo "<h4>📝 Email Template Generated</h4>";
        echo "<p><strong>Subject:</strong> " . htmlspecialchars($template['subject']) . "</p>";
        
        // Send the email
        $result = sendEmail(
            $patient['email'],
            $test_data['patient_name'],
            $template['subject'],
            $template['html_body'],
            $template['text_body']
        );
        
        echo "<h4>📤 Email Sending Result:</h4>";
        if ($result['success']) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb; color: #155724;'>";
            echo "<strong>✅ EMAIL SENT SUCCESSFULLY!</strong><br>";
            echo "Message: " . htmlspecialchars($result['message']) . "<br>";
            echo "<br><strong>What to check:</strong><br>";
            echo "• Check your inbox: " . htmlspecialchars($patient['email']) . "<br>";
            echo "• Check spam/junk folder<br>";
            echo "• Email should have subject: " . htmlspecialchars($template['subject']) . "<br>";
            echo "• Email should contain appointment details and professional styling<br>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb; color: #721c24;'>";
            echo "<strong>❌ EMAIL FAILED TO SEND</strong><br>";
            echo "Error: " . htmlspecialchars($result['message']) . "<br>";
            if (isset($result['technical_error'])) {
                echo "Technical details: " . htmlspecialchars($result['technical_error']) . "<br>";
            }
            echo "<br><strong>Common fixes:</strong><br>";
            echo "• Check your .env file has correct SMTP settings<br>";
            echo "• Verify Gmail app password is correct<br>";
            echo "• Ensure 2-factor authentication is enabled<br>";
            echo "• Check if your hosting provider blocks SMTP<br>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb; color: #721c24;'>";
        echo "<strong>❌ EXCEPTION OCCURRED</strong><br>";
        echo "Error: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "</div>";
    }
    
    echo "<br><h4>🔗 Next Steps:</h4>";
    echo "<ul>";
    echo "<li><a href='test_email_quick.php'>Run another test</a></li>";
    echo "<li><a href='book_appointment.php'>Test full appointment booking</a></li>";
    echo "<li><a href='debug_referrals.php'>Check referrals debug</a></li>";
    echo "<li><a href='../dashboard.php'>Go to dashboard</a></li>";
    echo "</ul>";
    
} else {
    // Show form to send test email
    echo "<h3>📧 Email Configuration Status</h3>";
    
    // Check email configuration
    try {
        $config = EmailConfig::getSMTPConfig();
        if (!empty($config['username']) && !empty($config['password'])) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb; color: #155724;'>";
            echo "✅ Email configuration appears to be set up<br>";
            echo "Host: " . htmlspecialchars($config['host']) . "<br>";
            echo "Username: " . htmlspecialchars($config['username']) . "<br>";
            echo "Port: " . htmlspecialchars($config['port']) . "<br>";
            echo "</div>";
        } else {
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border: 1px solid #ffeaa7; color: #856404;'>";
            echo "⚠️ Email not configured. Please set up your .env file first.<br>";
            echo "<a href='../../../EMAIL_SETUP_GUIDE.md' target='_blank'>View Email Setup Guide</a>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb; color: #721c24;'>";
        echo "❌ Error checking email configuration: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    
    echo "<br><h3>🧪 Send Test Email</h3>";
    echo "<p>This will send a sample appointment confirmation email to test the email functionality.</p>";
    
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 5px; border: 1px solid #bbdefb;'>";
    echo "<strong>📋 Test Email Details:</strong><br>";
    echo "• <strong>To:</strong> " . htmlspecialchars($patient['email']) . "<br>";
    echo "• <strong>Subject:</strong> Appointment Confirmation - CHO Koronadal [TEST-...] <br>";
    echo "• <strong>Content:</strong> Full appointment confirmation with professional styling<br>";
    echo "• <strong>Template:</strong> Same as real appointment emails<br>";
    echo "</div><br>";
    
    echo "<a href='test_email_quick.php?send=1' class='btn btn-primary' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>";
    echo "📧 Send Test Email Now";
    echo "</a>";
    
    echo "<br><br><h4>🔗 Other Tools:</h4>";
    echo "<ul>";
    echo "<li><a href='../../../test_email.php'>Full email diagnostic tool</a></li>";
    echo "<li><a href='book_appointment.php'>Test appointment booking</a></li>";
    echo "<li><a href='debug_referrals.php'>Debug referrals</a></li>";
    echo "</ul>";
}

?>

<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    line-height: 1.6; 
    max-width: 800px;
}
h2, h3, h4 { color: #333; }
.btn { 
    display: inline-block;
    padding: 10px 20px;
    margin: 5px;
    text-decoration: none;
    border-radius: 5px;
    color: white;
    font-weight: bold;
}
.btn-primary { background: #007bff; }
.btn-primary:hover { background: #0056b3; }
a { color: #007bff; }
a:hover { text-decoration: underline; }
ul { padding-left: 20px; }
</style>