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

// Fetch prescriptions (limit to recent 50 for better overview)
$prescriptions = [];
try {
    $stmt = $conn->prepare("
        SELECT p.*,
               e.first_name as doctor_first_name, e.last_name as doctor_last_name,
               a.scheduled_date, a.scheduled_time,
               COUNT(pm.prescribed_medication_id) as medication_count
        FROM prescriptions p
        LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
        LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
        LEFT JOIN prescribed_medications pm ON p.prescription_id = pm.prescription_id
        WHERE p.patient_id = ?
        GROUP BY p.prescription_id
        ORDER BY p.prescription_date DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $prescriptions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch prescriptions: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Prescriptions - CHO Koronadal</title>
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

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .prescription-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .prescription-table thead {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .prescription-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border: none;
        }

        .prescription-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: top;
        }

        .prescription-table tbody tr {
            transition: all 0.2s ease;
        }

        .prescription-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .prescription-table tbody tr:last-child td {
            border-bottom: none;
        }

        .date-info {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .date-info strong {
            color: #0077b6;
            font-weight: 600;
        }

        .date-info small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .doctor-info {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .doctor-info strong {
            color: #495057;
            font-weight: 600;
        }

        .doctor-info small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .text-muted {
            color: #6c757d !important;
            font-style: italic;
        }

        .medication-info {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .medication-info strong {
            color: #0077b6;
            font-weight: 600;
        }

        .medication-info small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .action-buttons-cell {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .empty-row td {
            padding: 3rem 1rem;
            text-align: center;
            border: none;
        }

        .empty-row .empty-state {
            color: #6c757d;
        }

        .empty-row .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-row .empty-state h3 {
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .empty-row .empty-state p {
            color: #6c757d;
            margin: 0;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-dispensed {
            background: #cce7ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
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

        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
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

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                justify-content: center;
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

            .table-container {
                overflow-x: auto;
            }

            .prescription-table {
                min-width: 600px;
            }

            .action-buttons-cell {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'prescriptions';
    include '../../../includes/sidebar_patient.php';
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span style="color: #0077b6; font-weight: 600;">My Prescriptions</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-prescription-bottle-alt" style="margin-right: 0.5rem;"></i>My Prescriptions</h1>
            <div class="action-buttons">
                <a href="../appointment/appointments.php" class="btn btn-secondary">
                    <i class="fas fa-calendar-check"></i>
                    <span class="hide-on-mobile">View Appointments</span>
                </a>
                <button class="btn btn-primary" onclick="downloadPrescriptionHistory()">
                    <i class="fas fa-download"></i>
                    <span class="hide-on-mobile">Download History</span>
                </button>
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

        <!-- Prescriptions Section -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-prescription-bottle-alt"></i>
                </div>
                <h2 class="section-title">Prescription History</h2>
                <div style="margin-left: auto; color: #6c757d; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> Showing recent 50 prescriptions
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="prescription-search">Search Prescriptions</label>
                        <input type="text" id="prescription-search" placeholder="Search by doctor, medication, or prescription ID..." 
                               onkeypress="handleSearchKeyPress(event, 'prescription')">
                    </div>
                    <div class="filter-group">
                        <label for="prescription-date-from">Date From</label>
                        <input type="date" id="prescription-date-from">
                    </div>
                    <div class="filter-group">
                        <label for="prescription-date-to">Date To</label>
                        <input type="date" id="prescription-date-to">
                    </div>
                    <div class="filter-group">
                        <label for="prescription-status-filter">Status</label>
                        <select id="prescription-status-filter">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="dispensed">Dispensed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="filter-actions">
                            <button type="button" class="btn-filter btn-filter-primary" onclick="filterPrescriptionsBySearch()">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button type="button" class="btn-filter btn-filter-secondary" onclick="clearPrescriptionFilters()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" onclick="filterPrescriptions('all', this)">
                    <i class="fas fa-list"></i> All Prescriptions
                </div>
                <div class="filter-tab" onclick="filterPrescriptions('active', this)">
                    <i class="fas fa-check-circle"></i> Active
                </div>
                <div class="filter-tab" onclick="filterPrescriptions('dispensed', this)">
                    <i class="fas fa-pills"></i> Dispensed
                </div>
                <div class="filter-tab" onclick="filterPrescriptions('cancelled', this)">
                    <i class="fas fa-times-circle"></i> Cancelled
                </div>
            </div>

            <!-- Prescriptions Table -->
            <div class="table-container">
                <table class="prescription-table" id="prescriptions-table">
                    <thead>
                        <tr>
                            <th>Prescription Date</th>
                            <th>Prescribed By</th>
                            <th>Medications</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="prescriptions-tbody">
                        <?php if (empty($prescriptions)): ?>
                            <tr class="empty-row">
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-prescription-bottle-alt"></i>
                                    <h3>No Prescriptions Found</h3>
                                    <p>You don't have any prescriptions yet. Prescriptions will appear here when doctors prescribe medications for you.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prescriptions as $prescription): ?>
                                <tr class="prescription-row" data-status="<?php echo htmlspecialchars($prescription['status']); ?>" data-prescription-date="<?php echo htmlspecialchars($prescription['prescription_date']); ?>">
                                    <td>
                                        <div class="date-info">
                                            <strong><?php echo date('M j, Y', strtotime($prescription['prescription_date'])); ?></strong>
                                            <small><?php echo date('g:i A', strtotime($prescription['prescription_date'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="doctor-info">
                                            <?php if (!empty($prescription['doctor_first_name'])): ?>
                                                <strong>Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">Unknown Doctor</span>
                                            <?php endif; ?>
                                            <?php if (!empty($prescription['appointment_date'])): ?>
                                                <small>Appointment: <?php echo date('M j, Y', strtotime($prescription['appointment_date'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="medication-info">
                                            <strong><?php echo htmlspecialchars($prescription['medication_count']); ?> Medication(s)</strong>
                                            <?php if (!empty($prescription['remarks'])): ?>
                                                <small><?php echo htmlspecialchars(substr($prescription['remarks'], 0, 50)) . (strlen($prescription['remarks']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($prescription['status']); ?>">
                                            <?php echo htmlspecialchars(strtoupper($prescription['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons-cell">
                                            <button type="button" class="btn btn-outline btn-outline-primary btn-sm" onclick="viewPrescriptionDetails(<?php echo $prescription['prescription_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-outline btn-outline-secondary btn-sm" onclick="printPrescription(<?php echo $prescription['prescription_id']; ?>)">
                                                <i class="fas fa-print"></i> Print
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Prescription Details Modal -->
    <div id="prescriptionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-prescription-bottle-alt"></i> Prescription Details</h2>
                <span class="close" onclick="closePrescriptionModal()">&times;</span>
            </div>
            <div class="modal-body" id="prescriptionModalBody">
                <!-- Content will be loaded dynamically -->
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading prescription details...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePrescriptionModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="printPrescriptionBtn" onclick="printCurrentPrescription()">
                    <i class="fas fa-print"></i> Print Prescription
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentPrescriptionId = null;

        // Filter functionality for prescriptions
        function filterPrescriptions(status, clickedElement) {
            // Remove active class from all tabs
            const prescriptionTabs = document.querySelectorAll('.filter-tab');
            prescriptionTabs.forEach(tab => {
                tab.classList.remove('active');
            });

            // Add active class to clicked tab
            if (clickedElement) {
                clickedElement.classList.add('active');
            }

            // Show/hide prescription rows based on status
            const prescriptions = document.querySelectorAll('#prescriptions-tbody .prescription-row');
            let visibleCount = 0;

            prescriptions.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = 'table-row';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Handle empty state
            const emptyRow = document.querySelector('#prescriptions-tbody .empty-row');
            if (emptyRow) {
                emptyRow.style.display = visibleCount === 0 && prescriptions.length > 0 ? 'table-row' : 'none';
            }

            // Show no results message if no prescriptions match the filter
            if (visibleCount === 0 && prescriptions.length > 0) {
                showNoResultsMessage('filter');
            } else {
                hideNoResultsMessage();
            }
        }

        // Handle Enter key press in search fields
        function handleSearchKeyPress(event, type) {
            if (event.key === 'Enter') {
                if (type === 'prescription') {
                    filterPrescriptionsBySearch();
                }
            }
        }

        // Advanced filter functionality for prescriptions
        function filterPrescriptionsBySearch() {
            const searchTerm = document.getElementById('prescription-search').value.toLowerCase();
            const dateFrom = document.getElementById('prescription-date-from').value;
            const dateTo = document.getElementById('prescription-date-to').value;
            const statusFilter = document.getElementById('prescription-status-filter').value;

            const prescriptions = document.querySelectorAll('#prescriptions-tbody .prescription-row');
            let visibleCount = 0;

            prescriptions.forEach(row => {
                let shouldShow = true;

                // Text search
                if (searchTerm) {
                    const rowText = row.textContent.toLowerCase();
                    if (!rowText.includes(searchTerm)) {
                        shouldShow = false;
                    }
                }

                // Date range filter
                if (dateFrom || dateTo) {
                    const rowDate = row.dataset.prescriptionDate;
                    if (rowDate) {
                        const prescriptionDate = new Date(rowDate).toISOString().split('T')[0];
                        if (dateFrom && prescriptionDate < dateFrom) shouldShow = false;
                        if (dateTo && prescriptionDate > dateTo) shouldShow = false;
                    }
                }

                // Status filter
                if (statusFilter && row.dataset.status !== statusFilter) {
                    shouldShow = false;
                }

                row.style.display = shouldShow ? 'table-row' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Show no results message if no prescriptions match the filter
            if (visibleCount === 0 && prescriptions.length > 0) {
                showNoResultsMessage('search');
            } else {
                hideNoResultsMessage();
            }

            // Clear tab selections when using search
            if (searchTerm || dateFrom || dateTo || statusFilter) {
                const prescriptionTabs = document.querySelectorAll('.filter-tab');
                prescriptionTabs.forEach(tab => {
                    tab.classList.remove('active');
                });
            }
        }

        // Clear prescription filters
        function clearPrescriptionFilters() {
            document.getElementById('prescription-search').value = '';
            document.getElementById('prescription-date-from').value = '';
            document.getElementById('prescription-date-to').value = '';
            document.getElementById('prescription-status-filter').value = '';

            hideNoResultsMessage();

            // Show all prescriptions
            const prescriptions = document.querySelectorAll('#prescriptions-tbody .prescription-row');
            prescriptions.forEach(row => {
                row.style.display = 'table-row';
            });

            // Reset to "All Prescriptions" tab
            const prescriptionTabs = document.querySelectorAll('.filter-tab');
            prescriptionTabs.forEach((tab, index) => {
                if (index === 0) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }

        // Show no results message
        function showNoResultsMessage(type) {
            hideNoResultsMessage(); // Remove existing message first
            
            const tbody = document.getElementById('prescriptions-tbody');
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="5" class="empty-state">
                    <i class="fas fa-${type === 'search' ? 'search' : 'filter'}"></i>
                    <h3>No matching prescriptions found</h3>
                    <p>${type === 'search' ? 'No prescriptions match your search criteria. Try adjusting your filters.' : 'No prescriptions match the selected filter. Try selecting a different status.'}</p>
                    <button type="button" class="btn btn-outline-secondary" onclick="clearPrescriptionFilters()">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </td>
            `;
            tbody.appendChild(noResultsRow);
        }

        // Hide no results message
        function hideNoResultsMessage() {
            const noResultsRow = document.querySelector('.no-results-row');
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }

        // View prescription details
        function viewPrescriptionDetails(prescriptionId) {
            currentPrescriptionId = prescriptionId;
            const modal = document.getElementById('prescriptionModal');
            const modalBody = document.getElementById('prescriptionModalBody');
            
            // Show loading state
            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading prescription details...</div>';
            modal.style.display = 'block';
            
            // Fetch prescription details via AJAX
            fetch(`get_prescription_details.php?id=${prescriptionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPrescriptionDetails(data.prescription, data.medications);
                    } else {
                        modalBody.innerHTML = `
                            <div class="error-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3>Error Loading Prescription</h3>
                                <p>${data.message || 'Failed to load prescription details.'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = `
                        <div class="error-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>Error Loading Prescription</h3>
                            <p>Failed to load prescription details. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Display prescription details in modal
        function displayPrescriptionDetails(prescription, medications) {
            const modalBody = document.getElementById('prescriptionModalBody');
            
            const statusClass = prescription.status;
            const statusText = prescription.status.toUpperCase();
            
            modalBody.innerHTML = `
                <div class="prescription-details">
                    <div class="prescription-header">
                        <div class="prescription-info">
                            <h3>Prescription #${prescription.prescription_id}</h3>
                            <span class="status-badge status-${statusClass}">${statusText}</span>
                        </div>
                        <div class="prescription-meta">
                            <p><strong>Date:</strong> ${new Date(prescription.prescription_date).toLocaleDateString('en-US', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })}</p>
                            <p><strong>Prescribed by:</strong> ${prescription.doctor_name || 'Unknown Doctor'}</p>
                            ${prescription.appointment_date ? `<p><strong>Appointment:</strong> ${new Date(prescription.appointment_date).toLocaleDateString()}</p>` : ''}
                        </div>
                    </div>
                    
                    ${prescription.remarks ? `
                        <div class="prescription-remarks">
                            <h4><i class="fas fa-notes-medical"></i> Remarks</h4>
                            <p>${prescription.remarks}</p>
                        </div>
                    ` : ''}
                    
                    <div class="medications-section">
                        <h4><i class="fas fa-pills"></i> Prescribed Medications</h4>
                        <div class="medications-list">
                            ${medications.map(med => `
                                <div class="medication-item">
                                    <div class="medication-header">
                                        <h5>${med.medication_name}</h5>
                                        <span class="medication-status status-${med.status}">${med.status.toUpperCase()}</span>
                                    </div>
                                    <div class="medication-details">
                                        <div class="detail-row">
                                            <span class="label">Dosage:</span>
                                            <span class="value">${med.dosage}</span>
                                        </div>
                                        ${med.frequency ? `
                                            <div class="detail-row">
                                                <span class="label">Frequency:</span>
                                                <span class="value">${med.frequency}</span>
                                            </div>
                                        ` : ''}
                                        ${med.duration ? `
                                            <div class="detail-row">
                                                <span class="label">Duration:</span>
                                                <span class="value">${med.duration}</span>
                                            </div>
                                        ` : ''}
                                        ${med.instructions ? `
                                            <div class="detail-row">
                                                <span class="label">Instructions:</span>
                                                <span class="value">${med.instructions}</span>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
        }

        // Close prescription modal
        function closePrescriptionModal() {
            document.getElementById('prescriptionModal').style.display = 'none';
            currentPrescriptionId = null;
        }

        // Print prescription
        function printPrescription(prescriptionId) {
            window.open(`print_prescription.php?id=${prescriptionId}`, '_blank');
        }

        // Print current prescription (from modal)
        function printCurrentPrescription() {
            if (currentPrescriptionId) {
                printPrescription(currentPrescriptionId);
            }
        }

        // Download prescription history
        function downloadPrescriptionHistory() {
            window.location.href = 'download_prescription_history.php';
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set default active filter
            const prescriptionTabs = document.querySelectorAll('.filter-tab');
            if (prescriptionTabs.length > 0) {
                prescriptionTabs[0].classList.add('active');
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('prescriptionModal');
                if (event.target === modal) {
                    closePrescriptionModal();
                }
            }
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .modal-header i {
            margin-right: 0.5rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #ccc;
        }

        .modal-body {
            padding: 2rem;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer {
            background: #f8f9fa;
            padding: 1rem 2rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .loading i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .error-state {
            text-align: center;
            padding: 2rem;
            color: #dc3545;
        }

        .error-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .prescription-details {
            line-height: 1.6;
        }

        .prescription-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f3f4;
        }

        .prescription-info h3 {
            margin: 0 0 0.5rem 0;
            color: #0077b6;
            font-size: 1.3rem;
        }

        .prescription-meta {
            text-align: right;
            color: #6c757d;
        }

        .prescription-meta p {
            margin: 0.2rem 0;
            font-size: 0.9rem;
        }

        .prescription-remarks {
            background: #f8f9fa;
            border-left: 4px solid #0077b6;
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 0 8px 8px 0;
        }

        .prescription-remarks h4 {
            margin: 0 0 0.5rem 0;
            color: #0077b6;
            font-size: 1rem;
        }

        .prescription-remarks p {
            margin: 0;
            color: #495057;
        }

        .medications-section h4 {
            margin: 0 0 1rem 0;
            color: #0077b6;
            font-size: 1.1rem;
        }

        .medications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .medication-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
        }

        .medication-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .medication-header h5 {
            margin: 0;
            color: #0077b6;
            font-size: 1rem;
            font-weight: 600;
        }

        .medication-status {
            font-size: 0.75rem;
            padding: 0.2rem 0.6rem;
        }

        .medication-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-row .label {
            font-weight: 600;
            color: #495057;
            min-width: 80px;
        }

        .detail-row .value {
            color: #6c757d;
            flex: 1;
        }

        @media (max-width: 768px) {
            .modal-content {
                margin: 10% auto;
                width: 95%;
            }

            .modal-header {
                padding: 1rem;
            }

            .modal-body {
                padding: 1rem;
            }

            .modal-footer {
                padding: 1rem;
                flex-direction: column;
            }

            .prescription-header {
                flex-direction: column;
                gap: 1rem;
            }

            .prescription-meta {
                text-align: left;
            }

            .medication-details {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: auto;
            }

            .prescription-table {
                min-width: 600px;
            }

            .action-buttons-cell {
                flex-direction: column;
            }
        }
    </style>

</body>

</html>