<?php
// Cashier Billing & Invoice Management
session_start();

// Root path for includes
$root_path = dirname(dirname(__DIR__));

// Authentication and role check
require_once $root_path . '/config/session/employee_session.php';

if (!is_employee_logged_in()) {
    header("Location: " . $root_path . "/login.php");
    exit();
}

// Check if user has cashier or admin role
$employee_role = get_employee_session('role_name');
if (!in_array($employee_role, ['cashier', 'admin'])) {
    $_SESSION['error_message'] = "Access denied. Only cashiers and administrators can access billing management.";
    header("Location: " . $root_path . "/pages/management/dashboard.php");
    exit();
}

// Include database connection
require_once $root_path . '/config/db.php';

// Get employee information
$employee_id = get_employee_session('employee_id');
$employee_name = get_employee_session('first_name') . ' ' . get_employee_session('last_name');

// Initialize variables
$message = '';
$error = '';
$selected_patient_id = $_GET['patient_id'] ?? '';
$selected_patient = null;

// Get patient information if ID provided
if ($selected_patient_id) {
    try {
        $patient_sql = "
            SELECT 
                id,
                first_name,
                last_name,
                middle_name,
                contact_number,
                email,
                address,
                DATE(created_at) as registration_date
            FROM patients 
            WHERE id = ?
        ";
        $stmt = $pdo->prepare($patient_sql);
        $stmt->execute([$selected_patient_id]);
        $selected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching patient information: " . $e->getMessage();
    }
}

