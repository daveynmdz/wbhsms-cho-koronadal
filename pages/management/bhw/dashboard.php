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

$employee_id = $_SESSION['employee_id'];
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
            --primary-dark: #218838;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --white: #ffffff;
            --border: #dee2e6;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
        }

        * { box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .content-wrapper {
            margin-left: var(--sidebar-width, 280px);
            padding: 2rem;
            min-height: 100vh;
            transition: var(--transition);
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
                margin-top: 70px;
            }
        }

        .welcome-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .welcome-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 300;
            line-height: 1.2;
        }

        .welcome-header .subtitle {
            margin-top: 0.5rem;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-color, var(--primary));
        }

        .stat-card.households { --card-color: #28a745; }
        .stat-card.visits { --card-color: #007bff; }
        .stat-card.programs { --card-color: #6f42c1; }
        .stat-card.immunizations { --card-color: #ffc107; }
        .stat-card.events { --card-color: #fd7e14; }
        .stat-card.maternal { --card-color: #e83e8c; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 2rem;
            color: var(--card-color, var(--primary));
            opacity: 0.8;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-color, var(--primary));
            transition: var(--transition);
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            text-decoration: none;
        }

        .action-card:hover::before {
            width: 8px;
        }

        .action-card.green { --card-color: #28a745; }
        .action-card.blue { --card-color: #007bff; }
        .action-card.purple { --card-color: #6f42c1; }
        .action-card.orange { --card-color: #fd7e14; }
        .action-card.teal { --card-color: #17a2b8; }
        .action-card.pink { --card-color: #e83e8c; }

        .action-card .icon {
            font-size: 2.5rem;
            color: var(--card-color, var(--primary));
            margin-bottom: 1rem;
            display: block;
        }

        .action-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
        }

        .action-card p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .stats-grid,
            .action-grid {
                grid-template-columns: 1fr;
            }
            .welcome-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>
    <?php
    $activePage = 'dashboard';
    include $root_path . '/includes/sidebar_bhw.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Good day, <?= htmlspecialchars($employee_name) ?>!</h1>
            <p class="subtitle">
                Community Health Worker Dashboard • <?= htmlspecialchars(strtoupper($role)) ?> 
                • ID: <?= htmlspecialchars($employee_number) ?>
            </p>
        </div>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Community Health Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card households">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-home"></i></div>
                </div>
                <div class="stat-number"><?= number_format($stats['assigned_households']) ?></div>
                <div class="stat-label">Assigned Households</div>
            </div>
            <div class="stat-card visits">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-walking"></i></div>
                </div>
                <div class="stat-number"><?= number_format($stats['visits_today']) ?></div>
                <div class="stat-label">Visits Today</div>
            </div>
            <div class="stat-card programs">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-heartbeat"></i></div>
                </div>
                <div class="stat-number"><?= number_format($stats['health_programs']) ?></div>
                <div class="stat-label">Active Programs</div>
            </div>
            <div class="stat-card immunizations">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-syringe"></i></div>
                </div>
                <div class="stat-number"><?= number_format($stats['immunizations_due']) ?></div>
                <div class="stat-label">Immunizations Due</div>
            </div>
            <div class="stat-card events">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="stat-number"><?= number_format($stats['community_events']) ?></div>
                <div class="stat-label">Community Events</div>
            </div>
            <div class="stat-card maternal">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-baby"></i></div>
                </div>
                <div class="stat-number"><?= number_format($stats['maternal_cases']) ?></div>
                <div class="stat-label">Maternal Cases</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="action-grid">
            <a href="household_management.php" class="action-card green">
                <i class="fas fa-home icon"></i>
                <h3>Household Management</h3>
                <p>Record and track household health visits</p>
            </a>
            <a href="health_education.php" class="action-card blue">
                <i class="fas fa-chalkboard-teacher icon"></i>
                <h3>Health Education</h3>
                <p>Conduct community health education sessions</p>
            </a>
            <a href="immunization_tracking.php" class="action-card purple">
                <i class="fas fa-syringe icon"></i>
                <h3>Immunization Tracking</h3>
                <p>Monitor and schedule child immunizations</p>
            </a>
            <a href="maternal_care.php" class="action-card orange">
                <i class="fas fa-baby icon"></i>
                <h3>Maternal Care</h3>
                <p>Track prenatal and postnatal care</p>
            </a>
            <a href="community_events.php" class="action-card teal">
                <i class="fas fa-users icon"></i>
                <h3>Community Events</h3>
                <p>Organize and manage health events</p>
            </a>
            <a href="referrals.php" class="action-card pink">
                <i class="fas fa-share icon"></i>
                <h3>Create Referrals</h3>
                <p>Create patient referrals for higher facilities</p>
            </a>
        </div>

        <div style="background: white; padding: 2rem; border-radius: 12px; margin-top: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h3><i class="fas fa-info-circle" style="color: #28a745; margin-right: 0.5rem;"></i>BHW Dashboard - Login Successful!</h3>
            <p><strong>Welcome to your Barangay Health Worker dashboard.</strong></p>
            <p>You are successfully logged in with <code>role_id=5</code> which correctly maps to the BHW role.</p>
            
            <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-left: 4px solid #28a745; border-radius: 4px;">
                <h4 style="margin: 0 0 0.5rem 0; color: #28a745;">Session Information:</h4>
                <ul style="margin: 0; padding-left: 1.2rem;">
                    <li><strong>Employee ID:</strong> <?= htmlspecialchars($employee_id) ?></li>
                    <li><strong>Name:</strong> <?= htmlspecialchars($employee_name) ?></li>
                    <li><strong>Employee Number:</strong> <?= htmlspecialchars($employee_number) ?></li>
                    <li><strong>Role:</strong> <?= htmlspecialchars($role) ?> (role_id=5)</li>
                </ul>
            </div>
            
            <p style="margin-top: 1rem;"><strong>What you can do here:</strong></p>
            <ul>
                <li>Manage household visits and health records</li>
                <li>Create referrals for patients to DHO/CHO facilities</li>
                <li>Track immunizations and maternal care</li>
                <li>Organize community health events</li>
            </ul>
            
            <p style="margin-top: 1rem; color: #28a745;"><strong>✓ Your login is working properly!</strong> You can now test the appointment booking system by creating referrals.</p>
        </div>
    </section>
</body>
</html>