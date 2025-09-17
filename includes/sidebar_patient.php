<?php
// sidebar_patient.php
// Expected (optional) from caller: $activePage, $defaults['name'], $defaults['patient_number'], $patient_id
// This file does NOT open/close <html> or <body>.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$activePage = $activePage ?? '';
$patient_id = $patient_id ?? ($_SESSION['patient_id'] ?? null);

// Initial display values from caller/session; will be refined from DB if needed.
$displayName = $defaults['name'] ?? ($_SESSION['patient_name'] ?? 'Patient');
$patientNo   = $defaults['patient_number'] ?? '';

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
            SELECT id, first_name, middle_name, last_name, suffix, username
            FROM patients
            WHERE id = ?
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
?>

<!-- Mobile topbar -->
<div class="mobile-topbar">
    <a href="../dashboard/dashboard_patient.php">
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

    <a href="../dashboard/dashboard_patient.php">
        <img id="topbarLogo" class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527" alt="City Health Logo" />
    </a>

    <div class="menu" role="menu">
        <a href="../dashboard/dashboard_patient.php"
            class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="../appointment/appointments.php"
            class="<?= $activePage === 'appointments' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-calendar-check"></i> Appointments
        </a>
        <a href="../prescription/prescriptions.php"
            class="<?= $activePage === 'prescription' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-prescription-bottle-alt"></i> Prescription
        </a>
        <a href="../laboratory/lab_tests.php"
            class="<?= $activePage === 'laboratory' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-vials"></i> Laboratory
        </a>
        <a href="../billing/billing.php"
            class="<?= $activePage === 'billing' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-file-invoice-dollar"></i> Billing
        </a>
    </div>

    <a href="../patient/profile.php"
        class="<?= $activePage === 'profile' ? 'active' : '' ?>" aria-label="View profile">
        <div class="user-profile">
            <div class="user-info">
                <img class="profile-photo"
                    src="<?= $patient_id
                                ? '../../vendor/photo_controller.php?patient_id=' . urlencode((string)$patient_id)
                                : 'https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172' ?>"
                    alt="User photo"
                    onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';">
                <div class="user-text">
                    <div class="user-name" style="font-size:16px;font-weight:600;line-height:1.2;color:#fff;">
                        <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="user-id" style="font-size:13px;font-weight:400;color:#e0e0e0;margin-top:2px;letter-spacing:1px;">
                        <i class="fas fa-id-card" style="margin-right:5px;color:#90e0ef;"></i>: <span style="font-weight:500; color:#fff;"><?= htmlspecialchars($patientNo, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <span class="tooltip">View Profile</span>
            </div>
        </div>
    </a>

    <div class="user-actions">
        <a href="../patient/user_settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="#" onclick="showLogoutModal(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<!-- Hidden logout form -->
<form id="logoutForm" action="../auth/logout.php" method="post" style="display:none;"></form>

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