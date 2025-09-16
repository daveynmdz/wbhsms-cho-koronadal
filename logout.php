<?php
// logout.php for patient side
session_start();
session_unset();
session_destroy();
header('Location: /pages/auth/patient_login.php');
exit;
