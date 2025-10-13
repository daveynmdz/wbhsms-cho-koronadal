<?php
// Records Officer Referrals - Redirect to Central Referrals System
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/employee_login.php');
    exit();
}

// Check if role is authorized for referrals
$authorized_roles = ['records_officer'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    header('Location: ../dashboard.php');
    exit();
}

// Redirect to central referrals management system
header('Location: ../../referrals/referrals_management.php');
exit();
?>