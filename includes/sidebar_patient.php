<?php
// Sidebar refactor: modular, robust, accessible, highlights active page
// Usage: set $activePage = 'dashboard'|'appointments'|'prescription'|'laboratory'|'billing'|'profile'|'notifications' before including
// sidebar_patient.php (reusable for patient pages)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$activePage = $activePage ?? ''; // allow caller to set
$patientId  = $_SESSION['patient_id'] ?? null;
$patientName = $_SESSION['patient_name'] ?? 'Patient';
require_once __DIR__ . '/../config/db.php';

$patient_id = isset($_SESSION['patient_id']) ? $_SESSION['patient_id'] : null;
$patient = null;
if ($patient_id) {
    // Use your correct patient table and columns if different
    $stmt = $pdo->prepare("SELECT * FROM personal_information WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
}
$defaults = [
    'name' => $patient ? ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '') : 'Patient',
    'patient_number' => $patient ? ($patient['patient_number'] ?? '') : ''
];
if (!isset($activePage)) $activePage = '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <!-- Mobile topbar -->
    <div class="mobile-topbar">
        <img src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527" alt="Sidebar Logo" class="logo">
        <button class="mobile-toggle" onclick="toggleNav()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Sidebar -->
    <nav class="nav" id="sidebarNav">
        <button class="close-btn" onclick="closeNav()"><i class="fas fa-times"></i></button>
        <img src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527" alt="Sidebar Logo" class="logo">

        <div class="menu">
            <a href="dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="appointments.php" class="<?= $activePage === 'appointments' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i> Appointments
            </a>
            <a href="prescriptions.php" class="<?= $activePage === 'prescription' ? 'active' : '' ?>">
                <i class="fas fa-prescription-bottle-alt"></i> Prescription
            </a>
            <a href="lab_tests.php" class="<?= $activePage === 'laboratory' ? 'active' : '' ?>">
                <i class="fas fa-vials"></i> Laboratory
            </a>
            <a href="billing.php" class="<?= $activePage === 'billing' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice-dollar"></i> Billing
            </a>

        </div>

        <a href="profile.php" class="<?= $activePage === 'profile' ? 'active' : '' ?>">
            <div class="user-profile">
                <div class="user-info">
                    <img class="profile-photo"
                        src="<?php
                                if ($patient_id) {
                                    echo '/WBHSMS-CHO/public/patient/PhotoController.php?patient_id=' . urlencode($patient_id);
                                } else {
                                    echo 'https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';
                                }
                                ?>" alt="User" onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';">
                    <div class="user-text">
                        <strong><?= htmlspecialchars($defaults['name']) ?></strong>
                        <small>Patient No.: <?= htmlspecialchars($defaults['patient_number']) ?></small>
                    </div>
                    <span class="tooltip">View Profile</span>
                </div>
        </a>

        <div class="user-actions">
            <a href="user_settings.php"><i class="fas fa-cog"></i> Settings</a>
            <a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="closeNav()"></div>
    <script>
        function toggleNav() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const topbarLogo = document.getElementById('topbarLogo');
            const menuIcon = document.getElementById('menuIcon');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            const isOpen = sidebar.classList.contains('open');
            if (window.innerWidth <= 768) {
                topbarLogo.style.display = isOpen ? 'none' : 'block';
            }
            menuIcon.classList.toggle('fa-bars', !isOpen);
            menuIcon.classList.toggle('fa-times', isOpen);
        }

        function closeNav() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('overlay').classList.remove('active');
            if (window.innerWidth <= 768) {
                document.getElementById('topbarLogo').style.display = 'block';
            }
            const menuIcon = document.getElementById('menuIcon');
            menuIcon.classList.remove('fa-times');
            menuIcon.classList.add('fa-bars');
        }

        function showLogoutModal(e) {
            e.preventDefault();
            closeNav();
            document.getElementById('logoutModal').style.display = 'flex';
        }

        function closeLogoutModal() {
            document.getElementById('logoutModal').style.display = 'none';
        }

        function confirmLogout() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/pages/patient/patientLogout.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    window.location.href = '/pages/auth/patient_login.php';
                }
            };
            xhr.send();
        }
    </script>
</body>

</html>