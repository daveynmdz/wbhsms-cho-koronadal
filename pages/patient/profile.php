<?php
// Redirect to the correct profile page location
$patient_id = $_GET['patient_id'] ?? '';
$view_mode = $_GET['view_mode'] ?? '';

// Build redirect URL
$redirect_url = 'profile/profile.php';

// Add query parameters if they exist
$params = [];
if (!empty($patient_id)) {
    $params[] = 'patient_id=' . urlencode($patient_id);
}
if (!empty($view_mode)) {
    $params[] = 'view_mode=' . urlencode($view_mode);
}

if (!empty($params)) {
    $redirect_url .= '?' . implode('&', $params);
}

// Redirect
header('Location: ' . $redirect_url);
exit;
?>