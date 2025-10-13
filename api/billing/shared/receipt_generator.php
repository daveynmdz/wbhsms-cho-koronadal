<?php
/**
 * Receipt Generator Utility
 * Shared functions for generating receipts in different formats
 */

/**
 * Generate HTML receipt for display/printing
 */
function generateHTMLReceipt($receipt_data) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt #<?php echo htmlspecialchars($receipt_data['receipt_number']); ?></title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background: #f5f5f5;
            }
            
            .receipt-container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                margin-bottom: 20px;
            }
            
            .receipt-header {
                text-align: center;
                border-bottom: 2px solid #28a745;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            
            .facility-name {
                font-size: 24px;
                font-weight: bold;
                color: #28a745;
                margin-bottom: 5px;
            }
            
            .facility-details {
                color: #666;
                font-size: 14px;
                line-height: 1.5;
            }
            
            .receipt-title {
                font-size: 20px;
                font-weight: bold;
                margin: 20px 0 10px 0;
                color: #333;
            }
            
            .receipt-number {
                font-size: 16px;
                color: #28a745;
                font-weight: bold;
            }
            
            .info-section {
                display: flex;
                justify-content: space-between;
                margin: 20px 0;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 5px;
            }
            
            .info-group h4 {
                margin: 0 0 10px 0;
                color: #28a745;
                font-size: 14px;
                font-weight: bold;
                text-transform: uppercase;
            }
            
            .info-group p {
                margin: 5px 0;
                font-size: 14px;
                color: #333;
            }
            
            .services-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            
            .services-table th {
                background: #28a745;
                color: white;
                padding: 12px;
                text-align: left;
                font-weight: bold;
                font-size: 14px;
            }
            
            .services-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #eee;
                font-size: 14px;
            }
            
            .services-table tbody tr:hover {
                background: #f8f9fa;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .amount {
                font-weight: bold;
                color: #28a745;
            }
            
            .totals-section {
                margin-top: 20px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 5px;
            }
            
            .total-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                font-size: 16px;
            }
            
            .total-row.final {
                border-top: 2px solid #28a745;
                font-weight: bold;
                font-size: 18px;
                color: #28a745;
                margin-top: 10px;
                padding-top: 15px;
            }
            
            .footer-section {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                text-align: center;
                color: #666;
                font-size: 12px;
            }
            
            .no-print {
                text-align: center;
                margin-top: 20px;
            }
            
            .print-btn {
                background: #28a745;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                margin: 0 10px;
            }
            
            .print-btn:hover {
                background: #1e7e34;
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                
                .receipt-container {
                    box-shadow: none;
                    padding: 0;
                }
                
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <!-- Header -->
            <div class="receipt-header">
                <div class="facility-name">City Health Office Koronadal</div>
                <div class="facility-details">
                    Koronadal City, South Cotabato<br>
                    Phone: (083) 228-8045 | Email: cho.koronadal@gmail.com
                </div>
                <div class="receipt-title">OFFICIAL RECEIPT</div>
                <div class="receipt-number">Receipt #<?php echo htmlspecialchars($receipt_data['receipt_number']); ?></div>
            </div>
            
            <!-- Patient and Receipt Info -->
            <div class="info-section">
                <div class="info-group">
                    <h4>Patient Information</h4>
                    <p><strong><?php echo htmlspecialchars($receipt_data['patient']['name']); ?></strong></p>
                    <p>Patient #: <?php echo htmlspecialchars($receipt_data['patient']['patient_number']); ?></p>
                    <p>Phone: <?php echo htmlspecialchars($receipt_data['patient']['phone'] ?? 'N/A'); ?></p>
                    <?php if (!empty($receipt_data['patient']['address'])): ?>
                    <p>Address: <?php echo htmlspecialchars($receipt_data['patient']['address']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="info-group">
                    <h4>Receipt Details</h4>
                    <p>Date: <?php echo date('F d, Y', strtotime($receipt_data['payment']['date'])); ?></p>
                    <p>Time: <?php echo date('h:i A', strtotime($receipt_data['payment']['date'])); ?></p>
                    <p>Payment Method: <?php echo ucfirst($receipt_data['payment']['method']); ?></p>
                    <p>Cashier: <?php echo htmlspecialchars($receipt_data['cashier']); ?></p>
                </div>
            </div>
            
            <!-- Services Table -->
            <table class="services-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipt_data['items'] as $item): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                            <?php if (!empty($item['description'])): ?>
                            <br><small style="color: #666;"><?php echo htmlspecialchars($item['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo number_format($item['quantity'], 0); ?></td>
                        <td class="text-right">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-right amount">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Totals Section -->
            <div class="totals-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>₱<?php echo number_format($receipt_data['amounts']['subtotal'], 2); ?></span>
                </div>
                
                <?php if ($receipt_data['amounts']['discount'] > 0): ?>
                <div class="total-row">
                    <span>Discount:</span>
                    <span>-₱<?php echo number_format($receipt_data['amounts']['discount'], 2); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($receipt_data['amounts']['philhealth_coverage'] > 0): ?>
                <div class="total-row">
                    <span>PhilHealth Coverage:</span>
                    <span>-₱<?php echo number_format($receipt_data['amounts']['philhealth_coverage'], 2); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="total-row final">
                    <span>TOTAL AMOUNT:</span>
                    <span>₱<?php echo number_format($receipt_data['amounts']['total'], 2); ?></span>
                </div>
                
                <div class="total-row">
                    <span>Amount Paid:</span>
                    <span>₱<?php echo number_format($receipt_data['amounts']['amount_paid'], 2); ?></span>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer-section">
                <p><strong>Thank you for your payment!</strong></p>
                <p>This receipt serves as your official record of payment.</p>
                <p>For inquiries, please contact us at (083) 228-8045</p>
                <p style="margin-top: 15px; font-size: 10px;">
                    Generated on <?php echo date('F d, Y \a\t h:i A'); ?>
                </p>
            </div>
        </div>
        
        <!-- Print Controls -->
        <div class="no-print">
            <button class="print-btn" onclick="window.print()">Print Receipt</button>
            <button class="print-btn" onclick="window.close()" style="background: #6c757d;">Close</button>
        </div>
        
        <script>
            // Auto-focus for better accessibility
            document.addEventListener('DOMContentLoaded', function() {
                // Auto-print if requested
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('auto_print') === '1') {
                    setTimeout(() => window.print(), 500);
                }
            });
        </script>
    </body>
    </html>
    <?php
}

/**
 * Generate printable receipt for management interface
 */
function generatePrintableReceipt($receipt_data) {
    // Use the same HTML receipt generator but with management-specific styling
    generateHTMLReceipt($receipt_data);
}

/**
 * Generate simple text receipt for legacy systems
 */
function generateTextReceipt($receipt_data) {
    $text = "=====================================\n";
    $text .= "    CITY HEALTH OFFICE KORONADAL    \n";
    $text .= "         OFFICIAL RECEIPT           \n";
    $text .= "=====================================\n\n";
    
    $text .= "Receipt #: " . $receipt_data['receipt_number'] . "\n";
    $text .= "Date: " . date('F d, Y h:i A', strtotime($receipt_data['payment']['date'])) . "\n\n";
    
    $text .= "Patient: " . $receipt_data['patient']['name'] . "\n";
    $text .= "Patient #: " . $receipt_data['patient']['patient_number'] . "\n\n";
    
    $text .= "SERVICES:\n";
    $text .= "-------------------------------------\n";
    
    foreach ($receipt_data['items'] as $item) {
        $text .= sprintf("%-20s %3d x %8s = %8s\n", 
            substr($item['item_name'], 0, 20),
            $item['quantity'],
            number_format($item['unit_price'], 2),
            number_format($item['subtotal'], 2)
        );
    }
    
    $text .= "-------------------------------------\n";
    $text .= sprintf("Subtotal: %23s\n", number_format($receipt_data['amounts']['subtotal'], 2));
    
    if ($receipt_data['amounts']['discount'] > 0) {
        $text .= sprintf("Discount: %23s\n", number_format($receipt_data['amounts']['discount'], 2));
    }
    
    if ($receipt_data['amounts']['philhealth_coverage'] > 0) {
        $text .= sprintf("PhilHealth: %21s\n", number_format($receipt_data['amounts']['philhealth_coverage'], 2));
    }
    
    $text .= "=====================================\n";
    $text .= sprintf("TOTAL: %26s\n", number_format($receipt_data['amounts']['total'], 2));
    $text .= sprintf("PAID: %27s\n", number_format($receipt_data['amounts']['amount_paid'], 2));
    $text .= "=====================================\n\n";
    
    $text .= "Payment Method: " . ucfirst($receipt_data['payment']['method']) . "\n";
    $text .= "Cashier: " . $receipt_data['cashier'] . "\n\n";
    
    $text .= "Thank you for your payment!\n";
    
    return $text;
}

/**
 * Validate receipt data structure
 */
function validateReceiptData($receipt_data) {
    $required_fields = [
        'receipt_number',
        'patient',
        'items',
        'amounts',
        'payment',
        'cashier'
    ];
    
    foreach ($required_fields as $field) {
        if (!isset($receipt_data[$field])) {
            throw new Exception("Missing required receipt field: $field");
        }
    }
    
    if (empty($receipt_data['items']) || !is_array($receipt_data['items'])) {
        throw new Exception("Receipt must have at least one item");
    }
    
    return true;
}
?>