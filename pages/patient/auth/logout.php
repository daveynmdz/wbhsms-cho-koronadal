<?php
// logout.php
declare(strict_types=1);

// Include patient session configuration
require_once __DIR__ . '/../../config/session/patient_session.php';

// Enforce POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

// Use the patient session clear function
clear_patient_session();

// Redirect to login with a flag
header('Location: ../auth/patient_login.php?logged_out=1');
exit;
