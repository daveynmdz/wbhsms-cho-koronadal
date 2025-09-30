<?php
// Include patient session configuration FIRST - before any output
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// If user is not logged in, redirect to login
if (!isset($_SESSION['patient_id'])) {
    header('Location: ../auth/patient_login.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

$patient_id = $_SESSION['patient_id'];
$message = '';
$error = '';

// Fetch patient information
$patient_info = null;
try {
    $stmt = $conn->prepare("
        SELECT p.*, b.barangay_name
        FROM patients p
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        WHERE p.patient_id = ?
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_info = $result->fetch_assoc();

    // Calculate priority level
    if ($patient_info) {
        $patient_info['priority_level'] = ($patient_info['isPWD'] || $patient_info['isSenior']) ? 1 : 2;
        $patient_info['priority_description'] = ($patient_info['priority_level'] == 1) ? 'Priority Patient' : 'Regular Patient';
    }

    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch patient information: " . $e->getMessage();
}

// Fetch appointments
$appointments = [];
try {
    $stmt = $conn->prepare("
        SELECT a.*, 
               f.name as facility_name, f.type as facility_type,
               s.name as service_name,
               r.referral_num, r.referral_reason
        FROM appointments a
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN referrals r ON a.referral_id = r.referral_id
        WHERE a.patient_id = ?
        ORDER BY a.scheduled_date DESC, a.scheduled_time DESC
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch appointments: " . $e->getMessage();
}

// Fetch referrals
$referrals = [];
try {
    $stmt = $conn->prepare("
        SELECT r.*, 
               f.name as facility_name, f.type as facility_type,
               e.first_name as doctor_first_name, e.last_name as doctor_last_name
        FROM referrals r
        LEFT JOIN facilities f ON r.referred_to_facility_id = f.facility_id
        LEFT JOIN employees e ON r.referred_by = e.employee_id
        WHERE r.patient_id = ?
        ORDER BY r.referral_date DESC
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $referrals = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch referrals: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Appointments & Referrals - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <style>
        .content-wrapper {
            margin-left: 300px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.2rem;
            color: #0077b6;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0 0 1rem 0;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .breadcrumb a {
            color: #6c757d;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: #0077b6;
        }

        .page-title {
            margin: 0;
            font-size: 2.2rem;
            color: #0077b6;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .action-buttons {
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: #007BFF;
            /* clean, modern blue */
            border: none;
            border-radius: 8px;
            /* softer corners */
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            padding: 12px 28px;
            text-align: center;
            display: inline-block;
            transition: all 0.3s ease;
        }


        .btn-primary:hover {
            background: #0056b3;
            /* darker blue on hover */
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .btn-primary i {
            margin-right: 8px;
            /* spacing between icon and text */
            font-size: 18px;
        }


        .btn-secondary {
            background: linear-gradient(135deg, #16a085, #0f6b5c);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #0f6b5c, #0a4f44);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #d68910);
            color: white;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d68910, #b7700a);
            transform: translateY(-2px);
        }

        .section-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
            justify-content: flex-start;
        }

        .section-icon {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .section-title {
            margin: 0;
            font-size: 1.5rem;
            color: #0077b6;
            font-weight: 600;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            border: 2px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .filter-tab.active {
            background: #0077b6;
            color: white;
            border-color: #023e8a;
        }

        .filter-tab:hover {
            background: #e3f2fd;
            border-color: #0077b6;
        }

        .filter-tab.active:hover {
            background: #023e8a;
        }

        .search-filters {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 2px rgba(0, 119, 182, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-filter {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-filter-primary {
            background: #0077b6;
            color: white;
        }

        .btn-filter-primary:hover {
            background: #023e8a;
        }

        .btn-filter-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-filter-secondary:hover {
            background: #5a6268;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
        }

        .appointment-card,
        .referral-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.8rem;
            transition: all 0.3s ease;
            position: relative;
            min-height: 320px;
            max-height: 320px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            box-sizing: content-box;
        }

        .appointment-card:hover,
        .referral-card:hover {
            border-color: #0077b6;
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 119, 182, 0.2);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #f1f3f4;
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0077b6;
            margin: 0;
            line-height: 1.3;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .card-info {
            flex-grow: 1;
            margin-bottom: 1.2rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.6rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .info-row i {
            color: #6c757d;
            width: 20px;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .info-row strong {
            color: #0077b6;
            font-weight: 600;
            min-width: 80px;
        }

        .info-row .value {
            color: #495057;
            font-weight: 500;
        }

        .card-actions {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid #f1f3f4;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid;
        }

        .btn-outline-primary {
            border-color: #0077b6;
            color: #0077b6;
        }

        .btn-outline-primary:hover {
            background: #0077b6;
            color: white;
        }

        .btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }

        .btn-outline-danger:hover {
            background: #dc3545;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .no-results-message {
            grid-column: 1 / -1;
        }

        .no-results-message .empty-state {
            background: #f8f9fa;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
            margin: 1rem 0;
        }

        .no-results-message .empty-state i {
            color: #6c757d;
        }

        .no-results-message .empty-state h3 {
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .no-results-message .empty-state p {
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
        }

        .priority-indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                justify-content: center;
            }

            .card-grid {
                grid-template-columns: 1fr;
            }

            .btn .hide-on-mobile {
                display: none;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: stretch;
            }

            .btn-filter {
                flex: 1;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 90%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 3% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .modal-header {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            padding: 1rem 1.25rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            flex: 1;
            text-align: left;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close {
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s;
        }

        .close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Body */
        .modal-body {
            padding: 1.25rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        /* Footer */
        .modal-footer {
            padding: 1rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            border-radius: 0 0 12px 12px;
            flex-wrap: wrap;
        }

        .modal-footer button {
            flex: 1;
            max-width: 150px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 6px;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #cce7ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        /* Info Sections */
        .info-section {
            margin-bottom: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid #0077b6;
        }

        .info-section h3 {
            color: #0077b6;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.75rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-item label {
            font-weight: 600;
            color: #495057;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .info-item span {
            color: #333;
            font-size: 0.9rem;
        }

        /* Priority Info */
        .info-item.priority-info {
            grid-column: 1 / -1;
            background: #e8f5e8;
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid #28a745;
        }

        .priority-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.4rem 0.75rem;
            border-radius: 16px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .modal-header {
                flex-direction: column;
                text-align: center;
            }

            .modal-header h3 {
                text-align: center;
            }

            .appointment-id-section {
                text-align: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .modal-footer {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'appointments';
    include '../../../includes/sidebar_patient.php';
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span style="color: #0077b6; font-weight: 600;">Appointments & Referrals</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-calendar-check" style="margin-right: 0.5rem;"></i>Appointments & Referrals</h1>
            <div class="action-buttons">
                <a href="book_appointment.php" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i>
                    <span class="hide-on-mobile">Create Appointment</span>
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Appointments Section -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div>
                    <h2 class="section-title">My Appointments</h2>
                    <p style="margin: 0; color: #6c757d;">Manage your healthcare appointments</p>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="appointment-search">Search Appointments</label>
                        <input type="text" id="appointment-search" placeholder="Search by facility, service, or ID..." onkeypress="handleSearchKeyPress(event, 'appointment')">
                    </div>
                    <div class="filter-group">
                        <label for="appointment-date-from">Date From</label>
                        <input type="date" id="appointment-date-from">
                    </div>
                    <div class="filter-group">
                        <label for="appointment-date-to">Date To</label>
                        <input type="date" id="appointment-date-to">
                    </div>
                    <div class="filter-group">
                        <label for="appointment-status-filter">Status</label>
                        <select id="appointment-status-filter">
                            <option value="">All Statuses</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <div class="filter-actions">
                            <button type="button" class="btn-filter btn-filter-primary" onclick="filterAppointmentsBySearch()">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button type="button" class="btn-filter btn-filter-secondary" onclick="clearAppointmentFilters()">Clear</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" onclick="filterAppointments('all', this)">
                    <i class="fas fa-list"></i> All Appointments
                </div>
                <div class="filter-tab" onclick="filterAppointments('confirmed', this)">
                    <i class="fas fa-check-circle"></i> Confirmed
                </div>
                <div class="filter-tab" onclick="filterAppointments('completed', this)">
                    <i class="fas fa-calendar-check"></i> Completed
                </div>
                <div class="filter-tab" onclick="filterAppointments('cancelled', this)">
                    <i class="fas fa-times-circle"></i> Cancelled
                </div>
            </div>

            <!-- Appointments Grid -->
            <div class="card-grid" id="appointments-grid">
                <?php if (empty($appointments)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Appointments Found</h3>
                        <p>You haven't booked any appointments yet. Click "Create Appointment" to schedule your first appointment.</p>
                        <a href="book_appointment.php" class="btn btn-primary" style="display:inline-flex; margin-top: 1rem;">
                            <i class="fas fa-calendar-plus"></i> Book Your First Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                        $appointment_date = new DateTime($appointment['scheduled_date']);
                        $appointment_time = new DateTime($appointment['scheduled_time']);
                        $is_priority = $patient_info['priority_level'] == 1;
                        $appointment_id = 'APT-' . str_pad($appointment['appointment_id'], 8, '0', STR_PAD_LEFT);
                        ?>
                        <div class="appointment-card" data-status="<?php echo $appointment['status']; ?>" data-appointment-date="<?php echo $appointment['scheduled_date']; ?>">
                            <?php if ($is_priority): ?>
                                <div class="priority-indicator" title="Priority Patient">
                                    <i class="fas fa-star"></i>
                                </div>
                            <?php endif; ?>

                            <div class="card-header">
                                <h3 class="card-title"><?php echo $appointment_id; ?></h3>
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>

                            <div class="card-info">
                                <div class="info-row">
                                    <i class="fas fa-hospital"></i>
                                    <strong>Facility:</strong>
                                    <span class="value"><?php echo htmlspecialchars($appointment['facility_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-stethoscope"></i>
                                    <strong>Service:</strong>
                                    <span class="value"><?php echo htmlspecialchars($appointment['service_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-calendar"></i>
                                    <strong>Date:</strong>
                                    <span class="value"><?php echo $appointment_date->format('M j, Y (l)'); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-clock"></i>
                                    <strong>Time:</strong>
                                    <span class="value"><?php echo $appointment_time->format('g:i A'); ?></span>
                                </div>
                                <?php if ($appointment['referral_num']): ?>
                                    <div class="info-row">
                                        <i class="fas fa-file-medical"></i>
                                        <strong>Referral:</strong>
                                        <span class="value">#<?php echo htmlspecialchars($appointment['referral_num']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <i class="fas fa-calendar-plus"></i>
                                    <strong>Booked:</strong>
                                    <span class="value"><?php echo (new DateTime($appointment['created_at']))->format('M j, Y'); ?></span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <?php if ($appointment['status'] == 'confirmed'): ?>
                                    <button class="btn btn-sm btn-outline btn-outline-primary" onclick="viewAppointmentDetails(<?php echo $appointment['appointment_id']; ?>)">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <?php
                                    // Combine date and time for proper comparison
                                    $appointment_datetime = $appointment['scheduled_date'] . ' ' . $appointment['scheduled_time'];
                                    if (strtotime($appointment_datetime) > time()):
                                    ?>
                                        <button class="btn btn-sm btn-outline btn-outline-danger" onclick="showCancelModal(<?php echo $appointment['appointment_id']; ?>, '<?php echo $appointment_id; ?>')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline btn-outline-primary" onclick="viewAppointmentDetails(<?php echo $appointment['appointment_id']; ?>)">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Referrals Section -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div>
                    <h2 class="section-title">My Referrals</h2>
                    <p style="margin: 0; color: #6c757d;">Track your medical referrals</p>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="referral-search">Search Referrals</label>
                        <input type="text" id="referral-search" placeholder="Search by facility, doctor, or ID..." onkeypress="handleSearchKeyPress(event, 'referral')">
                    </div>
                    <div class="filter-group">
                        <label for="referral-date-from">Date From</label>
                        <input type="date" id="referral-date-from">
                    </div>
                    <div class="filter-group">
                        <label for="referral-date-to">Date To</label>
                        <input type="date" id="referral-date-to">
                    </div>
                    <div class="filter-group">
                        <label for="referral-status-filter">Status</label>
                        <select id="referral-status-filter">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="accepted">Used</option>
                            <option value="expired">Expired</option>
                            <option value="issued">Issued</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <div class="filter-actions">
                            <button type="button" class="btn-filter btn-filter-primary" onclick="filterReferralsBySearch()">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button type="button" class="btn-filter btn-filter-secondary" onclick="clearReferralFilters()">Clear</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" onclick="filterReferrals('all', this)">
                    <i class="fas fa-list"></i> All Referrals
                </div>
                <div class="filter-tab" onclick="filterReferrals('active', this)">
                    <i class="fas fa-check-circle"></i> Active
                </div>
                <div class="filter-tab" onclick="filterReferrals('accepted', this)">
                    <i class="fas fa-calendar-check"></i> Used
                </div>
                <div class="filter-tab" onclick="filterReferrals('expired', this)">
                    <i class="fas fa-times-circle"></i> Expired
                </div>
            </div>

            <!-- Referrals Grid -->
            <div class="card-grid" id="referrals-grid">
                <?php if (empty($referrals)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-file-medical"></i>
                        <h3>No Referrals Found</h3>
                        <p>You don't have any medical referrals yet. Referrals are issued by healthcare providers when specialized care is needed.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($referrals as $referral): ?>
                        <?php
                        $referral_date = new DateTime($referral['referral_date']);
                        $expiry_date = !empty($referral['expiry_date']) ? new DateTime($referral['expiry_date']) : null;
                        $facility_name = $referral['facility_name'] ?: $referral['external_facility_name'] ?: 'External Facility';
                        $doctor_name = trim($referral['doctor_first_name'] . ' ' . $referral['doctor_last_name']) ?: 'Healthcare Provider';
                        ?>
                        <div class="referral-card" data-status="<?php echo $referral['status']; ?>" data-referral-date="<?php echo $referral_date->format('Y-m-d'); ?>">
                            <div class="card-header">
                                <h3 class="card-title">Referral #<?php echo htmlspecialchars($referral['referral_num']); ?></h3>
                                <span class="status-badge status-<?php echo $referral['status']; ?>">
                                    <?php echo ucfirst($referral['status']); ?>
                                </span>
                            </div>

                            <div class="card-info">
                                <div class="info-row">
                                    <i class="fas fa-hospital"></i>
                                    <strong>Facility:</strong>
                                    <span class="value"><?php echo htmlspecialchars($facility_name); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-user-md"></i>
                                    <strong>Doctor:</strong>
                                    <span class="value"><?php echo htmlspecialchars($doctor_name); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-calendar"></i>
                                    <strong>Date:</strong>
                                    <span class="value"><?php echo $referral_date->format('M j, Y (l)'); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-clipboard-list"></i>
                                    <strong>Reason:</strong>
                                    <span class="value"><?php echo htmlspecialchars(substr($referral['referral_reason'], 0, 50)) . (strlen($referral['referral_reason']) > 50 ? '...' : ''); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <strong>Type:</strong>
                                    <span class="value"><?php echo ucfirst(str_replace('_', ' ', $referral['destination_type'])); ?></span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <button class="btn btn-sm btn-outline btn-outline-primary" onclick="viewReferralDetails(<?php echo $referral['referral_id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <?php if ($referral['status'] == 'active'): ?>
                                    <button class="btn btn-sm btn-outline btn-outline-secondary" onclick="bookFromReferral(<?php echo $referral['referral_id']; ?>)">
                                        <i class="fas fa-calendar-plus"></i> Book Appointment
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Cancel Appointment Modal -->
    <div id="cancelModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Cancel Appointment</h3>
                <button type="button" class="close" onclick="closeCancelModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <strong>Are you sure you want to cancel this appointment?</strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">This action cannot be undone. Please provide a reason for cancellation.</p>
                </div>

                <div class="form-group">
                    <label for="cancellation-reason">Reason for Cancellation <span style="color: red;">*</span></label>
                    <select id="cancellation-reason" class="form-control" required>
                        <option value="">Select a reason...</option>
                        <option value="Personal Emergency">Personal Emergency</option>
                        <option value="Schedule Conflict">Schedule Conflict</option>
                        <option value="Feeling Better">Feeling Better / No Longer Needed</option>
                        <option value="Transportation Issues">Transportation Issues</option>
                        <option value="Financial Concerns">Financial Concerns</option>
                        <option value="Found Alternative Care">Found Alternative Care</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group" id="other-reason-group" style="display: none;">
                    <label for="other-reason">Please specify:</label>
                    <textarea id="other-reason" class="form-control" rows="3" placeholder="Please provide details..."></textarea>
                </div>

                <div class="appointment-info" id="cancel-appointment-info">
                    <!-- Appointment details will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCancelModal()">
                    <i class="fas fa-arrow-left"></i> Keep Appointment
                </button>
                <button type="button" class="btn btn-danger" id="confirm-cancel-btn" onclick="confirmCancellation()">
                    <i class="fas fa-times"></i> Cancel Appointment
                </button>
            </div>
        </div>
    </div>

    <!-- View Appointment Modal -->
    <div id="viewModal" class="modal" style="display: none;">
        <div class="modal-content view-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Appointment Details</h3>
                <div class="header-actions">
                    <button type="button" class="btn-icon" onclick="printAppointment()" title="Print Appointment">
                        <i class="fas fa-print"></i>
                    </button>
                    <button type="button" class="close" onclick="closeViewModal()">&times;</button>
                </div>
            </div>
            <div class="modal-body" id="appointment-details-content">
                <!-- Appointment details will be populated here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" onclick="printAppointment()">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>
    </div>

    <script>
        // Filter functionality for appointments
        function filterAppointments(status, clickedElement) {
            // Remove active class from all tabs
            document.querySelectorAll('#appointments-grid').closest('.section-container').querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Add active class to clicked tab
            if (clickedElement) {
                clickedElement.classList.add('active');
            }

            // Remove any existing no-results messages
            const appointmentsGrid = document.getElementById('appointments-grid');
            const existingNoResults = appointmentsGrid.querySelector('.no-results-message');
            if (existingNoResults) {
                existingNoResults.remove();
            }

            // Show/hide appointment cards based on status
            const appointments = document.querySelectorAll('#appointments-grid .appointment-card');
            let visibleCount = 0;

            appointments.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show no results message if no appointments match the filter
            if (visibleCount === 0 && appointments.length > 0) {
                const noResultsDiv = document.createElement('div');
                noResultsDiv.className = 'no-results-message';
                noResultsDiv.style.gridColumn = '1 / -1';
                noResultsDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No ${status === 'all' ? 'Appointments' : status.charAt(0).toUpperCase() + status.slice(1) + ' Appointments'} Found</h3>
                        <p>No ${status === 'all' ? 'appointments' : status + ' appointments'} are available at this time.</p>
                        <button class="btn btn-secondary" onclick="filterAppointments('all', this.closest('.section-container').querySelector('.filter-tab'))" style="margin-top: 1rem;">
                            <i class="fas fa-list"></i> Show All Appointments
                        </button>
                    </div>
                `;
                appointmentsGrid.appendChild(noResultsDiv);
            }
        }

        // Handle Enter key press in search fields
        function handleSearchKeyPress(event, type) {
            if (event.key === 'Enter') {
                if (type === 'appointment') {
                    filterAppointmentsBySearch();
                } else if (type === 'referral') {
                    filterReferralsBySearch();
                }
            }
        }

        // Advanced filter functionality for appointments
        function filterAppointmentsBySearch() {
            const searchTerm = document.getElementById('appointment-search').value.toLowerCase();
            const dateFrom = document.getElementById('appointment-date-from').value;
            const dateTo = document.getElementById('appointment-date-to').value;
            const statusFilter = document.getElementById('appointment-status-filter').value;

            const appointments = document.querySelectorAll('#appointments-grid .appointment-card');
            const appointmentsGrid = document.getElementById('appointments-grid');
            let visibleCount = 0;

            // Remove existing no-results message
            const existingNoResults = appointmentsGrid.querySelector('.no-results-message');
            if (existingNoResults) {
                existingNoResults.remove();
            }

            appointments.forEach(card => {
                let shouldShow = true;

                // Text search
                if (searchTerm) {
                    const cardText = card.textContent.toLowerCase();
                    if (!cardText.includes(searchTerm)) {
                        shouldShow = false;
                    }
                }

                // Date range filter
                if (dateFrom || dateTo) {
                    const cardDateElement = card.querySelector('[data-appointment-date]');
                    if (cardDateElement) {
                        const cardDate = cardDateElement.getAttribute('data-appointment-date');
                        if (dateFrom && cardDate < dateFrom) shouldShow = false;
                        if (dateTo && cardDate > dateTo) shouldShow = false;
                    }
                }

                // Status filter
                if (statusFilter && card.dataset.status !== statusFilter) {
                    shouldShow = false;
                }

                card.style.display = shouldShow ? 'block' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Show no results message if no appointments match the filter
            if (visibleCount === 0 && appointments.length > 0) {
                const noResultsDiv = document.createElement('div');
                noResultsDiv.className = 'no-results-message';
                noResultsDiv.style.gridColumn = '1 / -1';
                noResultsDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Appointments Found</h3>
                        <p>No appointments match your search criteria. Try adjusting your filters or search terms.</p>
                        <button class="btn btn-secondary" onclick="clearAppointmentFilters()" style="margin-top: 1rem;">
                            <i class="fas fa-undo"></i> Clear Filters
                        </button>
                    </div>
                `;
                appointmentsGrid.appendChild(noResultsDiv);
            }

            // Clear tab selections when using search
            if (searchTerm || dateFrom || dateTo || statusFilter) {
                document.querySelectorAll('#appointments-grid').closest('.section-container').querySelectorAll('.filter-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
            }
        }

        // Clear appointment filters
        function clearAppointmentFilters() {
            document.getElementById('appointment-search').value = '';
            document.getElementById('appointment-date-from').value = '';
            document.getElementById('appointment-date-to').value = '';
            document.getElementById('appointment-status-filter').value = '';

            // Remove no-results message if it exists
            const appointmentsGrid = document.getElementById('appointments-grid');
            const existingNoResults = appointmentsGrid.querySelector('.no-results-message');
            if (existingNoResults) {
                existingNoResults.remove();
            }

            // Show all appointments
            const appointments = document.querySelectorAll('#appointments-grid .appointment-card');
            appointments.forEach(card => {
                card.style.display = 'block';
            });

            // Reset to "All Appointments" tab
            document.querySelectorAll('#appointments-grid').closest('.section-container').querySelectorAll('.filter-tab').forEach((tab, index) => {
                if (index === 0) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }

        // Filter functionality for referrals
        function filterReferrals(status, clickedElement) {
            // Remove active class from all tabs in referrals section
            const referralTabs = document.querySelectorAll('.section-container:last-child .filter-tabs .filter-tab');
            referralTabs.forEach(tab => {
                tab.classList.remove('active');
            });

            // Add active class to clicked tab
            if (clickedElement) {
                clickedElement.classList.add('active');
            }

            // Remove any existing no-results messages
            const referralsGrid = document.getElementById('referrals-grid');
            const existingNoResults = referralsGrid.querySelector('.no-results-message');
            if (existingNoResults) {
                existingNoResults.remove();
            }

            // Show/hide referral cards based on status
            const referrals = document.querySelectorAll('#referrals-grid .referral-card');
            let visibleCount = 0;

            referrals.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show no results message if no referrals match the filter
            if (visibleCount === 0 && referrals.length > 0) {
                const noResultsDiv = document.createElement('div');
                noResultsDiv.className = 'no-results-message';
                noResultsDiv.style.gridColumn = '1 / -1';
                noResultsDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No ${status === 'all' ? 'Referrals' : status.charAt(0).toUpperCase() + status.slice(1) + ' Referrals'} Found</h3>
                        <p>No ${status === 'all' ? 'referrals' : status + ' referrals'} are available at this time.</p>
                        <button class="btn btn-secondary" onclick="filterReferrals('all', this.closest('.section-container').querySelector('.filter-tab'))" style="margin-top: 1rem;">
                            <i class="fas fa-list"></i> Show All Referrals
                        </button>
                    </div>
                `;
                referralsGrid.appendChild(noResultsDiv);
            }
        }

        // Advanced filter functionality for referrals
        function filterReferralsBySearch() {
            const searchTerm = document.getElementById('referral-search').value.toLowerCase();
            const dateFrom = document.getElementById('referral-date-from').value;
            const dateTo = document.getElementById('referral-date-to').value;
            const statusFilter = document.getElementById('referral-status-filter').value;

            const referrals = document.querySelectorAll('#referrals-grid .referral-card');
            const referralsGrid = document.getElementById('referrals-grid');
            let visibleCount = 0;

            // Remove existing no-results message
            const existingNoResults = referralsGrid.querySelector('.no-results-message');
            if (existingNoResults) {
                existingNoResults.remove();
            }

            referrals.forEach(card => {
                let shouldShow = true;

                // Text search
                if (searchTerm) {
                    const cardText = card.textContent.toLowerCase();
                    if (!cardText.includes(searchTerm)) {
                        shouldShow = false;
                    }
                }

                // Date range filter
                if (dateFrom || dateTo) {
                    const cardDateElement = card.querySelector('[data-referral-date]');
                    if (cardDateElement) {
                        const cardDate = cardDateElement.getAttribute('data-referral-date');
                        if (dateFrom && cardDate < dateFrom) shouldShow = false;
                        if (dateTo && cardDate > dateTo) shouldShow = false;
                    }
                }

                // Status filter
                if (statusFilter && card.dataset.status !== statusFilter) {
                    shouldShow = false;
                }

                card.style.display = shouldShow ? 'block' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Show no results message if no referrals match the filter
            if (visibleCount === 0 && referrals.length > 0) {
                const noResultsDiv = document.createElement('div');
                noResultsDiv.className = 'no-results-message';
                noResultsDiv.style.gridColumn = '1 / -1';
                noResultsDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Referrals Found</h3>
                        <p>No referrals match your search criteria. Try adjusting your filters or search terms.</p>
                        <button class="btn btn-secondary" onclick="clearReferralFilters()" style="margin-top: 1rem;">
                            <i class="fas fa-undo"></i> Clear Filters
                        </button>
                    </div>
                `;
                referralsGrid.appendChild(noResultsDiv);
            }

            // Clear tab selections when using search
            if (searchTerm || dateFrom || dateTo || statusFilter) {
                document.querySelectorAll('.section-container:last-child .filter-tabs .filter-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
            }
        }

        // Clear referral filters
        function clearReferralFilters() {
            document.getElementById('referral-search').value = '';
            document.getElementById('referral-date-from').value = '';
            document.getElementById('referral-date-to').value = '';
            document.getElementById('referral-status-filter').value = '';

            // Remove no-results message if it exists
            const referralsGrid = document.getElementById('referrals-grid');
            const existingNoResults = referralsGrid.querySelector('.no-results-message');
            if (existingNoResults) {
                existingNoResults.remove();
            }

            // Show all referrals
            const referrals = document.querySelectorAll('#referrals-grid .referral-card');
            referrals.forEach(card => {
                card.style.display = 'block';
            });

            // Reset to "All Referrals" tab
            document.querySelectorAll('.section-container:last-child .filter-tabs .filter-tab').forEach((tab, index) => {
                if (index === 0) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }

        // View appointment details
        function viewAppointmentDetails(appointmentId) {
            // Find appointment data
            const appointments = <?php echo json_encode($appointments); ?>;
            const appointment = appointments.find(app => app.appointment_id == appointmentId);

            if (!appointment) {
                alert('Appointment not found.');
                return;
            }

            // Populate modal with appointment details
            populateAppointmentDetails(appointment);

            // Show modal
            document.getElementById('viewModal').style.display = 'block';
        }

        function populateAppointmentDetails(appointment) {
            const content = document.getElementById('appointment-details-content');
            const appointmentDate = new Date(appointment.scheduled_date);
            const appointmentTime = new Date('1970-01-01T' + appointment.scheduled_time);
            const createdDate = new Date(appointment.created_at);
            const appointmentId = 'APT-' + String(appointment.appointment_id).padStart(8, '0');
            const patientInfo = <?php echo json_encode($patient_info); ?>;

            // Determine status styling
            const statusClass = {
                'confirmed': 'status-confirmed',
                'completed': 'status-completed',
                'cancelled': 'status-cancelled'
            } [appointment.status] || 'status-pending';

            content.innerHTML = `
                <div class="appointment-details-container" id="printable-content">
                    <!-- Header Section -->
                    <div class="details-header">
                        <div class="clinic-info">
                            <h2><i class="fas fa-hospital"></i> City Health Office of Koronadal</h2>
                            <p>Patient Appointment Record</p>
                        </div>
                        <div class="appointment-id-section">
                            <div class="appointment-id">${appointmentId}</div>
                            <div class="status-badge ${statusClass}">
                                ${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Patient Information -->
                    <div class="info-section">
                        <h3><i class="fas fa-user"></i> Patient Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Full Name:</label>
                                <span>${patientInfo.first_name} ${patientInfo.middle_name || ''} ${patientInfo.last_name}</span>
                            </div>
                            <div class="info-item">
                                <label>Patient ID:</label>
                                <span>${patientInfo.username}</span>
                            </div>
                            <div class="info-item">
                                <label>Contact Number:</label>
                                <span>${patientInfo.contact_num || 'Not provided'}</span>
                            </div>
                            <div class="info-item">
                                <label>Address:</label>
                                <span>${patientInfo.barangay_name || 'Not specified'}</span>
                            </div>
                            ${patientInfo.priority_level === 1 ? `
                            <div class="info-item priority-info">
                                <label>Priority Status:</label>
                                <span class="priority-badge">
                                    <i class="fas fa-star"></i> Priority Patient
                                </span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Appointment Details -->
                    <div class="info-section">
                        <h3><i class="fas fa-calendar-check"></i> Appointment Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Healthcare Facility:</label>
                                <span>${appointment.facility_name}</span>
                            </div>
                            <div class="info-item">
                                <label>Service Type:</label>
                                <span>${appointment.service_name}</span>
                            </div>
                            <div class="info-item">
                                <label>Appointment Date:</label>
                                <span>${appointmentDate.toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })}</span>
                            </div>
                            <div class="info-item">
                                <label>Appointment Time:</label>
                                <span>${appointmentTime.toLocaleTimeString('en-US', {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                    hour12: true
                                })}</span>
                            </div>
                            <div class="info-item">
                                <label>Booking Date:</label>
                                <span>${createdDate.toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })}</span>
                            </div>
                            <div class="info-item">
                                <label>Status:</label>
                                <span class="status-badge ${statusClass}">${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span>
                            </div>
                        </div>
                    </div>
                    
                    ${appointment.referral_num ? `
                    <!-- Referral Information -->
                    <div class="info-section">
                        <h3><i class="fas fa-file-medical"></i> Referral Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Referral Number:</label>
                                <span>#${appointment.referral_num}</span>
                            </div>
                            <div class="info-item full-width">
                                <label>Referral Reason:</label>
                                <span>${appointment.referral_reason || 'Not specified'}</span>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${appointment.status === 'cancelled' && appointment.cancellation_reason ? `
                    <!-- Cancellation Information -->
                    <div class="info-section cancellation-section">
                        <h3><i class="fas fa-times-circle"></i> Cancellation Information</h3>
                        <div class="info-grid">
                            <div class="info-item full-width">
                                <label>Cancellation Reason:</label>
                                <span>${appointment.cancellation_reason}</span>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- Important Notes -->
                    <div class="info-section notes-section">
                        <h3><i class="fas fa-info-circle"></i> Important Reminders</h3>
                        <ul class="notes-list">
                            <li><i class="fas fa-id-card"></i> Bring a valid government-issued ID</li>
                            <li><i class="fas fa-clock"></i> Arrive 15 minutes before your appointment time</li>
                            ${appointment.referral_num ? '<li><i class="fas fa-file-medical"></i> Present your referral document at the facility</li>' : ''}
                            <li><i class="fas fa-phone"></i> Contact the facility if you need to reschedule</li>
                            <li><i class="fas fa-mask"></i> Follow health protocols as required</li>
                        </ul>
                    </div>
                    
                    <!-- Footer -->
                    <div class="details-footer">
                        <div class="print-timestamp">
                            <small>Generated on: ${new Date().toLocaleString('en-US', {
                                year: 'numeric',
                                month: 'long', 
                                day: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            })}</small>
                        </div>
                        <div class="contact-info">
                            <small>For inquiries, contact City Health Office of Koronadal</small>
                        </div>
                    </div>
                </div>
            `;
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function printAppointment() {
            const printContent = document.getElementById('printable-content').innerHTML;
            const originalContent = document.body.innerHTML;

            // Create simple print styles
            const printStyles = `
                <style>
                    @media print {
                        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
                        .details-header { border-bottom: 2px solid #0077b6; margin-bottom: 20px; padding-bottom: 15px; }
                        .clinic-info h2 { color: #0077b6; margin: 0; font-size: 18px; }
                        .appointment-id { font-size: 16px; font-weight: bold; color: #0077b6; }
                        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; }
                        .info-section { margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; }
                        .info-section h3 { color: #0077b6; margin: 0 0 10px 0; font-size: 14px; }
                        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
                        .info-item label { font-weight: bold; font-size: 12px; }
                        .info-item span { font-size: 12px; }
                        .notes-list { list-style: none; padding: 0; }
                        .notes-list li { margin-bottom: 5px; font-size: 12px; }
                    }
                </style>
            `;

            document.body.innerHTML = printStyles + '<div>' + printContent + '</div>';
            window.print();
            document.body.innerHTML = originalContent;

            // Refresh page to restore functionality
            setTimeout(() => {
                location.reload();
            }, 100);
        }

        // Cancel appointment functionality
        let currentCancelAppointmentId = null;

        function showCancelModal(appointmentId, appointmentNumber) {
            currentCancelAppointmentId = appointmentId;

            // Update appointment info in modal
            const appointmentInfo = document.getElementById('cancel-appointment-info');
            appointmentInfo.innerHTML = `
                <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-top: 1rem;">
                    <h4 style="margin: 0 0 0.5rem 0; color: #0077b6;">Appointment Details</h4>
                    <p style="margin: 0; font-weight: 600;">Appointment ID: ${appointmentNumber}</p>
                </div>
            `;

            // Reset form
            document.getElementById('cancellation-reason').value = '';
            document.getElementById('other-reason').value = '';
            document.getElementById('other-reason-group').style.display = 'none';

            // Show modal
            document.getElementById('cancelModal').style.display = 'block';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
            currentCancelAppointmentId = null;
        }

        function confirmCancellation() {
            const reason = document.getElementById('cancellation-reason').value;
            const otherReason = document.getElementById('other-reason').value;

            if (!reason) {
                alert('Please select a reason for cancellation.');
                return;
            }

            if (reason === 'Other' && !otherReason.trim()) {
                alert('Please specify the reason for cancellation.');
                return;
            }

            const finalReason = reason === 'Other' ? otherReason.trim() : reason;

            // Disable button to prevent double submission
            const confirmBtn = document.getElementById('confirm-cancel-btn');
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';

            // Send cancellation request
            fetch('cancel_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        appointment_id: currentCancelAppointmentId,
                        cancellation_reason: finalReason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Appointment cancelled successfully.');
                        location.reload(); // Refresh the page to show updated status
                    } else {
                        alert('Error: ' + (data.message || 'Failed to cancel appointment'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the appointment.');
                })
                .finally(() => {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Appointment';
                    closeCancelModal();
                });
        }

        // Legacy function for backward compatibility
        function cancelAppointment(appointmentId) {
            showCancelModal(appointmentId, 'APT-' + appointmentId.toString().padStart(8, '0'));
        }

        // View referral details
        function viewReferralDetails(referralId) {
            alert('View referral details for ID: ' + referralId + '\n\nThis will show complete referral information including validity and usage status.');
            // TODO: Implement detailed view modal or page
        }

        // Book appointment from referral
        function bookFromReferral(referralId) {
            window.location.href = 'book_appointment.php?referral_id=' + referralId;
        }

        // Download Patient ID Card
        function downloadPatientID() {
            alert('Download Patient ID Card functionality will be implemented here.\n\nThis will generate a PDF with your patient ID card.');
            // TODO: Implement PDF generation
        }

        // Open User Settings
        function openUserSettings() {
            // Redirect to profile/settings page
            window.location.href = '../profile/edit_profile.php';
        }

        // Auto-refresh appointments every 5 minutes to show real-time updates
        setInterval(function() {
            // Only refresh if user is still on the page
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000); // 5 minutes

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set default active filter for appointments
            const appointmentTabs = document.querySelectorAll('.section-container:first-of-type .filter-tab');
            if (appointmentTabs.length > 0) {
                appointmentTabs[0].classList.add('active');
            }

            // Set default active filter for referrals
            const referralTabs = document.querySelectorAll('.section-container:last-child .filter-tab');
            if (referralTabs.length > 0) {
                referralTabs[0].classList.add('active');
            }

            // Setup cancel modal event listeners
            const reasonSelect = document.getElementById('cancellation-reason');
            if (reasonSelect) {
                reasonSelect.addEventListener('change', function() {
                    const otherGroup = document.getElementById('other-reason-group');
                    if (this.value === 'Other') {
                        otherGroup.style.display = 'block';
                    } else {
                        otherGroup.style.display = 'none';
                    }
                });
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('cancelModal');
                if (event.target === modal) {
                    closeCancelModal();
                }
            };

            // Add smooth transitions
            document.querySelectorAll('.appointment-card, .referral-card').forEach(card => {
                card.style.transition = 'all 0.3s ease';
            });
        });
    </script>

    <!-- Alert Styles -->
    <style>
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: #d1f2eb;
            color: #0d5e3d;
            border: 1px solid #7fb069;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f1b2b7;
        }

        .alert i {
            font-size: 1.1rem;
        }
    </style>

</body>

</html>