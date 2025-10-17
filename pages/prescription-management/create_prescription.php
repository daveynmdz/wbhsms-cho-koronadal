<?php
/**
 * Create Prescription Form (Modal Content)
 * This file is loaded via AJAX into the prescription management modal
 */

// Resolve path to root directory
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check if employee is logged in
if (!is_employee_logged_in()) {
    echo '<div class="alert alert-error">Session expired. Please log in again.</div>';
    exit();
}

$employee_id = get_employee_session('employee_id');
$employee_role = get_employee_session('role');

// Check if role is authorized (doctors, pharmacists, and admins can prescribe)
$authorized_roles = ['doctor', 'pharmacist', 'admin'];
if (!in_array(strtolower($employee_role), $authorized_roles)) {
    echo '<div class="alert alert-error">Only doctors, pharmacists, and administrators are authorized to create prescriptions.</div>';
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_prescription'])) {
    // Clear any previous output and set JSON header
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    
    try {
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $visit_id = !empty($_POST['visit_id']) ? (int)$_POST['visit_id'] : null;
        $medications = $_POST['medications'] ?? [];
        
        if (!$patient_id) {
            throw new Exception('Please select a patient.');
        }
        
        if (empty($medications)) {
            throw new Exception('Please add at least one medication.');
        }
        
        // Validate patient exists
        $patient_stmt = $conn->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE patient_id = ?");
        if (!$patient_stmt) {
            throw new Exception('Database preparation failed: ' . $conn->error);
        }
        $patient_stmt->bind_param("i", $patient_id);
        $patient_stmt->execute();
        $patient_data = $patient_stmt->get_result()->fetch_assoc();
        
        if (!$patient_data) {
            throw new Exception('Patient not found.');
        }
        
        $conn->begin_transaction();
        
        // Get consultation_id and appointment_id if visit_id is provided
        $consultation_id = null;
        $appointment_id = null;
        
        if ($visit_id) {
            // Get both consultation_id and appointment_id from visit
            $visit_stmt = $conn->prepare("SELECT appointment_id FROM visits WHERE visit_id = ?");
            if ($visit_stmt) {
                $visit_stmt->bind_param("i", $visit_id);
                $visit_stmt->execute();
                $visit_result = $visit_stmt->get_result();
                if ($visit_row = $visit_result->fetch_assoc()) {
                    $appointment_id = $visit_row['appointment_id'];
                }
            }
            
            // Get consultation_id if it exists
            $consult_stmt = $conn->prepare("SELECT consultation_id FROM consultations WHERE visit_id = ?");
            if ($consult_stmt) {
                $consult_stmt->bind_param("i", $visit_id);
                $consult_stmt->execute();
                $consult_result = $consult_stmt->get_result();
                if ($consult_row = $consult_result->fetch_assoc()) {
                    $consultation_id = $consult_row['consultation_id'];
                }
            }
        }
        
        // If no appointment_id is available, we need to create one or find an existing one
        if (!$appointment_id) {
            // Look for an existing appointment for this patient today
            $today = date('Y-m-d');
            $existing_appt_stmt = $conn->prepare("
                SELECT appointment_id FROM appointments 
                WHERE patient_id = ? AND DATE(scheduled_date) = ? 
                ORDER BY scheduled_date DESC LIMIT 1
            ");
            if ($existing_appt_stmt) {
                $existing_appt_stmt->bind_param("is", $patient_id, $today);
                $existing_appt_stmt->execute();
                $existing_result = $existing_appt_stmt->get_result();
                if ($existing_row = $existing_result->fetch_assoc()) {
                    $appointment_id = $existing_row['appointment_id'];
                }
            }
        }
        
        // If still no appointment_id, create a walk-in appointment
        if (!$appointment_id) {
            // Get the default facility_id (assuming facility_id = 1 for main facility)
            $facility_id = 1; 
            
            $walkin_stmt = $conn->prepare("
                INSERT INTO appointments (
                    patient_id, 
                    facility_id,
                    scheduled_date, 
                    scheduled_time, 
                    status,
                    created_at
                ) VALUES (?, ?, CURDATE(), CURTIME(), 'completed', NOW())
            ");
            if ($walkin_stmt) {
                $walkin_stmt->bind_param("ii", $patient_id, $facility_id);
                if ($walkin_stmt->execute()) {
                    $appointment_id = $conn->insert_id;
                } else {
                    throw new Exception('Failed to create appointment: ' . $walkin_stmt->error);
                }
            } else {
                throw new Exception('Failed to prepare appointment creation: ' . $conn->error);
            }
        }
        
        if (!$appointment_id) {
            throw new Exception('Unable to find or create an appointment for this prescription.');
        }
        
        // Debug info (remove in production)
        error_log("Creating prescription: patient_id=$patient_id, appointment_id=$appointment_id, visit_id=" . ($visit_id ?: 'NULL') . ", consultation_id=" . ($consultation_id ?: 'NULL') . ", employee_id=$employee_id");
        
        // First, insert the main prescription record
        $prescription_stmt = $conn->prepare("
            INSERT INTO prescriptions (
                patient_id, 
                appointment_id,
                visit_id, 
                consultation_id,
                prescribed_by_employee_id, 
                prescription_date, 
                status,
                remarks
            ) VALUES (?, ?, ?, ?, ?, NOW(), 'active', 'Prescription created by system')
        ");
        
        if (!$prescription_stmt) {
            throw new Exception('Failed to prepare prescription insert statement: ' . $conn->error);
        }
        
        // Handle null values properly - mysqli bind_param requires variables
        $patient_id_param = (int)$patient_id;
        $appointment_id_param = (int)$appointment_id;
        $visit_id_param = $visit_id ?: null;
        $consultation_id_param = $consultation_id ?: null;
        $employee_id_param = (int)$employee_id;
        
        $prescription_stmt->bind_param("iiiii", $patient_id_param, $appointment_id_param, $visit_id_param, $consultation_id_param, $employee_id_param);
        
        if (!$prescription_stmt->execute()) {
            throw new Exception('Failed to create prescription: ' . $prescription_stmt->error);
        }
        
        $prescription_id = $conn->insert_id;
        
        // Then, insert each medication into prescribed_medications table
        $medication_stmt = $conn->prepare("
            INSERT INTO prescribed_medications (
                prescription_id, 
                medication_name, 
                dosage, 
                frequency, 
                instructions,
                status
            ) VALUES (?, ?, ?, ?, ?, 'active')
        ");
        
        if (!$medication_stmt) {
            throw new Exception('Failed to prepare medication insert statement: ' . $conn->error);
        }
        
        $medication_count = 0;
        foreach ($medications as $medication) {
            if (!empty($medication['name']) && !empty($medication['dosage']) && !empty($medication['frequency'])) {
                // Prepare variables for bind_param (mysqli requires variables by reference)
                $prescription_id_param = (int)$prescription_id;
                $medication_name = (string)$medication['name'];
                $medication_dosage = (string)$medication['dosage'];
                $medication_frequency = (string)$medication['frequency'];
                $medication_instructions = (string)($medication['instructions'] ?? '');
                
                $medication_stmt->bind_param(
                    "issss", 
                    $prescription_id_param,
                    $medication_name, 
                    $medication_dosage,
                    $medication_frequency,
                    $medication_instructions
                );
                
                if (!$medication_stmt->execute()) {
                    throw new Exception('Failed to add medication: ' . $medication_stmt->error);
                }
                $medication_count++;
            }
        }
        
        if ($medication_count === 0) {
            throw new Exception('No valid medications were added.');
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully created prescription with {$medication_count} medication(s) for {$patient_data['first_name']} {$patient_data['last_name']}."
        ]);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Prescription creation error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit();
    } catch (Error $e) {
        $conn->rollback();
        error_log("Prescription creation fatal error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false,
            'message' => 'A fatal error occurred: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Get patients for selection
$patients = [];
try {
    $patients_stmt = $conn->prepare("
        SELECT patient_id, first_name, last_name, middle_name, username as patient_code, 
               date_of_birth, sex, contact_number
        FROM patients 
        WHERE status = 'active'
        ORDER BY last_name, first_name
        LIMIT 100
    ");
    if ($patients_stmt) {
        $patients_stmt->execute();
        $result = $patients_stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Calculate age
            if ($row['date_of_birth']) {
                $dob = new DateTime($row['date_of_birth']);
                $now = new DateTime();
                $row['age'] = $now->diff($dob)->y;
            }
            $row['full_name'] = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
            $patients[] = $row;
        }
    }
} catch (Exception $e) {
    // Handle error silently for now
}

// Get recent visits for context (optional)
$recent_visits = [];
try {
    $visits_stmt = $conn->prepare("
        SELECT v.visit_id, v.patient_id, v.visit_date,
               p.first_name, p.last_name, p.username as patient_code,
               COALESCE(s.service_name, 'General Consultation') as service_name
        FROM visits v
        INNER JOIN patients p ON v.patient_id = p.patient_id
        LEFT JOIN appointments a ON v.appointment_id = a.appointment_id
        LEFT JOIN services s ON a.service_id = s.service_id
        WHERE v.visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND v.visit_status IN ('active', 'completed')
        ORDER BY v.visit_date DESC
        LIMIT 20
    ");
    if ($visits_stmt) {
        $visits_stmt->execute();
        $result = $visits_stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
            $recent_visits[] = $row;
        }
    }
} catch (Exception $e) {
    // Handle error silently
}
?>

<style>
    .form-section {
        margin-bottom: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #28a745;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
        font-family: inherit;
        box-sizing: border-box;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #28a745;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
    }
    
    .patient-search-container {
        position: relative;
        margin-bottom: 1rem;
    }
    
    .patient-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid #e0e0e0;
        border-top: none;
        border-radius: 0 0 8px 8px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }
    
    .patient-option {
        padding: 0.75rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s ease;
    }
    
    .patient-option:hover {
        background-color: #f8f9fa;
    }
    
    .patient-option:last-child {
        border-bottom: none;
    }
    
    .patient-name {
        font-weight: 600;
        color: #333;
    }
    
    .patient-details {
        font-size: 0.85em;
        color: #666;
        margin-top: 0.25rem;
    }
    
    .medications-container {
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 1rem;
        background: #f8f9fa;
    }
    
    .medication-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 2fr auto;
        gap: 0.75rem;
        align-items: end;
        margin-bottom: 1rem;
        padding: 1rem;
        background: white;
        border-radius: 6px;
        border: 1px solid #ddd;
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
        font-family: inherit;
        font-size: 0.9rem;
    }
    
    .btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }
    
    .btn-success:hover {
        background: linear-gradient(135deg, #218838, #1e7e34);
        transform: translateY(-1px);
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-1px);
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
        padding: 0.5rem;
        font-size: 0.8rem;
    }
    
    .btn-danger:hover {
        background: #c82333;
    }
    
    .btn-small {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }
    
    .text-muted {
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 2px solid #e0e0e0;
    }
    
    .selected-patient-info {
        background: #e8f5e8;
        padding: 1rem;
        border-radius: 8px;
        border-left: 4px solid #28a745;
        margin-bottom: 1rem;
    }
    
    /* Search Filter Styles */
    .section-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #03045e;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .search-inputs {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .input-group {
        display: flex;
        flex-direction: column;
    }
    
    .form-label {
        font-weight: 600;
        color: #28a745;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }
    
    .form-input {
        padding: 0.75rem;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
        font-family: inherit;
        box-sizing: border-box;
    }
    
    .form-input:focus {
        outline: none;
        border-color: #28a745;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
    }
    
    .search-button-container {
        grid-column: span 4;
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        margin-top: 1rem;
    }
    
    .search-results-container {
        margin-top: 1.5rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .results-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .results-table th {
        background: #28a745;
        color: white;
        padding: 0.75rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .results-table td {
        padding: 0.75rem;
        border-bottom: 1px solid #dee2e6;
        font-size: 0.9rem;
    }
    
    .results-table tbody tr:hover {
        background: #f8f9fa;
    }
    
    .select-btn {
        background: #28a745;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.8rem;
        transition: background 0.3s ease;
    }
    
    .select-btn:hover {
        background: #218838;
    }
    
    .selected-info {
        margin-top: 1rem;
        padding: 1rem;
        background: #e8f5e8;
        border-radius: 8px;
        border-left: 4px solid #28a745;
        font-weight: 500;
        color: #155724;
    }
    
    .badge {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
        border-radius: 4px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .badge-visit {
        background: #28a745;
        color: white;
    }
    
    /* Selected patient row styles */
    .selected-patient-row {
        background-color: #e8f5e8 !important;
        border: 2px solid #28a745 !important;
        animation: selectedRowPulse 0.5s ease-in-out;
    }
    
    .selected-patient-row td {
        border-color: #28a745 !important;
    }
    
    @keyframes selectedRowPulse {
        0% { 
            background-color: #28a745; 
            transform: scale(1.02);
        }
        100% { 
            background-color: #e8f5e8; 
            transform: scale(1);
        }
    }
    
    .change-selection-row {
        background-color: #f8f9fa !important;
        border-top: 1px solid #dee2e6 !important;
    }
    
    .change-selection-row td {
        border: none !important;
        padding: 15px !important;
    }
    
    .selected-badge {
        background: #28a745 !important;
        color: white !important;
        font-size: 12px !important;
        padding: 4px 8px !important;
        cursor: default !important;
        border: none !important;
        border-radius: 4px !important;
    }
    
    .change-selection-btn {
        background: #6c757d !important;
        color: white !important;
        border: none !important;
        padding: 8px 16px !important;
        border-radius: 6px !important;
        font-size: 14px !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
    }
    
    .change-selection-btn:hover {
        background: #5a6268 !important;
        transform: translateY(-1px) !important;
    }
    
    .badge-appointment {
        background: #007bff;
        color: white;
    }
    
    .badge-patient {
        background: #6c757d;
        color: white;
    }
    
    @media (max-width: 768px) {
        .medication-row {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .search-inputs {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        
        .search-button-container {
            grid-column: span 1;
        }
        
        .results-table {
            font-size: 0.8rem;
        }
        
        .results-table th,
        .results-table td {
            padding: 0.5rem 0.25rem;
        }
    }
</style>

<form id="createPrescriptionForm" method="post">
    <input type="hidden" name="create_prescription" value="1">
    <input type="hidden" name="patient_id" id="selectedPatientId">
    <input type="hidden" name="visit_id" id="selectedVisitId">
    
    <!-- Modal Alert Container -->
    <div id="modalAlertContainer" style="margin-bottom: 15px;"></div>
    
    <div class="form-section">
        <div class="section-title">
            <i class="fas fa-search"></i> Search by Filter
        </div>
        
        <div class="search-inputs">
            <div class="input-group">
                <label class="form-label" for="patientIdFilter">Patient ID</label>
                <input type="text" class="form-input" id="patientIdFilter" name="patient_id_filter" placeholder="Enter Patient ID..." autocomplete="off">
            </div>
            
            <div class="input-group">
                <label class="form-label" for="firstNameFilter">First Name</label>
                <input type="text" class="form-input" id="firstNameFilter" name="first_name_filter" placeholder="Enter First Name..." autocomplete="off">
            </div>
            
            <div class="input-group">
                <label class="form-label" for="lastNameFilter">Last Name</label>
                <input type="text" class="form-input" id="lastNameFilter" name="last_name_filter" placeholder="Enter Last Name..." autocomplete="off">
            </div>
            
            <div class="input-group">
                <label class="form-label" for="barangayFilter">Barangay</label>
                <select class="form-input" id="barangayFilter" name="barangay_filter">
                    <option value="">All Barangays</option>
                    <?php
                    // Get barangays from database
                    try {
                        $barangay_sql = "SELECT barangay_name FROM barangay WHERE status = 'active' ORDER BY barangay_name";
                        if ($barangay_stmt = $conn->prepare($barangay_sql)) {
                            $barangay_stmt->execute();
                            $barangay_result = $barangay_stmt->get_result();
                            while ($barangay_row = $barangay_result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($barangay_row['barangay_name']) . "'>" . htmlspecialchars($barangay_row['barangay_name']) . "</option>";
                            }
                            $barangay_stmt->close();
                        }
                    } catch (Exception $e) {
                        // Fallback barangays if query fails
                        echo "<option value='Brgy. Assumption'>Brgy. Assumption</option>";
                        echo "<option value='Brgy. Carpenter Hill'>Brgy. Carpenter Hill</option>";
                        echo "<option value='Brgy. Concepcion'>Brgy. Concepcion</option>";
                        echo "<option value='Brgy. General Paulino Santos'>Brgy. General Paulino Santos</option>";
                        echo "<option value='Brgy. Magsaysay'>Brgy. Magsaysay</option>";
                        echo "<option value='Brgy. Morales'>Brgy. Morales</option>";
                        echo "<option value='Brgy. Rotonda'>Brgy. Rotonda</option>";
                        echo "<option value='Brgy. San Roque'>Brgy. San Roque</option>";
                    }
                    ?>
                </select>
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
                            <th>Barangay</th>
                            <th>Visit ID</th>
                            <th>Visit Date</th>
                            <th>Service Type</th>
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
        </div>
    </div>
    
    <div class="form-section">
        <h4><i class="fas fa-pills"></i> Medications</h4>
        
        <div id="medicationsContainer" class="medications-container">
            <div id="medicationsList">
                <!-- Medication rows will be added here -->
            </div>
            
            <button type="button" class="btn btn-secondary btn-small" onclick="addMedicationRow()">
                <i class="fas fa-plus"></i> Add Medication
            </button>
        </div>
    </div>
    
    <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="closeCreatePrescriptionModal()">
            <i class="fas fa-times"></i> Cancel
        </button>
        <button type="button" class="btn btn-success" id="submitBtn" disabled onclick="submitPrescription(event)">
            <i class="fas fa-prescription-bottle"></i> Create Prescription
        </button>
    </div>
</form>

<script>
    // Initialize global variables
    let patients = <?= json_encode($patients) ?>;
    let selectedPatient = null;
    let medicationCount = 0;
    
    // Declare all functions globally first - BEFORE any HTML tries to use them
    window.searchPatients = function() {
        const patientIdFilter = document.getElementById('patientIdFilter').value.trim();
        const firstNameFilter = document.getElementById('firstNameFilter').value.trim();
        const lastNameFilter = document.getElementById('lastNameFilter').value.trim();
        const barangayFilter = document.getElementById('barangayFilter').value.trim();
        
        if (!patientIdFilter && !firstNameFilter && !lastNameFilter && !barangayFilter) {
            showModalAlert('Please enter at least one search criteria.', 'error');
            return;
        }
        
        // Show loading state
        const searchResultsContainer = document.getElementById('searchResultsContainer');
        const searchResultsBody = document.getElementById('searchResultsBody');
        
        searchResultsContainer.style.display = 'block';
        searchResultsBody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Searching...</td></tr>';
        
        // Prepare search parameters
        const searchParams = new URLSearchParams();
        if (patientIdFilter) searchParams.set('patient_id', patientIdFilter);
        if (firstNameFilter) searchParams.set('first_name', firstNameFilter);
        if (lastNameFilter) searchParams.set('last_name', lastNameFilter);
        if (barangayFilter) searchParams.set('barangay', barangayFilter);
        
        // Make API call to search - use path relative to the prescription_management.php page
        const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/pages/'));
        const apiUrl = `${basePath}/api/search_patients.php?${searchParams.toString()}`;
        console.log('Making API call to:', apiUrl);
        console.log('Base path detected:', basePath);
        
        fetch(apiUrl)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(`HTTP ${response.status}: ${errorData.message || 'Unknown error'}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Search response data:', data);
                if (data.success && data.results && data.results.length > 0) {
                    searchResultsBody.innerHTML = data.results.map(result => {
                        const isCurrentlySelected = selectedPatient && selectedPatient.patientId == result.patient_id && selectedPatient.visitId == result.visit_id;
                        const rowClass = isCurrentlySelected ? 'style="background-color: #fff3cd; border-left: 4px solid #ffc107;"' : '';
                        const buttonText = isCurrentlySelected ? '<i class="fas fa-sync-alt"></i> Reselect' : 'Select';
                        const buttonClass = isCurrentlySelected ? 'style="background: #ffc107; color: #000; font-weight: bold;"' : '';
                        
                        return `
                            <tr ${rowClass}>
                                <td><button class="select-btn" ${buttonClass} onclick="selectSearchResult(${result.patient_id}, '${result.full_name}', '${result.patient_code}', ${result.visit_id}, '${result.barangay}', '${result.visit_date}', '${result.service_name}')">${buttonText}</button></td>
                                <td><strong>${result.full_name}</strong></td>
                                <td>${result.patient_code}</td>
                                <td>${result.barangay}</td>
                                <td>${result.visit_id}</td>
                                <td>${result.visit_date}</td>
                                <td><span class="badge badge-visit">${result.service_name}</span></td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    searchResultsBody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #666;">No patients with visits found matching your search criteria.</td></tr>';
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                
                // Show error in modal instead of main page
                showModalAlert('Search Error: ' + error.message, 'error');
                searchResultsBody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px; color: #dc3545;">Error: ${error.message}</td></tr>`;
            });
    };

    window.clearSearch = function() {
        document.getElementById('patientIdFilter').value = '';
        document.getElementById('firstNameFilter').value = '';
        document.getElementById('lastNameFilter').value = '';
        document.getElementById('barangayFilter').value = '';
        document.getElementById('searchResultsContainer').style.display = 'none';
        document.getElementById('selectedInfo').style.display = 'none';
        
        // Clear hidden fields
        document.getElementById('selectedPatientId').value = '';
        document.getElementById('selectedVisitId').value = '';
        
        selectedPatient = null;
        updateSubmitButton();
        
        showModalAlert('Search cleared. Please enter new search criteria.', 'info');
    };

    window.selectSearchResult = function(patientId, fullName, patientCode, visitId, barangay, visitDate, serviceName) {
        selectedPatient = { 
            patientId: patientId, 
            fullName: fullName, 
            patientCode: patientCode,
            visitId: visitId,
            barangay: barangay,
            visitDate: visitDate,
            serviceName: serviceName
        };
        
        // Update hidden fields
        document.getElementById('selectedPatientId').value = patientId;
        document.getElementById('selectedVisitId').value = visitId;
        
        // Debug: Log the selected patient data
        console.log('Debug - Patient selected:', {
            patientId: patientId,
            visitId: visitId,
            fullName: fullName,
            patientCode: patientCode
        });
        
        // Update selected info display
        const selectedInfo = document.getElementById('selectedInfo');
        const selectedDetails = document.getElementById('selectedDetails');
        
        let detailsText = `${fullName} (ID: ${patientCode}) - ${barangay} - Visit: ${visitId} - ${visitDate} - ${serviceName}`;
        
        selectedDetails.textContent = detailsText;
        selectedInfo.style.display = 'block';
        
        // Show only the selected row in the table
        showSelectedPatientOnly(patientId, fullName, patientCode, visitId, barangay, visitDate, serviceName);
        
        updateSubmitButton();
        
        showModalAlert('Patient with visit selected successfully!', 'success');
    };

    // Function to show only the selected patient row with option to change selection
    window.showSelectedPatientOnly = function(patientId, fullName, patientCode, visitId, barangay, visitDate, serviceName) {
        const searchResultsBody = document.getElementById('searchResultsBody');
        
        searchResultsBody.innerHTML = `
            <tr class="selected-patient-row">
                <td>
                    <button class="selected-badge" disabled>
                        <i class="fas fa-check"></i> Selected
                    </button>
                </td>
                <td><strong>${fullName}</strong></td>
                <td><strong>${patientCode}</strong></td>
                <td>${barangay}</td>
                <td><strong>${visitId}</strong></td>
                <td>${visitDate}</td>
                <td><span class="badge badge-visit">${serviceName}</span></td>
            </tr>
            <tr class="change-selection-row">
                <td colspan="7" style="text-align: center;">
                    <button type="button" class="change-selection-btn" onclick="showAllSearchResults()">
                        <i class="fas fa-list"></i> Change Selection / View All Results
                    </button>
                    <div style="margin-top: 8px; font-size: 12px; color: #6c757d;">
                        Click above to select a different patient or view all search results
                    </div>
                </td>
            </tr>
        `;
    };

    // Function to restore the full search results table
    window.showAllSearchResults = function() {
        // Re-run the search to show all results again
        const patientIdFilter = document.getElementById('patientIdFilter').value.trim();
        const firstNameFilter = document.getElementById('firstNameFilter').value.trim();
        const lastNameFilter = document.getElementById('lastNameFilter').value.trim();
        const barangayFilter = document.getElementById('barangayFilter').value.trim();
        
        if (patientIdFilter || firstNameFilter || lastNameFilter || barangayFilter) {
            // Re-execute the search with current criteria
            searchPatients();
        } else {
            // If no search criteria, show message to search again
            const searchResultsBody = document.getElementById('searchResultsBody');
            searchResultsBody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #666;">Please enter search criteria to view all results.</td></tr>';
        }
    };

    // Modal-specific alert function
    window.showModalAlert = function(message, type = 'success') {
        const alertContainer = document.getElementById('modalAlertContainer');
        if (!alertContainer) {
            console.error('Modal alert container not found');
            return;
        }
        
        // Clear previous alerts
        alertContainer.innerHTML = '';
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.style.cssText = `
            padding: 10px 15px;
            margin-bottom: 15px;
            border: 1px solid transparent;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        `;
        
        if (type === 'success') {
            alertDiv.style.cssText += `
                background-color: #d4edda;
                border-color: #c3e6cb;
                color: #155724;
            `;
        } else if (type === 'error') {
            alertDiv.style.cssText += `
                background-color: #f8d7da;
                border-color: #f5c6cb;
                color: #721c24;
            `;
        } else if (type === 'info') {
            alertDiv.style.cssText += `
                background-color: #cce7ff;
                border-color: #b3d9ff;
                color: #0c5460;
            `;
        }
        
        alertDiv.innerHTML = `
            <span><i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}</span>
            <button type="button" style="background: none; border: none; font-size: 18px; cursor: pointer; color: inherit;" onclick="this.parentElement.remove();">&times;</button>
        `;
        
        alertContainer.appendChild(alertDiv);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 5000);
    };

    window.addMedicationRow = function() {
        medicationCount++;
        const medicationsList = document.getElementById('medicationsList');
        
        if (!medicationsList) {
            console.error('Medications list element not found');
            return;
        }
        
        const row = document.createElement('div');
        row.className = 'medication-row';
        row.id = `medication-${medicationCount}`;
        row.innerHTML = `
            <div class="form-group">
                <label>Medication Name *</label>
                <input type="text" name="medications[${medicationCount}][name]" class="form-control" 
                       placeholder="e.g., Paracetamol" required oninput="updateSubmitButton()">
            </div>
            <div class="form-group">
                <label>Dosage *</label>
                <input type="text" name="medications[${medicationCount}][dosage]" class="form-control" 
                       placeholder="e.g., 500mg" required oninput="updateSubmitButton()">
            </div>
            <div class="form-group">
                <label>Frequency *</label>
                <input type="text" name="medications[${medicationCount}][frequency]" class="form-control" 
                       placeholder="e.g., 3x daily" required oninput="updateSubmitButton()">
            </div>
            <div class="form-group">
                <label>Instructions</label>
                <input type="text" name="medications[${medicationCount}][instructions]" class="form-control" 
                       placeholder="e.g., Take after meals">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-danger" onclick="removeMedicationRow(${medicationCount})" 
                        ${medicationCount === 1 ? 'style="display:none;"' : ''}>
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        
        medicationsList.appendChild(row);
        updateSubmitButton();
    };

    window.removeMedicationRow = function(id) {
        const row = document.getElementById(`medication-${id}`);
        if (row) {
            row.remove();
            updateSubmitButton();
        }
    };
    
    window.updateSubmitButton = function() {
        const submitBtn = document.getElementById('submitBtn');
        
        if (!submitBtn) {
            console.error('Submit button element not found');
            return;
        }
        
        const hasPatient = selectedPatient !== null;
        const hasMedications = document.querySelectorAll('.medication-row input[name*="[name]"]').length > 0;
        
        let hasValidMedication = false;
        document.querySelectorAll('.medication-row').forEach(row => {
            const nameInput = row.querySelector('input[name*="[name]"]');
            const dosageInput = row.querySelector('input[name*="[dosage]"]');
            const frequencyInput = row.querySelector('input[name*="[frequency]"]');
            
            if (nameInput && dosageInput && frequencyInput) {
                const name = nameInput.value.trim();
                const dosage = dosageInput.value.trim();
                const frequency = frequencyInput.value.trim();
                
                if (name && dosage && frequency) {
                    hasValidMedication = true;
                }
            }
        });
        
        submitBtn.disabled = !(hasPatient && hasValidMedication);
    };
    
    window.submitPrescription = function(event) {
        event.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('createPrescriptionForm');
        
        if (!submitBtn || !form) {
            console.error('Required elements not found in submitPrescription');
            return;
        }
        
        // Debug: Log selected patient data before submission
        const patientId = document.getElementById('selectedPatientId').value;
        const visitId = document.getElementById('selectedVisitId').value;
        console.log('Debug - Before submission:', {
            patientId: patientId,
            visitId: visitId,
            selectedPatient: selectedPatient
        });
        
        if (!patientId) {
            showModalAlert('Please select a patient before creating prescription.', 'error');
            return;
        }
        
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        
        // Debug: Log FormData contents
        console.log('Debug - FormData contents:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }

        fetch('create_prescription.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message on main page and close modal
                if (window.showAlert) {
                    showAlert(data.message, 'success');
                }
                
                // Refresh the prescription list if function exists
                if (window.loadPrescriptions) {
                    loadPrescriptions();
                }
                
                // Close modal
                closeCreatePrescriptionModal();
            } else {
                // Show error message in modal
                showModalAlert(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showModalAlert('Network error occurred. Please try again.', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    };
    
    // Initialize with one medication row - handle both DOMContentLoaded and immediate execution
    function initializeForm() {
        // Check if elements exist before proceeding
        if (document.getElementById('medicationsList')) {
            addMedicationRow();
            
            // Add Enter key listeners to search inputs
            const patientIdFilter = document.getElementById('patientIdFilter');
            const firstNameFilter = document.getElementById('firstNameFilter');  
            const lastNameFilter = document.getElementById('lastNameFilter');
            
            [patientIdFilter, firstNameFilter, lastNameFilter].forEach(input => {
                if (input) {
                    input.addEventListener('keypress', function(event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            searchPatients();
                        }
                    });
                }
            });
            
            console.log('Form initialized successfully');
        } else {
            console.log('Form elements not ready, retrying...');
            setTimeout(initializeForm, 50);
        }
    }
    
    // Try immediate initialization or wait for DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeForm);
    } else {
        // DOM is already loaded, initialize immediately
        initializeForm();
    }
    
    console.log('All prescription form functions declared globally');
</script>
