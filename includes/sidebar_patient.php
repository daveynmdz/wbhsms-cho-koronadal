<?php
// sidebar_patient.php
// Expected (optional) from caller: $activePage, $defaults['name'], $defaults['patient_number'], $patient_id
// This file does NOT open/close <html> or <body>.

if (session_status() === PHP_SESSION_NONE) {
    // Include patient session configuration
    require_once __DIR__ . '/../config/session/patient_session.php';
}

$activePage = $activePage ?? '';
$patient_id = $patient_id ?? ($_SESSION['patient_id'] ?? null);

// Initial display values from session first, then caller defaults if available
$displayName = $_SESSION['patient_name'] ?? ($defaults['name'] ?? 'Patient');
$patientNo   = $_SESSION['patient_number'] ?? ($defaults['patient_number'] ?? '');

// If we don't have good display values yet, pull from DB (only if we have an id)
$needsName = empty($displayName) || $displayName === 'Patient';
$needsNo   = empty($patientNo);

if (($needsName || $needsNo) && $patient_id) {
    // Ensure $pdo exists; adjust the path if your config lives elsewhere
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/db.php';
    }

    if (isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT patient_id as id, first_name, middle_name, last_name, suffix, username
            FROM patients
            WHERE patient_id = ?
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($needsName) {
                $parts = [];
                if (!empty($row['first_name'])) {
                    $parts[] = $row['first_name'];
                }
                if (!empty($row['middle_name'])) {
                    $parts[] = $row['middle_name'];
                }
                if (!empty($row['last_name'])) {
                    $parts[] = $row['last_name'];
                }
                $full = trim(implode(' ', $parts));
                if (!empty($row['suffix'])) {
                    $full .= ' ' . $row['suffix'];
                }
                $displayName = $full ?: 'Patient';
            }
            if ($needsNo && !empty($row['username'])) {
                $patientNo = $row['username'];
            }
        }
    }
}

// Get the proper base URL by extracting the project folder from the request URI
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Extract the base path (project folder) from the script name
// For example: /wbhsms-cho-koronadal/pages/patient/profile/dashboard.php -> /wbhsms-cho-koronadal/
if (preg_match('#^(.*?)/pages/#', $script_name, $matches)) {
    $base_path = $matches[1];
} else {
    // Fallback: try to extract from REQUEST_URI
    $uri_parts = explode('/', trim($request_uri, '/'));
    if (count($uri_parts) > 0 && $uri_parts[0] !== 'pages') {
        $base_path = '/' . $uri_parts[0];
    } else {
        $base_path = '';
    }
}

// Calculate navigation base for patient pages specifically
if ($base_path) {
    // Local development: /project-folder/pages/patient/
    $nav_base = $base_path . '/pages/patient/';
} else {
    // Production deployment: /pages/patient/
    $nav_base = '/pages/patient/';
}

$assets_path = $base_path . '/assets/css/sidebar.css';
$vendor_path = $base_path . '/vendor/photo_controller.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= $assets_path ?>">

<!-- Mobile topbar -->
<div class="mobile-topbar">
    <a href="<?= $nav_base ?>dashboard.php">
        <img id="topbarLogo" class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527" alt="City Health Logo" />
    </a>
</div>
<button class="mobile-toggle" onclick="toggleNav()" aria-label="Toggle Menu">
    <i id="menuIcon" class="fas fa-bars"></i>
