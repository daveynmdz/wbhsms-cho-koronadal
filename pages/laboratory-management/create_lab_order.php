<?php
// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Server-side role enforcement
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role'], ['admin', 'doctor', 'nurse'])) {
    http_response_code(403);
    exit('Not authorized');
}

// Handle AJAX search requests
if (isset($_GET['action'])) {
    // Enable error reporting for debugging
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'search_patients') {
        try {
            // Validate database connection
            if (!$conn || $conn->connect_error) {
                throw new Exception('Database connection failed: ' . ($conn ? $conn->connect_error : 'No connection object'));
            }
            
            $search = $_GET['search'] ?? '';
            $searchParam = "%{$search}%";
            
            $sql = "SELECT p.patient_id, p.first_name, p.last_name, p.middle_name, p.username, 
                           p.date_of_birth, p.sex, p.contact_number
                    FROM patients p 
                    WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR p.username LIKE ? OR p.contact_number LIKE ?)
                    ORDER BY p.last_name, p.first_name 
                    LIMIT 20";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare SQL statement: ' . $conn->error);
            }
            
            $stmt->bind_param("ssss", $searchParam, $searchParam, $searchParam, $searchParam);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if (!$result) {
                throw new Exception('Failed to get result: ' . $stmt->error);
            }
            
            $patients = [];
            while ($row = $result->fetch_assoc()) {
                $patients[] = $row;
            }
            
            echo json_encode($patients);
            exit();
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Search failed: ' . $e->getMessage(),
                'debug' => [
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'search_term' => $search ?? 'undefined'
                ]
            ]);
            exit();
        }
    }
    
    if ($_GET['action'] === 'search_visits') {
        try {
            // Validate database connection
            if (!$conn || $conn->connect_error) {
                throw new Exception('Database connection failed: ' . ($conn ? $conn->connect_error : 'No connection object'));
            }
            
            $search = $_GET['search'] ?? '';
            $searchParam = "%{$search}%";
            
            $sql = "SELECT v.visit_id, v.patient_id, v.appointment_id, v.visit_date, v.visit_status,
                           p.first_name, p.last_name, p.username,
                           a.scheduled_date, a.scheduled_time
                    FROM visits v
                    LEFT JOIN patients p ON v.patient_id = p.patient_id
                    LEFT JOIN appointments a ON v.appointment_id = a.appointment_id
                    WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR p.username LIKE ? 
                           OR v.visit_id LIKE ? OR a.appointment_id LIKE ?)
                    ORDER BY v.visit_date DESC 
                    LIMIT 20";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare SQL statement: ' . $conn->error);
            }
            
            $stmt->bind_param("sssss", $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if (!$result) {
                throw new Exception('Failed to get result: ' . $stmt->error);
            }
            
            $visits = [];
            while ($row = $result->fetch_assoc()) {
                $visits[] = $row;
            }
            
            echo json_encode($visits);
            exit();
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Search visits failed: ' . $e->getMessage(),
                'debug' => [
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'search_term' => $search ?? 'undefined'
                ]
            ]);
            exit();
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'] ?? null;
    $selected_tests = $_POST['selected_tests'] ?? [];
    $others_test = $_POST['others_test'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $appointment_id = $_POST['appointment_id'] ?? null;
    $visit_id = $_POST['visit_id'] ?? null;

    if (!$patient_id || empty($selected_tests)) {
        $_SESSION['lab_message'] = 'Please select a patient and at least one lab test.';
        $_SESSION['lab_message_type'] = 'error';
        header('Location: lab_management.php');
        exit();
    }

    try {
        $conn->begin_transaction();

        // Create lab order (using existing schema)
        $insertOrderSql = "INSERT INTO lab_orders (patient_id, appointment_id, visit_id, ordered_by_employee_id, remarks, status) VALUES (?, ?, ?, ?, ?, 'pending')";
        $orderStmt = $conn->prepare($insertOrderSql);
        $orderStmt->bind_param("iiiis", $patient_id, $appointment_id, $visit_id, $_SESSION['employee_id'], $remarks);
        $orderStmt->execute();
        $lab_order_id = $conn->insert_id;

        // Create lab order items for each selected test (using existing schema)
        $insertItemSql = "INSERT INTO lab_order_items (lab_order_id, test_type, status) VALUES (?, ?, 'pending')";
        $itemStmt = $conn->prepare($insertItemSql);

        // Add "Others" test if specified
        if (!empty($others_test)) {
            $selected_tests[] = "Others: " . $others_test;
        }

        foreach ($selected_tests as $test_type) {
            $itemStmt->bind_param("is", $lab_order_id, $test_type);
            $itemStmt->execute();
        }

        $conn->commit();
        
        $_SESSION['lab_message'] = 'Lab order created successfully.';
        $_SESSION['lab_message_type'] = 'success';
        
        // Return JSON response for AJAX requests
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Lab order created successfully.']);
            exit();
        }
        
        header('Location: lab_management.php');
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['lab_message'] = 'Error creating lab order: ' . $e->getMessage();
        $_SESSION['lab_message_type'] = 'error';
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error creating lab order: ' . $e->getMessage()]);
            exit();
        }
        
        header('Location: lab_management.php');
        exit();
    }
}

