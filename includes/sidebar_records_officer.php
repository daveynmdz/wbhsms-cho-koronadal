<?php
// sidebar_records_officer.php - Records Officer sidebar navigation
// Expected (optional) from caller: $activePage, $defaults['name'], $defaults['employee_number'], $employee_id

if (session_status() === PHP_SESSION_NONE) {
    // Include employee session configuration
    require_once __DIR__ . '/../config/session/employee_session.php';
}

// Keep just the variable initialization
$activePage = $activePage ?? '';
$employee_id = $employee_id ?? ($_SESSION['employee_id'] ?? null);

// Initial display values from caller/session; will be refined from DB if needed.
$displayName = $defaults['name'] ?? ($_SESSION['employee_name'] ?? ($_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name']) ?? 'Records Officer');
$employeeNo = $defaults['employee_number'] ?? ($_SESSION['employee_number'] ?? '');
$role = $_SESSION['role'] ?? 'Records Officer';

// If we don't have good display values yet, pull from DB (only if we have an id)
$needsName = empty($displayName) || $displayName === 'Records Officer';
$needsNo = empty($employeeNo);

if (($needsName || $needsNo) && $employee_id) {
    // Ensure $conn exists; adjust the path if your config lives elsewhere
    if (!isset($conn)) {
        require_once __DIR__ . '/../config/db.php';
    }

    if (isset($conn)) {
        $stmt = $conn->prepare("
            SELECT employee_id, first_name, middle_name, last_name, employee_number, role
            FROM employees
            WHERE employee_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
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
                $displayName = $full ?: 'Records Officer';
            }
            if ($needsNo && !empty($row['employee_number'])) {
                $employeeNo = $row['employee_number'];
            }
            if (!empty($row['role'])) {
                $role = $row['role'];
            }
        }
        $stmt->close();
    }
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php
// Get the proper base URL by extracting the project folder from the request URI
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Extract the base path (project folder) from the script name
// For example: /wbhsms-cho-koronadal/pages/management/records_officer/dashboard.php -> /wbhsms-cho-koronadal/
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

// Ensure base_path ends with / if it's not empty
if ($base_path && !str_ends_with($base_path, '/')) {
    $base_path .= '/';
}

$cssPath = $base_path . 'assets/css/sidebar.css';
$vendorPath = $base_path . 'vendor/photo_controller.php';
$nav_base = $base_path . 'pages/';
?>
<link rel="stylesheet" href="<?= $cssPath ?>">

<!-- Mobile topbar -->
<div class="mobile-topbar">
    <a href="<?= $nav_base ?>management/records_officer/dashboard.php">
        <img id="topbarLogo" class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527" alt="City Health Logo" />
    </a>
</div>
<button class="mobile-toggle" onclick="toggleNav()" aria-label="Toggle Menu">
    <i id="menuIcon" class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<nav class="nav" id="sidebarNav" aria-label="Records Officer sidebar">
    <button class="close-btn" type="button" onclick="closeNav()" aria-label="Close navigation">
        <i class="fas fa-times"></i>
    </button>

    <a href="<?= $nav_base ?>management/records_officer/dashboard.php">
        <img id="topbarLogo" class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527" alt="City Health Logo" />
    </a>

    <div class="menu" role="menu">
        <a href="<?= $nav_base ?>management/records_officer/dashboard.php"
            class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="<?= $nav_base ?>management/records_officer/patient_records_management.php"
            class="<?= $activePage === 'patient_records' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-user-injured"></i> Patient Records
        </a>
        <a href="<?= $nav_base ?>management/records_officer/medical_records.php"
            class="<?= $activePage === 'medical_records' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-file-medical"></i> Medical Records
        </a>
        <a href="<?= $nav_base ?>clinical-encounter-management/index.php"
            class="<?= $activePage === 'clinical_encounters' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-notes-medical"></i> Clinical Encounters
        </a>
        <a href="<?= $nav_base ?>management/records_officer/data_entry.php"
            class="<?= $activePage === 'data_entry' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-keyboard"></i> Data Entry
        </a>
        <a href="<?= $nav_base ?>management/records_officer/filing_system.php"
            class="<?= $activePage === 'filing' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-archive"></i> Filing System
        </a>
        <a href="<?= $nav_base ?>management/records_officer/document_scanning.php"
            class="<?= $activePage === 'scanning' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-scanner"></i> Document Scanning
        </a>
        <a href="<?= $nav_base ?>management/records_officer/reports.php"
            class="<?= $activePage === 'reports' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="<?= $nav_base ?>management/records_officer/audit_trail.php"
            class="<?= $activePage === 'audit' ? 'active' : '' ?>" role="menuitem">
            <i class="fas fa-search"></i> Audit Trail
        </a>
    </div>

    <a href="<?= $nav_base ?>user/records_officer_profile.php"
        class="<?= $activePage === 'profile' ? 'active' : '' ?>" aria-label="View profile">
        <div class="user-profile">
            <div class="user-info">
                <img class="user-profile-photo"
                    src="<?= $employee_id
                                ? $vendorPath . '?employee_id=' . urlencode((string)$employee_id)
                                : 'https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172' ?>"
                    alt="User photo"
                    onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';">
                <div class="user-text">
                    <div class="user-name">
                        <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="user-id">
                        <i class="fas fa-id-badge" style="margin-right:5px;color:#90e0ef;"></i>: <span style="font-weight:500;"><?= htmlspecialchars($employeeNo, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="user-role" style="font-size:11px;color:#b3d9ff;margin-top:2px;">
                        <i class="fas fa-folder" style="margin-right:3px;"></i><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
                <span class="tooltip">View Profile</span>
            </div>
        </div>
    </a>

    <div class="user-actions">
        <a href="<?= $nav_base ?>user/records_officer_settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="#" onclick="showLogoutModal(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<?php
// Generate correct logout URL based on current file location
$logoutUrl = '';

if (strpos($_SERVER['PHP_SELF'], '/pages/management/') !== false) {
    // Called from /pages/management/ (3 levels deep)
    $logoutUrl = '../../../pages/management/auth/employee_logout.php';
} elseif (strpos($_SERVER['PHP_SELF'], '/pages/patient/profile/') !== false) {
    // Called from /pages/patient/profile/ (admin viewing patient)
    $logoutUrl = '../../../pages/management/auth/employee_logout.php';
} elseif (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
    // Called from /pages/ (2 levels deep)
    $logoutUrl = '../../pages/management/auth/employee_logout.php';
} else {
    // Default fallback (1 level deep)
    $logoutUrl = '../pages/management/auth/employee_logout.php';
}
?>

<!-- Hidden logout form with CSRF protection -->
<form id="logoutForm" action="<?= $logoutUrl ?>" method="post" style="display:none;">
    <?php if (isset($_SESSION['csrf_token'])): ?>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <?php endif; ?>
</form>

<!-- Logout Modal -->
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

<!-- Optional overlay -->
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