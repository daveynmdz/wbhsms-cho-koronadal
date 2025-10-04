<?php
/**
 * Queueing - Station View Page
 * Purpose: Station-specific view (parameterized by service_id/station_id) for healthcare providers
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// DB connection
require_once $root_path . '/config/db.php';
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Get station parameters (for future implementation)
$station_id = $_GET['station_id'] ?? null;
$service_id = $_GET['service_id'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station View - CHO Koronadal WBHSMS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    
    <style>
        #queue-app {
            padding: 2rem;
            background: #f8f9fa;
            min-height: 80vh;
            border-radius: 8px;
            margin: 1rem;
        }
        
        .placeholder-message {
            text-align: center;
            color: #6c757d;
            font-size: 1.2rem;
            margin-top: 3rem;
        }
        
        .station-params {
            background: #e9ecef;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body>
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'queueing';
    include '../../includes/sidebar_admin.php';
    ?>

    <section class="homepage">
        <div id="queue-app">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-desktop"></i> Station View</h1>
                <div class="btn-group">
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-tachometer-alt"></i> Queue Dashboard
                    </a>
                    <a href="checkin.php" class="btn btn-outline-secondary">
                        <i class="fas fa-clipboard-check"></i> Check-in
                    </a>
                </div>
            </div>
            
            <?php if ($station_id || $service_id): ?>
                <div class="station-params">
                    <h6><i class="fas fa-info-circle"></i> Station Parameters</h6>
                    <?php if ($station_id): ?>
                        <span class="badge bg-primary">Station ID: <?php echo htmlspecialchars($station_id); ?></span>
                    <?php endif; ?>
                    <?php if ($service_id): ?>
                        <span class="badge bg-secondary">Service ID: <?php echo htmlspecialchars($service_id); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="placeholder-message">
                <i class="fas fa-stethoscope fa-3x mb-3"></i>
                <h3>Queueing — STATION PAGE</h3>
                <p>(Implementation pending)</p>
                <p class="text-muted">This page will display the station-specific queue view for healthcare providers</p>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Queueing Module JS -->
    <script src="../../assets/js/queueing.js"></script>
</body>
</html>