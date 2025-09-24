<?php
// Use patient session configuration
require_once dirname(__DIR__, 3) . '/config/session/patient_session.php';
require_once dirname(__DIR__, 3) . '/config/db.php';

// Ensure patient is logged in and get patient_id
if (!is_patient_logged_in()) {
    header('Location: ../auth/patient_login.php');
    exit;
}
$patient_id = $_SESSION['patient_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    $maxSize = 10 * 1024 * 1024; // 10 MB

    if ($file['size'] > $maxSize) {
        $error = "File is too large. Max size is 10 MB.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed with error code " . $file['error'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed)) {
            $error = "Invalid file type.";
        } else {
            $imgData = file_get_contents($file['tmp_name']);
            // Check if row exists
            $stmt = $pdo->prepare("SELECT id FROM personal_information WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            if ($stmt->fetch()) {
                // Update
                $stmt = $pdo->prepare("UPDATE personal_information SET profile_photo = ? WHERE patient_id = ?");
                $stmt->execute([$imgData, $patient_id]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO personal_information (patient_id, profile_photo) VALUES (?, ?)");
                $stmt->execute([$patient_id, $imgData]);
            }
            $_SESSION['snackbar_message'] = 'Profile photo saved.';
            $_SESSION['show_snackbar'] = true;
            header("Location: profile_edit.php");
            exit;
        }
    }
}
