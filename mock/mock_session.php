<?php
/**
 * Mock Session Management for Frontend Development
 * CHO Koronadal Healthcare Management System
 */

// Only set up mock session data if functions don't exist (avoid conflicts with real session)
if (!function_exists('is_employee_logged_in')) {
    // Mock session data
    $_SESSION['employee_id'] = 1;
    $_SESSION['employee_name'] = 'Dr. John Smith';
    $_SESSION['employee_role'] = 'doctor';
    $_SESSION['employee_username'] = 'drsmith';
    $_SESSION['department'] = 'Medical';

    // Mock session functions
    function is_employee_logged_in() {
        return true; // Always logged in for development
    }

    function get_employee_session($key) {
        $session_data = [
            'employee_id' => 1,
            'employee_name' => 'Dr. John Smith',
            'employee_role' => 'doctor',
            'employee_username' => 'drsmith',
            'department' => 'Medical'
        ];
        
        return isset($session_data[$key]) ? $session_data[$key] : null;
    }

    function clear_employee_session() {
        // Mock function for development
        return true;
    }
} else {
    // Real session functions exist, just ensure we have session data for testing
    if (empty($_SESSION['employee_id'])) {
        $_SESSION['employee_id'] = 1;
        $_SESSION['employee_name'] = 'Dr. John Smith';
        $_SESSION['employee_role'] = 'doctor';
        $_SESSION['employee_username'] = 'drsmith';
        $_SESSION['department'] = 'Medical';
    }
}