<?php
// Include patient session configuration FIRST - before any output
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// If user is not logged in, redirect to login
if (!isset($_SESSION['patient_id'])) {
    header('Location: ../patient/auth/patient_login.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Include automatic status updater
require_once $root_path . '/utils/automatic_status_updater.php';

$patient_id = $_SESSION['patient_id'];
$message = '';
$error = '';

// Run automatic status updates when page loads
try {
    $status_updater = new AutomaticStatusUpdater($conn);
    $update_result = $status_updater->runAllUpdates();
    
    // Optional: Show update message to user (you can remove this if you don't want to show it)
    if ($update_result['success'] && $update_result['total_updates'] > 0) {
        $message = "Status updates applied: " . $update_result['total_updates'] . " records updated automatically.";
    }
} catch (Exception $e) {
    // Log error but don't show to user to avoid confusion
    error_log("Failed to run automatic status updates: " . $e->getMessage());
}

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

// Fetch appointments (limit to recent 30 for better overview)
$appointments = [];
try {
    $stmt = $conn->prepare("
        SELECT a.*, 
               s.name as service_name, s.description as service_description,
               d.first_name as doctor_first_name, d.last_name as doctor_last_name,
               e.first_name as employee_first_name, e.last_name as employee_last_name,
               f.name as facility_name
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN employees d ON a.doctor_id = d.employee_id
        LEFT JOIN employees e ON a.employee_id = e.employee_id
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 30
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch appointments: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Appointments - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
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
            border: none;
            border-radius: 8px;
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
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .btn-primary i {
            margin-right: 8px;
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

        .appointment-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.8rem;
            transition: all 0.3s ease;
            position: relative;
            min-height: 320px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            box-sizing: content-box;
        }

        .appointment-card:hover {
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

        .status-scheduled {
            background: #d4edda;
            color: #155724;
        }

        .status-confirmed {
            background: #cce7ff;
            color: #004085;
        }

        .status-completed {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-checked_in {
            background: #d1ecf1;
            color: #0c5460;
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

        .btn-outline-success {
            border-color: #28a745;
            color: #28a745;
        }

        .btn-outline-success:hover {
            background: #28a745;
            color: white;
        }

        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
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

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .action-buttons {
                width: 100%;
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
                grid-column: 1;
            }

            .btn-filter {
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'appointments';
    include '../../includes/sidebar_patient.php';
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="../patient/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span style="color: #0077b6; font-weight: 600;">My Appointments</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-calendar-check" style="margin-right: 0.5rem;"></i>My Appointments</h1>
            <div class="action-buttons">
                <a href="book_appointment.php" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i>
                    <span class="hide-on-mobile">Book New Appointment</span>
                </a>
                <button class="btn btn-secondary" onclick="downloadAppointmentHistory()">
                    <i class="fas fa-download"></i>
                    <span class="hide-on-mobile">Download History</span>
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Appointments Section -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h2 class="section-title">Appointment History</h2>
                <div style="margin-left: auto; color: #6c757d; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> Showing recent 30 appointments
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="appointment-search">Search Appointments</label>
                        <input type="text" id="appointment-search" placeholder="Search by service, doctor, or appointment ID..." 
                               onkeypress="handleSearchKeyPress(event, 'appointment')">
                    </div>
                    <div class="filter-group">
                        <label for="appointment-date-from">From Date</label>
                        <input type="date" id="appointment-date-from">
                    </div>
                    <div class="filter-group">
                        <label for="appointment-date-to">To Date</label>
                        <input type="date" id="appointment-date-to">
                    </div>
                    <div class="filter-group">
                        <label for="appointment-status-filter">Status</label>
                        <select id="appointment-status-filter">
                            <option value="">All Statuses</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="checked_in">Checked In</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button class="btn-filter btn-filter-primary" onclick="filterAppointmentsBySearch()">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <button class="btn-filter btn-filter-secondary" onclick="clearAppointmentFilters()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" onclick="filterAppointments('all', this)">
                    <i class="fas fa-list"></i> All Appointments
                </div>
                <div class="filter-tab" onclick="filterAppointments('scheduled', this)">
                    <i class="fas fa-calendar-alt"></i> Scheduled
                </div>
                <div class="filter-tab" onclick="filterAppointments('confirmed', this)">
                    <i class="fas fa-check-circle"></i> Confirmed
                </div>
                <div class="filter-tab" onclick="filterAppointments('completed', this)">
                    <i class="fas fa-check-double"></i> Completed
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
                        <p>You haven't booked any appointments yet.</p>
                        <a href="book_appointment.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book Your First Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card" data-status="<?= htmlspecialchars($appointment['status']) ?>" 
                             data-date="<?= htmlspecialchars($appointment['appointment_date']) ?>" 
                             data-search-text="<?= htmlspecialchars(strtolower(($appointment['service_name'] ?? '') . ' ' . ($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? '') . ' ' . ($appointment['appointment_id'] ?? ''))) ?>">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?= htmlspecialchars($appointment['service_name'] ?? 'General Consultation') ?>
                                </h3>
                                <span class="status-badge status-<?= htmlspecialchars($appointment['status']) ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $appointment['status']))) ?>
                                </span>
                            </div>

                            <div class="card-info">
                                <div class="info-row">
                                    <i class="fas fa-id-card"></i>
                                    <strong>ID:</strong>
                                    <span class="value"><?= htmlspecialchars($appointment['appointment_id']) ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <i class="fas fa-calendar"></i>
                                    <strong>Date:</strong>
                                    <span class="value"><?= date('M j, Y', strtotime($appointment['appointment_date'])) ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <i class="fas fa-clock"></i>
                                    <strong>Time:</strong>
                                    <span class="value"><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></span>
                                </div>
                                
                                <?php if (!empty($appointment['doctor_first_name']) || !empty($appointment['doctor_last_name'])): ?>
                                <div class="info-row">
                                    <i class="fas fa-user-md"></i>
                                    <strong>Doctor:</strong>
                                    <span class="value">Dr. <?= htmlspecialchars(trim($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name'])) ?></span>
                                </div>
                                <?php elseif (!empty($appointment['employee_first_name']) || !empty($appointment['employee_last_name'])): ?>
                                <div class="info-row">
                                    <i class="fas fa-user"></i>
                                    <strong>Staff:</strong>
                                    <span class="value"><?= htmlspecialchars(trim($appointment['employee_first_name'] . ' ' . $appointment['employee_last_name'])) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($appointment['facility_name'])): ?>
                                <div class="info-row">
                                    <i class="fas fa-hospital"></i>
                                    <strong>Facility:</strong>
                                    <span class="value"><?= htmlspecialchars($appointment['facility_name']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($appointment['notes'])): ?>
                                <div class="info-row">
                                    <i class="fas fa-sticky-note"></i>
                                    <strong>Notes:</strong>
                                    <span class="value"><?= htmlspecialchars($appointment['notes']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-actions">
                                <button class="btn btn-outline btn-outline-primary btn-sm" 
                                        onclick="viewAppointmentDetails(<?= $appointment['appointment_id'] ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                
                                <?php if (in_array($appointment['status'], ['scheduled', 'confirmed'])): ?>
                                    <button class="btn btn-outline btn-outline-success btn-sm" 
                                            onclick="rescheduleAppointment(<?= $appointment['appointment_id'] ?>)">
                                        <i class="fas fa-calendar-alt"></i> Reschedule
                                    </button>
                                    <button class="btn btn-outline btn-outline-danger btn-sm" 
                                            onclick="cancelAppointment(<?= $appointment['appointment_id'] ?>)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($appointment['status'] === 'completed'): ?>
                                    <button class="btn btn-outline btn-outline-secondary btn-sm" 
                                            onclick="downloadAppointmentReport(<?= $appointment['appointment_id'] ?>)">
                                        <i class="fas fa-download"></i> Download Report
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        // Filter functionality for appointments
        function filterAppointments(status, clickedElement) {
            // Remove active class from all tabs
            const appointmentTabs = document.querySelectorAll('.filter-tab');
            appointmentTabs.forEach(tab => {
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
                const cardStatus = card.getAttribute('data-status');
                const shouldShow = status === 'all' || cardStatus === status;
                
                card.style.display = shouldShow ? 'block' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Show no results message if no appointments match the filter
            if (visibleCount === 0 && appointments.length > 0) {
                const noResultsDiv = document.createElement('div');
                noResultsDiv.className = 'no-results-message';
                noResultsDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No ${status === 'all' ? '' : status.charAt(0).toUpperCase() + status.slice(1)} Appointments Found</h3>
                        <p>Try selecting a different status filter or adjust your search criteria.</p>
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
                const cardSearchText = card.getAttribute('data-search-text');
                const cardStatus = card.getAttribute('data-status');
                const cardDate = card.getAttribute('data-date');

                let shouldShow = true;

                // Text search
                if (searchTerm && !cardSearchText.includes(searchTerm)) {
                    shouldShow = false;
                }

                // Status filter
                if (statusFilter && cardStatus !== statusFilter) {
                    shouldShow = false;
                }

                // Date range filter
                if (dateFrom && cardDate < dateFrom) {
                    shouldShow = false;
                }
                if (dateTo && cardDate > dateTo) {
                    shouldShow = false;
                }

                card.style.display = shouldShow ? 'block' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Show no results message if no appointments match the filter
            if (visibleCount === 0 && appointments.length > 0) {
                const noResultsDiv = document.createElement('div');
                noResultsDiv.className = 'no-results-message';
                noResultsDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Appointments Match Your Search</h3>
                        <p>Try adjusting your search criteria or clearing the filters.</p>
                    </div>
                `;
                appointmentsGrid.appendChild(noResultsDiv);
            }

            // Clear tab selections when using search
            if (searchTerm || dateFrom || dateTo || statusFilter) {
                const appointmentTabs = document.querySelectorAll('.filter-tab');
                appointmentTabs.forEach(tab => {
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
            const appointmentTabs = document.querySelectorAll('.filter-tab');
            appointmentTabs.forEach((tab, index) => {
                if (index === 0) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }

        // View appointment details
        function viewAppointmentDetails(appointmentId) {
            // TODO: Implement detailed view modal or page
            alert('View appointment details for ID: ' + appointmentId + '\n\nThis will show complete appointment information including medical records and visit history.');
        }

        // Reschedule appointment
        function rescheduleAppointment(appointmentId) {
            window.location.href = 'reschedule_appointment.php?id=' + appointmentId;
        }

        // Cancel appointment
        function cancelAppointment(appointmentId) {
            if (confirm('Are you sure you want to cancel this appointment?')) {
                // TODO: Implement cancel functionality
                alert('Cancel appointment functionality will be implemented here for appointment ID: ' + appointmentId);
            }
        }

        // Download appointment history
        function downloadAppointmentHistory() {
            // TODO: Implement download functionality
            alert('Download appointment history functionality will be implemented here.');
        }

        // Download appointment report
        function downloadAppointmentReport(appointmentId) {
            // TODO: Implement download functionality
            alert('Download appointment report for ID: ' + appointmentId + '\n\nThis will generate a PDF report with appointment details and medical records.');
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set default date range to last 3 months
            const today = new Date();
            const threeMonthsAgo = new Date();
            threeMonthsAgo.setMonth(today.getMonth() - 3);
            
            // Format dates for input fields
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };
            
            // Uncomment these if you want to set default date range
            // document.getElementById('appointment-date-from').value = formatDate(threeMonthsAgo);
            // document.getElementById('appointment-date-to').value = formatDate(today);
        });
    </script>

    <!-- Alert Styles -->
    <style>
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .alert i {
            font-size: 1.1rem;
        }
    </style>

</body>
</html>