// Available lab tests based on requirements
$available_tests = [
    'Complete Blood Count (CBC)',
    'Platelet Count',
    'Blood Typing',
    'Clotting Time and Bleeding Time',
    'Urinalysis',
    'Pregnancy Test',
    'Fecalysis',
    'Serum Potassium',
    'Thyroid Function Tests: TSH',
    'Thyroid Function Tests: FT3',
    'Thyroid Function Tests: FT4',
    'CXR – PA',
    'Drug Test',
    'ECG w/ reading',
    'FBS',
    'Creatinine',
    'SGPT',
    'Uric Acid',
    'Lipid Profile',
    'Serum Na K'
];
?>

<style>
    .create-order-form {
        max-width: 700px;
        margin: 0 auto;
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .form-section {
        margin-bottom: 25px;
        padding: 15px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        background-color: #f8f9fa;
    }

    .section-title {
        font-size: 1.1em;
        font-weight: bold;
        color: #03045e;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid #03045e;
    }

    .search-container {
        position: relative;
        margin-bottom: 15px;
    }

    .search-input {
        width: 100%;
        padding: 12px;
        border: 2px solid #ddd;
        border-radius: 6px;
        font-size: 0.9em;
        transition: border-color 0.3s;
    }

    .search-input:focus {
        border-color: #03045e;
        outline: none;
    }

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-top: none;
        border-radius: 0 0 6px 6px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .search-result-item {
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background-color 0.3s;
    }

    .search-result-item:hover {
        background-color: #f8f9fa;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .selected-info {
        padding: 10px;
        background-color: #e8f5e8;
        border: 1px solid #4CAF50;
        border-radius: 5px;
        margin-top: 10px;
        display: none;
    }

    .tests-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }

    .test-checkbox {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .test-checkbox:hover {
        background-color: #f0f8ff;
        border-color: #007bff;
    }

    .test-checkbox input[type="checkbox"] {
        margin-right: 8px;
        transform: scale(1.2);
    }

    .test-checkbox input[type="checkbox"]:checked + label {
        color: #007bff;
        font-weight: bold;
    }

    .others-input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 5px;
        display: none;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #333;
    }

    .form-input, .form-textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 0.9em;
    }

    .form-textarea {
        height: 80px;
        resize: vertical;
    }

    .btn-container {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 25px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }

    .btn {
        padding: 12px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.9em;
        transition: all 0.3s;
    }

    .btn-primary {
        background-color: #03045e;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0218A7;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #545b62;
    }

    .loading {
        text-align: center;
        padding: 20px;
        color: #666;
    }

    .alert {
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* New Search Interface Styles */
    .input-group {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
    }

    .input-group label {
        min-width: 120px;
        font-weight: bold;
        color: #333;
    }

    .input-group input {
        flex: 1;
        padding: 8px 12px;
        border: 2px solid #ddd;
        border-radius: 5px;
        font-size: 0.9em;
    }

    .input-group input:focus {
        border-color: #03045e;
        outline: none;
    }

    .search-button-container {
        display: flex;
        gap: 10px;
        margin: 15px 0;
        justify-content: flex-end;
    }

    .search-button-container button {
        padding: 8px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.9em;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .btn-search {
        background-color: #03045e;
        color: white;
    }

    .btn-search:hover {
        background-color: #02024a;
    }

    .btn-clear {
        background-color: #6c757d;
        color: white;
    }

    .btn-clear:hover {
        background-color: #545b62;
    }

    .results-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .results-table th,
    .results-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e9ecef;
    }

    .results-table th {
        background-color: #03045e;
        color: white;
        font-weight: bold;
        font-size: 0.9em;
    }

    .results-table tr:hover {
        background-color: #f8f9fa;
    }

    .results-table td {
        font-size: 0.9em;
    }

    .results-table input[type="radio"] {
        transform: scale(1.2);
        margin: 0;
    }

    .search-message-info {
        background-color: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
    }

    .search-message-warning {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
    }

    .search-message-error {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    .tests-disabled-message {
        text-align: center;
        padding: 20px;
        background-color: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        margin: 15px 0;
    }

    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 2px solid #e9ecef;
    }

    .form-actions button:disabled {
        background-color: #e9ecef;
        color: #adb5bd;
        cursor: not-allowed;
    }

    @media (max-width: 768px) {
        .create-order-form {
            margin: 10px;
            padding: 15px;
        }
        
        .tests-grid {
            grid-template-columns: 1fr;
        }
        
        .input-group {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        
        .input-group label {
            min-width: auto;
        }
        
        .search-button-container {
            justify-content: center;
        }
        
        .form-actions {
            flex-direction: column;
            align-items: center;
        }
        
        .results-table {
            font-size: 0.8em;
        }
    }
</style>

<div class="create-order-form">
    <h2 style="text-align: center; color: #03045e; margin-bottom: 25px;">
        <i class="fas fa-plus-circle"></i> Create New Lab Order
    </h2>

    <form id="createOrderForm" method="POST">
        <!-- Search by Filter Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-search"></i> Search by Filter
            </div>
            
            <div class="search-inputs">
                <div class="input-group">
                    <label class="form-label" for="patientIdFilter">Patient ID</label>
                    <input type="text" 
                           class="form-input" 
                           id="patientIdFilter" 
                           name="patient_id_filter"
                           placeholder="Enter Patient ID..."
                           autocomplete="off">
                </div>
                
                <div class="input-group">
                    <label class="form-label" for="appointmentIdFilter">Appointment ID</label>
                    <input type="text" 
                           class="form-input" 
                           id="appointmentIdFilter" 
                           name="appointment_id_filter"
                           placeholder="Enter Appointment ID..."
                           autocomplete="off">
                </div>
                
                <div class="input-group">
                    <label class="form-label" for="visitIdFilter">Visit ID</label>
                    <input type="text" 
                           class="form-input" 
                           id="visitIdFilter" 
                           name="visit_id_filter"
                           placeholder="Enter Visit ID..."
                           autocomplete="off">
                </div>
                
                <div class="search-button-container">
                    <button type="button" class="btn btn-primary" onclick="searchPatients()">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearSearch()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>
            
            <!-- Search Results Table -->
            <div class="search-results-container" id="searchResultsContainer" style="display: none;">
                <h4 style="color: #03045e; margin-bottom: 15px;">Search Results</h4>
                <div class="table-responsive">
                    <table class="results-table" id="searchResultsTable">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <th>Patient Name</th>
                                <th>Patient ID</th>
                                <th>Appointment ID</th>
                                <th>Visit ID</th>
                                <th>Date</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody id="searchResultsBody">
                            <!-- Results will be populated here -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Selected Patient Info -->
            <div class="selected-info" id="selectedInfo" style="display: none;">
                <strong>Selected Patient:</strong> <span id="selectedDetails"></span>
                <input type="hidden" name="patient_id" id="selectedPatientId">
                <input type="hidden" name="appointment_id" id="selectedAppointmentId">
                <input type="hidden" name="visit_id" id="selectedVisitId">
            </div>
        </div>

        <!-- Lab Tests Section -->
        <div class="form-section" id="labTestsSection">
            <div class="section-title">
                <i class="fas fa-flask"></i> Select Lab Tests
            </div>
            
            <div class="tests-disabled-message" id="testsDisabledMessage">
                <p style="text-align: center; color: #666; font-style: italic; margin: 20px 0;">
                    <i class="fas fa-info-circle"></i> Please select a patient from the search results above to enable lab test selection.
                </p>
            </div>
            
            <div class="tests-grid" id="testsGrid" style="display: none;">
                <?php foreach ($available_tests as $test): ?>
                <div class="test-checkbox">
                    <input type="checkbox" 
                           name="selected_tests[]" 
                           value="<?= htmlspecialchars($test) ?>" 
                           id="test_<?= md5($test) ?>"
                           disabled>
                    <label for="test_<?= md5($test) ?>"><?= htmlspecialchars($test) ?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-notes-medical"></i> Additional Information
            </div>
            
            <div class="form-group">
                <label class="form-label" for="remarks">Remarks/Instructions</label>
                <textarea class="form-textarea" 
                         id="remarks" 
                         name="remarks" 
                         placeholder="Optional: Add any special instructions or remarks..."></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="submitButton" disabled>
                <i class="fas fa-plus"></i> Create Lab Order
            </button>
            <a href="lab_management.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Hidden Form Fields -->
        <input type="hidden" name="patient_id" id="hiddenPatientId">
        <input type="hidden" name="visit_id" id="hiddenVisitId">
    </form>
</div>

<script>
// Debug: Log that this script is loading
console.log('create_lab_order.php script is loading...');

// Define functions in multiple scopes to ensure they're accessible
function searchPatients() {
    console.log('searchPatients function called');
    
    try {
        // Debug: Check if elements exist
        const patientIdEl = document.getElementById('patientIdFilter');
        const appointmentIdEl = document.getElementById('appointmentIdFilter');
        const visitIdEl = document.getElementById('visitIdFilter');
        
        if (!patientIdEl || !appointmentIdEl || !visitIdEl) {
            console.error('Search input elements not found:', {
                patientIdEl: !!patientIdEl,
                appointmentIdEl: !!appointmentIdEl,
                visitIdEl: !!visitIdEl
            });
            alert('Error: Search input elements not found. Please check if the form is properly loaded.');
            return;
        }
        
        const patientId = patientIdEl.value;
        const appointmentId = appointmentIdEl.value;
        const visitId = visitIdEl.value;
        
        console.log('Search values:', { patientId, appointmentId, visitId });
        
        // Prepare search parameters
        let searchParams = [];
        if (patientId) searchParams.push(`patient_id=${encodeURIComponent(patientId)}`);
        if (appointmentId) searchParams.push(`appointment_id=${encodeURIComponent(appointmentId)}`);
        if (visitId) searchParams.push(`visit_id=${encodeURIComponent(visitId)}`);
        
        if (searchParams.length === 0) {
            showSearchMessage('Please enter at least one search criteria (Patient ID, Appointment ID, or Visit ID) to search for patients.', 'warning');
            return;
        }
        
        // Debug: Check if results container exists
        const resultsContainer = document.getElementById('searchResultsContainer');
        if (!resultsContainer) {
            console.error('Results container not found');
            alert('Error: Results container not found. Please check if the form is properly loaded.');
            return;
        }
        
        // Show the results container
        resultsContainer.style.display = 'block';
        
        // Make AJAX request using the existing search endpoint
        const searchQuery = patientId || appointmentId || visitId;
        const requestUrl = `create_lab_order.php?action=search_patients&search=${encodeURIComponent(searchQuery)}`;
        
        console.log('Making request to:', requestUrl);
        
        fetch(requestUrl)
            .then(response => {
                console.log('Response received:', response.status, response.headers.get('content-type'));
                
                // Check if response is actually JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.warn('Response is not JSON, probably an error page');
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned an error page instead of search results. This may indicate a database connection issue or PHP error.');
                    });
                }
                
                // For JSON responses, try to parse even if there's an error status
                return response.json().then(data => {
                    if (!response.ok) {
                        // If it's a JSON error response, use the server's error message
                        if (data.error && data.message) {
                            throw new Error(data.message);
                        } else {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                    }
                    return data;
                });
            })
            .then(data => {
                console.log('Search results:', data);
                
                const resultsTable = document.getElementById('searchResultsTable');
                const tableBody = document.getElementById('searchResultsBody');
                
                if (!resultsTable || !tableBody) {
                    console.error('Results table elements not found');
                    showSearchMessage('Error: Results table not found.', 'error');
                    return;
                }
                
                // Clear previous results
                tableBody.innerHTML = '';
                
                if (data && Array.isArray(data) && data.length > 0) {
                    data.forEach(patient => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>
                                <input type="radio" name="selectedPatient" value="${patient.patient_id}" 
                                       data-patient-id="${patient.patient_id}" 
                                       data-patient-name="${patient.first_name} ${patient.last_name}"
                                       data-username="${patient.username}"
                                       onchange="selectPatient(this)">
                            </td>
                            <td>${patient.first_name} ${patient.middle_name || ''} ${patient.last_name}</td>
                            <td>${patient.patient_id || 'N/A'}</td>
                            <td>N/A</td>
                            <td>N/A</td>
                            <td>${patient.date_of_birth || 'N/A'}</td>
                            <td>${patient.sex || 'N/A'}</td>
                        `;
                        tableBody.appendChild(row);
                    });
                    resultsTable.style.display = 'table';
                    console.log('Results populated successfully');
                } else {
                    // Show specific message based on search criteria used
                    let searchedFor = [];
                    if (patientId) searchedFor.push(`Patient ID "${patientId}"`);
                    if (appointmentId) searchedFor.push(`Appointment ID "${appointmentId}"`);
                    if (visitId) searchedFor.push(`Visit ID "${visitId}"`);
                    
                    const searchText = searchedFor.join(', ');
                    showSearchMessage(`No patients found with ${searchText}. Please verify the information and ensure the patient exists in the system.`, 'info');
                    console.log('No results found for:', searchText);
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                
                // Show user-friendly error messages based on error type
                if (error.message.includes('JSON')) {
                    showSearchMessage('Server error: Unable to process search request. Please check if the patient data exists in the system.', 'error');
                } else if (error.message.includes('network') || error.message.includes('fetch')) {
                    showSearchMessage('Network error: Unable to connect to server. Please check your connection and try again.', 'error');
                } else {
                    showSearchMessage(`Search failed: ${error.message}`, 'error');
                }
            });
    } catch (error) {
        console.error('Unexpected error in searchPatients:', error);
        alert(`Unexpected error: ${error.message}. Please check the console for details.`);
    }
}

// Also assign to window object for backup access
window.searchPatients = searchPatients;

function showSearchMessage(message, type = 'info') {
    const resultsTable = document.getElementById('searchResultsTable');
    const tableBody = document.getElementById('searchResultsBody');
    
    if (!tableBody) return;
    
    // Clear previous results
    tableBody.innerHTML = '';
    
    // Create message row with appropriate styling
    const row = document.createElement('tr');
    const messageClass = `search-message-${type}`;
    
    const iconClass = type === 'error' ? 'fas fa-exclamation-triangle' : 
                     type === 'warning' ? 'fas fa-exclamation-circle' : 
                     'fas fa-info-circle';
    
    row.innerHTML = `
        <td colspan="7" style="text-align: center; padding: 20px;" class="${messageClass}">
            <i class="${iconClass}"></i> ${message}
        </td>
    `;
    
    tableBody.appendChild(row);
    resultsTable.style.display = 'table';
    
    // Also show results container to make message visible
    const resultsContainer = document.getElementById('searchResultsContainer');
    if (resultsContainer) {
        resultsContainer.style.display = 'block';
    }
    
    console.log(`Search message (${type}):`, message);
}

function clearSearch() {
    console.log('clearSearch function called');
    
    try {
        // Clear input fields
        const patientIdEl = document.getElementById('patientIdFilter');
        const appointmentIdEl = document.getElementById('appointmentIdFilter');
        const visitIdEl = document.getElementById('visitIdFilter');
        
        if (patientIdEl) patientIdEl.value = '';
        if (appointmentIdEl) appointmentIdEl.value = '';
        if (visitIdEl) visitIdEl.value = '';
        
        // Clear results table
        const resultsContainer = document.getElementById('searchResultsContainer');
        const tableBody = document.getElementById('searchResultsBody');
        
        if (tableBody) tableBody.innerHTML = '';
        if (resultsContainer) resultsContainer.style.display = 'none';
        
        // Reset lab tests section
        disableLabTests();
        
        console.log('Search cleared successfully');
    } catch (error) {
        console.error('Error in clearSearch:', error);
        alert(`Error clearing search: ${error.message}`);
    }
}

// Also assign to window object for backup access
window.clearSearch = clearSearch;

function selectPatient(radio) {
    console.log('selectPatient function called', radio);
    
    try {
        const patientId = radio.getAttribute('data-patient-id');
        const patientName = radio.getAttribute('data-patient-name');
        const username = radio.getAttribute('data-username');
        
        console.log('Patient selected:', { patientId, patientName, username });
        
        // Set hidden form fields
        const hiddenPatientId = document.getElementById('hiddenPatientId');
        if (hiddenPatientId) {
            hiddenPatientId.value = patientId;
        }
        
        // Show selected patient info
        const selectedInfo = document.getElementById('selectedInfo');
        const selectedDetails = document.getElementById('selectedDetails');
        
        if (selectedDetails) {
            selectedDetails.textContent = `${patientName} (ID: ${username})`;
        }
        if (selectedInfo) {
            selectedInfo.style.display = 'block';
        }
        
        // Set form fields for submission
        const selectedPatientId = document.getElementById('selectedPatientId');
        if (selectedPatientId) {
            selectedPatientId.value = patientId;
        }
        
        // Enable lab tests section
        enableLabTests();
        
        console.log('Patient selected successfully');
    } catch (error) {
        console.error('Error in selectPatient:', error);
        alert(`Error selecting patient: ${error.message}`);
    }
}

// Also assign to window object for backup access
window.selectPatient = selectPatient;

function enableLabTests() {
    console.log('enableLabTests function called');
    
    try {
        // Hide disabled message
        const testsDisabledMessage = document.getElementById('testsDisabledMessage');
        if (testsDisabledMessage) {
            testsDisabledMessage.style.display = 'none';
        }
        
        // Show tests grid
        const testsGrid = document.getElementById('testsGrid');
        if (testsGrid) {
            testsGrid.style.display = 'block';
        }
        
        // Enable all test checkboxes
        const checkboxes = document.querySelectorAll('#testsGrid input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.disabled = false;
        });
        
        // Enable submit button
        const submitButton = document.getElementById('submitButton');
        if (submitButton) {
            submitButton.disabled = false;
        }
        
        console.log('Lab tests enabled successfully');
    } catch (error) {
        console.error('Error in enableLabTests:', error);
        alert(`Error enabling lab tests: ${error.message}`);
    }
}

// Also assign to window object for backup access
window.enableLabTests = enableLabTests;

function disableLabTests() {
    console.log('disableLabTests function called');
    
    try {
        // Show disabled message
        const testsDisabledMessage = document.getElementById('testsDisabledMessage');
        if (testsDisabledMessage) {
            testsDisabledMessage.style.display = 'block';
        }
        
        // Hide tests grid
        const testsGrid = document.getElementById('testsGrid');
        if (testsGrid) {
            testsGrid.style.display = 'none';
        }
        
        // Disable all test checkboxes and clear selections
        const checkboxes = document.querySelectorAll('#testsGrid input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.disabled = true;
            checkbox.checked = false;
        });
        
        // Clear hidden form fields
        const hiddenPatientId = document.getElementById('hiddenPatientId');
        const hiddenVisitId = document.getElementById('hiddenVisitId');
        
        if (hiddenPatientId) hiddenPatientId.value = '';
        if (hiddenVisitId) hiddenVisitId.value = '';
        
        // Disable submit button
        const submitButton = document.getElementById('submitButton');
        if (submitButton) {
            submitButton.disabled = true;
        }
        
        console.log('Lab tests disabled successfully');
    } catch (error) {
        console.error('Error in disableLabTests:', error);
        alert(`Error disabling lab tests: ${error.message}`);
    }
}

// Also assign to window object for backup access
window.disableLabTests = disableLabTests;

// Form submission handler
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up form submission handler');
    
    const form = document.getElementById('createOrderForm');
    if (!form) {
        console.error('Form not found!');
        return;
    }
    
    form.addEventListener('submit', function(e) {
        console.log('Form submission attempted');
        e.preventDefault();
        
        try {
            // Validate required fields
            const patientId = document.getElementById('hiddenPatientId').value;
            console.log('Patient ID for submission:', patientId);
            
            if (!patientId) {
                alert('Please select a patient from the search results.');
                return;
            }
            
            const selectedTests = document.querySelectorAll('input[name="selected_tests[]"]:checked');
            console.log('Selected tests count:', selectedTests.length);
            
            if (selectedTests.length === 0) {
                alert('Please select at least one lab test.');
                return;
            }
            
            // Submit form normally (not AJAX since we want to redirect)
            console.log('Submitting form...');
            this.submit();
        } catch (error) {
            console.error('Error during form submission:', error);
            alert(`Error during form submission: ${error.message}`);
        }
    });
    
    console.log('Form submission handler set up successfully');
});

// Debug: Log when script finishes loading
console.log('create_lab_order.php script loaded completely');
</script>