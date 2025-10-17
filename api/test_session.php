<?php
// Session Test API - temporary debugging file
$root_path = dirname(__DIR__);
require_once $root_path . '/config/session/employee_session.php';

header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_active' => session_status() === PHP_SESSION_ACTIVE,
    'employee_logged_in' => is_employee_logged_in(),
    'employee_id' => get_employee_session('employee_id'),
    'employee_role' => get_employee_session('role'),
    'employee_name' => get_employee_session('first_name') . ' ' . get_employee_session('last_name'),
    'all_session_data' => $_SESSION
]);
?>