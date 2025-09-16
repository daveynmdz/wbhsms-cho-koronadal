<?php
//photo_controller.php
require_once dirname(__DIR__) . '/config/db.php';

// Accept patient_id from GET
$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : null;
if (!$patient_id || !is_numeric($patient_id)) {
    header('Content-Type: image/png');
    readfile('https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172');
    exit;
}

$stmt = $pdo->prepare('SELECT profile_photo FROM personal_information WHERE patient_id = ?');
$stmt->execute([$patient_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && !empty($row['profile_photo'])) {
    $img = $row['profile_photo'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($img);
    if (!$mime) $mime = 'image/jpeg';
    header('Content-Type: ' . $mime);
    echo $img;
} else {
    header('Content-Type: image/png');
    readfile('https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172');
}
