<?php
// Payment Processing - Real-time Payment with Change Calculation
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

// Include appropriate sidebar based on user role
if ($employee_role === 'admin') {
    include '../../../../includes/sidebar_admin.php';
} else {
    include '../../../../includes/sidebar_cashier.php';
}

$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$selected_billing_id = isset($_GET['billing_id']) ? intval($_GET['billing_id']) : null;

// If billing_id is provided, load the invoice details
$selected_invoice = null;
if ($selected_billing_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                b.billing_id,
                b.total_amount,
                b.discount_amount,
                b.philhealth_coverage,
                b.payment_status,
                b.billing_date,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                p.patient_id,
                p.phone,
                p.email,
                COALESCE(SUM(r.amount_paid), 0) as amount_paid,
                (b.total_amount - COALESCE(SUM(r.amount_paid), 0)) as balance_due
            FROM billing b
            JOIN patients p ON b.patient_id = p.patient_id
            LEFT JOIN receipts r ON b.billing_id = r.billing_id
            WHERE b.billing_id = ? AND b.payment_status != 'paid'
            GROUP BY b.billing_id
        ");
        $stmt->execute([$selected_billing_id]);
        $selected_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$selected_invoice) {
            $error = "Invoice not found or already paid.";
        }
    } catch (Exception $e) {
        error_log("Error loading invoice: " . $e->getMessage());
        $error = "Error loading invoice details.";
    }
}

