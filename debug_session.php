<?php
// Debug session to understand what's happening with get_referral_details.php
session_start();

echo "<h2>Session Debug</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session data:\n";
var_dump($_SESSION);
echo "\n\nSuperglobal _GET:\n";
var_dump($_GET);
echo "\n\nSuperglobal _POST:\n";
var_dump($_POST);
echo "</pre>";

// Test database connection
require_once 'config/db.php';
echo "<h3>Database Connection Test</h3>";
if (isset($conn) && !$conn->connect_error) {
    echo "✅ Database connection successful<br>";
    
    // Test basic referrals query
    $test_query = "SELECT referral_id, referral_num FROM referrals LIMIT 1";
    $result = $conn->query($test_query);
    if ($result) {
        echo "✅ Referrals table accessible<br>";
        if ($row = $result->fetch_assoc()) {
            echo "Sample referral ID: " . $row['referral_id'] . "<br>";
        }
    } else {
        echo "❌ Error querying referrals: " . $conn->error . "<br>";
    }
} else {
    echo "❌ Database connection failed<br>";
}
?>