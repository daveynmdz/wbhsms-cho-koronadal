<?php
// Session Debug Test - Check if admin session is working for billing access
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';

echo "<h2>Session Debug - Admin Billing Access</h2>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<h3>Session Status:</h3>";
echo "<p><strong>Session Active:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Employee Logged In:</strong> " . (is_employee_logged_in() ? 'Yes' : 'No') . "</p>";

echo "<h3>Session Data:</h3>";
echo "<pre>";
foreach ($_SESSION as $key => $value) {
    echo htmlspecialchars($key) . " => " . htmlspecialchars(print_r($value, true)) . "\n";
}
echo "</pre>";

echo "<h3>Billing Access Check:</h3>";
$employee_role = get_employee_session('role', 'none');
echo "<p><strong>Employee Role:</strong> " . htmlspecialchars($employee_role) . "</p>";
echo "<p><strong>Has Admin Access:</strong> " . (in_array($employee_role, ['admin']) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Has Billing Access:</strong> " . (in_array($employee_role, ['cashier', 'admin']) ? 'Yes' : 'No') . "</p>";

echo "<h3>Employee Info:</h3>";
echo "<p><strong>Employee ID:</strong> " . htmlspecialchars(get_employee_session('employee_id', 'none')) . "</p>";
echo "<p><strong>Employee Name:</strong> " . htmlspecialchars(get_employee_session('employee_name', 'Unknown')) . "</p>";
echo "<p><strong>Employee Number:</strong> " . htmlspecialchars(get_employee_session('employee_number', 'none')) . "</p>";

echo "<h3>Quick Links:</h3>";
echo "<p><a href='../dashboard.php'>← Back to Admin Dashboard</a></p>";
echo "<p><a href='billing_overview.php'>→ Try Billing Overview</a></p>";
?>