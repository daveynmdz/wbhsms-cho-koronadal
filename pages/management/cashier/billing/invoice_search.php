<?php
// Invoice Search - Advanced Search and Filtering Interface
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

// Get filter options for dropdowns
try {
    // Get service types for filtering
    $stmt = $pdo->query("SELECT DISTINCT category FROM service_items WHERE category IS NOT NULL ORDER BY category");
    $service_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get payment methods
    $payment_methods = [
        'cash' => 'Cash',
        'check' => 'Check', 
        'card' => 'Credit/Debit Card',
        'bank_transfer' => 'Bank Transfer'
    ];
    
    // Get recent statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_invoices,
            COUNT(CASE WHEN payment_status = 'unpaid' THEN 1 END) as unpaid_count,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN payment_status = 'exempted' THEN 1 END) as exempted_count,
            COALESCE(SUM(CASE WHEN payment_status = 'unpaid' THEN total_amount END), 0) as outstanding_amount
        FROM billing 
        WHERE billing_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error loading search data: " . $e->getMessage());
    $service_categories = [];
    $stats = ['total_invoices' => 0, 'unpaid_count' => 0, 'paid_count' => 0, 'exempted_count' => 0, 'outstanding_amount' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Search - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <style>
        .search-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-card.total .stat-value { color: #007bff; }
        .stat-card.unpaid .stat-value { color: #dc3545; }
        .stat-card.paid .stat-value { color: #28a745; }
        .stat-card.exempted .stat-value { color: #6f42c1; }
        .stat-card.outstanding .stat-value { color: #fd7e14; }
        
        .search-panel {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .panel-header {
            background: #007bff;
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-content {
            padding: 1.5rem;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
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
        
        .btn-outline {
            background: transparent;
            color: #007bff;
            border: 1px solid #007bff;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .search-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .results-panel {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .results-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .results-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .results-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .results-table th,
        .results-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            position: relative;
        }
        
        .results-table th:hover {
            background: #e9ecef;
        }
        
        .results-table th.sortable::after {
            content: '\f0dc';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 8px;
            opacity: 0.5;
        }
        
        .results-table th.sort-asc::after {
            content: '\f0de';
            opacity: 1;
        }
        
        .results-table th.sort-desc::after {
            content: '\f0dd';
            opacity: 1;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-unpaid {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-exempted {
            background: #cce5ff;
            color: #004085;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 3px;
            text-decoration: none;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            border-top: 1px solid #dee2e6;
        }
        
        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }
        
        .pagination-btn:hover {
            background: #f8f9fa;
        }
        
        .pagination-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .loading {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .filter-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .filter-tag .remove {
            cursor: pointer;
            opacity: 0.7;
        }
        
        .filter-tag .remove:hover {
            opacity: 1;
        }
        
        .export-options {
            position: relative;
            display: inline-block;
        }
        
        .export-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .export-dropdown.show {
            display: block;
        }
        
        .export-option {
            display: block;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: #333;
            border-bottom: 1px solid #eee;
        }
        
        .export-option:last-child {
            border-bottom: none;
        }
        
        .export-option:hover {
            background: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .search-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .results-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .results-table {
                font-size: 0.9rem;
            }
            
            .results-table th,
            .results-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
<div class="homepage">
    <div style="margin-left: 260px; padding: 20px; min-height: 100vh; background-color: #f5f5f5;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h1 style="margin: 0; font-size: 1.8rem; font-weight: 600;"><i class="fas fa-search"></i> Invoice Search</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 1rem;"><?php echo $employee_role === 'admin' ? 'Administrative invoice search and financial record management' : 'Search and view patient invoices and billing history'; ?></p>
        </div>
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
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-value"><?php echo number_format($stats['total_invoices']); ?></div>
                    <div class="stat-label">Total Invoices (30 days)</div>
                </div>
                
                <div class="stat-card unpaid">
                    <div class="stat-value"><?php echo number_format($stats['unpaid_count']); ?></div>
                    <div class="stat-label">Unpaid</div>
                </div>
                
                <div class="stat-card paid">
                    <div class="stat-value"><?php echo number_format($stats['paid_count']); ?></div>
                    <div class="stat-label">Paid</div>
                </div>
                
                <div class="stat-card exempted">
                    <div class="stat-value"><?php echo number_format($stats['exempted_count']); ?></div>
                    <div class="stat-label">Exempted</div>
                </div>
                
                <div class="stat-card outstanding">
                    <div class="stat-value"><?php echo number_format($stats['outstanding_amount'], 2); ?></div>
                    <div class="stat-label">Outstanding Amount</div>
                </div>
            </div>
            
            <!-- Search Panel -->
            <div class="search-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-search"></i> Advanced Invoice Search</h3>
                    <button type="button" class="btn btn-outline" onclick="resetFilters()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
                
                <div class="panel-content">
                    <form id="searchForm" onsubmit="performSearch(event)">
                        <div class="search-form">
                            <div class="form-group">
                                <label for="patientSearch">Patient Name/ID</label>
                                <input type="text" id="patientSearch" class="form-control" placeholder="Enter patient name or ID...">
                            </div>
                            
                            <div class="form-group">
                                <label for="invoiceNumber">Invoice Number</label>
                                <input type="text" id="invoiceNumber" class="form-control" placeholder="Invoice number...">
                            </div>
                            
                            <div class="form-group">
                                <label for="paymentStatus">Payment Status</label>
                                <select id="paymentStatus" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="unpaid">Unpaid</option>
                                    <option value="paid">Paid</option>
                                    <option value="exempted">Exempted</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="serviceCategory">Service Category</label>
                                <select id="serviceCategory" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($service_categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="dateFrom">Date From</label>
                                <input type="date" id="dateFrom" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="dateTo">Date To</label>
                                <input type="date" id="dateTo" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="amountMin">Min Amount</label>
                                <input type="number" id="amountMin" class="form-control" min="0" step="0.01" placeholder="0.00">
                            </div>
                            
                            <div class="form-group">
                                <label for="amountMax">Max Amount</label>
                                <input type="number" id="amountMax" class="form-control" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        
                        <div class="filter-tags" id="filterTags"></div>
                        
                        <div class="search-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search Invoices
                            </button>
                            
                            <button type="button" class="btn btn-outline" onclick="saveSearch()">
                                <i class="fas fa-bookmark"></i> Save Search
                            </button>
                            
                            <div class="export-options">
                                <button type="button" class="btn btn-success" onclick="toggleExportDropdown()">
                                    <i class="fas fa-download"></i> Export Results
                                </button>
                                <div class="export-dropdown" id="exportDropdown">
                                    <a href="#" class="export-option" onclick="exportResults('csv')">
                                        <i class="fas fa-file-csv"></i> Export as CSV
                                    </a>
                                    <a href="#" class="export-option" onclick="exportResults('pdf')">
                                        <i class="fas fa-file-pdf"></i> Export as PDF
                                    </a>
                                    <a href="#" class="export-option" onclick="exportResults('excel')">
                                        <i class="fas fa-file-excel"></i> Export as Excel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Results Panel -->
            <div class="results-panel">
                <div class="results-header">
                    <div class="results-info">
                        <h4 id="resultsTitle">Search Results</h4>
                        <span id="resultsCount">No search performed yet</span>
                    </div>
                    <div class="results-actions">
                        <select id="pageSize" class="form-control" style="width: auto;" onchange="changePageSize()">
                            <option value="10">10 per page</option>
                            <option value="25" selected>25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                </div>
                
                <div id="resultsContainer">
                    <div class="no-results">
                        <i class="fas fa-search" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                        <h4>No Search Performed</h4>
                        <p>Use the search form above to find invoices</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <script>
        let currentPage = 1;
        let currentSort = { column: 'billing_date', direction: 'desc' };
        let searchResults = [];
        let totalResults = 0;
        
        function performSearch(event) {
            if (event) event.preventDefault();
            
            currentPage = 1;
            executeSearch();
        }
        
        async function executeSearch() {
            const formData = new FormData(document.getElementById('searchForm'));
            const params = new URLSearchParams();
            
            // Add form parameters
            const searchParams = {
                patient_search: document.getElementById('patientSearch').value.trim(),
                invoice_number: document.getElementById('invoiceNumber').value.trim(),
                payment_status: document.getElementById('paymentStatus').value,
                service_category: document.getElementById('serviceCategory').value,
                date_from: document.getElementById('dateFrom').value,
                date_to: document.getElementById('dateTo').value,
                amount_min: document.getElementById('amountMin').value,
                amount_max: document.getElementById('amountMax').value,
                page: currentPage,
                limit: document.getElementById('pageSize').value,
                sort_column: currentSort.column,
                sort_direction: currentSort.direction
            };
            
            // Only add non-empty parameters
            Object.keys(searchParams).forEach(key => {
                if (searchParams[key]) {
                    params.append(key, searchParams[key]);
                }
            });
            
            showLoading();
            updateFilterTags();
            
            try {
                const response = await fetch(`../../../../api/billing/management/search_invoices.php?${params}`);
                const result = await response.json();
                
                if (result.success) {
                    searchResults = result.data;
                    totalResults = result.total || result.data.length;
                    displayResults();
                } else {
                    showError('Search failed: ' + result.message);
                }
            } catch (error) {
                console.error('Search error:', error);
                showError('Search failed. Please try again.');
            }
        }
        
        function showLoading() {
            document.getElementById('resultsContainer').innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <h4>Searching...</h4>
                    <p>Please wait while we search for invoices</p>
                </div>
            `;
        }
        
        function showError(message) {
            document.getElementById('resultsContainer').innerHTML = `
                <div class="no-results">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem; color: #dc3545;"></i>
                    <h4>Search Error</h4>
                    <p>${message}</p>
                </div>
            `;
        }
        
        function displayResults() {
            const container = document.getElementById('resultsContainer');
            const pageSize = parseInt(document.getElementById('pageSize').value);
            
            // Update results count
            document.getElementById('resultsCount').textContent = 
                `${searchResults.length} results found ${totalResults > searchResults.length ? '(showing page ' + currentPage + ')' : ''}`;
            
            if (searchResults.length === 0) {
                container.innerHTML = `
                    <div class="no-results">
                        <i class="fas fa-search" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                        <h4>No Results Found</h4>
                        <p>No invoices match your search criteria. Try adjusting your filters.</p>
                    </div>
                `;
                return;
            }
            
            const tableHTML = `
                <table class="results-table">
                    <thead>
                        <tr>
                            <th class="sortable" onclick="sortBy('billing_id')">
                                Invoice # ${getSortIcon('billing_id')}
                            </th>
                            <th class="sortable" onclick="sortBy('patient_name')">
                                Patient ${getSortIcon('patient_name')}
                            </th>
                            <th class="sortable" onclick="sortBy('billing_date')">
                                Date ${getSortIcon('billing_date')}
                            </th>
                            <th class="sortable" onclick="sortBy('total_amount')">
                                Total Amount ${getSortIcon('total_amount')}
                            </th>
                            <th class="sortable" onclick="sortBy('balance_due')">
                                Balance Due ${getSortIcon('balance_due')}
                            </th>
                            <th class="sortable" onclick="sortBy('payment_status')">
                                Status ${getSortIcon('payment_status')}
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${searchResults.map(invoice => `
                            <tr>
                                <td>${String(invoice.billing_id).padStart(6, '0')}</td>
                                <td>
                                    <strong>${invoice.patient_name}</strong><br>
                                    <small>ID: ${invoice.patient_id}</small>
                                </td>
                                <td>${formatDate(invoice.billing_date)}</td>
                                <td>${Number(invoice.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                <td>${Number(invoice.balance_due || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                <td>
                                    <span class="status-badge status-${invoice.payment_status}">
                                        ${invoice.payment_status.toUpperCase()}
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="../../../../pages/patient/billing/invoice_details.php?billing_id=${invoice.billing_id}" 
                                           class="action-btn btn-primary" target="_blank" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        ${invoice.payment_status === 'unpaid' ? `
                                            <a href="process_payment.php?billing_id=${invoice.billing_id}" 
                                               class="action-btn btn-success" title="Process Payment">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
                                        ` : ''}
                                        ${invoice.has_receipt ? `
                                            <a href="print_receipt.php?billing_id=${invoice.billing_id}" 
                                               class="action-btn btn-outline" target="_blank" title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                ${generatePagination()}
            `;
            
            container.innerHTML = tableHTML;
        }
        
        function getSortIcon(column) {
            if (currentSort.column === column) {
                return currentSort.direction === 'asc' 
                    ? '<i class="fas fa-sort-up"></i>' 
                    : '<i class="fas fa-sort-down"></i>';
            }
            return '<i class="fas fa-sort"></i>';
        }
        
        function sortBy(column) {
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }
            
            executeSearch();
        }
        
        function generatePagination() {
            const pageSize = parseInt(document.getElementById('pageSize').value);
            const totalPages = Math.ceil(totalResults / pageSize);
            
            if (totalPages <= 1) return '';
            
            let paginationHTML = '<div class="pagination">';
            
            // Previous button
            paginationHTML += `
                <button class="pagination-btn" onclick="changePage(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
            `;
            
            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                paginationHTML += `<button class="pagination-btn" onclick="changePage(1)">1</button>`;
                if (startPage > 2) {
                    paginationHTML += `<span>...</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `
                    <button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">
                        ${i}
                    </button>
                `;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHTML += `<span>...</span>`;
                }
                paginationHTML += `<button class="pagination-btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
            }
            
            // Next button
            paginationHTML += `
                <button class="pagination-btn" onclick="changePage(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            `;
            
            paginationHTML += '</div>';
            return paginationHTML;
        }
        
        function changePage(page) {
            const pageSize = parseInt(document.getElementById('pageSize').value);
            const totalPages = Math.ceil(totalResults / pageSize);
            
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                executeSearch();
            }
        }
        
        function changePageSize() {
            currentPage = 1;
            executeSearch();
        }
        
        function updateFilterTags() {
            const tags = [];
            const formElements = {
                'patientSearch': 'Patient',
                'invoiceNumber': 'Invoice',
                'paymentStatus': 'Status',
                'serviceCategory': 'Category',
                'dateFrom': 'From',
                'dateTo': 'To',
                'amountMin': 'Min Amount',
                'amountMax': 'Max Amount'
            };
            
            Object.keys(formElements).forEach(id => {
                const value = document.getElementById(id).value;
                if (value) {
                    tags.push({
                        label: formElements[id],
                        value: value,
                        id: id
                    });
                }
            });
            
            const container = document.getElementById('filterTags');
            container.innerHTML = tags.map(tag => `
                <div class="filter-tag">
                    <span>${tag.label}: ${tag.value}</span>
                    <span class="remove" onclick="removeFilter('${tag.id}')">&times;</span>
                </div>
            `).join('');
        }
        
        function removeFilter(fieldId) {
            document.getElementById(fieldId).value = '';
            updateFilterTags();
            executeSearch();
        }
        
        function resetFilters() {
            document.getElementById('searchForm').reset();
            currentPage = 1;
            currentSort = { column: 'billing_date', direction: 'desc' };
            updateFilterTags();
            
            // Show initial state
            document.getElementById('resultsContainer').innerHTML = `
                <div class="no-results">
                    <i class="fas fa-search" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                    <h4>No Search Performed</h4>
                    <p>Use the search form above to find invoices</p>
                </div>
            `;
            document.getElementById('resultsCount').textContent = 'No search performed yet';
        }
        
        function toggleExportDropdown() {
            const dropdown = document.getElementById('exportDropdown');
            dropdown.classList.toggle('show');
        }
        
        async function exportResults(format) {
            document.getElementById('exportDropdown').classList.remove('show');
            
            if (searchResults.length === 0) {
                alert('No results to export. Please perform a search first.');
                return;
            }
            
            try {
                const formData = new FormData(document.getElementById('searchForm'));
                const params = new URLSearchParams();
                
                // Add current search parameters
                const searchParams = {
                    patient_search: document.getElementById('patientSearch').value.trim(),
                    invoice_number: document.getElementById('invoiceNumber').value.trim(),
                    payment_status: document.getElementById('paymentStatus').value,
                    service_category: document.getElementById('serviceCategory').value,
                    date_from: document.getElementById('dateFrom').value,
                    date_to: document.getElementById('dateTo').value,
                    amount_min: document.getElementById('amountMin').value,
                    amount_max: document.getElementById('amountMax').value,
                    format: format,
                    export: 'true'
                };
                
                Object.keys(searchParams).forEach(key => {
                    if (searchParams[key]) {
                        params.append(key, searchParams[key]);
                    }
                });
                
                // Create download link
                const url = `../../../../api/billing/management/search_invoices.php?${params}`;
                const link = document.createElement('a');
                link.href = url;
                link.download = `invoices_export_${new Date().toISOString().slice(0,10)}.${format}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
            } catch (error) {
                console.error('Export error:', error);
                alert('Export failed. Please try again.');
            }
        }
        
        function saveSearch() {
            const searchParams = {
                patient_search: document.getElementById('patientSearch').value.trim(),
                invoice_number: document.getElementById('invoiceNumber').value.trim(),
                payment_status: document.getElementById('paymentStatus').value,
                service_category: document.getElementById('serviceCategory').value,
                date_from: document.getElementById('dateFrom').value,
                date_to: document.getElementById('dateTo').value,
                amount_min: document.getElementById('amountMin').value,
                amount_max: document.getElementById('amountMax').value
            };
            
            const searchName = prompt('Enter a name for this saved search:');
            if (searchName) {
                try {
                    const savedSearches = JSON.parse(localStorage.getItem('billing_saved_searches') || '[]');
                    savedSearches.push({
                        name: searchName,
                        params: searchParams,
                        created: new Date().toISOString()
                    });
                    localStorage.setItem('billing_saved_searches', JSON.stringify(savedSearches));
                    alert('Search saved successfully!');
                } catch (error) {
                    alert('Failed to save search.');
                }
            }
        }
        
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        // Close export dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const exportOptions = document.querySelector('.export-options');
            if (!exportOptions.contains(event.target)) {
                document.getElementById('exportDropdown').classList.remove('show');
            }
        });
        
        // Auto-dismiss alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
        
        // Initialize with today's data
        document.addEventListener('DOMContentLoaded', function() {
            // Set default date range to last 30 days
            const today = new Date();
            const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
            
            document.getElementById('dateFrom').value = thirtyDaysAgo.toISOString().slice(0, 10);
            document.getElementById('dateTo').value = today.toISOString().slice(0, 10);
            
            // Perform initial search
            executeSearch();
        });
    </script>
</body>
</html>
