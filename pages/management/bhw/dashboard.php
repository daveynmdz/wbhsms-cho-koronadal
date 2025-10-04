<?php
// dashboard_bhw.php
// Using the same approach as admin dashboard for consistency
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Authentication check - refactored to eliminate redirect loops
// Check 1: Is the user logged in at all?
if (!isset($_SESSION['employee_id']) || empty($_SESSION['employee_id'])) {
    // User is not logged in - redirect to login
    error_log('BHW Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'bhw') {
    // User is logged in but has wrong role - log and redirect
    error_log('Access denied to BHW dashboard - User: ' . $_SESSION['employee_id'] . ' with role: ' . 
              ($_SESSION['role'] ?? 'none'));
    
    // Clear any redirect loop detection
    unset($_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}

// Log session data for debugging
error_log('BHW Dashboard - Session Data: ' . print_r($_SESSION, true));

require_once $root_path . '/utils/staff_assignment.php';
$employee_id = $_SESSION['employee_id'];

// Enforce staff assignment for today
$assignment = getStaffAssignment($conn, $employee_id);
if (!$assignment) {
    // Not assigned today, block access
    error_log('BHW Dashboard: No active staff assignment for today.');
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'You are not assigned to any station today. Please contact the administrator.');
    header('Location: ../auth/employee_login.php?not_assigned=1');
    exit();
}

$employee_name = $_SESSION['employee_name'] ?? ($_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name']);
$employee_number = $_SESSION['employee_number'] ?? 'N/A';
$role = $_SESSION['role'] ?? 'BHW';

// Default stats for display
$stats = [
    'assigned_households' => 15,
    'visits_today' => 3,
    'health_programs' => 2,
    'immunizations_due' => 5,
    'community_events' => 1,
    'maternal_cases' => 4
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — BHW Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $root_path ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?= $root_path ?>/assets/css/sidebar.css">
    <style>
        :root {
            --primary: #28a745;
            --primary-light: #51c46d;
            --primary-dark: #218838;
            --secondary: #6c757d;
            --secondary-light: #adb5bd;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --light-hover: #e2e6ea;
            --dark: #343a40;
            --white: #ffffff;
            --border: #dee2e6;
            --border-light: #f1f1f1;
            --shadow-sm: 0 .125rem .25rem rgba(0,0,0,.075);
            --shadow: 0 .5rem 1rem rgba(0,0,0,.08);
            --shadow-lg: 0 1rem 3rem rgba(0,0,0,.1);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
            --card-hover-y: -5px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: var(--dark);
        }

        /* Content Wrapper */
        .content-wrapper {
            margin-left: var(--sidebar-width, 280px);
            padding: 2rem;
            min-height: 100vh;
            transition: var(--transition);
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1.5rem;
                margin-top: 70px;
            }
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-message h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin: 0;
            color: var(--primary-dark);
            line-height: 1.2;
        }

        .welcome-message p {
            margin: 0.5rem 0 0;
            color: var(--secondary);
            font-size: 1rem;
        }

        .dashboard-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background: var(--dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Info Card */
        .info-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .info-card::before,
        .info-card::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 0;
        }

        .info-card::before {
            width: 300px;
            height: 300px;
            right: -100px;
            top: -100px;
        }

        .info-card::after {
            width: 200px;
            height: 200px;
            left: -50px;
            bottom: -50px;
        }

        .info-card h2 {
            position: relative;
            z-index: 1;
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .info-card p {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 700px;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .welcome-message h1 {
                font-size: 1.8rem;
            }
            
            .dashboard-actions {
                margin-top: 1rem;
                flex-wrap: wrap;
            }
        }

        /* Section Heading */
        .section-heading {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin: 2.5rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-heading i {
            font-size: 1.25rem;
        }

        /* Stats Section */
        .stats-section {
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
            border: 1px solid var(--border-light);
            overflow: hidden;
            position: relative;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--card-color, var(--primary));
        }

        .stat-card:hover {
            transform: translateY(var(--card-hover-y));
            box-shadow: var(--shadow);
        }

        .stat-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--card-color, var(--primary));
            background: rgba(var(--card-color-rgb, 40, 167, 69), 0.1);
            border-radius: 50%;
            flex-shrink: 0;
        }

        .stat-details {
            flex-grow: 1;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary);
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .stat-card.households { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .stat-card.visits { --card-color: #007bff; --card-color-rgb: 0, 123, 255; }
        .stat-card.programs { --card-color: #6f42c1; --card-color-rgb: 111, 66, 193; }
        .stat-card.immunizations { --card-color: #ffc107; --card-color-rgb: 255, 193, 7; }
        .stat-card.events { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }
        .stat-card.maternal { --card-color: #e83e8c; --card-color-rgb: 232, 62, 140; }

        /* Quick Actions */
        .quick-actions-section {
            margin-bottom: 2.5rem;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            text-decoration: none;
            color: var(--dark);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--border-light);
            position: relative;
        }

        .action-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--card-color, var(--primary));
            transition: width 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(var(--card-hover-y));
            box-shadow: var(--shadow);
        }

        .action-card:hover::before {
            width: 8px;
        }

        .action-icon {
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(var(--card-color-rgb, 40, 167, 69), 0.1);
            color: var(--card-color, var(--primary));
            border-radius: 12px;
            flex-shrink: 0;
        }

        .action-content {
            flex-grow: 1;
        }

        .action-content h3 {
            margin: 0 0 0.35rem;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--dark);
        }

        .action-content p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .action-arrow {
            color: var(--card-color, var(--primary));
            font-size: 1rem;
            opacity: 0.7;
            transition: transform 0.2s;
        }

        .action-card:hover .action-arrow {
            transform: translateX(4px);
            opacity: 1;
        }

        .action-card.green { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .action-card.blue { --card-color: #007bff; --card-color-rgb: 0, 123, 255; }
        .action-card.purple { --card-color: #6f42c1; --card-color-rgb: 111, 66, 193; }
        .action-card.orange { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }
        .action-card.teal { --card-color: #17a2b8; --card-color-rgb: 23, 162, 184; }
        .action-card.pink { --card-color: #e83e8c; --card-color-rgb: 232, 62, 140; }

        /* Info Section */
        .info-section {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
        }

        .info-section h3 {
            margin: 0 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
        }

        .info-content {
            margin: 1rem 0;
            padding: 1rem;
            background: var(--light);
            border-left: 4px solid var(--primary);
            border-radius: 4px;
        }

        .info-content h4 {
            margin: 0 0 0.5rem;
            color: var(--primary);
        }

        .info-content ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        /* Animations */
        @keyframes fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fade-right {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes fade-left {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .animated {
            animation-duration: 0.6s;
            animation-fill-mode: both;
        }

        .fade-in { animation-name: fade-in; }
        .fade-up { animation-name: fade-up; }
        .fade-right { animation-name: fade-right; }
        .fade-left { animation-name: fade-left; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php
    $activePage = 'dashboard';
    include $root_path . '/includes/sidebar_bhw.php';
    ?>

    <main class="content-wrapper">
        <!-- Dashboard Header with Actions -->
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Good day, <?php echo htmlspecialchars($employee_name); ?>!</h1>
                <p>Barangay Health Worker Dashboard • <?php echo htmlspecialchars(strtoupper($role)); ?> • ID: <?php echo htmlspecialchars($employee_number); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="create_referrals.php" class="btn btn-primary">
                    <i class="fas fa-share"></i> Create Referral
                </a>
                <a href="household_management.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Manage Households
                </a>
                <a href="../auth/employee_logout.php" class="btn btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </section>
        
        <!-- System Overview Card -->
        <section class="info-card">
            <h2><i class="fas fa-heartbeat"></i> Community Health Overview</h2>
            <p>Welcome to your barangay health worker dashboard. Monitor household health, track immunizations, manage referrals, and coordinate community health programs in your assigned area.</p>
        </section>

        <!-- Statistics Cards -->
        <section class="stats-section">
            <h2 class="section-heading"><i class="fas fa-chart-pie"></i> Community Health Metrics</h2>
            
            <div class="stats-grid">
                <div class="stat-card households animate-on-scroll" data-animation="fade-up" data-delay="100">
                    <div class="stat-icon"><i class="fas fa-home"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['assigned_households']); ?></div>
                        <div class="stat-label">Assigned Households</div>
                    </div>
                </div>
                
                <div class="stat-card visits animate-on-scroll" data-animation="fade-up" data-delay="200">
                    <div class="stat-icon"><i class="fas fa-walking"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['visits_today']); ?></div>
                        <div class="stat-label">Visits Today</div>
                    </div>
                </div>
                
                <div class="stat-card programs animate-on-scroll" data-animation="fade-up" data-delay="300">
                    <div class="stat-icon"><i class="fas fa-heartbeat"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['health_programs']); ?></div>
                        <div class="stat-label">Active Programs</div>
                    </div>
                </div>
                
                <div class="stat-card immunizations animate-on-scroll" data-animation="fade-up" data-delay="400">
                    <div class="stat-icon"><i class="fas fa-syringe"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['immunizations_due']); ?></div>
                        <div class="stat-label">Immunizations Due</div>
                    </div>
                </div>
                
                <div class="stat-card events animate-on-scroll" data-animation="fade-up" data-delay="500">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['community_events']); ?></div>
                        <div class="stat-label">Community Events</div>
                    </div>
                </div>
                
                <div class="stat-card maternal animate-on-scroll" data-animation="fade-up" data-delay="600">
                    <div class="stat-icon"><i class="fas fa-baby"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['maternal_cases']); ?></div>
                        <div class="stat-label">Maternal Cases</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="quick-actions-section">
            <h2 class="section-heading"><i class="fas fa-bolt"></i> Quick Actions</h2>
            
            <div class="action-grid">
                <a href="household_management.php" class="action-card green animate-on-scroll" data-animation="fade-up" data-delay="100">
                    <div class="action-icon"><i class="fas fa-home"></i></div>
                    <div class="action-content">
                        <h3>Household Management</h3>
                        <p>Record and track household health visits</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="health_education.php" class="action-card blue animate-on-scroll" data-animation="fade-up" data-delay="200">
                    <div class="action-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="action-content">
                        <h3>Health Education</h3>
                        <p>Conduct community health education sessions</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="immunization_tracking.php" class="action-card purple animate-on-scroll" data-animation="fade-up" data-delay="300">
                    <div class="action-icon"><i class="fas fa-syringe"></i></div>
                    <div class="action-content">
                        <h3>Immunization Tracking</h3>
                        <p>Monitor and schedule child immunizations</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="maternal_care.php" class="action-card orange animate-on-scroll" data-animation="fade-up" data-delay="400">
                    <div class="action-icon"><i class="fas fa-baby"></i></div>
                    <div class="action-content">
                        <h3>Maternal Care</h3>
                        <p>Track prenatal and postnatal care</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="community_events.php" class="action-card teal animate-on-scroll" data-animation="fade-up" data-delay="500">
                    <div class="action-icon"><i class="fas fa-users"></i></div>
                    <div class="action-content">
                        <h3>Community Events</h3>
                        <p>Organize and manage health events</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="create_referrals.php" class="action-card pink animate-on-scroll" data-animation="fade-up" data-delay="600">
                    <div class="action-icon"><i class="fas fa-share"></i></div>
                    <div class="action-content">
                        <h3>Create Referrals</h3>
                        <p>Create patient referrals for higher facilities</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
            </div>
        </section>

        <!-- BHW Information Section -->
        <section class="info-section animate-on-scroll" data-animation="fade-up" data-delay="100">
            <h3><i class="fas fa-info-circle"></i>BHW Dashboard - Login Successful!</h3>
            <p><strong>Welcome to your Barangay Health Worker dashboard.</strong></p>
            <p>You are successfully logged in with <code>role_id=5</code> which correctly maps to the BHW role.</p>
            
            <div class="info-content">
                <h4>Session Information:</h4>
                <ul>
                    <li><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee_id); ?></li>
                    <li><strong>Name:</strong> <?php echo htmlspecialchars($employee_name); ?></li>
                    <li><strong>Employee Number:</strong> <?php echo htmlspecialchars($employee_number); ?></li>
                    <li><strong>Role:</strong> <?php echo htmlspecialchars($role); ?> (role_id=5)</li>
                </ul>
            </div>
            
            <p><strong>What you can do here:</strong></p>
            <ul>
                <li>Manage household visits and health records</li>
                <li>Create referrals for patients to DHO/CHO facilities</li>
                <li>Track immunizations and maternal care</li>
                <li>Organize community health events</li>
            </ul>
            
            <p style="color: var(--primary); font-weight: 600;">✓ Your login is working properly! You can now test the appointment booking system by creating referrals.</p>
        </section>
    </main>

    <!-- Animation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate elements when they come into view
            const animateElements = document.querySelectorAll('.animate-on-scroll');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const animation = element.dataset.animation || 'fade-in';
                        const delay = element.dataset.delay || 0;
                        
                        setTimeout(() => {
                            element.classList.add('animated', animation);
                            element.style.visibility = 'visible';
                        }, delay);
                        
                        observer.unobserve(element);
                    }
                });
            }, { threshold: 0.1 });
            
            animateElements.forEach(element => {
                element.style.visibility = 'hidden';
                observer.observe(element);
            });
        });
    </script>
</body>
</html>