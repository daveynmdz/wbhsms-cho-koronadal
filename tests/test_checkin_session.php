<?php
// Simple session test for check-in page
session_start();

// Set up a test session
$_SESSION['employee_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

echo "<h2>Session Test for Check-in Page</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Employee ID: " . ($_SESSION['employee_id'] ?? 'Not set') . "</p>";
echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";

echo "<h3>Testing Check-in Page Access</h3>";
echo "<p><a href='../pages/queueing/checkin.php' target='_blank'>Open Check-in Page</a></p>";

echo "<h3>Current Session Data</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>