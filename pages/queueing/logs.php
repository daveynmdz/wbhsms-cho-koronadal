<?php
/**
 * Queueing - Logs and History Page
 * Purpose: Display queue logs and historical data for analysis and reporting
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

// Get filter parameters (for future implementation)
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$service_filter = $_GET['service'] ?? 'all';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Logs - CHO Koronadal WBHSMS</title>
    
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
        
        .filter-panel {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .log-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
                <h1><i class="fas fa-history"></i> Queue Logs & History</h1>
                <div class="btn-group">
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-tachometer-alt"></i> Queue Dashboard
                    </a>
                    <button class="btn btn-outline-success" disabled>
                        <i class="fas fa-download"></i> Export Report
                    </button>
                </div>
            </div>
            
            <div class="filter-panel">
                <h5><i class="fas fa-filter"></i> Filter Options</h5>
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Service</label>
                        <select class="form-select" disabled>
                            <option value="all">All Services</option>
                            <option value="consultation">Consultation</option>
                            <option value="laboratory">Laboratory</option>
                            <option value="pharmacy">Pharmacy</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary d-block w-100" disabled>
                            <i class="fas fa-search"></i> Apply Filter
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="log-table">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Date/Time</th>
                                <th>Patient</th>
                                <th>Service</th>
                                <th>Queue #</th>
                                <th>Status</th>
                                <th>Wait Time</th>
                                <th>Served By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                    No queue logs available yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="placeholder-message">
                <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                <h3>Queueing — LOGS PAGE</h3>
                <p>(Implementation pending)</p>
                <p class="text-muted">This page will display comprehensive queue logs and historical data for analysis</p>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Queueing Module JS -->
    <script src="../../assets/js/queueing.js"></script>
</body>
</html>