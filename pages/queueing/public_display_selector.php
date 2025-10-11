<?php
/**
 * Queueing - Public Display Selector Page
 * Purpose: Launcher interface for all public display pages for admins to open on separate monitors
 */

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// Check if role is authorized for admin functions only
if (strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../management/admin/dashboard.php');
    exit();
}

require_once $root_path . '/config/db.php';

// Define the 6 relevant station types and their display file mappings
$station_types = [
    'triage' => [
        'name' => 'Triage Station',
        'icon' => 'fas fa-user-md',
        'file' => 'public_display_triage.php'
    ],
    'consultation' => [
        'name' => 'Consultation',
        'icon' => 'fas fa-stethoscope', 
        'file' => 'public_display_consultation.php'
    ],
    'lab' => [
        'name' => 'Laboratory',
        'icon' => 'fas fa-microscope',
        'file' => 'public_display_lab.php'
    ],
    'pharmacy' => [
        'name' => 'Pharmacy',
        'icon' => 'fas fa-pills',
        'file' => 'public_display_pharmacy.php'
    ],
    'billing' => [
        'name' => 'Billing',
        'icon' => 'fas fa-file-invoice-dollar',
        'file' => 'public_display_billing.php'
    ],
    'document' => [
        'name' => 'Document Processing',
        'icon' => 'fas fa-file-alt',
        'file' => 'public_display_document.php'
    ]
];

// Fetch stations data with assignments for today
$today = date('Y-m-d');
$station_types_list = "'" . implode("','", array_keys($station_types)) . "'";

$query = "
    SELECT 
        s.station_id,
        s.station_name,
        s.station_type,
        s.is_open,
        s.is_active,
        sv.name as service_name,
        CONCAT(e.first_name, ' ', e.last_name) as assigned_employee,
        r.role_name as employee_role,
        asch.schedule_id
    FROM stations s
    LEFT JOIN services sv ON s.service_id = sv.service_id
    LEFT JOIN assignment_schedules asch ON s.station_id = asch.station_id 
        AND asch.start_date <= ? 
        AND (asch.end_date IS NULL OR asch.end_date >= ?)
        AND asch.is_active = 1
    LEFT JOIN employees e ON asch.employee_id = e.employee_id
    LEFT JOIN roles r ON e.role_id = r.role_id
    WHERE s.station_type IN ($station_types_list)
        AND s.is_active = 1
    ORDER BY 
        FIELD(s.station_type, 'triage', 'consultation', 'lab', 'pharmacy', 'billing', 'document')
";

$stmt = $pdo->prepare($query);
$stmt->execute([$today, $today]);
$stations_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stations_data = [];

foreach ($stations_result as $row) {
    $stations_data[$row['station_type']] = $row;
}

// Set active page for sidebar highlighting
$activePage = 'queue_management';

