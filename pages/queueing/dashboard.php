<?php
/**
 * Queueing - Admin Dashboard Page
 * Purpose: Multi-service queue dashboard for administrators to monitor all queues
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Dashboard - CHO Koronadal WBHSMS</title>
    
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
        
        .queue-stats {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .service-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
    </style>
</head>

<body>
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'queueing';
    include '../../includes/sidebar_admin.php';
    ?>

    <main class="homepage">
        <div id="queue-app">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-tachometer-alt"></i> Queue Management Dashboard</h1>
                <div class="btn-group">
                    <a href="checkin.php" class="btn btn-primary">
                        <i class="fas fa-clipboard-check"></i> Check-in
                    </a>
                    <a href="station.php" class="btn btn-outline-secondary">
                        <i class="fas fa-desktop"></i> Station View
                    </a>
                    <a href="logs.php" class="btn btn-outline-info">
                        <i class="fas fa-history"></i> Queue Logs
                    </a>
                    <a href="public_display.php" class="btn btn-outline-success" target="_blank">
                        <i class="fas fa-tv"></i> Public Display
                    </a>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="queue-stats">
                        <h6><i class="fas fa-users text-primary"></i> Total Waiting</h6>
                        <div class="fs-2 fw-bold text-primary">--</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="queue-stats">
                        <h6><i class="fas fa-user-check text-success"></i> Being Served</h6>
                        <div class="fs-2 fw-bold text-success">--</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="queue-stats">
                        <h6><i class="fas fa-check-circle text-info"></i> Completed Today</h6>
                        <div class="fs-2 fw-bold text-info">--</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="queue-stats">
                        <h6><i class="fas fa-clock text-warning"></i> Avg Wait Time</h6>
                        <div class="fs-2 fw-bold text-warning">-- min</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="service-card">
                        <h5><i class="fas fa-stethoscope"></i> Consultation</h5>
                        <p class="text-muted">General medical consultation services</p>
                        <div class="d-flex justify-content-between">
                            <span>Waiting: <strong>--</strong></span>
                            <span>Serving: <strong>--</strong></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="service-card">
                        <h5><i class="fas fa-vial"></i> Laboratory</h5>
                        <p class="text-muted">Laboratory tests and diagnostics</p>
                        <div class="d-flex justify-content-between">
                            <span>Waiting: <strong>--</strong></span>
                            <span>Serving: <strong>--</strong></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="placeholder-message">
                <i class="fas fa-chart-line fa-3x mb-3"></i>
                <h3>Queueing — DASHBOARD PAGE</h3>
                <p>(Implementation pending)</p>
                <p class="text-muted">This page will provide a comprehensive overview of all service queues</p>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Queueing Module JS -->
    <script src="../../assets/js/queueing.js"></script>
</body>
</html>