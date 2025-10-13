<?php
/**
 * Patient Billing History - View complete billing history with filters
 */

// Start output buffering immediately to prevent any header issues
ob_start();

// Clean any potential output that might have been sent
if (ob_get_length()) {
    ob_clean();
}

// Set error reporting for debugging but don't display errors in production
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Never show errors to users in production
ini_set('log_errors', '1');      // Log errors for debugging

// Include patient session configuration
$root_path = dirname(dirname(dirname(__DIR__)));

// Load configuration first
require_once $root_path . '/config/env.php';

// Load database connection
require_once $root_path . '/config/db.php';

// Then load session management
require_once $root_path . '/config/session/patient_session.php';

// Ensure session is properly started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!is_patient_logged_in()) {
    // Clear any output buffer content
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Check if headers can still be sent
    if (!headers_sent()) {
        header("Location: ../auth/patient_login.php");
        exit();
    } else {
        // Fallback if headers already sent
        echo '<script>window.location.href = "../auth/patient_login.php";</script>';
        exit();
    }
}

// Get patient information
$patient_id = get_patient_session('patient_id');
$patient_name = get_patient_session('first_name') . ' ' . get_patient_session('last_name');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing History - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/topbar.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #495057;
        }

        .history-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }

        .page-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .page-header p {
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .filters-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            color: #28a745;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .filter-input {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: #28a745;
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #28a745;
            color: white;
        }

        .btn-primary:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #6c757d;
            color: #6c757d;
        }

        .btn-outline:hover {
            background: #6c757d;
            color: white;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem auto;
            font-size: 1.2rem;
            color: white;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }

        .stat-icon.paid {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }

        .stat-icon.unpaid {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .stat-icon.average {
            background: linear-gradient(135deg, #fd7e14, #e66a00);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 0.25rem 0;
            color: #495057;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin: 0;
        }

        .history-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
            font-size: 1.5rem;
            color: #28a745;
            font-weight: 600;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .history-table th,
        .history-table td {
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .history-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
        }

        .history-table td {
            color: #6c757d;
        }

        .history-table .amount-cell {
            text-align: right;
            font-weight: 600;
            color: #495057;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }

        .status-partial {
            background: #fff3cd;
            color: #856404;
        }

        .bill-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #dee2e6;
            background: white;
            color: #6c757d;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .page-btn:hover {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .page-btn.active {
            background: #28a745;
            color: white;
            border-color: #28a745;
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

        .loading-state {
            text-align: center;
            padding: 3rem;
        }

        .loading-state i {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .history-container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .filters-section {
                padding: 1.5rem;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: stretch;
                flex-direction: column;
            }

            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .history-table {
                font-size: 0.85rem;
            }

            .history-table th,
            .history-table td {
                padding: 0.75rem 0.5rem;
            }

            .bill-actions {
                flex-direction: column;
                gap: 0.25rem;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }
    </style>
</head>

<body>
    <?php
    include '../../../includes/topbar.php';
    echo renderTopbar([
        'title' => 'Billing History',
        'back_url' => 'billing.php',
        'user_type' => 'patient'
    ]);
    ?>

    <section class="homepage">
        <div class="history-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-history" style="margin-right: 0.5rem;"></i>Billing History</h1>
                <p>Complete history of your healthcare bills and payments</p>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filters-header">
                    <i class="fas fa-filter"></i>
                    Filter Bills
                </div>
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Date From</label>
                        <input type="date" class="filter-input" id="dateFrom">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Date To</label>
                        <input type="date" class="filter-input" id="dateTo">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-input" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial Payment</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Amount Range</label>
                        <select class="filter-input" id="amountRange">
                            <option value="">All Amounts</option>
                            <option value="0-100">₱0 - ₱100</option>
                            <option value="101-500">₱101 - ₱500</option>
                            <option value="501-1000">₱501 - ₱1,000</option>
                            <option value="1000+">Above ₱1,000</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button class="btn btn-outline" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                    <button class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="summary-stats" id="summaryStats">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-value" id="totalBills">0</div>
                    <div class="stat-label">Total Bills</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon paid">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value" id="paidAmount">₱0.00</div>
                    <div class="stat-label">Total Paid</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon unpaid">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-value" id="unpaidAmount">₱0.00</div>
                    <div class="stat-label">Outstanding</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon average">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value" id="averageAmount">₱0.00</div>
                    <div class="stat-label">Average Bill</div>
                </div>
            </div>

            <!-- History Section -->
            <div class="history-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-file-invoice"></i>
                        Complete Billing History
                    </h2>
                    <button class="btn btn-outline" onclick="exportHistory()">
                        <i class="fas fa-download"></i> Export History
                    </button>
                </div>

                <div id="historyContent">
                    <!-- Loading state initially -->
                    <div class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <h3>Loading billing history...</h3>
                        <p>Please wait while we fetch your complete billing records.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Global variables
        let currentPage = 1;
        let totalPages = 1;
        let currentFilters = {};

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadBillingHistory();
        });

        // Load billing history with current filters
        async function loadBillingHistory(page = 1) {
            try {
                currentPage = page;
                
                // Build query parameters
                const params = new URLSearchParams({
                    page: page,
                    limit: 20,
                    ...currentFilters
                });

                const response = await fetch(`/wbhsms-cho-koronadal-1/api/billing/patient/get_patient_invoices.php?${params}`);
                const result = await response.json();

                if (result.success) {
                    updateSummaryStats(result.data.summary);
                    displayBillingHistory(result.data.invoices);
                    updatePagination(result.data.pagination);
                } else {
                    showError('Failed to load billing history: ' + result.message);
                }
            } catch (error) {
                console.error('Error loading billing history:', error);
                showError('Unable to load billing history. Please try again later.');
            }
        }

        // Update summary statistics
        function updateSummaryStats(summary) {
            document.getElementById('totalBills').textContent = summary.total_invoices;
            document.getElementById('paidAmount').textContent = 
                '₱' + Number(summary.paid_this_year).toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('unpaidAmount').textContent = 
                '₱' + Number(summary.total_outstanding).toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('averageAmount').textContent = 
                '₱' + Number(summary.average_bill).toLocaleString('en-PH', {minimumFractionDigits: 2});
        }

        // Display billing history table
        function displayBillingHistory(bills) {
            const container = document.getElementById('historyContent');

            if (bills.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No Bills Found</h3>
                        <p>No bills match your current filter criteria.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Services</th>
                            <th style="text-align: right;">Amount</th>
                            <th>Status</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${bills.map(bill => `
                            <tr>
                                <td><strong>#${String(bill.billing_id).padStart(6, '0')}</strong></td>
                                <td>${formatDate(bill.billing_date)}</td>
                                <td>${bill.item_count} service${bill.item_count !== 1 ? 's' : ''}</td>
                                <td class="amount-cell">₱${Number(bill.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                <td>
                                    <span class="status-badge status-${bill.payment_status.toLowerCase()}">
                                        ${bill.payment_status.toUpperCase()}
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div class="bill-actions">
                                        <button class="btn btn-outline btn-sm" onclick="viewBillDetails(${bill.billing_id})">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        ${bill.has_receipt ? `
                                            <button class="btn btn-primary btn-sm" onclick="downloadReceipt(${bill.billing_id})">
                                                <i class="fas fa-download"></i> Receipt
                                            </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        // Update pagination
        function updatePagination(pagination) {
            totalPages = pagination.total_pages;
            
            if (totalPages <= 1) return;

            const container = document.getElementById('historyContent');
            const paginationHtml = `
                <div class="pagination">
                    ${currentPage > 1 ? `<a href="#" class="page-btn" onclick="loadBillingHistory(${currentPage - 1})">&laquo; Previous</a>` : ''}
                    
                    ${Array.from({length: Math.min(5, totalPages)}, (_, i) => {
                        const page = i + Math.max(1, currentPage - 2);
                        const isActive = page === currentPage ? 'active' : '';
                        return `<a href="#" class="page-btn ${isActive}" onclick="loadBillingHistory(${page})">${page}</a>`;
                    }).join('')}
                    
                    ${currentPage < totalPages ? `<a href="#" class="page-btn" onclick="loadBillingHistory(${currentPage + 1})">Next &raquo;</a>` : ''}
                </div>
            `;
            
            container.innerHTML += paginationHtml;
        }

        // Apply filters
        function applyFilters() {
            currentFilters = {
                date_from: document.getElementById('dateFrom').value,
                date_to: document.getElementById('dateTo').value,
                status: document.getElementById('statusFilter').value,
                amount_range: document.getElementById('amountRange').value
            };

            // Remove empty filters
            Object.keys(currentFilters).forEach(key => {
                if (!currentFilters[key]) {
                    delete currentFilters[key];
                }
            });

            loadBillingHistory(1);
        }

        // Clear all filters
        function clearFilters() {
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('amountRange').value = '';
            
            currentFilters = {};
            loadBillingHistory(1);
        }

        // View bill details
        function viewBillDetails(billingId) {
            window.open(`invoice_details.php?billing_id=${billingId}`, '_blank', 'width=800,height=600');
        }

        // Download receipt
        function downloadReceipt(billingId) {
            window.open(`/wbhsms-cho-koronadal-1/api/billing/patient/download_receipt.php?billing_id=${billingId}&format=html`, '_blank');
        }

        // Export history
        function exportHistory() {
            const params = new URLSearchParams(currentFilters);
            params.set('format', 'pdf');
            window.open(`/wbhsms-cho-koronadal-1/api/billing/patient/get_patient_invoices.php?${params}`, '_blank');
        }

        // Show error state
        function showError(message) {
            const container = document.getElementById('historyContent');
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
                    <h3>Error</h3>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="loadBillingHistory()" style="margin-top: 1rem;">
                        <i class="fas fa-refresh"></i> Try Again
                    </button>
                </div>
            `;
        }

        // Utility functions
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>