$current_datetime = date('F j, Y g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Display Launcher | CHO Koronadal</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Public Display Selector specific styles - MATCHING CHO THEME */
        .public-display-container {
            /* CHO Theme Variables - Matching dashboard.php */
            --cho-primary: #0077b6;
            --cho-primary-dark: #03045e;
            --cho-secondary: #6c757d;
            --cho-success: #2d6a4f;
            --cho-info: #17a2b8;
            --cho-warning: #ffc107;
            --cho-danger: #d00000;
            --cho-light: #f8f9fa;
            --cho-border: #dee2e6;
            --cho-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --cho-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --cho-border-radius: 0.5rem;
            --cho-border-radius-lg: 1rem;
            --cho-transition: all 0.3s ease;
        }

        /* Breadcrumb Navigation - exactly matching dashboard */
        .public-display-container .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--cho-secondary);
        }

        .public-display-container .breadcrumb a {
            color: var(--cho-primary);
            text-decoration: none;
            transition: var(--cho-transition);
        }

        .public-display-container .breadcrumb a:hover {
            color: var(--cho-primary-dark);
            text-decoration: underline;
        }

        /* Page header styling - exactly matching dashboard */
        .public-display-container .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: linear-gradient(135deg, var(--cho-primary) 0%, var(--cho-primary-dark) 100%);
            color: white;
            padding: 25px;
            border-radius: var(--cho-border-radius-lg);
            box-shadow: var(--cho-shadow-lg);
        }

        .public-display-container .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .public-display-container .page-header .refresh-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px 20px;
            border-radius: var(--cho-border-radius);
            cursor: pointer;
            transition: var(--cho-transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .public-display-container .page-header .refresh-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-2px);
        }

        /* Intro text styling */
        .public-display-container .intro-text {
            background: var(--cho-light);
            padding: 20px;
            border-radius: var(--cho-border-radius);
            border-left: 4px solid var(--cho-primary);
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.6;
        }

        /* Station Cards Grid Layout */
        .stations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .station-card {
            background: white;
            border: 2px solid var(--cho-border);
            border-radius: var(--cho-border-radius-lg);
            padding: 25px;
            box-shadow: var(--cho-shadow);
            transition: var(--cho-transition);
            position: relative;
        }

        .station-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--cho-shadow-lg);
            border-color: var(--cho-primary);
        }

        .station-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--cho-light);
        }

        .station-icon {
            font-size: 28px;
            color: var(--cho-primary);
            margin-right: 15px;
            width: 40px;
            text-align: center;
        }

        .station-title {
            flex: 1;
        }

        .station-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--cho-primary-dark);
            margin: 0 0 5px 0;
        }

        .service-name {
            font-size: 14px;
            color: var(--cho-secondary);
            margin: 0;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-open {
            background: #d1edda;
            color: var(--cho-success);
            border: 1px solid #c3e6cb;
        }

        .status-closed {
            background: #f8d7da;
            color: var(--cho-danger);
            border: 1px solid #f5c6cb;
        }

        .station-info {
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--cho-secondary);
            font-size: 14px;
        }

        .info-value {
            color: var(--cho-primary-dark);
            font-weight: 600;
        }

        .employee-name {
            color: var(--cho-primary);
            font-weight: 600;
        }

        .no-assignment {
            color: var(--cho-secondary);
            font-style: italic;
        }

        /* Open Display Button */
        .open-display-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--cho-primary) 0%, var(--cho-primary-dark) 100%);
            color: white;
            border: none;
            padding: 15px 20px;
            border-radius: var(--cho-border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--cho-transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .open-display-btn:hover {
            background: linear-gradient(135deg, var(--cho-primary-dark) 0%, #001d3d 100%);
            transform: translateY(-2px);
            box-shadow: var(--cho-shadow-lg);
            color: white;
            text-decoration: none;
        }

        /* Footer styling */
        .footer-info {
            text-align: center;
            color: var(--cho-secondary);
            font-size: 14px;
            padding: 20px;
            border-top: 2px solid var(--cho-light);
            background: #fafafa;
            border-radius: var(--cho-border-radius);
        }

        /* Responsive design - matching dashboard */
        @media (max-width: 768px) {
            .stations-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .public-display-container .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .station-card {
                padding: 20px;
            }
            
            .station-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .station-icon {
                margin-right: 0;
            }
        }

        @media (max-width: 576px) {
            .station-card {
                padding: 15px;
            }
            
            .station-name {
                font-size: 18px;
            }
            
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/sidebar_admin.php'; ?>
    
    <div class="homepage">
        <div class="main-content">
            <!-- Include topbar -->
            <?php include '../../includes/topbar.php'; ?>
            
            <div class="card">
                <div class="public-display-container">
                    <!-- Breadcrumb Navigation - matching dashboard -->
                    <div class="breadcrumb" style="margin-top: 50px;">
                        <a href="../management/admin/dashboard.php">Admin Dashboard</a>
                        <span>›</span>
                        <a href="dashboard.php">Queue Management Dashboard</a>
                        <span>›</span>
                        <span>Public Display Launcher</span>
                    </div>

                    <!-- Page Header with Refresh Button -->
                    <div class="page-header">
                        <h1>
                            <i class="fas fa-tv"></i>
                            Public Display Launcher
                        </h1>
                        <a href="javascript:void(0)" class="refresh-btn" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt"></i>Refresh Status
                        </a>
                    </div>

                    <!-- Intro Text -->
                    <div class="intro-text">
                        <strong>Select a display to open on a monitor.</strong> Active stations show their current status below. 
                        Each display opens in a new window/tab for use on separate monitors in waiting areas.
                    </div>

                    <!-- Stations Grid -->
                    <div class="stations-grid">
                        <?php foreach ($station_types as $type => $config): ?>
                            <?php 
                                $station_data = $stations_data[$type] ?? null;
                                $is_open = $station_data ? $station_data['is_open'] : 0;
                                $assigned_employee = $station_data ? $station_data['assigned_employee'] : null;
                                $employee_role = $station_data ? $station_data['employee_role'] : null;
                                $service_name = $station_data ? $station_data['service_name'] : null;
                                $station_name = $station_data ? $station_data['station_name'] : $config['name'];
                            ?>
                            <div class="station-card">
                                <div class="station-header">
                                    <i class="station-icon <?php echo $config['icon']; ?>"></i>
                                    <div class="station-title">
                                        <h3 class="station-name"><?php echo htmlspecialchars($station_name); ?></h3>
                                        <?php if ($service_name): ?>
                                            <p class="service-name"><?php echo htmlspecialchars($service_name); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status-badge <?php echo $is_open ? 'status-open' : 'status-closed'; ?>">
                                        <?php echo $is_open ? '🟢 Open' : '🔴 Closed'; ?>
                                    </span>
                                </div>

                                <div class="station-info">
                                    <div class="info-row">
                                        <span class="info-label">Service Type:</span>
                                        <span class="info-value">
                                            <?php echo $service_name ? htmlspecialchars($service_name) : ucfirst($type) . ' Services'; ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Current Status:</span>
                                        <span class="info-value <?php echo $is_open ? 'status-open' : 'status-closed'; ?>">
                                            <?php echo $is_open ? 'Station Open' : 'Station Closed'; ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Assigned Employee:</span>
                                        <span class="info-value">
                                            <?php if ($assigned_employee): ?>
                                                <span class="employee-name"><?php echo htmlspecialchars($assigned_employee); ?></span>
                                                <?php if ($employee_role): ?>
                                                    <br><small>(<?php echo htmlspecialchars(ucfirst($employee_role)); ?>)</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="no-assignment">No assignment for today</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>

                                <a href="<?php echo $config['file']; ?>" target="_blank" class="open-display-btn">
                                    <i class="fas fa-external-link-alt"></i>
                                    Open Display
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Footer Info -->
                    <div class="footer-info">
                        <i class="fas fa-clock"></i>
                        Last updated: <?php echo $current_datetime; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/sidebar.js"></script>
</body>
</html>