$payment_methods = [
    'cash' => 'Cash',
    'check' => 'Check',
    'card' => 'Credit/Debit Card',
    'bank_transfer' => 'Bank Transfer'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <style>
        .payment-management {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f5f5f5;
        }

        .payment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .payment-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .payment-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 1rem;
        }

        .professional-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }

        .card-header h3 {
            margin: 0;
            color: #495057;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control:disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: none;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .billing-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
            color: #495057;
        }

        .amount {
            font-weight: 600;
            color: #28a745;
        }

        .total-amount {
            font-size: 1.3rem;
            color: #495057;
        }

        @media (max-width: 768px) {
            .payment-management {
                margin-left: 0;
                padding: 15px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
        }
    </style>
    <style>
        .payment-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }
        
        .main-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .sidebar-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .page-header {
            background: #007bff;
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .content-section {
            padding: 2rem;
        }
        
        .search-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: end;
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
        
        .btn-danger {
            background: #dc3545;
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
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .invoice-card {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .invoice-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        
        .invoice-card.selected {
            border-color: #28a745;
            background: #f8fff8;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .invoice-details h4 {
            margin: 0 0 0.25rem 0;
            color: #333;
        }
        
        .invoice-details p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .invoice-amount {
            text-align: right;
        }
        
        .amount-label {
            font-size: 0.8rem;
            color: #666;
            margin: 0;
        }
        
        .amount-value {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0;
            color: #dc3545;
        }
        
        .payment-form {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .calculator-section {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .calc-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
        }
        
        .calc-row:last-child {
            margin-bottom: 0;
            font-size: 1.2rem;
            font-weight: bold;
            border-top: 2px solid #1976d2;
            padding-top: 1rem;
        }
        
        .calc-label {
            font-weight: 500;
        }
        
        .calc-value {
            font-weight: bold;
        }
        
        .calc-value.positive {
            color: #28a745;
        }
        
        .calc-value.negative {
            color: #dc3545;
        }
        
        .summary-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .summary-row:last-child {
            margin-bottom: 0;
            font-weight: bold;
            padding-top: 0.5rem;
            border-top: 1px solid #dee2e6;
        }
        
        .receipt-preview {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }
        
        .receipt-total {
            border-top: 1px solid #333;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
            font-weight: bold;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .error-state {
            text-align: center;
            padding: 2rem;
            color: #dc3545;
        }
        
        .success-state {
            text-align: center;
            padding: 2rem;
            color: #28a745;
        }
        
        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .quick-amount-btn {
            padding: 0.5rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .quick-amount-btn:hover {
            background: #e9ecef;
            border-color: #007bff;
        }
        
        @media (max-width: 768px) {
            .payment-container {
                grid-template-columns: 1fr;
            }
            
            .payment-grid {
                grid-template-columns: 1fr;
            }
            
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .quick-amounts {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php
    $activePage = 'billing';
    // Include appropriate sidebar based on user role
    if ($employee_role === 'admin') {
        include '../../../includes/sidebar_admin.php';
    } else {
        include '../../../includes/sidebar_cashier.php';
    }
    ?>

<div class="homepage">
    <div class="payment-management">
        <div class="payment-header">
            <h1><i class="fas fa-credit-card"></i> Process Payment</h1>
            <p><?php echo $employee_role === 'admin' ? 'Administrative payment processing and transaction management' : 'Process patient payments and generate receipts'; ?></p>
        </div>
                
                <div class="content-section">
                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$selected_invoice): ?>
                        <!-- Invoice Search Section -->
                        <div class="professional-card">
                            <div class="card-header">
                                <h3><i class="fas fa-search"></i> Search Unpaid Invoices</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="invoiceSearch" class="form-label">Patient Name, ID, or Invoice Number</label>
                                    <input type="text" id="invoiceSearch" class="form-control" placeholder="Enter search term..." autocomplete="off">
                                </div>
                                <button type="button" class="btn btn-primary" onclick="searchInvoices()">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <div id="searchResults"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Selected Invoice Display -->
                        <div class="professional-card">
                            <div class="card-header">
                                <h3><i class="fas fa-file-invoice-dollar"></i> Selected Invoice</h3>
                            </div>
                            <div class="card-body">
                                <div class="billing-summary">
                                    <div class="summary-row">
                                        <span><strong><?php echo htmlspecialchars($selected_invoice['patient_name']); ?></strong></span>
                                        <span>Invoice #<?php echo str_pad($selected_invoice['billing_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Date:</span>
                                        <span><?php echo date('M j, Y', strtotime($selected_invoice['billing_date'])); ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Patient ID:</span>
                                        <span><?php echo $selected_invoice['patient_id']; ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Total Amount:</span>
                                        <span class="amount">₱<?php echo number_format($selected_invoice['total_amount'], 2); ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Amount Paid:</span>
                                        <span class="amount">₱<?php echo number_format($selected_invoice['amount_paid'], 2); ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Balance Due:</span>
                                        <span class="total-amount">₱<?php echo number_format($selected_invoice['balance_due'], 2); ?></span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                                    <i class="fas fa-times"></i> Select Different Invoice
                                </button>
                            </div>
                        </div>
                        
                        <!-- Payment Form -->
                        <div class="professional-card">
                            <div class="card-header">
                                <h3><i class="fas fa-money-bill-wave"></i> Payment Details</h3>
                            </div>
                            <div class="card-body">
                                <form id="paymentForm" onsubmit="processPayment(event)">
                                    <input type="hidden" id="selectedBillingId" value="<?php echo $selected_invoice['billing_id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="paymentMethod" class="form-label">Payment Method</label>
                                        <select id="paymentMethod" class="form-control" required onchange="togglePaymentFields()">
                                            <option value="">Select payment method</option>
                                            <?php foreach ($payment_methods as $value => $label): ?>
                                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="paymentAmount" class="form-label">Payment Amount</label>
                                        <input type="number" id="paymentAmount" class="form-control" min="0.01" step="0.01" 
                                               placeholder="0.00" required onchange="calculateChange()">
                                    </div>
                                </div>
                                
                                    <!-- Quick Amount Buttons -->
                                    <div style="display: flex; gap: 10px; margin: 15px 0; flex-wrap: wrap;">
                                        <button type="button" class="btn btn-secondary" onclick="setQuickAmount(<?php echo $selected_invoice['balance_due']; ?>)">
                                            Exact Amount (₱<?php echo number_format($selected_invoice['balance_due'], 2); ?>)
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="setQuickAmount(<?php echo ceil($selected_invoice['balance_due'] / 100) * 100; ?>)">
                                            Round to 100 (₱<?php echo number_format(ceil($selected_invoice['balance_due'] / 100) * 100, 2); ?>)
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="setQuickAmount(<?php echo ceil($selected_invoice['balance_due'] / 500) * 500; ?>)">
                                            Round to 500 (₱<?php echo number_format(ceil($selected_invoice['balance_due'] / 500) * 500, 2); ?>)
                                        </button>
                                    </div>
                                    
                                    <!-- Check-specific fields -->
                                    <div id="checkFields" style="display: none;">
                                        <div class="form-group">
                                            <label for="checkNumber" class="form-label">Check Number</label>
                                            <input type="text" id="checkNumber" class="form-control" placeholder="Check number">
                                        </div>
                                        <div class="form-group">
                                            <label for="checkBank" class="form-label">Bank</label>
                                            <input type="text" id="checkBank" class="form-control" placeholder="Bank name">
                                        </div>
                                    </div>
                                    
                                    <!-- Card-specific fields -->
                                    <div id="cardFields" style="display: none;">
                                        <div class="form-group">
                                            <label for="cardType" class="form-label">Card Type</label>
                                            <select id="cardType" class="form-control">
                                                <option value="visa">Visa</option>
                                                <option value="mastercard">Mastercard</option>
                                                <option value="amex">American Express</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Bank Transfer fields -->
                                    <div id="transferFields" style="display: none;">
                                        <div class="form-group">
                                            <label for="referenceNumber" class="form-label">Reference Number</label>
                                            <input type="text" id="referenceNumber" class="form-control" placeholder="Transaction reference">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="paymentNotes" class="form-label">Notes (Optional)</label>
                                        <textarea id="paymentNotes" class="form-control" rows="2" placeholder="Additional notes about this payment..."></textarea>
                                    </div>
                                    
                                    <!-- Calculation Section -->
                                    <div class="billing-summary" style="margin-top: 20px;">
                                        <div class="summary-row">
                                            <span>Total Amount Due:</span>
                                            <span class="amount">₱<?php echo number_format($selected_invoice['balance_due'], 2); ?></span>
                                        </div>
                                        <div class="summary-row">
                                            <span>Payment Amount:</span>
                                            <span class="amount" id="displayPaymentAmount">₱0.00</span>
                                        </div>
                                        <div class="summary-row">
                                            <span>Change:</span>
                                            <span class="total-amount" id="displayChange">₱0.00</span>
                                        </div>
                                    </div>
                                    
                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-primary" id="processBtn" disabled>
                                            <i class="fas fa-check"></i> Process Payment
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                            <i class="fas fa-undo"></i> Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
        </div>
    </div>
</div>
    
    <script>
        let selectedInvoice = <?php echo $selected_invoice ? json_encode($selected_invoice) : 'null'; ?>;
        
        function clearSelection() {
            window.location.href = 'process_payment.php';
        }
        
        async function searchInvoices() {
            const searchTerm = document.getElementById('invoiceSearch').value.trim();
            
            if (searchTerm.length < 2) {
                document.getElementById('searchResults').innerHTML = '';
                return;
            }
            
            try {
                const response = await fetch(`../../../../api/billing/management/search_invoices.php?search=${encodeURIComponent(searchTerm)}&status=unpaid&limit=10`);
                const result = await response.json();
                
                const container = document.getElementById('searchResults');
                
                if (result.success && result.data.length > 0) {
                    container.innerHTML = `
                        <h4 style="margin-top: 1.5rem;">Search Results:</h4>
                        ${result.data.map(invoice => `
                            <div class="invoice-card" onclick="selectInvoice(${invoice.billing_id})">
                                <div class="invoice-header">
                                    <div class="invoice-details">
                                        <h4>${invoice.patient_name}</h4>
                                        <p>
                                            Invoice #${String(invoice.billing_id).padStart(6, '0')}  
                                            ${formatDate(invoice.billing_date)} 
                                            Patient ID: ${invoice.patient_id}
                                        </p>
                                    </div>
                                    <div class="invoice-amount">
                                        <p class="amount-label">Amount Due</p>
                                        <p class="amount-value">${Number(invoice.balance_due || invoice.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    `;
                } else {
                    container.innerHTML = `
                        <div class="error-state">
                            <i class="fas fa-search"></i>
                            <p>No unpaid invoices found for "${searchTerm}"</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Search error:', error);
                document.getElementById('searchResults').innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Search failed. Please try again.</p>
                    </div>
                `;
            }
        }
        
        function selectInvoice(billingId) {
            window.location.href = `process_payment.php?billing_id=${billingId}`;
        }
        
        function togglePaymentFields() {
            const method = document.getElementById('paymentMethod').value;
            
            // Hide all specific fields
            document.getElementById('checkFields').style.display = 'none';
            document.getElementById('cardFields').style.display = 'none';
            document.getElementById('transferFields').style.display = 'none';
            
            // Show relevant fields
            if (method === 'check') {
                document.getElementById('checkFields').style.display = 'block';
            } else if (method === 'card') {
                document.getElementById('cardFields').style.display = 'block';
            } else if (method === 'bank_transfer') {
                document.getElementById('transferFields').style.display = 'block';
            }
            
            validateForm();
        }
        
        function setQuickAmount(amount) {
            document.getElementById('paymentAmount').value = amount.toFixed(2);
            calculateChange();
        }
        
        function calculateChange() {
            if (!selectedInvoice) return;
            
            const paymentAmount = parseFloat(document.getElementById('paymentAmount').value) || 0;
            const amountDue = parseFloat(selectedInvoice.balance_due);
            const change = paymentAmount - amountDue;
            
            // Update display
            document.getElementById('displayPaymentAmount').textContent = `${paymentAmount.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
            
            const changeElement = document.getElementById('displayChange');
            if (change >= 0) {
                changeElement.textContent = `${change.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                changeElement.className = 'calc-value positive';
            } else {
                changeElement.textContent = `${Math.abs(change).toLocaleString('en-PH', {minimumFractionDigits: 2})} (Insufficient)`;
                changeElement.className = 'calc-value negative';
            }
            
            validateForm();
            generateReceiptPreview();
        }
        
        function validateForm() {
            if (!selectedInvoice) {
                document.getElementById('processBtn').disabled = true;
                return;
            }
            
            const paymentMethod = document.getElementById('paymentMethod').value;
            const paymentAmount = parseFloat(document.getElementById('paymentAmount').value) || 0;
            const amountDue = parseFloat(selectedInvoice.balance_due);
            
            let isValid = paymentMethod && paymentAmount >= 0.01 && paymentAmount >= amountDue;
            
            // Additional validation for specific payment methods
            if (paymentMethod === 'check') {
                const checkNumber = document.getElementById('checkNumber').value.trim();
                const checkBank = document.getElementById('checkBank').value.trim();
                isValid = isValid && checkNumber && checkBank;
            } else if (paymentMethod === 'bank_transfer') {
                const referenceNumber = document.getElementById('referenceNumber').value.trim();
                isValid = isValid && referenceNumber;
            }
            
            document.getElementById('processBtn').disabled = !isValid;
        }
        
        function generateReceiptPreview() {
            if (!selectedInvoice) return;
            
            const paymentAmount = parseFloat(document.getElementById('paymentAmount').value) || 0;
            const paymentMethod = document.getElementById('paymentMethod').value;
            
            if (paymentAmount <= 0 || !paymentMethod) {
                document.getElementById('receiptPreview').style.display = 'none';
                return;
            }
            
            const change = paymentAmount - parseFloat(selectedInvoice.balance_due);
            
            const receiptContent = `
                <div class="receipt-header">
                    <strong>CITY HEALTH OFFICE</strong><br>
                    Koronadal City<br>
                    <small>PAYMENT RECEIPT</small>
                </div>
                <div class="receipt-row">
                    <span>Receipt #:</span>
                    <span>REC-${new Date().toISOString().slice(0,10).replace(/-/g,'')}-XXXX</span>
                </div>
                <div class="receipt-row">
                    <span>Date:</span>
                    <span>${new Date().toLocaleDateString('en-PH')}</span>
                </div>
                <div class="receipt-row">
                    <span>Patient:</span>
                    <span>${selectedInvoice.patient_name}</span>
                </div>
                <div class="receipt-row">
                    <span>Invoice #:</span>
                    <span>${String(selectedInvoice.billing_id).padStart(6, '0')}</span>
                </div>
                <hr>
                <div class="receipt-row">
                    <span>Amount Due:</span>
                    <span>${parseFloat(selectedInvoice.balance_due).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="receipt-row">
                    <span>Payment (${paymentMethod.replace('_', ' ').toUpperCase()}):</span>
                    <span>${paymentAmount.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>
                ${change > 0 ? `
                    <div class="receipt-row">
                        <span>Change:</span>
                        <span>${change.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                    </div>
                ` : ''}
                <div class="receipt-row receipt-total">
                    <span>Balance:</span>
                    <span>0.00</span>
                </div>
            `;
            
            document.getElementById('receiptContent').innerHTML = receiptContent;
            document.getElementById('receiptPreview').style.display = 'block';
        }
        
        async function processPayment(event) {
            event.preventDefault();
            
            if (!selectedInvoice) return;
            
            const processBtn = document.getElementById('processBtn');
            processBtn.disabled = true;
            processBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            try {
                const paymentData = {
                    billing_id: selectedInvoice.billing_id,
                    amount_paid: parseFloat(document.getElementById('paymentAmount').value),
                    payment_method: document.getElementById('paymentMethod').value,
                    payment_notes: document.getElementById('paymentNotes').value.trim(),
                    check_number: document.getElementById('checkNumber').value.trim(),
                    check_bank: document.getElementById('checkBank').value.trim(),
                    card_type: document.getElementById('cardType').value,
                    reference_number: document.getElementById('referenceNumber').value.trim()
                };
                
                const response = await fetch('../../../../api/billing/management/process_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(paymentData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Redirect to success page with receipt option
                    window.location.href = `billing_management.php?message=Payment processed successfully! Receipt ID: ${result.receipt_id}`;
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Payment error:', error);
                alert('Payment processing failed: ' + error.message);
                
                processBtn.disabled = false;
                processBtn.innerHTML = '<i class="fas fa-check"></i> Process Payment';
            }
        }
        
        function resetForm() {
            document.getElementById('paymentForm').reset();
            document.getElementById('displayPaymentAmount').textContent = '0.00';
            document.getElementById('displayChange').textContent = '0.00';
            document.getElementById('receiptPreview').style.display = 'none';
            togglePaymentFields();
        }
        
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        // Real-time search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('invoiceSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    if (this.value.length >= 2) {
                        searchInvoices();
                    } else {
                        document.getElementById('searchResults').innerHTML = '';
                    }
                });
            }
            
            // Initialize form validation
            const paymentAmountInput = document.getElementById('paymentAmount');
            if (paymentAmountInput) {
                paymentAmountInput.addEventListener('input', calculateChange);
                
                const paymentMethodSelect = document.getElementById('paymentMethod');
                paymentMethodSelect.addEventListener('change', togglePaymentFields);
                
                // Add validation to all relevant fields
                ['checkNumber', 'checkBank', 'referenceNumber'].forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.addEventListener('input', validateForm);
                    }
                });
            }
        });
        
        // Auto-dismiss alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>
