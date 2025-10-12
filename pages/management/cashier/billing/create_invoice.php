<?php
// Invoice Creation - Multi-step Wizard
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check if user is logged in and has cashier/admin privileges
if (!is_employee_logged_in()) {
    header("Location: ../../auth/employee_login.php");
    exit();
}

$employee_role = get_employee_session('role');
if (!in_array($employee_role, ['cashier', 'admin'])) {
    header("Location: ../../dashboard.php?error=Access denied");
    exit();
}

$employee_id = get_employee_session('employee_id');
$employee_name = get_employee_session('first_name') . ' ' . get_employee_session('last_name');

// Include appropriate sidebar based on user role
if ($employee_role === 'admin') {
    include '../../../../includes/sidebar_admin.php';
} else {
    include '../../../../includes/sidebar_cashier.php';
}

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
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../../../assets/css/profile-edit.css">
    <style>
        .wizard-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .wizard-header {
            background: #007bff;
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .wizard-steps {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .wizard-step {
            flex: 1;
            padding: 1rem;
            text-align: center;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .wizard-step.active {
            background: white;
            color: #007bff;
            font-weight: bold;
        }
        
        .wizard-step.completed {
            background: #28a745;
            color: white;
        }
        
        .wizard-step:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-top: 20px solid #f8f9fa;
        }
        
        .wizard-step.active:not(:last-child)::after {
            border-top-color: white;
        }
        
        .wizard-step.completed:not(:last-child)::after {
            border-top-color: #28a745;
        }
        
        .wizard-content {
            padding: 2rem;
        }
        
        .step-content {
            display: none;
        }
        
        .step-content.active {
            display: block;
        }
        
        .patient-search {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .search-input {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .search-input input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: #007bff;
            border: 1px solid #007bff;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .patient-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .patient-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        
        .patient-card.selected {
            border-color: #28a745;
            background: #f8fff8;
        }
        
        .patient-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .patient-details h4 {
            margin: 0 0 0.25rem 0;
            color: #333;
        }
        
        .patient-details p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .service-category {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .category-header {
            background: #007bff;
            color: white;
            padding: 1rem;
            font-weight: bold;
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
            border-radius: 4px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .service-item:hover {
            background: #f8f9fa;
        }
        
        .service-item.selected {
            background: #e3f2fd;
            border-color: #007bff;
        }
        
        .service-info h5 {
            margin: 0 0 0.25rem 0;
            font-size: 0.9rem;
        }
        
        .service-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #666;
        }
        
        .service-price {
            font-weight: bold;
            color: #28a745;
        }
        
        .selected-services {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .service-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        
        .summary-info h5 {
            margin: 0;
            font-size: 0.9rem;
        }
        
        .summary-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #666;
        }
        
        .summary-price {
            font-weight: bold;
            color: #333;
        }
        
        .total-section {
            background: #007bff;
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .total-row:last-child {
            margin-bottom: 0;
            font-size: 1.2rem;
            font-weight: bold;
            border-top: 1px solid rgba(255,255,255,0.3);
            padding-top: 0.5rem;
        }
        
        .wizard-actions {
            display: flex;
            justify-content: space-between;
            padding: 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        
        .discount-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .philhealth-section {
            background: #cce5ff;
            border: 1px solid #99ccff;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .invoice-preview {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            font-family: 'Courier New', monospace;
        }
        
        .invoice-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .invoice-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        
        .invoice-items th,
        .invoice-items td {
            border: 1px solid #ddd;
            padding: 0.5rem;
            text-align: left;
        }
        
        .invoice-items th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .invoice-totals {
            margin-left: auto;
            width: 300px;
        }
        
        @media (max-width: 768px) {
            .wizard-steps {
                flex-direction: column;
            }
            
            .wizard-step:not(:last-child)::after {
                display: none;
            }
            
            .service-grid {
                grid-template-columns: 1fr;
            }
            
            .search-input {
                flex-direction: column;
            }
            
            .invoice-details {
                grid-template-columns: 1fr;
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
        
        <div class="wizard-container">
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
            document.getElementById('patientSearchInput').addEventListener('input', function() {
                if (this.value.length >= 2) {
                    searchPatients();
                }
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
        
        function displayServiceCatalog() {
            const container = document.getElementById('serviceCategories');
            const categories = {};
            
            // Group services by category
            servicesCatalog.forEach(service => {
                const category = service.category || 'General Services';
                if (!categories[category]) {
                    categories[category] = [];
                }
                categories[category].push(service);
            });
            
            container.innerHTML = Object.keys(categories).map(category => `
                <div class="service-category">
                    <div class="category-header">${category}</div>
                    <div class="service-list">
                        ${categories[category].map(service => `
                            <div class="service-item" onclick="toggleService(${service.service_item_id})" data-service-id="${service.service_item_id}">
                                <div class="service-info">
                                    <h5>${service.service_name}</h5>
                                    <p>${service.description || 'No description'}</p>
                                </div>
                                <div class="service-price">${Number(service.price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `).join('');
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
            updateWizardButtonState();
        }
        
        function updateSelectedServicesDisplay() {
            const container = document.getElementById('selectedServicesList');
            
            if (selectedServices.length === 0) {
                container.innerHTML = '<p>No services selected yet.</p>';
                return;
            }
            
            container.innerHTML = selectedServices.map(service => `
                <div class="service-summary">
                    <div class="summary-info">
                        <h5>${service.service_name}</h5>
                        <p>${service.description || 'No description'}</p>
                    </div>
                    <div class="summary-price">${Number(service.price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                </div>
            `).join('');
            
            calculateTotal();
        }
        
        async function searchPatients() {
            const searchTerm = document.getElementById('patientSearchInput').value.trim();
            
            if (searchTerm.length < 2) {
                document.getElementById('patientResults').innerHTML = '';
                return;
            }
            
            try {
                const response = await fetch(`../../../../api/billing/management/search_invoices.php?search=${encodeURIComponent(searchTerm)}&patients_only=1`);
                const result = await response.json();
                
                const container = document.getElementById('patientResults');
                
                if (result.success && result.data.length > 0) {
                    container.innerHTML = result.data.map(patient => `
                        <div class="patient-card" onclick="selectPatient(${patient.patient_id}, '${patient.patient_name}', '${patient.phone}', '${patient.email}')">
                            <div class="patient-info">
                                <div class="patient-details">
                                    <h4>${patient.patient_name}</h4>
                                    <p>ID: ${patient.patient_id}  Phone: ${patient.phone || 'N/A'}  Email: ${patient.email || 'N/A'}</p>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<p>No patients found matching your search.</p>';
                }
            } catch (error) {
                console.error('Search error:', error);
                document.getElementById('patientResults').innerHTML = '<p style="color: #dc3545;">Search failed. Please try again.</p>';
            }
        }
        
        function selectPatient(patientId, patientName, phone, email) {
            selectedPatient = {
                patient_id: patientId,
                patient_name: patientName,
                phone: phone,
                email: email
            };
            
            // Remove selection from all patient cards
            document.querySelectorAll('.patient-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            event.target.closest('.patient-card').classList.add('selected');
            
            // Show selected patient info
            document.getElementById('selectedPatientInfo').style.display = 'block';
            document.getElementById('selectedPatientCard').innerHTML = `
                <div class="patient-card selected">
                    <div class="patient-info">
                        <div class="patient-details">
                            <h4>${patientName}</h4>
                            <p>ID: ${patientId}  Phone: ${phone || 'N/A'}  Email: ${email || 'N/A'}</p>
                        </div>
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                    </div>
                </div>
            `;
            
            updateWizardButtonState();
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
            } else if (discountType === 'custom') {
                discount = Number(document.getElementById('customDiscount').value) || 0;
            }
            
            let philHealthAmount = 0;
            if (document.getElementById('philHealthCovered').checked) {
                philHealthAmount = Number(document.getElementById('philHealthAmount').value) || 0;
            }
            
            let total = subtotal - discount - philHealthAmount;
            
            // Full exemption
            if (document.getElementById('fullExemption').checked) {
                total = 0;
            }
            
            // Ensure total is not negative
            total = Math.max(0, total);
            
            // Update display
            document.getElementById('subtotalAmount').textContent = `${subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
            document.getElementById('discountAmount').textContent = `${discount.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
            document.getElementById('philHealthDisplayAmount').textContent = `${philHealthAmount.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
            document.getElementById('totalAmount').textContent = `${total.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
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
