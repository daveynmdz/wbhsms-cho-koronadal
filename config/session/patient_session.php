<?php
/**
 * Patient Session Configuration
 * 
 * This file configures the session for patient users.
 * It ensures patient sessions are separate from employee sessions.
 */

// Check if a session is already active - we don't want to start another one
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '1' : '0');
    
    // Set unique session name for patients
    session_name('PATIENT_SESSID');
    
    // Set cookie path to restrict to patient areas
    // Exclude the management path to prevent conflicts with employee sessions
    session_set_cookie_params([
        'lifetime' => 0, // 0 = until browser is closed
        'path' => '/', // Root path
        'domain' => '', // Current domain
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Start the session
    session_start();
}

/**
 * Check if a patient is logged in
 *
 * @return bool True if patient is logged in, false otherwise
 */
function is_patient_logged_in() {
    return !empty($_SESSION['patient_id']);
}

/**
 * Get patient session value
 *
 * @param string $key The session key to retrieve
 * @param mixed $default Default value if key doesn't exist
 * @return mixed The session value or default
 */
function get_patient_session($key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

/**
 * Set patient session value
 *
 * @param string $key The session key to set
 * @param mixed $value The value to store
 */
function set_patient_session($key, $value) {
    $_SESSION[$key] = $value;
}

/**
 * Clear the patient session
 */
function clear_patient_session() {
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}