// Get service types for dropdown
$service_types = [
    'consultation' => 'Consultation',
    'laboratory' => 'Laboratory Test',
    'pharmacy' => 'Medication/Prescription',
    'dental' => 'Dental Service',
    'prenatal' => 'Prenatal Care',
    'immunization' => 'Immunization/Vaccination',
    'emergency' => 'Emergency Care',
    'family_planning' => 'Family Planning',
    'nutrition' => 'Nutrition Counseling',
    'other' => 'Other Services'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Invoice Management - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        /* Billing Management Specific Styles */
        .content-wrapper {
            margin-left: 300px;
            padding: 2rem;
            transition: margin-left 0.3s;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
        }

        .page-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.2);
        }

        .page-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2.2rem;
            font-weight: 700;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
            margin: 0;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0 0 1rem 0;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Main Content Grid */
        .billing-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1200px) {
            .billing-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Patient Information Card */
        .patient-info-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #007bff;
        }

        .patient-info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .patient-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .patient-details h3 {
            margin: 0;
            color: #007bff;
            font-size: 1.3rem;
        }

        .patient-details p {
            margin: 0.2rem 0 0 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .patient-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.2rem;
        }

        .meta-value {
            font-weight: 600;
            color: #495057;
        }

        /* Invoice List Section */
        .invoice-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: between;
            gap: 1rem;
        }

        .section-title {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            flex: 1;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
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
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid;
        }

        .btn-outline-primary {
            border-color: #007bff;
            color: #007bff;
        }

        .btn-outline-primary:hover {
            background: #007bff;
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Patient Search */
        .patient-search {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .search-input-group {
            display: flex;
            gap: 0.5rem;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .search-results {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 1rem;
        }

        .patient-result {
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .patient-result:hover {
            background: #f8f9fa;
            border-color: #28a745;
        }

        .patient-result.selected {
            background: #e8f5e8;
            border-color: #28a745;
        }

        /* Invoice Table */
        .invoice-table-container {
            padding: 1.5rem;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
            color: #495057;
        }

        .invoice-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: top;
        }

        .invoice-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .invoice-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-unpaid {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .status-cancelled {
            background: #e2e3e5;
            color: #383d41;
        }

        /* Amount Display */
        .amount-display {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .amount-unpaid {
            color: #dc3545;
        }

        .amount-paid {
            color: #28a745;
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
            background-color: white;
            margin: 2% auto;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .modal-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1rem 2rem 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            border-top: 1px solid #e9ecef;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-input {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Service Items */
        .service-items {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .service-items-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .service-item {
            display: grid;
            grid-template-columns: 2fr 3fr 1fr 1fr auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }

        .service-item:last-child {
            margin-bottom: 0;
        }

        .remove-service {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .remove-service:hover {
            background: #c82333;
        }

        /* Invoice Summary */
        .invoice-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.1rem;
            color: #28a745;
        }

        /* Empty States */
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

        .empty-state h3 {
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            margin: 0;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .service-item {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                margin: 5% auto;
                width: 95%;
            }

            .invoice-table {
                font-size: 0.8rem;
            }

            .invoice-table th,
            .invoice-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <?php
    $activePage = 'billing_management';
    include '../../includes/sidebar_cashier.php';
    ?>

    <section class="content-wrapper">
        <div class="page-header">
            <div class="breadcrumb">
                <a href="../management/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span> / </span>
                <span>Billing & Invoice Management</span>
            </div>
            <h1><i class="fas fa-file-invoice-dollar"></i> Billing & Invoice Management</h1>
            <p class="page-subtitle">Manage patient invoices and billing records</p>
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

        <div class="billing-grid">
            <!-- Main Invoice Management -->
            <div class="invoice-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-receipt"></i> 
                        <?php if ($selected_patient): ?>
                            Invoices for <?php echo htmlspecialchars($selected_patient['first_name'] . ' ' . $selected_patient['last_name']); ?>
                        <?php else: ?>
                            Patient Invoices
                        <?php endif; ?>
                    </h2>
                    <div class="action-buttons">
                        <?php if ($selected_patient): ?>
                            <button class="btn btn-success" onclick="openCreateInvoiceModal()">
                                <i class="fas fa-plus"></i> Create Invoice
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-primary" onclick="refreshInvoices()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Patient Search -->
                <?php if (!$selected_patient): ?>
                    <div class="patient-search">
                        <div class="search-input-group">
                            <input type="text" class="search-input" id="patientSearch" 
                                   placeholder="Search patient by name, phone, or patient ID...">
                            <button class="btn btn-primary" onclick="searchPatients()">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        <div class="search-results" id="searchResults"></div>
                    </div>
                <?php endif; ?>

                <!-- Invoice Table -->
                <div class="invoice-table-container">
                    <div id="invoiceTableContainer">
                        <?php if ($selected_patient): ?>
                            <table class="invoice-table" id="invoiceTable">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Services</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="invoiceTableBody">
                                    <!-- Invoices will be loaded here -->
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <h3>Search for a Patient</h3>
                                <p>Use the search box above to find a patient and view their billing records.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Patient Information Sidebar -->
            <?php if ($selected_patient): ?>
                <div class="patient-info-card">
                    <div class="patient-info-header">
                        <div class="patient-avatar">
                            <?php echo strtoupper(substr($selected_patient['first_name'], 0, 1) . substr($selected_patient['last_name'], 0, 1)); ?>
                        </div>
                        <div class="patient-details">
                            <h3><?php echo htmlspecialchars($selected_patient['first_name'] . ' ' . $selected_patient['last_name']); ?></h3>
                            <p>Patient ID: #<?php echo str_pad($selected_patient['id'], 6, '0', STR_PAD_LEFT); ?></p>
                        </div>
                    </div>

                    <div class="patient-meta">
                        <div class="meta-item">
                            <span class="meta-label">Contact Number</span>
                            <span class="meta-value"><?php echo htmlspecialchars($selected_patient['contact_number'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Email</span>
                            <span class="meta-value"><?php echo htmlspecialchars($selected_patient['email'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Address</span>
                            <span class="meta-value"><?php echo htmlspecialchars($selected_patient['address'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Registration Date</span>
                            <span class="meta-value"><?php echo date('M d, Y', strtotime($selected_patient['registration_date'])); ?></span>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem;">
                        <button class="btn btn-outline-primary btn-sm" onclick="changePatient()">
                            <i class="fas fa-user-friends"></i> Select Different Patient
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Create Invoice Modal -->
    <div id="createInvoiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-invoice"></i> Create New Invoice</h3>
                <button class="modal-close" onclick="closeCreateInvoiceModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createInvoiceForm">
                    <input type="hidden" id="invoicePatientId" value="<?php echo $selected_patient_id; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" class="form-input" id="invoiceDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-input" id="invoiceDueDate" required>
                        </div>
                    </div>

                    <div class="service-items">
                        <div class="service-items-header">
                            <h4><i class="fas fa-list"></i> Services & Items</h4>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addServiceItem()">
                                <i class="fas fa-plus"></i> Add Service
                            </button>
                        </div>
                        <div id="serviceItemsContainer">
                            <!-- Service items will be added here -->
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-input form-textarea" id="invoiceNotes" 
                                  placeholder="Additional notes or comments..."></textarea>
                    </div>

                    <div class="invoice-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="invoiceSubtotal">₱0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (if applicable):</span>
                            <span id="invoiceTax">₱0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Total Amount:</span>
                            <span id="invoiceTotal">₱0.00</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="closeCreateInvoiceModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" onclick="saveInvoice()">
                    <i class="fas fa-save"></i> Create Invoice
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let serviceItemCounter = 0;
        const selectedPatientId = <?php echo json_encode($selected_patient_id); ?>;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set today's date as default
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('invoiceDate').value = today;
            
            // Set due date to 30 days from today
            const dueDate = new Date();
            dueDate.setDate(dueDate.getDate() + 30);
            document.getElementById('invoiceDueDate').value = dueDate.toISOString().split('T')[0];

            // Load invoices if patient selected
            if (selectedPatientId) {
                loadPatientInvoices(selectedPatientId);
            }

            // Auto-dismiss alerts
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                });
            }, 5000);
        });

        // Patient search functionality
        function searchPatients() {
            const query = document.getElementById('patientSearch').value.trim();
            if (query.length < 2) {
                alert('Please enter at least 2 characters to search');
                return;
            }

            fetch('../../api/search_patients.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ query: query })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySearchResults(data.patients);
                } else {
                    alert('Error searching patients: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error searching patients. Please try again.');
            });
        }

        function displaySearchResults(patients) {
            const container = document.getElementById('searchResults');
            if (patients.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-user-slash"></i><h3>No Patients Found</h3><p>Try adjusting your search terms.</p></div>';
                return;
            }

            container.innerHTML = patients.map(patient => `
                <div class="patient-result" onclick="selectPatient(${patient.id})">
                    <strong>${patient.first_name} ${patient.last_name}</strong>
                    <br><small>ID: #${String(patient.id).padStart(6, '0')} | Contact: ${patient.contact_number || 'Not provided'}</small>
                </div>
            `).join('');
        }

        function selectPatient(patientId) {
            window.location.href = `billing_management.php?patient_id=${patientId}`;
        }

        function changePatient() {
            window.location.href = 'billing_management.php';
        }

        // Invoice management
        function loadPatientInvoices(patientId) {
            fetch(`../../api/get_patient_invoices.php?patient_id=${patientId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayInvoices(data.invoices);
                } else {
                    console.error('Error loading invoices:', data.message);
                    document.getElementById('invoiceTableBody').innerHTML = `
                        <tr><td colspan="7" class="text-center">Error loading invoices: ${data.message}</td></tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('invoiceTableBody').innerHTML = `
                    <tr><td colspan="7" class="text-center">Error loading invoices. Please try again.</td></tr>
                `;
            });
        }

        function displayInvoices(invoices) {
            const tbody = document.getElementById('invoiceTableBody');
            
            if (invoices.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-file-invoice"></i>
                                <h3>No Invoices Found</h3>
                                <p>This patient doesn't have any invoices yet.</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = invoices.map(invoice => {
                const statusClass = getStatusClass(invoice.status);
                const amountClass = invoice.status === 'paid' ? 'amount-paid' : 'amount-unpaid';
                
                return `
                    <tr>
                        <td><strong>#${String(invoice.id).padStart(6, '0')}</strong></td>
                        <td>${formatDate(invoice.invoice_date)}</td>
                        <td>${invoice.service_summary || 'Various services'}</td>
                        <td><span class="amount-display ${amountClass}">₱${parseFloat(invoice.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span></td>
                        <td>${formatDate(invoice.due_date)}</td>
                        <td><span class="status-badge status-${statusClass}">${invoice.status}</span></td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewInvoice(${invoice.id})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                ${invoice.status === 'unpaid' ? `
                                    <button class="btn btn-sm btn-success" onclick="markAsPaid(${invoice.id})">
                                        <i class="fas fa-check"></i> Mark Paid
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function getStatusClass(status) {
            switch (status.toLowerCase()) {
                case 'paid': return 'paid';
                case 'unpaid': return 'unpaid';
                case 'overdue': return 'overdue';
                case 'cancelled': return 'cancelled';
                default: return 'unpaid';
            }
        }

        // Create invoice modal
        function openCreateInvoiceModal() {
            document.getElementById('createInvoiceModal').style.display = 'block';
            // Add initial service item
            addServiceItem();
        }

        function closeCreateInvoiceModal() {
            document.getElementById('createInvoiceModal').style.display = 'none';
            document.getElementById('createInvoiceForm').reset();
            document.getElementById('serviceItemsContainer').innerHTML = '';
            serviceItemCounter = 0;
            updateInvoiceTotal();
        }

        function addServiceItem() {
            serviceItemCounter++;
            const container = document.getElementById('serviceItemsContainer');
            const serviceTypes = <?php echo json_encode($service_types); ?>;
            
            const serviceHtml = `
                <div class="service-item" id="serviceItem${serviceItemCounter}">
                    <select class="form-input" name="service_type[]" required>
                        <option value="">Select Service Type</option>
                        ${Object.entries(serviceTypes).map(([key, value]) => 
                            `<option value="${key}">${value}</option>`
                        ).join('')}
                    </select>
                    <input type="text" class="form-input" name="service_description[]" 
                           placeholder="Service description" required>
                    <input type="number" class="form-input" name="service_quantity[]" 
                           placeholder="Qty" value="1" min="1" step="1" required 
                           onchange="updateInvoiceTotal()">
                    <input type="number" class="form-input" name="service_amount[]" 
                           placeholder="Amount" step="0.01" min="0" required 
                           onchange="updateInvoiceTotal()">
                    <button type="button" class="remove-service" onclick="removeServiceItem(${serviceItemCounter})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', serviceHtml);
        }

        function removeServiceItem(itemId) {
            document.getElementById(`serviceItem${itemId}`).remove();
            updateInvoiceTotal();
        }

        function updateInvoiceTotal() {
            const quantities = document.querySelectorAll('input[name="service_quantity[]"]');
            const amounts = document.querySelectorAll('input[name="service_amount[]"]');
            let subtotal = 0;

            for (let i = 0; i < quantities.length; i++) {
                const qty = parseFloat(quantities[i].value) || 0;
                const amount = parseFloat(amounts[i].value) || 0;
                subtotal += qty * amount;
            }

            const tax = 0; // No tax for now
            const total = subtotal + tax;

            document.getElementById('invoiceSubtotal').textContent = `₱${subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
            document.getElementById('invoiceTax').textContent = `₱${tax.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
            document.getElementById('invoiceTotal').textContent = `₱${total.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
        }

        function saveInvoice() {
            const form = document.getElementById('createInvoiceForm');
            const formData = new FormData(form);
            
            // Validate form
            const serviceTypes = document.querySelectorAll('select[name="service_type[]"]');
            const serviceDescriptions = document.querySelectorAll('input[name="service_description[]"]');
            const serviceQuantities = document.querySelectorAll('input[name="service_quantity[]"]');
            const serviceAmounts = document.querySelectorAll('input[name="service_amount[]"]');

            if (serviceTypes.length === 0) {
                alert('Please add at least one service item.');
                return;
            }

            // Validate all service items
            for (let i = 0; i < serviceTypes.length; i++) {
                if (!serviceTypes[i].value || !serviceDescriptions[i].value || 
                    !serviceQuantities[i].value || !serviceAmounts[i].value) {
                    alert('Please fill in all service item fields.');
                    return;
                }
            }

            // Prepare invoice data
            const invoiceData = {
                patient_id: document.getElementById('invoicePatientId').value,
                invoice_date: document.getElementById('invoiceDate').value,
                due_date: document.getElementById('invoiceDueDate').value,
                notes: document.getElementById('invoiceNotes').value,
                services: []
            };

            // Collect service items
            for (let i = 0; i < serviceTypes.length; i++) {
                invoiceData.services.push({
                    service_type: serviceTypes[i].value,
                    description: serviceDescriptions[i].value,
                    quantity: parseFloat(serviceQuantities[i].value),
                    unit_amount: parseFloat(serviceAmounts[i].value)
                });
            }

            // Save invoice
            fetch('../../api/create_invoice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(invoiceData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Invoice created successfully!');
                    closeCreateInvoiceModal();
                    loadPatientInvoices(selectedPatientId);
                } else {
                    alert('Error creating invoice: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating invoice. Please try again.');
            });
        }

        // Utility functions
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function refreshInvoices() {
            if (selectedPatientId) {
                loadPatientInvoices(selectedPatientId);
            }
        }

        function viewInvoice(invoiceId) {
            window.open(`invoice_details.php?id=${invoiceId}`, '_blank');
        }

        function markAsPaid(invoiceId) {
            if (confirm('Mark this invoice as paid?')) {
                fetch('../../api/update_invoice_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        invoice_id: invoiceId,
                        status: 'paid'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Invoice marked as paid!');
                        loadPatientInvoices(selectedPatientId);
                    } else {
                        alert('Error updating invoice: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating invoice. Please try again.');
                });
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('createInvoiceModal');
            if (event.target === modal) {
                closeCreateInvoiceModal();
            }
        }

        // Enter key search
        document.getElementById('patientSearch')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchPatients();
            }
        });
    </script>
</body>
</html>