</button>
<!-- Sidebar -->
<nav class="nav" id="sidebarNav" aria-label="Patient sidebar">
    <button class="close-btn" type="button" onclick="closeNav()" aria-label="Close navigation">
        <i class="fas fa-times"></i>
    </button>

    <a href="<?= $nav_base ?>dashboard.php">
        <img id="topbarLogo" class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527" alt="City Health Logo" />
    </a>

    <div class="menu" role="menu">
        <a href="<?= $nav_base ?>dashboard.php"
            class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="<?= $nav_base ?>appointment/appointments.php"
            class="<?= $activePage === 'appointments' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-calendar-check"></i> My Appointments
        </a>
        <a href="<?= $nav_base ?>queueing/queue_status.php"
            class="<?= $activePage === 'queue_status' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-ticket-alt"></i> My Queue Status
        </a>
        <a href="<?= $nav_base ?>referrals/referrals.php"
            class="<?= $activePage === 'referrals' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-file-medical"></i> Medical Referrals
        </a>
        <a href="<?= $nav_base ?>prescription/prescriptions.php"
            class="<?= $activePage === 'prescription' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-prescription-bottle-alt"></i> Prescription
        </a>
        <a href="<?= $nav_base ?>laboratory/lab_test.php"
            class="<?= $activePage === 'laboratory' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-vials"></i> Laboratory
        </a>
        <a href="<?= $nav_base ?>billing/billing.php"
            class="<?= $activePage === 'billing' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-file-invoice-dollar"></i> Billing
        </a>
    </div>

    <a href="<?= $nav_base ?>profile/profile.php"
        class="<?= $activePage === 'profile' ? 'active' : '' ?>" aria-label="View profile">
        <div class="user-profile">
            <div class="user-info">
                <img class="user-profile-photo"
                    src="<?= $patient_id
                                ? $vendor_path . '?patient_id=' . urlencode((string)$patient_id)
                                : 'https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172' ?>"
                    alt="User photo"
                    onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';">
                <div class="user-text">
                    <div class="user-name">
                        <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="user-id">
                        <i class="fas fa-id-card" style="margin-right:5px;color:#90e0ef;"></i>: <span style="font-weight:500;"><?= htmlspecialchars($patientNo, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <span class="tooltip">View Profile</span>
            </div>
        </div>
    </a>

    <div class="user-actions">
        <a href="<?= $nav_base ?>user_settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="#" onclick="showLogoutModal(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<?php
// Generate correct logout URL based on current file location
$logoutUrl = '';

if (strpos($_SERVER['PHP_SELF'], '/pages/patient/') !== false) {
    // Called from patient pages (most common case)
    if (strpos($_SERVER['PHP_SELF'], '/pages/patient/appointment/') !== false || 
        strpos($_SERVER['PHP_SELF'], '/pages/patient/billing/') !== false ||
        strpos($_SERVER['PHP_SELF'], '/pages/patient/laboratory/') !== false ||
        strpos($_SERVER['PHP_SELF'], '/pages/patient/prescription/') !== false ||
        strpos($_SERVER['PHP_SELF'], '/pages/patient/profile/') !== false ||
        strpos($_SERVER['PHP_SELF'], '/pages/patient/queueing/') !== false ||
        strpos($_SERVER['PHP_SELF'], '/pages/patient/referrals/') !== false) {
        // Called from subfolders within patient (3 levels deep)
        $logoutUrl = '../auth/logout.php';
    } else {
        // Called from /pages/patient/ directly (2 levels deep)
        $logoutUrl = 'auth/logout.php';
    }
} else {
    // Fallback for other locations
    $logoutUrl = '/pages/patient/auth/logout.php';
}
?>

<!-- Hidden logout form -->
<form id="logoutForm" action="<?= $logoutUrl ?>" method="post" style="display:none;"></form>

<!-- Logout Modal (can be styled via your site-wide CSS) -->
<div id="logoutModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
        <h2 id="logoutTitle">Sign Out</h2>
        <p>Are you sure you want to sign out?</p>
        <div class="modal-actions">
            <button type="button" onclick="confirmLogout()" class="btn btn-danger">Sign Out</button>
            <button type="button" onclick="closeLogoutModal()" class="btn btn-secondary">Cancel</button>
        </div>
    </div>
</div>

<!-- Optional overlay (if your layout uses it). Safe if duplicated; JS guards for missing element. -->
<div class="overlay" id="overlay" onclick="closeNav()"></div>

<script>
    function toggleNav() {
        const s = document.getElementById('sidebarNav');
        const o = document.getElementById('overlay');
        if (s) s.classList.toggle('open');
        if (o) o.classList.toggle('active');
    }

    function closeNav() {
        const s = document.getElementById('sidebarNav');
        const o = document.getElementById('overlay');
        if (s) s.classList.remove('open');
        if (o) o.classList.remove('active');
    }

    function showLogoutModal(e) {
        if (e) e.preventDefault();
        closeNav();
        const m = document.getElementById('logoutModal');
        if (m) m.style.display = 'flex';
    }

    function closeLogoutModal() {
        const m = document.getElementById('logoutModal');
        if (m) m.style.display = 'none';
    }

    function confirmLogout() {
        const f = document.getElementById('logoutForm');
        if (f) f.submit();
    }
</script>