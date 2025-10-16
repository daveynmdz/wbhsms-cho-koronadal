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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'] ?? null;
    $selected_tests = $_POST['selected_tests'] ?? [];
    $remarks = $_POST['remarks'] ?? '';
    $appointment_id = $_POST['appointment_id'] ?? null;
    $consultation_id = $_POST['consultation_id'] ?? null;
    $visit_id = $_POST['visit_id'] ?? null;

    if (!$patient_id || empty($selected_tests)) {
        $_SESSION['lab_message'] = 'Please select a patient and at least one lab test.';
        $_SESSION['lab_message_type'] = 'error';
        header('Location: lab_management.php');
        exit();
    }

    try {
        $conn->begin_transaction();

        // Create lab order
        $insertOrderSql = "INSERT INTO lab_orders (patient_id, appointment_id, consultation_id, visit_id, ordered_by_employee_id, remarks, overall_status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $orderStmt = $conn->prepare($insertOrderSql);
        $orderStmt->bind_param("iiiiss", $patient_id, $appointment_id, $consultation_id, $visit_id, $_SESSION['employee_id'], $remarks);
        $orderStmt->execute();
        $lab_order_id = $conn->insert_id;

        // Create lab order items for each selected test
        $insertItemSql = "INSERT INTO lab_order_items (lab_order_id, test_type, special_instructions, status) VALUES (?, ?, ?, 'pending')";
        $itemStmt = $conn->prepare($insertItemSql);

        // Predefined lab tests with instructions
        $predefined_tests = [
            'Complete Blood Count (CBC)' => 'Fasting not required',
            'Urinalysis' => 'Mid-stream clean catch specimen required',
            'Fasting Blood Sugar (FBS)' => '8-12 hour fasting required',
            'Lipid Profile' => '12-hour fasting required',
            'Hepatitis B Surface Antigen' => 'No special preparation needed',
            'Pregnancy Test (HCG)' => 'First morning urine preferred',
            'Stool Examination' => 'Fresh specimen within 2 hours',
            'Chest X-ray' => 'Remove metallic objects',
            'Electrocardiogram (ECG)' => 'Rest for 5 minutes before test',
            'Blood Typing & Rh Factor' => 'No special preparation needed',
            'Creatinine' => 'No special preparation needed',
            'SGPT/ALT' => 'Fasting for 8-12 hours preferred',
            'SGOT/AST' => 'Fasting for 8-12 hours preferred',
            'Total Cholesterol' => '12-hour fasting required',
            'Triglycerides' => '12-hour fasting required',
            'Hemoglobin A1C' => 'No fasting required',
            'Thyroid Function Test (TSH)' => 'No special preparation needed',
            'Prostate Specific Antigen (PSA)' => 'Avoid ejaculation 48 hours before test',
            'Pap Smear' => 'Avoid douching and intercourse 24 hours before test',
            'Sputum Examination' => 'Early morning specimen preferred'
        ];

        foreach ($selected_tests as $test_type) {
            $instructions = $predefined_tests[$test_type] ?? 'Follow standard preparation guidelines';
            $itemStmt->bind_param("iss", $lab_order_id, $test_type, $instructions);
            $itemStmt->execute();
        }

        $conn->commit();
        
        $_SESSION['lab_message'] = 'Lab order created successfully.';
        $_SESSION['lab_message_type'] = 'success';
        header('Location: lab_management.php');
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['lab_message'] = 'Error creating lab order: ' . $e->getMessage();
        $_SESSION['lab_message_type'] = 'error';
        header('Location: lab_management.php');
        exit();
    }
}

// Fetch patients for dropdown
$patientsStmt = $conn->prepare("SELECT patient_id, first_name, middle_name, last_name, username FROM patients ORDER BY last_name, first_name");
$patientsStmt->execute();
$patientsResult = $patientsStmt->get_result();

