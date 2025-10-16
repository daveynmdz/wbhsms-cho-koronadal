<?php
/**
 * Management Feedback Dashboard
 * Role-based feedback analytics and management interface
 * WBHSMS CHO Koronadal
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

// Authentication check
if (!isset($_SESSION['employee_id']) || empty($_SESSION['employee_id'])) {
    error_log('Feedback Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Role-based access control
$allowed_roles = ['Admin', 'Manager', 'Doctor', 'Nurse', 'DHO'];
$user_role = $_SESSION['role'] ?? '';

if (!in_array($user_role, $allowed_roles)) {
    error_log('Access denied: User ' . $_SESSION['employee_id'] . ' with role ' . $user_role . ' attempted to access feedback dashboard');
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view feedback analytics.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}

// Database and backend services
require_once $root_path . '/config/db.php';
require_once $root_path . '/pages/management/backend/feedback/FeedbackController.php';
require_once $root_path . '/pages/management/backend/feedback/FeedbackDataService.php';
require_once $root_path . '/pages/management/backend/feedback/FeedbackHelper.php';

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'User';

// Initialize services
$feedbackController = new FeedbackController($conn, $pdo);
$feedbackDataService = new FeedbackDataService($conn, $pdo);

// Get facilities for filtering
$facilities = $feedbackController->getFacilities();

// Process filters
$filters = [
    'facility_id' => $_GET['facility_id'] ?? null,
    'service_category' => $_GET['service_category'] ?? null,
    'user_type' => $_GET['user_type'] ?? null,
    'date_from' => $_GET['date_from'] ?? date('Y-m-01'), // Default to current month
    'date_to' => $_GET['date_to'] ?? date('Y-m-t')
];

// Remove empty filters
$active_filters = array_filter($filters, function($value) {
    return !empty($value);
});

// Role-based data restrictions
$is_dho = ($user_role === 'DHO');
$show_individual_responses = !$is_dho; // DHO can only see aggregated data

// Get analytics data
try {
    $analytics_data = $feedbackDataService->getFeedbackAnalytics($active_filters);
    $facility_summary = [];
    
    if (!empty($active_filters['facility_id'])) {
        $facility_summary = $feedbackDataService->getFacilitySummary(
            $active_filters['facility_id'],
            $active_filters['date_from'] ?? null,
            $active_filters['date_to'] ?? null
        );
    }
    
    // Get trending data
    $trending_data = $feedbackDataService->getTrendingData(
        $active_filters['facility_id'] ?? null,
        6 // Last 6 months
    );
    
} catch (Exception $e) {
    error_log("Error fetching feedback analytics: " . $e->getMessage());
    $analytics_data = [];
    $facility_summary = [];
    $trending_data = [];
}

// Generate statistics
$stats = FeedbackHelper::generateStatsSummary($analytics_data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Analytics Dashboard - WBHSMS CHO Koronadal</title>
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/clinical-encounter.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Dashboard specific styles */
        .analytics-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-info h1 {
            margin: 0 0 10px 0;
            font-size: 2em;
        }
        
        .role-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.9em;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 0.95em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .stat-detail {
            font-size: 0.8em;
            color: #4a5568;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .analytics-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .section-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-content {
            padding: 20px;
        }
        
        .analytics-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        
        .analytics-table th {
            background: #f7fafc;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .analytics-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
        }
        
        .analytics-table tr:hover {
            background: #f9fafb;
        }
        
        .rating-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .rating-stars {
            color: #f6e05e;
        }
        
        .rating-excellent { color: #38a169; }
        .rating-good { color: #718096; }
        .rating-fair { color: #d69e2e; }
        .rating-poor { color: #e53e3e; }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .chart-placeholder {
            height: 100%;
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #718096;
            background: #f7fafc;
        }
        
        .export-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #cbd5e0;
        }
        
        .permission-notice {
            background: #fef5e7;
            border: 1px solid #f6ad55;
            color: #744210;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="homepage">
        <!-- Include appropriate sidebar based on role -->
        <?php 
        $activePage = 'feedback_analytics';
        $sidebarFile = match($user_role) {
            'Admin' => $root_path . '/includes/sidebar_admin.php',
            'Manager' => $root_path . '/includes/sidebar_admin.php', // Use admin sidebar for managers
            'Doctor' => $root_path . '/includes/sidebar_doctor.php',
            'Nurse' => $root_path . '/includes/sidebar_nurse.php',
            'DHO' => $root_path . '/includes/sidebar_admin.php', // Use admin sidebar for DHO
            default => $root_path . '/includes/sidebar_admin.php'
        };
        
        if (file_exists($sidebarFile)) {
            include $sidebarFile;
        }
        ?>
        
        <div class="main-content">
            <div class="analytics-container">
                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="header-content">
                        <div class="header-info">
                            <h1><i class="fas fa-chart-bar"></i> Feedback Analytics Dashboard</h1>
                            <p>Monitor and analyze patient satisfaction feedback</p>
                            <div class="role-badge">
                                <i class="fas fa-user-tag"></i>
                                <?php echo htmlspecialchars($user_role); ?> Access Level
                            </div>
                        </div>
                        <div class="header-stats">
                            <div style="text-align: right;">
                                <div style="font-size: 0.9em; opacity: 0.8;">Welcome back,</div>
                                <div style="font-size: 1.1em; font-weight: 600;"><?php echo htmlspecialchars($employee_name); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DHO Permission Notice -->
                <?php if ($is_dho): ?>
                    <div class="permission-notice">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>District Health Officer Access:</strong> 
                            You can view aggregated feedback statistics only. Individual feedback details are not accessible to maintain patient confidentiality.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filters Section -->
                <div class="filters-section">
                    <h3><i class="fas fa-filter"></i> Filter Analytics</h3>
                    <form class="filters-form" method="GET" action="">
                        <div class="filter-group">
                            <label for="facility_id">Facility</label>
                            <select name="facility_id" id="facility_id">
                                <option value="">All Facilities</option>
                                <?php foreach ($facilities as $facility): ?>
                                    <option value="<?php echo $facility['facility_id']; ?>" 
                                            <?php echo ($filters['facility_id'] == $facility['facility_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($facility['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="service_category">Service Category</label>
                            <select name="service_category" id="service_category">
                                <option value="">All Services</option>
                                <option value="General" <?php echo ($filters['service_category'] === 'General') ? 'selected' : ''; ?>>General</option>
                                <option value="Consultation" <?php echo ($filters['service_category'] === 'Consultation') ? 'selected' : ''; ?>>Consultation</option>
                                <option value="Laboratory" <?php echo ($filters['service_category'] === 'Laboratory') ? 'selected' : ''; ?>>Laboratory</option>
                                <option value="Pharmacy" <?php echo ($filters['service_category'] === 'Pharmacy') ? 'selected' : ''; ?>>Pharmacy</option>
                                <option value="Dental" <?php echo ($filters['service_category'] === 'Dental') ? 'selected' : ''; ?>>Dental</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="user_type">Respondent Type</label>
                            <select name="user_type" id="user_type">
                                <option value="">All Types</option>
                                <option value="Patient" <?php echo ($filters['user_type'] === 'Patient') ? 'selected' : ''; ?>>Patient</option>
                                <option value="BHW" <?php echo ($filters['user_type'] === 'BHW') ? 'selected' : ''; ?>>Barangay Health Worker</option>
                                <option value="Employee" <?php echo ($filters['user_type'] === 'Employee') ? 'selected' : ''; ?>>Employee</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_from">Date From</label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo $filters['date_from']; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to">Date To</label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo $filters['date_to']; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_responses']; ?></div>
                        <div class="stat-label">Total Responses</div>
                        <div class="stat-detail">
                            <?php echo !empty($filters['date_from']) ? 'In selected period' : 'All time'; ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number <?php echo FeedbackHelper::getRatingColorClass($stats['average_rating']); ?>">
                            <?php echo number_format($stats['average_rating'], 1); ?>
                        </div>
                        <div class="stat-label">Average Rating</div>
                        <div class="stat-detail">
                            <?php echo FeedbackHelper::formatRating($stats['average_rating'], 'text'); ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['satisfaction_rate']; ?>%</div>
                        <div class="stat-label">Satisfaction Rate</div>
                        <div class="stat-detail">
                            Excellent + Good ratings
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($facilities); ?></div>
                        <div class="stat-label">Active Facilities</div>
                        <div class="stat-detail">
                            Collecting feedback
                        </div>
                    </div>
                </div>

                <div class="content-grid">
                    <!-- Main Analytics Table -->
                    <div class="analytics-section">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-table"></i>
                                Detailed Analytics
                            </div>
                        </div>
                        <div class="section-content">
                            <?php if (empty($analytics_data)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <h3>No Data Available</h3>
                                    <p>No feedback data found for the selected criteria. Try adjusting your filters or check back later.</p>
                                </div>
                            <?php else: ?>
                                <div style="overflow-x: auto;">
                                    <table class="analytics-table">
                                        <thead>
                                            <tr>
                                                <th>Facility</th>
                                                <th>Service</th>
                                                <th>User Type</th>
                                                <th>Responses</th>
                                                <th>Avg Rating</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics_data as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['facility_name'] ?? 'Unknown'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['service_category'] ?? 'General'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['user_type'] ?? 'Unknown'); ?></td>
                                                    <td><?php echo intval($row['total_submissions']); ?></td>
                                                    <td class="rating-cell">
                                                        <div class="rating-stars">
                                                            <?php
                                                            $rating = floatval($row['avg_overall_rating'] ?? 0);
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                if ($i <= $rating) {
                                                                    echo '<i class="fas fa-star"></i>';
                                                                } elseif ($i - 0.5 <= $rating) {
                                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                                } else {
                                                                    echo '<i class="far fa-star"></i>';
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                        <span class="<?php echo FeedbackHelper::getRatingColorClass($rating); ?>">
                                                            <?php echo number_format($rating, 1); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($row['submission_date'] ?? 'now')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rating Distribution Chart -->
                    <div class="analytics-section">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-chart-pie"></i>
                                Rating Distribution
                            </div>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($stats['rating_distribution'])): ?>
                                <div class="rating-breakdown">
                                    <?php foreach ($stats['rating_distribution'] as $category => $data): ?>
                                        <div class="rating-item" style="margin-bottom: 15px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                                <span style="font-weight: 600;"><?php echo ucfirst(str_replace('_', ' ', $category)); ?></span>
                                                <span><?php echo $data['count']; ?> (<?php echo $data['percentage']; ?>%)</span>
                                            </div>
                                            <div style="background: #e2e8f0; border-radius: 10px; height: 8px; overflow: hidden;">
                                                <div style="background: <?php 
                                                    echo match($category) {
                                                        'excellent' => '#38a169',
                                                        'good' => '#718096', 
                                                        'fair' => '#d69e2e',
                                                        'poor' => '#e53e3e',
                                                        default => '#cbd5e0'
                                                    }; 
                                                ?>; width: <?php echo $data['percentage']; ?>%; height: 100%; transition: width 0.3s;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="chart-placeholder">
                                    <div>
                                        <i class="fas fa-chart-pie" style="font-size: 2em; margin-bottom: 10px;"></i>
                                        <p>No rating data available</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Export Actions -->
                <div class="analytics-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-download"></i>
                            Export Data
                        </div>
                    </div>
                    <div class="section-content">
                        <div class="export-actions">
                            <a href="export_feedback.php?<?php echo http_build_query($active_filters); ?>&format=csv" 
                               class="btn btn-success">
                                <i class="fas fa-file-csv"></i>
                                Export to CSV
                            </a>
                            
                            <?php if ($show_individual_responses): ?>
                                <a href="export_feedback.php?<?php echo http_build_query($active_filters); ?>&format=detailed" 
                                   class="btn btn-primary">
                                    <i class="fas fa-file-alt"></i>
                                    Detailed Report
                                </a>
                            <?php endif; ?>
                            
                            <button onclick="window.print()" class="btn btn-secondary">
                                <i class="fas fa-print"></i>
                                Print Report
                            </button>
                        </div>
                        
                        <div style="margin-top: 15px; font-size: 0.85em; color: #718096;">
                            <p><strong>Export Options:</strong></p>
                            <ul style="margin: 5px 0; padding-left: 20px;">
                                <li><strong>CSV:</strong> Raw data for further analysis in spreadsheet applications</li>
                                <?php if ($show_individual_responses): ?>
                                    <li><strong>Detailed Report:</strong> Comprehensive report with individual responses</li>
                                <?php endif; ?>
                                <li><strong>Print:</strong> Current view optimized for printing</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>