<?php
// Receipt Printing Interface - Dedicated receipt printing and reprint functionality
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
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0;

$receipt_data = null;
$billing_data = null;
$patient_data = null;
$items_data = [];

// If receipt ID is provided, fetch receipt data
if ($receipt_id > 0) {
    try {
        // Get receipt data with billing and patient information
        $stmt = $pdo->prepare("
            SELECT 
                r.*,
                b.billing_id,
                b.billing_date,
                b.total_amount as invoice_total,
                b.discount_amount,
                b.philhealth_coverage,
                b.payment_status,
                b.notes as billing_notes,
                p.patient_id,
                p.first_name,
                p.last_name,
                p.middle_name,
                p.date_of_birth,
                p.address,
                p.phone_number,
                e.first_name as cashier_first_name,
                e.last_name as cashier_last_name
            FROM receipts r
            JOIN billing b ON r.billing_id = b.billing_id
            JOIN patients p ON b.patient_id = p.patient_id
            LEFT JOIN employees e ON r.received_by_employee_id = e.employee_id
            WHERE r.receipt_id = ?
        ");
        $stmt->execute([$receipt_id]);
        $receipt_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($receipt_data) {
            // Get billing items
            $stmt = $pdo->prepare("
                SELECT 
                    bi.*,
                    si.service_name,
                    si.category,
                    si.description as service_description
                FROM billing_items bi
                JOIN service_items si ON bi.service_item_id = si.service_item_id
                WHERE bi.billing_id = ?
                ORDER BY bi.billing_item_id
            ");
            $stmt->execute([$receipt_data['billing_id']]);
            $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "Receipt not found.";
        }
    } catch (Exception $e) {
        error_log("Receipt lookup error: " . $e->getMessage());
        $error = "Failed to retrieve receipt data.";
    }
}

// Get recent receipts for quick access
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.receipt_id,
            r.receipt_number,
            r.payment_date,
            r.amount_paid,
            r.payment_method,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            b.billing_id
        FROM receipts r
        JOIN billing b ON r.billing_id = b.billing_id
        JOIN patients p ON b.patient_id = p.patient_id
        ORDER BY r.payment_date DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recent_receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Recent receipts error: " . $e->getMessage());
    $recent_receipts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt Printing - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css">
    <style>
        .print-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .search-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .receipt-lookup {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 1rem;
            align-items: end;
            margin-bottom: 2rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            height: fit-content;
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
        
        .recent-receipts {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .recent-receipts h4 {
            margin: 0 0 1rem 0;
            color: #333;
        }
        
        .receipts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 0.75rem;
        }
        
        .receipt-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .receipt-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .receipt-card.selected {
            border-color: #007bff;
            background: #f8f9ff;
        }
        
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .receipt-number {
            font-weight: bold;
            color: #007bff;
        }
        
        .receipt-amount {
            font-weight: bold;
            color: #28a745;
        }
        
        .receipt-details {
            font-size: 0.9rem;
            color: #666;
        }
        
        .receipt-preview {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .receipt-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .receipt-actions {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: fit-content;
            min-width: 250px;
        }
        
        .actions-grid {
            display: grid;
            gap: 1rem;
        }
        
        .receipt-paper {
            background: white;
            padding: 2rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.4;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .receipt-header-info {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        
        .clinic-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .clinic-address {
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }
        
        .receipt-title {
            font-size: 1rem;
            font-weight: bold;
            margin-top: 0.5rem;
            text-decoration: underline;
        }
        
        .receipt-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        
        .info-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .info-label {
            font-weight: bold;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.8rem;
        }
        
        .items-table th,
        .items-table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .items-table th {
            font-weight: bold;
            border-bottom: 2px solid #333;
        }
        
        .items-table .amount {
            text-align: right;
        }
        
        .receipt-totals {
            border-top: 2px solid #333;
            padding-top: 1rem;
            margin-top: 1rem;
        }
        
        .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }
        
        .total-line.grand-total {
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 1px solid #333;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px dashed #333;
            font-size: 0.8rem;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            margin-bottom: 0.5rem;
            height: 2rem;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            
            .receipt-paper,
            .receipt-paper * {
                visibility: visible;
            }
            
            .receipt-paper {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 1rem;
                font-size: 12px;
            }
            
            .receipt-preview {
                grid-template-columns: 1fr;
            }
            
            .receipt-actions {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .receipt-lookup {
                grid-template-columns: 1fr;
            }
            
            .receipt-preview {
                grid-template-columns: 1fr;
            }
            
            .receipts-grid {
                grid-template-columns: 1fr;
            }
            
            .receipt-info {
                grid-template-columns: 1fr;
            }
            
            .signature-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="homepage">
    <div style="margin-left: 260px; padding: 20px; min-height: 100vh; background-color: #f5f5f5;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h1 style="margin: 0; font-size: 1.8rem; font-weight: 600;"><i class="fas fa-print"></i> Print Receipt</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 1rem;"><?php echo $employee_role === 'admin' ? 'Administrative receipt management and printing' : 'Print and manage patient payment receipts'; ?></p>
        </div>
        
        <div class="print-container">
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
            
            <!-- Receipt Search Section -->
            <div class="search-section">
                <h3><i class="fas fa-search"></i> Find Receipt</h3>
                
                <form method="GET" class="receipt-lookup">
                    <div class="form-group">
                        <label for="receipt_search">Receipt Number or Transaction ID</label>
                        <input type="text" 
                               id="receipt_search" 
                               name="receipt_id" 
                               class="form-control" 
                               placeholder="Enter receipt number or ID"
                               value="<?php echo $receipt_id; ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <button type="button" class="btn btn-outline" onclick="clearSearch()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </form>
                
                <!-- Recent Receipts -->
                <div class="recent-receipts">
                    <h4><i class="fas fa-clock"></i> Recent Receipts</h4>
                    <div class="receipts-grid">
                        <?php foreach ($recent_receipts as $receipt): ?>
                            <div class="receipt-card <?php echo ($receipt['receipt_id'] == $receipt_id) ? 'selected' : ''; ?>" 
                                 onclick="loadReceipt(<?php echo $receipt['receipt_id']; ?>)">
                                <div class="receipt-header">
                                    <div class="receipt-number">#<?php echo htmlspecialchars($receipt['receipt_number']); ?></div>
                                    <div class="receipt-amount"><?php echo number_format($receipt['amount_paid'], 2); ?></div>
                                </div>
                                <div class="receipt-details">
                                    <div><strong><?php echo htmlspecialchars($receipt['patient_name']); ?></strong></div>
                                    <div><?php echo date('M j, Y g:i A', strtotime($receipt['payment_date'])); ?></div>
                                    <div><?php echo ucfirst(str_replace('_', ' ', $receipt['payment_method'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($receipt_data): ?>
            <!-- Receipt Preview and Actions -->
            <div class="receipt-preview">
                <div class="receipt-content">
                    <div class="receipt-paper" id="receipt-paper">
                        <!-- Receipt Header -->
                        <div class="receipt-header-info">
                            <div class="clinic-name">CITY HEALTH OFFICE</div>
                            <div class="clinic-address">Koronadal City, South Cotabato</div>
                            <div class="clinic-address">Phone: (083) 228-xxxx</div>
                            <div class="receipt-title">OFFICIAL RECEIPT</div>
                        </div>
                        
                        <!-- Receipt Information -->
                        <div class="receipt-info">
                            <div class="info-group">
                                <div><span class="info-label">Receipt No:</span> <?php echo htmlspecialchars($receipt_data['receipt_number']); ?></div>
                                <div><span class="info-label">Date:</span> <?php echo date('F j, Y', strtotime($receipt_data['payment_date'])); ?></div>
                                <div><span class="info-label">Time:</span> <?php echo date('g:i A', strtotime($receipt_data['payment_date'])); ?></div>
                                <div><span class="info-label">Cashier:</span> <?php echo htmlspecialchars($receipt_data['cashier_first_name'] . ' ' . $receipt_data['cashier_last_name']); ?></div>
                            </div>
                            <div class="info-group">
                                <div><span class="info-label">Patient:</span> <?php echo htmlspecialchars($receipt_data['first_name'] . ' ' . $receipt_data['last_name']); ?></div>
                                <div><span class="info-label">Address:</span> <?php echo htmlspecialchars($receipt_data['address']); ?></div>
                                <div><span class="info-label">Invoice No:</span> <?php echo $receipt_data['billing_id']; ?></div>
                                <div><span class="info-label">Payment Method:</span> <?php echo ucfirst(str_replace('_', ' ', $receipt_data['payment_method'])); ?></div>
                            </div>
                        </div>
                        
                        <!-- Services/Items Table -->
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="amount">Qty</th>
                                    <th class="amount">Unit Price</th>
                                    <th class="amount">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_data as $item): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($item['service_name']); ?>
                                            <?php if ($item['category']): ?>
                                                <br><small><?php echo htmlspecialchars($item['category']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="amount"><?php echo number_format($item['quantity']); ?></td>
                                        <td class="amount"><?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="amount"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Receipt Totals -->
                        <div class="receipt-totals">
                            <div class="total-line">
                                <span>Subtotal:</span>
                                <span><?php echo number_format($receipt_data['invoice_total'] + $receipt_data['discount_amount'] + $receipt_data['philhealth_coverage'], 2); ?></span>
                            </div>
                            
                            <?php if ($receipt_data['discount_amount'] > 0): ?>
                                <div class="total-line">
                                    <span>Discount:</span>
                                    <span>-<?php echo number_format($receipt_data['discount_amount'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($receipt_data['philhealth_coverage'] > 0): ?>
                                <div class="total-line">
                                    <span>PhilHealth Coverage:</span>
                                    <span>-<?php echo number_format($receipt_data['philhealth_coverage'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="total-line grand-total">
                                <span>TOTAL AMOUNT:</span>
                                <span><?php echo number_format($receipt_data['invoice_total'], 2); ?></span>
                            </div>
                            
                            <div class="total-line">
                                <span>Amount Paid:</span>
                                <span><?php echo number_format($receipt_data['amount_paid'], 2); ?></span>
                            </div>
                            
                            <?php if ($receipt_data['change_amount'] > 0): ?>
                                <div class="total-line">
                                    <span>Change:</span>
                                    <span><?php echo number_format($receipt_data['change_amount'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Payment Details -->
                        <?php if ($receipt_data['payment_details']): ?>
                            <div style="margin-top: 1rem; font-size: 0.8rem;">
                                <strong>Payment Details:</strong><br>
                                <?php echo htmlspecialchars($receipt_data['payment_details']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Signature Section -->
                        <div class="signature-section">
                            <div class="signature-box">
                                <div class="signature-line"></div>
                                <div>Patient Signature</div>
                            </div>
                            <div class="signature-box">
                                <div class="signature-line"></div>
                                <div>Authorized Signature</div>
                            </div>
                        </div>
                        
                        <!-- Receipt Footer -->
                        <div class="receipt-footer">
                            <div>This is an official receipt of City Health Office, Koronadal</div>
                            <div>Thank you for your payment!</div>
                            <div style="margin-top: 0.5rem; font-size: 0.7rem;">
                                Generated on: <?php echo date('Y-m-d H:i:s'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="receipt-actions">
                    <h4><i class="fas fa-cog"></i> Actions</h4>
                    <div class="actions-grid">
                        <button class="btn btn-primary" onclick="printReceipt()">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                        
                        <button class="btn btn-success" onclick="downloadPDF()">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </button>
                        
                        <button class="btn btn-outline" onclick="emailReceipt()">
                            <i class="fas fa-envelope"></i> Email Receipt
                        </button>
                        
                        <button class="btn btn-outline" onclick="copyReceiptLink()">
                            <i class="fas fa-link"></i> Copy Link
                        </button>
                        
                        <hr style="margin: 1rem 0; border-color: #dee2e6;">
                        
                        <button class="btn btn-outline" onclick="viewInvoice(<?php echo $receipt_data['billing_id']; ?>)">
                            <i class="fas fa-file-invoice"></i> View Invoice
                        </button>
                        
                        <button class="btn btn-outline" onclick="viewPaymentHistory(<?php echo $receipt_data['patient_id']; ?>)">
                            <i class="fas fa-history"></i> Payment History
                        </button>
                    </div>
                    
                    <!-- Receipt Info Summary -->
                    <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #dee2e6; font-size: 0.9rem;">
                        <h5 style="margin-bottom: 1rem;">Receipt Details</h5>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Status:</strong> 
                            <span style="color: #28a745;">Paid</span>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Transaction ID:</strong> <?php echo $receipt_data['receipt_id']; ?>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Payment Date:</strong><br>
                            <?php echo date('F j, Y \a\t g:i A', strtotime($receipt_data['payment_date'])); ?>
                        </div>
                        <?php if ($receipt_data['notes']): ?>
                            <div style="margin-top: 1rem;">
                                <strong>Notes:</strong><br>
                                <em><?php echo htmlspecialchars($receipt_data['notes']); ?></em>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    
    <script>
        function loadReceipt(receiptId) {
            window.location.href = `print_receipt.php?receipt_id=${receiptId}`;
        }
        
        function clearSearch() {
            window.location.href = 'print_receipt.php';
        }
        
        function printReceipt() {
            window.print();
        }
        
        async function downloadPDF() {
            try {
                const receiptId = <?php echo $receipt_id; ?>;
                const url = `../../../../api/billing/shared/download_receipt.php?receipt_id=${receiptId}&format=pdf`;
                
                const link = document.createElement('a');
                link.href = url;
                link.download = `receipt_${receiptId}_${new Date().toISOString().slice(0,10)}.pdf`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                console.error('PDF download error:', error);
                alert('Failed to download PDF. Please try again.');
            }
        }
        
        async function emailReceipt() {
            const receiptId = <?php echo $receipt_id; ?>;
            const email = prompt('Enter email address to send receipt:');
            
            if (email && email.includes('@')) {
                try {
                    const response = await fetch('../../../../api/billing/management/print_receipt.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'email_receipt',
                            receipt_id: receiptId,
                            email: email
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Receipt sent successfully!');
                    } else {
                        alert('Failed to send receipt: ' + (result.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Email error:', error);
                    alert('Failed to send receipt. Please try again.');
                }
            }
        }
        
        function copyReceiptLink() {
            const receiptId = <?php echo $receipt_id; ?>;
            const url = `${window.location.origin}${window.location.pathname}?receipt_id=${receiptId}`;
            
            navigator.clipboard.writeText(url).then(() => {
                alert('Receipt link copied to clipboard!');
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Receipt link copied to clipboard!');
            });
        }
        
        function viewInvoice(billingId) {
            window.open(`invoice_search.php?billing_id=${billingId}`, '_blank');
        }
        
        function viewPaymentHistory(patientId) {
            window.open(`../../../patient/billing/billing_history.php?patient_id=${patientId}`, '_blank');
        }
        
        // Auto-dismiss alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printReceipt();
            }
        });
        
        // Auto-focus search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('receipt_search');
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>
