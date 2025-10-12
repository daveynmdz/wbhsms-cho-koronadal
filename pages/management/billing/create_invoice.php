<?php
// Invoice Creation - Multi-step Wizard
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check if user is logged in and has cashier/admin privileges
if (!is_employee_logged_in()) {
    header("Location: ../auth/employee_login.php");
    exit();
}

$employee_role = get_employee_session('role');
if (!in_array($employee_role, ['cashier', 'admin'])) {
    header("Location: ../dashboard.php?error=Access denied");
    exit();
}

$employee_id = get_employee_session('employee_id');
$employee_name = get_employee_session('first_name') . ' ' . get_employee_session('last_name');

// Include topbar function
include '../../../includes/topbar.php';

$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../../assets/css/profile-edit-responsive.css">
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css">
    <link rel="stylesheet" href="../../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .search-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
        }

        .patient-table {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .patient-table table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            min-width: 600px;
        }

        .patient-table th,
        .patient-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .patient-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #0077b6;
        }

        .patient-table tbody tr:hover {
            background: #f8f9fa;
        }

        .patient-checkbox {
            width: 18px;
            height: 18px;
            margin-right: 0.5rem;
        }

        .invoice-form {
            opacity: 0.5;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .invoice-form.enabled {
            opacity: 1;
            pointer-events: auto;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #0077b6;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        .selected-patient {
            background: #d4edda !important;
            border-left: 4px solid #28a745;
        }

        .empty-search {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .service-category {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .service-category:hover {
            border-color: #0077b6;
            box-shadow: 0 2px 8px rgba(0, 119, 182, 0.1);
        }

        .category-header {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            padding: 1rem;
            font-weight: 600;
        }

        .service-list {
            padding: 1rem;
        }

        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid #eee;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .service-item:hover {
            background: #f8f9fa;
            border-color: #0077b6;
        }

        .service-item.selected {
            background: #e3f2fd;
            border-color: #0077b6;
            border-width: 2px;
        }

        .selected-services {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #28a745;
        }

        .service-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            border: 1px solid #dee2e6;
        }

        .discount-section,
        .philhealth-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #ffc107;
        }

        .philhealth-section {
            border-left-color: #007bff;
        }

        .calculation-summary {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
        }

        .total-row.grand-total {
            border-top: 2px solid #0077b6;
            padding-top: 1rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: #0077b6;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        /* New Form Section Styles */
        .service-selection {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 1rem;
        }

        .service-categories .category-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #dee2e6;
        }

        .category-tab {
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: #6c757d;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .category-tab.active {
            color: #0077b6;
            border-bottom-color: #0077b6;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .service-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .service-card:hover {
            border-color: #0077b6;
            box-shadow: 0 2px 8px rgba(0, 119, 182, 0.1);
        }

        .service-card.selected {
            border-color: #28a745;
            background: #f8fff9;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        }

        .service-card .service-info h5 {
            margin: 0 0 0.5rem 0;
            color: #333;
            font-size: 1rem;
        }

        .service-card .service-info p {
            margin: 0 0 0.5rem 0;
            color: #666;
            font-size: 0.875rem;
        }

        .service-card .service-price {
            font-weight: 700;
            color: #0077b6;
            font-size: 1.1rem;
        }

        .selected-service {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .selected-service .service-info {
            flex: 1;
        }

        .selected-service .service-info h5 {
            margin: 0 0 0.25rem 0;
            font-size: 0.9rem;
        }

        .selected-service .service-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #666;
        }

        .selected-service .service-price {
            font-weight: 600;
            color: #0077b6;
            margin-right: 0.5rem;
        }

        .remove-service {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.75rem;
        }

        .remove-service:hover {
            background: #c82333;
        }

        .billing-calculation {
            display: grid;
            gap: 1.5rem;
        }

        .calculation-table {
            width: 100%;
            border-collapse: collapse;
        }

        .calculation-table td {
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }

        .calculation-table td:last-child {
            text-align: right;
            font-weight: 600;
        }

        .calculation-table .total-row {
            border-top: 2px solid #0077b6;
            border-bottom: none;
        }

        .calculation-table .total-row td {
            border-bottom: none;
            padding-top: 1rem;
            font-size: 1.1rem;
            color: #0077b6;
        }

        .invoice-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .patient-info-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 1rem;
        }

        .patient-info-card .patient-details h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .patient-info-card .patient-details p {
            margin: 0.25rem 0;
            color: #666;
            font-size: 0.9rem;
        }

        .patient-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #28a745;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .service-selection {
                grid-template-columns: 1fr;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .category-tabs {
                flex-wrap: wrap;
            }
            
            .invoice-actions {
                flex-direction: column;
            }
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f1b2b7;
        }

        .patient-card {
            display: none;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .patient-card:hover {
            border-color: #0077b6;
            box-shadow: 0 2px 8px rgba(0, 119, 182, 0.1);
        }

        .patient-card.selected {
            border-color: #28a745;
            background: #f8fff8;
        }

        .patient-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .patient-card-name {
            font-weight: 600;
            color: #0077b6;
            font-size: 1.1em;
        }

        .patient-card-checkbox {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 20px;
            height: 20px;
        }

        @media (max-width: 768px) {
            .search-grid {
                grid-template-columns: 1fr;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
            
            .patient-table table {
                display: none;
            }
            
            .patient-card {
                display: block;
            }
        }
    </style>
</head>
<body>
    <section class="homepage">
        <?php renderTopbar([
            'title' => 'Create Invoice',
            'back_url' => 'billing_management.php',
            'user_type' => 'employee'
        ]); ?>
        
        <div class="profile-wrapper">
            <div class="search-container">
                <h2><i class="fas fa-file-invoice"></i> Create New Invoice</h2>
                <p>Search for a patient and create their invoice</p>
                
                <div class="search-form">
                    <div class="search-inputs">
                        <input type="text" id="patientSearch" placeholder="Search by patient name, ID, or contact number">
                        <button type="button" onclick="searchPatients()">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                
                <div id="searchResults" class="patient-table" style="display: none;">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Barangay</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="searchTableBody">
                            <!-- Results will be populated here -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="invoice-form" id="invoiceForm" style="display: none;">
                <form id="createInvoiceForm" action="process_invoice.php" method="POST">
                    <input type="hidden" id="selectedPatientId" name="patient_id">
                    
                    <div class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-user"></i> Patient Information</h3>
                        </div>
                        <div class="patient-info" id="selectedPatientInfo">
                            <!-- Patient details will be shown here -->
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-stethoscope"></i> Services</h3>
                        </div>
                        <div class="service-selection">
                            <div class="service-categories">
                                <div class="category-tabs">
                                    <button type="button" class="category-tab active" data-category="all">All Services</button>
                                    <button type="button" class="category-tab" data-category="consultation">Consultation</button>
                                    <button type="button" class="category-tab" data-category="laboratory">Laboratory</button>
                                    <button type="button" class="category-tab" data-category="pharmacy">Pharmacy</button>
                                    <button type="button" class="category-tab" data-category="other">Other</button>
                                </div>
                                <div class="services-grid" id="servicesGrid">
                                    <!-- Services will be loaded here -->
                                </div>
                            </div>
                            
                            <div class="selected-services">
                                <h4>Selected Services:</h4>
                                <div id="selectedServicesList">
                                    <p>No services selected yet.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-calculator"></i> Billing Details</h3>
                        </div>
                        <div class="billing-calculation">
                            <div class="discount-section">
                                <h4><i class="fas fa-percentage"></i> Discounts</h4>
                                <div class="form-group">
                                    <label for="discountType">Discount Type</label>
                                    <select id="discountType" class="form-control" onchange="calculateDiscount()">
                                        <option value="none">No Discount</option>
                                        <option value="senior">Senior Citizen (20%)</option>
                                        <option value="pwd">PWD (20%)</option>
                                        <option value="indigent">Indigent (50%)</option>
                                        <option value="employee">Employee (30%)</option>
                                        <option value="custom">Custom Amount</option>
                                    </select>
                                </div>
                                
                                <div id="customDiscountGroup" class="form-group" style="display: none;">
                                    <label for="customDiscount">Custom Discount Amount</label>
                                    <input type="number" id="customDiscount" class="form-control" min="0" step="0.01" onchange="calculateDiscount()">
                                </div>
                            </div>
                            
                            <div class="total-calculation">
                                <table class="calculation-table">
                                    <tr>
                                        <td>Subtotal:</td>
                                        <td>₱<span id="subtotal">0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td>Discount:</td>
                                        <td>-₱<span id="discountAmount">0.00</span></td>
                                    </tr>
                                    <tr class="total-row">
                                        <td><strong>Total Amount:</strong></td>
                                        <td><strong>₱<span id="totalAmount">0.00</span></strong></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="invoice-actions">
                                <button type="button" class="btn-secondary" onclick="resetForm()">Reset</button>
                                <button type="submit" class="btn-primary">Create Invoice</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
        <div class="wizard-container" style="display: none;">
            <div class="wizard-header">
                <h2><i class="fas fa-file-invoice"></i> Create New Invoice</h2>
                <p>Multi-step wizard for creating patient invoices</p>
            </div>
            
            <div class="wizard-steps">
                <div class="wizard-step active" data-step="1">
                    <i class="fas fa-user-search"></i>
                    <div>Select Patient</div>
                </div>
                <div class="wizard-step" data-step="2">
                    <i class="fas fa-shopping-cart"></i>
                    <div>Choose Services</div>
                </div>
                <div class="wizard-step" data-step="3">
                    <i class="fas fa-calculator"></i>
                    <div>Calculate Total</div>
                </div>
                <div class="wizard-step" data-step="4">
                    <i class="fas fa-eye"></i>
                    <div>Review & Create</div>
                </div>
            </div>
            
            <div class="wizard-content">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
                    </div>
                <?php endif; ?>
                
                <!-- Step 1: Patient Selection -->
                <div class="step-content active" data-step="1">
                    <h3>Step 1: Select Patient</h3>
                    <div class="patient-search">
                        <div class="search-input">
                            <input type="text" id="patientSearchInput" placeholder="Search by name, phone, or patient ID..." autocomplete="off">
                            <button type="button" class="btn btn-primary" onclick="searchPatients()">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        <div id="patientResults"></div>
                    </div>
                    
                    <div id="selectedPatientInfo" style="display: none;">
                        <h4>Selected Patient:</h4>
                        <div id="selectedPatientCard"></div>
                    </div>
                </div>
                
                <!-- Step 2: Service Selection -->
                <div class="step-content" data-step="2">
                    <h3>Step 2: Choose Services</h3>
                    <div id="serviceCategories" class="service-grid">
                        <!-- Services will be loaded dynamically -->
                    </div>
                    
                    <div class="selected-services">
                        <h4>Selected Services:</h4>
                        <div id="selectedServicesList">
                            <p>No services selected yet.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Calculate Total -->
                <div class="step-content" data-step="3">
                    <h3>Step 3: Calculate Total</h3>
                    
                    <div class="discount-section">
                        <h4><i class="fas fa-percentage"></i> Discounts</h4>
                        <div class="form-group">
                            <label for="discountType">Discount Type</label>
                            <select id="discountType" class="form-control" onchange="calculateDiscount()">
                                <option value="">No Discount</option>
                                <option value="senior">Senior Citizen (20%)</option>
                                <option value="pwd">Person with Disability (20%)</option>
                                <option value="custom">Custom Amount</option>
                            </select>
                        </div>
                        <div class="form-group" id="customDiscountGroup" style="display: none;">
                            <label for="customDiscount">Custom Discount Amount</label>
                            <input type="number" id="customDiscount" class="form-control" min="0" step="0.01" onchange="calculateDiscount()">
                        </div>
                    </div>
                    
                    <div class="philhealth-section">
                        <h4><i class="fas fa-shield-alt"></i> PhilHealth</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="philHealthCovered" onchange="togglePhilHealth()">
                            <label for="philHealthCovered">PhilHealth Coverage</label>
                        </div>
                        <div class="form-group" id="philHealthAmountGroup" style="display: none;">
                            <label for="philHealthAmount">PhilHealth Coverage Amount</label>
                            <input type="number" id="philHealthAmount" class="form-control" min="0" step="0.01" onchange="calculateTotal()">
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="fullExemption" onchange="toggleFullExemption()">
                            <label for="fullExemption">Full Exemption (No Payment Required)</label>
                        </div>
                    </div>
                    
                    <div class="total-section">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span id="subtotalAmount">0.00</span>
                        </div>
                        <div class="total-row">
                            <span>Discount:</span>
                            <span id="discountAmount">0.00</span>
                        </div>
                        <div class="total-row">
                            <span>PhilHealth:</span>
                            <span id="philHealthDisplayAmount">0.00</span>
                        </div>
                        <div class="total-row">
                            <span>Total Amount:</span>
                            <span id="totalAmount">0.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Step 4: Review & Create -->
                <div class="step-content" data-step="4">
                    <h3>Step 4: Review & Create Invoice</h3>
                    
                    <div class="invoice-preview" id="invoicePreview">
                        <!-- Invoice preview will be generated here -->
                    </div>
                </div>
            </div>
            
            <div class="wizard-actions">
                <button type="button" id="prevBtn" class="btn btn-outline" onclick="previousStep()" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <div></div>
                <button type="button" id="nextBtn" class="btn btn-primary" onclick="nextStep()" disabled>
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button type="button" id="createBtn" class="btn btn-success" onclick="createInvoice()" style="display: none;">
                    <i class="fas fa-check"></i> Create Invoice
                </button>
            </div>
            </div>
        </div>
    </section>
    
    <script>
        let currentStep = 1;
        let selectedPatient = null;
        let selectedServices = [];
        let servicesCatalog = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadServiceCatalog();
            
            // Real-time patient search
            document.getElementById('patientSearch').addEventListener('input', function() {
                if (this.value.length >= 2) {
                    searchPatients();
                }
            });
            
            // Category tab handlers
            document.querySelectorAll('.category-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    filterServicesByCategory(this.dataset.category);
                });
            });
        });
        
        async function loadServiceCatalog() {
            try {
                const response = await fetch('../../../../api/billing/shared/get_service_catalog.php');
                const result = await response.json();
                
                if (result.success) {
                    servicesCatalog = result.data;
                    displayServiceCatalog();
                }
            } catch (error) {
                console.error('Error loading service catalog:', error);
            }
        }
        
        function displayServiceCatalog(category = 'all') {
            const container = document.getElementById('servicesGrid');
            
            let filteredServices = servicesCatalog;
            if (category !== 'all') {
                filteredServices = servicesCatalog.filter(service => 
                    (service.category || 'other').toLowerCase() === category.toLowerCase()
                );
            }
            
            container.innerHTML = filteredServices.map(service => `
                <div class="service-card" onclick="toggleService(${service.service_item_id})" data-service-id="${service.service_item_id}">
                    <div class="service-info">
                        <h5>${service.service_name}</h5>
                        <p>${service.description || 'No description'}</p>
                    </div>
                    <div class="service-price">₱${Number(service.price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                </div>
            `).join('');
        }
        
        function filterServicesByCategory(category) {
            displayServiceCatalog(category);
        }
        
        function toggleService(serviceId) {
            const service = servicesCatalog.find(s => s.service_item_id == serviceId);
            const serviceElement = document.querySelector(`[data-service-id="${serviceId}"]`);
            
            if (selectedServices.find(s => s.service_item_id == serviceId)) {
                // Remove service
                selectedServices = selectedServices.filter(s => s.service_item_id != serviceId);
                serviceElement.classList.remove('selected');
            } else {
                // Add service
                selectedServices.push(service);
                serviceElement.classList.add('selected');
            }
            
            updateSelectedServicesDisplay();
        }
        
        function updateSelectedServicesDisplay() {
            const container = document.getElementById('selectedServicesList');
            
            if (selectedServices.length === 0) {
                container.innerHTML = '<p>No services selected yet.</p>';
                return;
            }
            
            container.innerHTML = selectedServices.map(service => `
                <div class="selected-service">
                    <div class="service-info">
                        <h5>${service.service_name}</h5>
                        <p>${service.description || 'No description'}</p>
                    </div>
                    <div class="service-price">₱${Number(service.price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                    <button type="button" class="remove-service" onclick="removeService(${service.service_item_id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `).join('');
            
            calculateTotal();
        }
        
        async function searchPatients() {
            const searchTerm = document.getElementById('patientSearch').value.trim();
            
            if (searchTerm.length < 2) {
                document.getElementById('searchResults').style.display = 'none';
                return;
            }
            
            try {
                const response = await fetch(`../../../../api/billing/management/search_invoices.php?search=${encodeURIComponent(searchTerm)}&patients_only=1`);
                const result = await response.json();
                
                const container = document.getElementById('searchTableBody');
                const searchResults = document.getElementById('searchResults');
                
                if (result.success && result.data.length > 0) {
                    container.innerHTML = result.data.map(patient => `
                        <tr>
                            <td>${patient.patient_id}</td>
                            <td>${patient.patient_name}</td>
                            <td>${patient.phone || 'N/A'}</td>
                            <td>${patient.barangay || 'N/A'}</td>
                            <td>
                                <button type="button" class="btn-primary" onclick="selectPatient(${patient.patient_id}, '${patient.patient_name}', '${patient.phone}', '${patient.email}', '${patient.barangay}')">
                                    Select
                                </button>
                            </td>
                        </tr>
                    `).join('');
                    searchResults.style.display = 'block';
                } else {
                    container.innerHTML = '<tr><td colspan="5">No patients found matching your search.</td></tr>';
                    searchResults.style.display = 'block';
                }
            } catch (error) {
                console.error('Search error:', error);
                document.getElementById('searchTableBody').innerHTML = '<tr><td colspan="5" style="color: #dc3545;">Search failed. Please try again.</td></tr>';
                document.getElementById('searchResults').style.display = 'block';
            }
        }
        
        function selectPatient(patientId, patientName, phone, email, barangay) {
            selectedPatient = {
                patient_id: patientId,
                patient_name: patientName,
                phone: phone,
                email: email,
                barangay: barangay
            };
            
            // Set hidden input
            document.getElementById('selectedPatientId').value = patientId;
            
            // Show selected patient info
            document.getElementById('selectedPatientInfo').innerHTML = `
                <div class="patient-info-card">
                    <div class="patient-details">
                        <h4><i class="fas fa-user"></i> ${patientName}</h4>
                        <p><strong>Patient ID:</strong> ${patientId}</p>
                        <p><strong>Phone:</strong> ${phone || 'N/A'}</p>
                        <p><strong>Email:</strong> ${email || 'N/A'}</p>
                        <p><strong>Barangay:</strong> ${barangay || 'N/A'}</p>
                    </div>
                    <div class="patient-status">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                        <span>Selected</span>
                    </div>
                </div>
            `;
            
            // Show invoice form
            document.getElementById('invoiceForm').style.display = 'block';
            
            // Hide search results
            document.getElementById('searchResults').style.display = 'none';
            
            // Clear search input
            document.getElementById('patientSearch').value = '';
        }
        
        function calculateDiscount() {
            const discountType = document.getElementById('discountType').value;
            const customDiscountGroup = document.getElementById('customDiscountGroup');
            
            if (discountType === 'custom') {
                customDiscountGroup.style.display = 'block';
            } else {
                customDiscountGroup.style.display = 'none';
            }
            
            calculateTotal();
        }
        
        function togglePhilHealth() {
            const isChecked = document.getElementById('philHealthCovered').checked;
            const amountGroup = document.getElementById('philHealthAmountGroup');
            
            if (isChecked) {
                amountGroup.style.display = 'block';
            } else {
                amountGroup.style.display = 'none';
                document.getElementById('philHealthAmount').value = '';
            }
            
            calculateTotal();
        }
        
        function toggleFullExemption() {
            const isChecked = document.getElementById('fullExemption').checked;
            
            if (isChecked) {
                // Disable other options
                document.getElementById('discountType').disabled = true;
                document.getElementById('philHealthCovered').disabled = true;
            } else {
                // Re-enable options
                document.getElementById('discountType').disabled = false;
                document.getElementById('philHealthCovered').disabled = false;
            }
            
            calculateTotal();
        }
        
        function calculateTotal() {
            const subtotal = selectedServices.reduce((sum, service) => sum + Number(service.price), 0);
            
            let discount = 0;
            const discountType = document.getElementById('discountType').value;
            
            if (discountType === 'senior' || discountType === 'pwd') {
                discount = subtotal * 0.20;
            } else if (discountType === 'indigent') {
                discount = subtotal * 0.50;
            } else if (discountType === 'employee') {
                discount = subtotal * 0.30;
            } else if (discountType === 'custom') {
                discount = Number(document.getElementById('customDiscount').value) || 0;
            }
            
            let total = subtotal - discount;
            
            // Ensure total is not negative
            total = Math.max(0, total);
            
            // Update display
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('discountAmount').textContent = discount.toFixed(2);
            document.getElementById('totalAmount').textContent = total.toFixed(2);
        }
        
        function generateInvoicePreview() {
            const subtotal = selectedServices.reduce((sum, service) => sum + Number(service.price), 0);
            
            let discount = 0;
            const discountType = document.getElementById('discountType').value;
            
            if (discountType === 'senior' || discountType === 'pwd') {
                discount = subtotal * 0.20;
            } else if (discountType === 'custom') {
                discount = Number(document.getElementById('customDiscount').value) || 0;
            }
            
            let philHealthAmount = 0;
            if (document.getElementById('philHealthCovered').checked) {
                philHealthAmount = Number(document.getElementById('philHealthAmount').value) || 0;
            }
            
            let total = subtotal - discount - philHealthAmount;
            const isExempted = document.getElementById('fullExemption').checked;
            
            if (isExempted) {
                total = 0;
            }
            
            total = Math.max(0, total);
            
            const preview = `
                <div class="invoice-header">
                    <h2>CITY HEALTH OFFICE</h2>
                    <h3>Koronadal City</h3>
                    <p>INVOICE</p>
                </div>
                
                <div class="invoice-details">
                    <div>
                        <h4>Patient Information:</h4>
                        <p><strong>Name:</strong> ${selectedPatient.patient_name}</p>
                        <p><strong>Patient ID:</strong> ${selectedPatient.patient_id}</p>
                        <p><strong>Phone:</strong> ${selectedPatient.phone || 'N/A'}</p>
                        <p><strong>Email:</strong> ${selectedPatient.email || 'N/A'}</p>
                    </div>
                    <div>
                        <h4>Invoice Details:</h4>
                        <p><strong>Date:</strong> ${new Date().toLocaleDateString('en-PH')}</p>
                        <p><strong>Generated by:</strong> <?php echo $employee_name; ?></p>
                        <p><strong>Status:</strong> ${isExempted ? 'EXEMPTED' : 'UNPAID'}</p>
                    </div>
                </div>
                
                <table class="invoice-items">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Description</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${selectedServices.map(service => `
                            <tr>
                                <td>${service.service_name}</td>
                                <td>${service.description || 'No description'}</td>
                                <td style="text-align: right;">${Number(service.price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                
                <div class="invoice-totals">
                    <table style="width: 100%;">
                        <tr>
                            <td><strong>Subtotal:</strong></td>
                            <td style="text-align: right;"><strong>${subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></td>
                        </tr>
                        ${discount > 0 ? `
                            <tr>
                                <td>Discount:</td>
                                <td style="text-align: right;">-${discount.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                            </tr>
                        ` : ''}
                        ${philHealthAmount > 0 ? `
                            <tr>
                                <td>PhilHealth:</td>
                                <td style="text-align: right;">-${philHealthAmount.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                            </tr>
                        ` : ''}
                        <tr style="border-top: 2px solid #333; font-size: 1.2rem;">
                            <td><strong>TOTAL:</strong></td>
                            <td style="text-align: right;"><strong>${total.toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong></td>
                        </tr>
                    </table>
                </div>
            `;
            
            document.getElementById('invoicePreview').innerHTML = preview;
        }
        
        function nextStep() {
            if (currentStep < 4) {
                // Hide current step
                document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.remove('active');
                document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.add('completed');
                document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.remove('active');
                
                // Show next step
                currentStep++;
                document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.add('active');
                document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.add('active');
                
                if (currentStep === 4) {
                    generateInvoicePreview();
                }
                
                updateWizardButtonState();
            }
        }
        
        function previousStep() {
            if (currentStep > 1) {
                // Hide current step
                document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.remove('active');
                document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.remove('active');
                
                // Show previous step
                currentStep--;
                document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.add('active');
                document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.add('active');
                document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.remove('completed');
                
                updateWizardButtonState();
            }
        }
        
        function updateWizardButtonState() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const createBtn = document.getElementById('createBtn');
            
            // Show/hide previous button
            prevBtn.style.display = currentStep > 1 ? 'inline-flex' : 'none';
            
            // Check if current step is valid
            let canProceed = false;
            
            switch (currentStep) {
                case 1:
                    canProceed = selectedPatient !== null;
                    break;
                case 2:
                    canProceed = selectedServices.length > 0;
                    break;
                case 3:
                    canProceed = true; // Always can proceed from calculation step
                    break;
                case 4:
                    canProceed = true;
                    break;
            }
            
            // Show/hide and enable/disable buttons
            if (currentStep < 4) {
                nextBtn.style.display = 'inline-flex';
                createBtn.style.display = 'none';
                nextBtn.disabled = !canProceed;
            } else {
                nextBtn.style.display = 'none';
                createBtn.style.display = 'inline-flex';
                createBtn.disabled = !canProceed;
            }
        }
        
        async function createInvoice() {
            if (!selectedPatient || selectedServices.length === 0) {
                alert('Please complete all required steps.');
                return;
            }
            
            // Prepare invoice data
            const subtotal = selectedServices.reduce((sum, service) => sum + Number(service.price), 0);
            
            let discount = 0;
            const discountType = document.getElementById('discountType').value;
            
            if (discountType === 'senior' || discountType === 'pwd') {
                discount = subtotal * 0.20;
            } else if (discountType === 'custom') {
                discount = Number(document.getElementById('customDiscount').value) || 0;
            }
            
            let philHealthAmount = 0;
            if (document.getElementById('philHealthCovered').checked) {
                philHealthAmount = Number(document.getElementById('philHealthAmount').value) || 0;
            }
            
            const isExempted = document.getElementById('fullExemption').checked;
            let total = subtotal - discount - philHealthAmount;
            
            if (isExempted) {
                total = 0;
            }
            
            total = Math.max(0, total);
            
            const invoiceData = {
                patient_id: selectedPatient.patient_id,
                services: selectedServices.map(s => ({
                    service_item_id: s.service_item_id,
                    quantity: 1,
                    unit_price: s.price
                })),
                subtotal: subtotal,
                discount_amount: discount,
                discount_type: discountType,
                philhealth_coverage: philHealthAmount,
                total_amount: total,
                payment_status: isExempted ? 'exempted' : 'unpaid'
            };
            
            try {
                const response = await fetch('../../../../api/billing/management/create_invoice.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(invoiceData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Redirect to billing management with success message
                    window.location.href = `billing_management.php?message=Invoice created successfully! Invoice ID: ${result.billing_id}`;
                } else {
                    alert('Error creating invoice: ' + result.message);
                }
            } catch (error) {
                console.error('Error creating invoice:', error);
                alert('Failed to create invoice. Please try again.');
            }
        }
        
        function removeService(serviceId) {
            selectedServices = selectedServices.filter(s => s.service_item_id != serviceId);
            
            // Update service card visual state
            const serviceElement = document.querySelector(`[data-service-id="${serviceId}"]`);
            if (serviceElement) {
                serviceElement.classList.remove('selected');
            }
            
            updateSelectedServicesDisplay();
        }
        
        function resetForm() {
            // Reset patient selection
            selectedPatient = null;
            document.getElementById('selectedPatientId').value = '';
            document.getElementById('selectedPatientInfo').innerHTML = '';
            document.getElementById('invoiceForm').style.display = 'none';
            
            // Reset search
            document.getElementById('patientSearch').value = '';
            document.getElementById('searchResults').style.display = 'none';
            
            // Reset services
            selectedServices = [];
            updateSelectedServicesDisplay();
            
            // Reset service cards
            document.querySelectorAll('.service-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Reset discount
            document.getElementById('discountType').value = 'none';
            document.getElementById('customDiscountGroup').style.display = 'none';
            document.getElementById('customDiscount').value = '';
            
            calculateTotal();
        }
        
        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>
