<?php
/**
 * Patient Search & Check-In Interface
 * Integrated with Check-In Station for accepting bookings
 * Follows patient flow routing per patient-flow_Version2.md
 */

// Include employee session configuration first
require_once '../../config/session/employee_session.php';
require_once '../../config/db.php';

// Access Control
$allowed_roles = ['admin', 'records_officer', 'dho', 'bhw'];
$user_role = $_SESSION['role'] ?? '';
$employee_id = $_SESSION['employee_id'] ?? 0;

if (!isset($_SESSION['employee_id']) || !in_array($user_role, $allowed_roles)) {
    header('Location: ../../pages/management/auth/employee_login.php');
    exit();
}

// Initialize variables
$today = date('Y-m-d');
$message = '';
$error = '';

// Get today's appointment statistics
$stats = ['total' => 0, 'checked_in' => 0, 'pending' => 0];

try {
    // Total appointments today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_date) = ? AND facility_id = 1");
    $stmt->execute([$today]);
    $stats['total'] = $stmt->fetchColumn();
    
    // Checked-in patients today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments a
        JOIN visits v ON a.appointment_id = v.appointment_id
        WHERE DATE(a.scheduled_date) = ? AND a.facility_id = 1 AND a.status = 'checked_in'
    ");
    $stmt->execute([$today]);
    $stats['checked_in'] = $stmt->fetchColumn();
    
    $stats['pending'] = $stats['total'] - $stats['checked_in'];
} catch (Exception $e) {
    error_log("Statistics error: " . $e->getMessage());
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Search & Check-In - CHO Koronadal</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        .search-container {
            --cho-primary: #0077b6;
            --cho-primary-dark: #03045e;
            --cho-success: #2d6a4f;
            --cho-warning: #ffc107;
            --cho-danger: #d00000;
            --cho-light: #f8f9fa;
            --cho-border: #dee2e6;
            --cho-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --cho-border-radius: 0.5rem;
            
            padding: 20px;
            background: #f5f6fa;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: var(--cho-border-radius);
            box-shadow: var(--cho-shadow);
        }

        .page-title {
            color: var(--cho-primary);
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-badges {
            display: flex;
            gap: 15px;
        }

        .stat-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .stat-badge.total { background: #e3f2fd; color: #1565c0; }
        .stat-badge.checked-in { background: #e8f5e8; color: #2e7d32; }
        .stat-badge.pending { background: #fff3e0; color: #f57c00; }

        .search-section {
            background: white;
            border-radius: var(--cho-border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--cho-shadow);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--cho-primary);
            color: var(--cho-primary-dark);
            font-weight: 600;
            font-size: 1.2rem;
        }

        .search-form {
            display: grid;
            grid-template-columns: 200px 1fr 150px auto;
            gap: 15px;
            align-items: end;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
            font-size: 0.9rem;
        }

        .form-control {
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--cho-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 182, 0.25);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-primary {
            background: var(--cho-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--cho-primary-dark);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--cho-success);
            color: white;
        }

        .btn-success:hover {
            background: #1e5631;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: var(--cho-warning);
            color: #333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .search-results {
            background: white;
            border-radius: var(--cho-border-radius);
            padding: 25px;
            box-shadow: var(--cho-shadow);
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .results-table th,
        .results-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .results-table th {
            background: var(--cho-light);
            font-weight: 600;
            color: var(--cho-primary-dark);
            position: sticky;
            top: 0;
        }

        .results-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-checked-in {
            background: #cce5ff;
            color: #004085;
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.4rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 5px;
        }

        .priority-senior {
            background: #fff3cd;
            color: #856404;
        }

        .priority-pwd {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left-width: 4px;
            border-left-style: solid;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: var(--cho-border-radius);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            background: var(--cho-primary);
            color: white;
            border-radius: var(--cho-border-radius) var(--cho-border-radius) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            margin: 0;
            font-size: 1.25rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 25px;
        }

        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .detail-value {
            font-weight: 500;
            color: #333;
        }

        .philhealth-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--cho-primary);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0;
        }

        .checkbox-group input[type="checkbox"] {
            transform: scale(1.2);
        }

        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .stats-badges {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php 
    $sidebar_file = "../../includes/sidebar_" . strtolower(str_replace(' ', '_', $user_role)) . ".php";
    if (file_exists($sidebar_file)) {
        include $sidebar_file;
    } else {
        include "../../includes/sidebar_admin.php";
    }
    ?>
    
    <main class="homepage">
        <div class="search-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-search"></i>
                    Patient Search & Check-In
                </h1>
                <div class="stats-badges">
                    <span class="stat-badge total"><?php echo number_format($stats['total']); ?> Total Today</span>
                    <span class="stat-badge checked-in"><?php echo number_format($stats['checked_in']); ?> Checked-In</span>
                    <span class="stat-badge pending"><?php echo number_format($stats['pending']); ?> Pending</span>
                </div>
            </div>

            <!-- Alert Messages -->
            <div id="alertContainer"></div>

            <!-- Quick Actions -->
            <div class="search-section">
                <div class="section-header">
                    <i class="fas fa-bolt"></i>
                    <span>Quick Actions</span>
                </div>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="checkin_new.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Station Grid</span>
                    </a>
                    <button class="btn btn-secondary" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh Data</span>
                    </button>
                </div>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="section-header">
                    <i class="fas fa-search"></i>
                    <span>Search Patients</span>
                </div>
                
                <form id="searchForm" class="search-form">
                    <div class="form-group">
                        <label class="form-label">Search By</label>
                        <select name="search_type" class="form-control" id="searchType">
                            <option value="appointment_id">Appointment ID</option>
                            <option value="patient_id">Patient ID</option>
                            <option value="patient_number">Patient Number</option>
                            <option value="last_name">Last Name</option>
                            <option value="philhealth">PhilHealth ID</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Search Term</label>
                        <input type="text" name="search_term" class="form-control" id="searchTerm" placeholder="Enter search term..." required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" name="appointment_date" class="form-control" value="<?php echo $today; ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            <span>Search</span>
                        </button>
                    </div>
                </form>

                <div style="margin-top: 15px; padding: 15px; background: #e3f2fd; border-radius: 6px; border-left: 4px solid var(--cho-primary);">
                    <p style="margin: 0; font-size: 0.9rem; color: #1565c0;">
                        <strong>Search Tips:</strong> 
                        For Appointment ID, you can use either "APT-00000024" or just "24". 
                        Use partial names for patient search (e.g., "Dela" will find "Dela Cruz").
                    </p>
                </div>
            </div>

            <!-- Search Results -->
            <div class="search-results" id="searchResults" style="display: none;">
                <div class="section-header">
                    <i class="fas fa-list"></i>
                    <span>Search Results</span>
                </div>
                
                <div id="resultsContent">
                    <!-- Results will be loaded here -->
                </div>
            </div>
        </div>
    </main>

    <!-- Check-In Confirmation Modal -->
    <div id="checkinModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-check"></i>
                    Confirm Patient Check-In
                </h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="patientDetailsContainer">
                    <!-- Patient details will be loaded here -->
                </div>
                
                <div class="philhealth-section">
                    <h4 style="margin: 0 0 15px 0; color: var(--cho-primary);">
                        <i class="fas fa-id-card"></i>
                        PhilHealth Verification
                    </h4>
                    <p style="margin-bottom: 15px; font-size: 0.9rem;">
                        Please verify the patient's PhilHealth membership status. This affects their routing through the system.
                    </p>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="philhealthVerified">
                        <label for="philhealthVerified" style="font-weight: 600;">
                            PhilHealth membership verified and active
                        </label>
                    </div>
                    
                    <div style="margin-top: 10px; font-size: 0.85rem; color: #6c757d;">
                        <strong>Note:</strong> PhilHealth members skip billing for covered services.
                        Non-members will be routed through billing for payment.
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: end; margin-top: 25px;">
                    <button class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button class="btn btn-success" onclick="confirmCheckin()">
                        <i class="fas fa-check"></i>
                        Confirm Check-In
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentPatient = null;

        // Search form submission
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            searchPatients();
        });

        // Update placeholder based on search type
        document.getElementById('searchType').addEventListener('change', function() {
            const searchTerm = document.getElementById('searchTerm');
            const placeholders = {
                'appointment_id': 'APT-00000024 or 24',
                'patient_id': '12345',
                'patient_number': 'PTN-2024-001',
                'last_name': 'Dela Cruz',
                'philhealth': '01-234567890-1'
            };
            searchTerm.placeholder = placeholders[this.value] || 'Enter search term...';
        });

        // Search patients function
        async function searchPatients() {
            const form = document.getElementById('searchForm');
            const formData = new FormData(form);
            formData.append('action', 'search_patients');
            formData.append('csrf_token', '<?php echo $_SESSION["csrf_token"]; ?>');

            showLoading();

            try {
                const response = await fetch('checkin_actions_enhanced.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    displayResults(data.results);
                } else {
                    showAlert(data.message, 'danger');
                    hideResults();
                }
            } catch (error) {
                showAlert('Search failed: ' + error.message, 'danger');
                hideResults();
            }
        }

        // Display search results
        function displayResults(results) {
            const container = document.getElementById('resultsContent');
            const resultsSection = document.getElementById('searchResults');

            if (!results || results.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        No patients found matching your search criteria.
                    </div>
                `;
            } else {
                let html = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Found ${results.length} patient(s) matching your search.
                    </div>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient Details</th>
                                <th>Service</th>
                                <th>Scheduled Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                results.forEach(patient => {
                    const statusClass = patient.appointment_status === 'checked_in' ? 'status-checked-in' : 'status-confirmed';
                    const statusText = patient.appointment_status === 'checked_in' ? 'Checked-In' : 'Confirmed';
                    
                    let priorityBadges = '';
                    if (patient.isSenior == 1) {
                        priorityBadges += '<span class="priority-badge priority-senior"><i class="fas fa-user-clock"></i> Senior</span>';
                    }
                    if (patient.isPWD == 1) {
                        priorityBadges += '<span class="priority-badge priority-pwd"><i class="fas fa-wheelchair"></i> PWD</span>';
                    }

                    const actionButton = patient.already_checked_in == 1 
                        ? '<span class="status-badge status-checked-in">Already Checked-In</span>'
                        : `<button class="btn btn-success" onclick="openCheckinModal(${JSON.stringify(patient).replace(/"/g, '&quot;')})">
                             <i class="fas fa-user-check"></i> Check-In
                           </button>`;

                    html += `
                        <tr>
                            <td>
                                <strong>APT-${String(patient.appointment_id).padStart(8, '0')}</strong>
                                ${patient.queue_code ? `<br><small>Queue: ${patient.queue_code}</small>` : ''}
                            </td>
                            <td>
                                <strong>${patient.first_name} ${patient.last_name}</strong><br>
                                <small>ID: ${patient.patient_number}</small><br>
                                <small>Phone: ${patient.phone_number || 'N/A'}</small><br>
                                <small>Barangay: ${patient.barangay_name || 'N/A'}</small>
                                ${priorityBadges}
                            </td>
                            <td>${patient.service_name}</td>
                            <td>${new Date('1970-01-01T' + patient.scheduled_time + 'Z').toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}</td>
                            <td>
                                <span class="status-badge ${statusClass}">
                                    ${statusText}
                                </span>
                            </td>
                            <td>${actionButton}</td>
                        </tr>
                    `;
                });

                html += `</tbody></table>`;
                container.innerHTML = html;
            }

            resultsSection.style.display = 'block';
        }

        // Open check-in modal
        function openCheckinModal(patient) {
            currentPatient = patient;
            
            const detailsContainer = document.getElementById('patientDetailsContainer');
            detailsContainer.innerHTML = `
                <div class="patient-details">
                    <div class="detail-item">
                        <span class="detail-label">Patient Name</span>
                        <span class="detail-value">${patient.first_name} ${patient.last_name}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Patient Number</span>
                        <span class="detail-value">${patient.patient_number}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Appointment ID</span>
                        <span class="detail-value">APT-${String(patient.appointment_id).padStart(8, '0')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Service</span>
                        <span class="detail-value">${patient.service_name}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Scheduled Time</span>
                        <span class="detail-value">${new Date('1970-01-01T' + patient.scheduled_time + 'Z').toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">PhilHealth ID</span>
                        <span class="detail-value">${patient.philhealth_id_number || 'Not provided'}</span>
                    </div>
                </div>
            `;

            // Pre-check PhilHealth if ID exists
            document.getElementById('philhealthVerified').checked = !!patient.philhealth_id_number;
            
            document.getElementById('checkinModal').classList.add('show');
        }

        // Confirm check-in
        async function confirmCheckin() {
            if (!currentPatient) return;

            const philhealthVerified = document.getElementById('philhealthVerified').checked ? 'yes' : 'no';
            
            const formData = new FormData();
            formData.append('action', 'accept_booking');
            formData.append('appointment_id', currentPatient.appointment_id);
            formData.append('patient_id', currentPatient.patient_id);
            formData.append('philhealth_verified', philhealthVerified);
            formData.append('csrf_token', '<?php echo $_SESSION["csrf_token"]; ?>');

            try {
                const response = await fetch('checkin_actions_enhanced.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(`
                        ${data.message}<br>
                        <strong>Queue Code:</strong> ${data.queue_code}<br>
                        <strong>Next Station:</strong> ${data.next_station}<br>
                        <strong>PhilHealth Status:</strong> ${data.has_philhealth ? 'Verified' : 'Not verified'}
                    `, 'success');
                    closeModal();
                    // Refresh search results
                    searchPatients();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                showAlert('Check-in failed: ' + error.message, 'danger');
            }
        }

        // Utility functions
        function showLoading() {
            const container = document.getElementById('resultsContent');
            container.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner fa-2x"></i>
                    <p>Searching patients...</p>
                </div>
            `;
            document.getElementById('searchResults').style.display = 'block';
        }

        function hideResults() {
            document.getElementById('searchResults').style.display = 'none';
        }

        function closeModal() {
            document.getElementById('checkinModal').classList.remove('show');
            currentPatient = null;
        }

        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'danger' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                <div>${message}</div>
            `;
            
            container.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 8000);
        }

        function refreshData() {
            location.reload();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('checkinModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Auto-focus search term on load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchTerm').focus();
        });
    </script>
</body>
</html>