// Define available lab tests
$available_tests = [
    'Complete Blood Count (CBC)',
    'Urinalysis',
    'Fasting Blood Sugar (FBS)',
    'Lipid Profile',
    'Hepatitis B Surface Antigen',
    'Pregnancy Test (HCG)',
    'Stool Examination',
    'Chest X-ray',
    'Electrocardiogram (ECG)',
    'Blood Typing & Rh Factor',
    'Creatinine',
    'SGPT/ALT',
    'SGOT/AST',
    'Total Cholesterol',
    'Triglycerides',
    'Hemoglobin A1C',
    'Thyroid Function Test (TSH)',
    'Prostate Specific Antigen (PSA)',
    'Pap Smear',
    'Sputum Examination'
];
?>

<style>
    .create-order-form {
        max-width: 600px;
        margin: 0 auto;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-weight: bold;
        color: #03045e;
        margin-bottom: 5px;
    }

    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 0.9em;
        box-sizing: border-box;
    }

    .form-control:focus {
        outline: none;
        border-color: #03045e;
        box-shadow: 0 0 5px rgba(3, 4, 94, 0.2);
    }

    .test-selection {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 15px;
        background-color: #f9f9f9;
    }

    .test-item {
        display: flex;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }

    .test-item:last-child {
        border-bottom: none;
    }

    .test-checkbox {
        margin-right: 10px;
    }

    .test-label {
        flex: 1;
        font-size: 0.9em;
        cursor: pointer;
    }

    .test-instructions {
        font-size: 0.8em;
        color: #666;
        margin-left: 25px;
        font-style: italic;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    .btn {
        padding: 10px 20px;
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

    .patient-search {
        position: relative;
    }

    .patient-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-top: none;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .patient-option {
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }

    .patient-option:hover {
        background-color: #f8f9fa;
    }

    .selected-count {
        font-size: 0.9em;
        color: #03045e;
        font-weight: bold;
        margin-bottom: 10px;
    }
</style>

<form method="POST" class="create-order-form" onsubmit="return validateForm()">
    <div class="form-group">
        <label class="form-label">Patient *</label>
        <div class="patient-search">
            <input type="text" id="patientSearch" class="form-control" placeholder="Search patient by name or ID..." oninput="searchPatients()" autocomplete="off">
            <input type="hidden" id="selectedPatientId" name="patient_id" required>
            <div id="patientDropdown" class="patient-dropdown"></div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Appointment ID (Optional)</label>
        <input type="number" name="appointment_id" class="form-control" placeholder="Enter appointment ID if applicable">
    </div>

    <div class="form-group">
        <label class="form-label">Consultation ID (Optional)</label>
        <input type="number" name="consultation_id" class="form-control" placeholder="Enter consultation ID if applicable">
    </div>

    <div class="form-group">
        <label class="form-label">Visit ID (Optional)</label>
        <input type="number" name="visit_id" class="form-control" placeholder="Enter visit ID if applicable">
    </div>

    <div class="form-group">
        <label class="form-label">Lab Tests *</label>
        <div class="selected-count">Selected: <span id="selectedCount">0</span> test(s)</div>
        <div class="test-selection">
            <?php foreach ($available_tests as $test): ?>
            <div class="test-item">
                <input type="checkbox" name="selected_tests[]" value="<?= htmlspecialchars($test) ?>" 
                       class="test-checkbox" id="test_<?= md5($test) ?>" onchange="updateSelectedCount()">
                <label for="test_<?= md5($test) ?>" class="test-label">
                    <?= htmlspecialchars($test) ?>
                </label>
            </div>
            <div class="test-instructions" id="instructions_<?= md5($test) ?>">
                <?php
                $instructions = [
                    'Complete Blood Count (CBC)' => 'Fasting not required',
                    'Urinalysis' => 'Mid-stream clean catch specimen required',
                    'Fasting Blood Sugar (FBS)' => '8-12 hour fasting required',
                    'Lipid Profile' => '12-hour fasting required',
                    'Hepatitis B Surface Antigen' => 'No special preparation needed',
                    'Pregnancy Test (HCG)' => 'First morning urine preferred',
                    'Stool Examination' => 'Fresh specimen within 2 hours',
                    'Chest X-ray' => 'Remove metallic objects',
                    'Electrocardiogram (ECG)' => 'Rest for 5 minutes before test',
                    'Blood Typing & Rh Factor' => 'No special preparation needed',
                    'Creatinine' => 'No special preparation needed',
                    'SGPT/ALT' => 'Fasting for 8-12 hours preferred',
                    'SGOT/AST' => 'Fasting for 8-12 hours preferred',
                    'Total Cholesterol' => '12-hour fasting required',
                    'Triglycerides' => '12-hour fasting required',
                    'Hemoglobin A1C' => 'No fasting required',
                    'Thyroid Function Test (TSH)' => 'No special preparation needed',
                    'Prostate Specific Antigen (PSA)' => 'Avoid ejaculation 48 hours before test',
                    'Pap Smear' => 'Avoid douching and intercourse 24 hours before test',
                    'Sputum Examination' => 'Early morning specimen preferred'
                ];
                echo htmlspecialchars($instructions[$test] ?? 'Follow standard preparation guidelines');
                ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Remarks (Optional)</label>
        <textarea name="remarks" class="form-control" rows="3" placeholder="Additional instructions or notes..."></textarea>
    </div>

    <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('createOrderModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Lab Order</button>
    </div>
</form>

<script>
    const patients = <?= json_encode($patientsResult->fetch_all(MYSQLI_ASSOC)) ?>;
    
    function searchPatients() {
        const searchTerm = document.getElementById('patientSearch').value.toLowerCase();
        const dropdown = document.getElementById('patientDropdown');
        
        if (searchTerm.length < 2) {
            dropdown.style.display = 'none';
            return;
        }
        
        const matches = patients.filter(patient => 
            patient.first_name.toLowerCase().includes(searchTerm) ||
            patient.last_name.toLowerCase().includes(searchTerm) ||
            patient.username.toLowerCase().includes(searchTerm) ||
            (patient.middle_name && patient.middle_name.toLowerCase().includes(searchTerm))
        );
        
        dropdown.innerHTML = '';
        
        if (matches.length > 0) {
            matches.slice(0, 10).forEach(patient => {
                const option = document.createElement('div');
                option.className = 'patient-option';
                option.innerHTML = `
                    <strong>${patient.first_name} ${patient.middle_name || ''} ${patient.last_name}</strong><br>
                    <small>ID: ${patient.username}</small>
                `;
                option.onclick = () => selectPatient(patient);
                dropdown.appendChild(option);
            });
            dropdown.style.display = 'block';
        } else {
            dropdown.innerHTML = '<div class="patient-option">No patients found</div>';
            dropdown.style.display = 'block';
        }
    }
    
    function selectPatient(patient) {
        document.getElementById('patientSearch').value = `${patient.first_name} ${patient.middle_name || ''} ${patient.last_name} (ID: ${patient.username})`;
        document.getElementById('selectedPatientId').value = patient.patient_id;
        document.getElementById('patientDropdown').style.display = 'none';
    }
    
    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('input[name="selected_tests[]"]:checked');
        document.getElementById('selectedCount').textContent = checkboxes.length;
    }
    
    function validateForm() {
        const patientId = document.getElementById('selectedPatientId').value;
        const selectedTests = document.querySelectorAll('input[name="selected_tests[]"]:checked');
        
        if (!patientId) {
            alert('Please select a patient.');
            return false;
        }
        
        if (selectedTests.length === 0) {
            alert('Please select at least one lab test.');
            return false;
        }
        
        return true;
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const searchContainer = document.querySelector('.patient-search');
        if (!searchContainer.contains(event.target)) {
            document.getElementById('patientDropdown').style.display = 'none';
        }
    });
    
    // Initialize selected count
    updateSelectedCount();
</script>