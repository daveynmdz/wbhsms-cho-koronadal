<?php
/**
 * Triage Screen for Nurses - Clinical Encounter Module
 * CHO Koronadal Healthcare Management System
 */

// Include configuration (use mock for frontend development)
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/mock/mock_config.php';
require_once $root_path . '/mock/mock_session.php';

// Check if user is logged in and has appropriate role
if (!is_employee_logged_in() || !in_array(get_employee_session('employee_role'), ['nurse', 'doctor', 'admin'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$employee_name = get_employee_session('employee_name');
$employee_role = get_employee_session('employee_role');

// Mock patient data for pre-filling (in real app, this would come from appointment or search)
$patient_data = null;
if (isset($_GET['patient_id'])) {
    $patients = getMockPatients();
    foreach ($patients as $patient) {
        if ($patient['patient_id'] == $_GET['patient_id']) {
            $patient_data = $patient;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Triage Assessment - CHO Koronadal</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../../assets/css/clinical-encounter/clinical-encounter.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Include Sidebar -->
    <?php include '../../../includes/sidebar_' . $employee_role . '.php'; ?>
    
    <div class="homepage">
        <!-- Include Topbar -->
        <?php 
        include '../../../includes/topbar.php';
        renderTopbar([
            'title' => 'Triage Assessment',
            'back_url' => '../dashboard.php',
            'user_type' => 'employee'
        ]);
        ?>
        
        <div class="clinical-encounter-container">
            <div class="encounter-header">
                <h1><i class="fas fa-stethoscope"></i> Triage Assessment</h1>
                <p>Initial patient evaluation and vital signs assessment</p>
            </div>
            
            <div class="encounter-card">
                <div class="card-header">
                    <i class="fas fa-user-injured"></i> Patient Identity Verification
                </div>
                <div class="card-body">
                    <form id="triageForm" class="triage-form">
                        <!-- Patient Search Section -->
                        <div class="card-section">
                            <div class="section-title">
                                <i class="fas fa-search"></i> Patient Lookup
                            </div>
                            
                            <div class="form-grid two-column">
                                <div class="form-group">
                                    <label class="form-label" for="patientSearch">Search Patient</label>
                                    <input type="text" 
                                           id="patientSearch" 
                                           name="patientSearch" 
                                           class="form-control" 
                                           placeholder="Enter patient name, ID, or contact number"
                                           value="<?= $patient_data ? $patient_data['first_name'] . ' ' . $patient_data['last_name'] : '' ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" id="searchPatientBtn" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search Patient
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Patient Information Display -->
                        <div class="card-section" id="patientInfoSection" style="<?= $patient_data ? '' : 'display: none;' ?>">
                            <div class="section-title">
                                <i class="fas fa-user"></i> Patient Information
                            </div>
                            
                            <div class="patient-info-card">
                                <div class="patient-info-grid">
                                    <div class="patient-info-item">
                                        <span class="patient-info-label">Patient ID</span>
                                        <span class="patient-info-value" id="displayPatientId">
                                            <?= $patient_data ? 'P' . str_pad($patient_data['patient_id'], 4, '0', STR_PAD_LEFT) : '' ?>
                                        </span>
                                    </div>
                                    <div class="patient-info-item">
                                        <span class="patient-info-label">Full Name</span>
                                        <span class="patient-info-value" id="displayPatientName">
                                            <?= $patient_data ? $patient_data['first_name'] . ' ' . ($patient_data['middle_name'] ? $patient_data['middle_name'] . ' ' : '') . $patient_data['last_name'] : '' ?>
                                        </span>
                                    </div>
                                    <div class="patient-info-item">
                                        <span class="patient-info-label">Age</span>
                                        <span class="patient-info-value" id="displayPatientAge">
                                            <?= $patient_data ? (date('Y') - date('Y', strtotime($patient_data['birthdate']))) . ' years' : '' ?>
                                        </span>
                                    </div>
                                    <div class="patient-info-item">
                                        <span class="patient-info-label">Gender</span>
                                        <span class="patient-info-value" id="displayPatientGender">
                                            <?= $patient_data ? $patient_data['gender'] : '' ?>
                                        </span>
                                    </div>
                                    <div class="patient-info-item">
                                        <span class="patient-info-label">Contact</span>
                                        <span class="patient-info-value" id="displayPatientContact">
                                            <?= $patient_data ? $patient_data['contact_number'] : '' ?>
                                        </span>
                                    </div>
                                    <div class="patient-info-item">
                                        <span class="patient-info-label">Blood Type</span>
                                        <span class="patient-info-value" id="displayPatientBloodType">
                                            <?= $patient_data ? $patient_data['blood_type'] : '' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Vital Signs Section -->
                        <div class="card-section">
                            <div class="section-title">
                                <i class="fas fa-heartbeat"></i> Vital Signs Assessment
                            </div>
                            
                            <div class="vitals-grid">
                                <div class="vital-sign-group">
                                    <div class="vital-sign-label">Blood Pressure</div>
                                    <input type="text" 
                                           id="bloodPressure" 
                                           name="bloodPressure" 
                                           class="vital-sign-input" 
                                           placeholder="120/80"
                                           pattern="[0-9]{2,3}/[0-9]{2,3}">
                                    <div class="vital-sign-unit">mmHg</div>
                                </div>
                                
                                <div class="vital-sign-group">
                                    <div class="vital-sign-label">Heart Rate</div>
                                    <input type="number" 
                                           id="heartRate" 
                                           name="heartRate" 
                                           class="vital-sign-input" 
                                           placeholder="72"
                                           min="40" 
                                           max="200">
                                    <div class="vital-sign-unit">bpm</div>
                                </div>
                                
                                <div class="vital-sign-group">
                                    <div class="vital-sign-label">Temperature</div>
                                    <input type="number" 
                                           id="temperature" 
                                           name="temperature" 
                                           class="vital-sign-input" 
                                           placeholder="36.5"
                                           min="30" 
                                           max="45" 
                                           step="0.1">
                                    <div class="vital-sign-unit">°C</div>
                                </div>
                                
                                <div class="vital-sign-group">
                                    <div class="vital-sign-label">Respiratory Rate</div>
                                    <input type="number" 
                                           id="respiratoryRate" 
                                           name="respiratoryRate" 
                                           class="vital-sign-input" 
                                           placeholder="18"
                                           min="8" 
                                           max="40">
                                    <div class="vital-sign-unit">breaths/min</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Measurements -->
                        <div class="card-section">
                            <div class="section-title">
                                <i class="fas fa-weight-hanging"></i> Additional Measurements
                            </div>
                            
                            <div class="form-grid four-column">
                                <div class="form-group">
                                    <label class="form-label" for="weight">Weight</label>
                                    <div style="position: relative;">
                                        <input type="number" 
                                               id="weight" 
                                               name="weight" 
                                               class="form-control" 
                                               placeholder="65"
                                               min="1" 
                                               max="500" 
                                               step="0.1">
                                        <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #666;">kg</span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="height">Height</label>
                                    <div style="position: relative;">
                                        <input type="number" 
                                               id="height" 
                                               name="height" 
                                               class="form-control" 
                                               placeholder="165"
                                               min="50" 
                                               max="250">
                                        <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #666;">cm</span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="bmi">BMI</label>
                                    <input type="text" 
                                           id="bmi" 
                                           name="bmi" 
                                           class="form-control readonly" 
                                           placeholder="Auto-calculated"
                                           readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="oxygenSaturation">Oxygen Saturation</label>
                                    <div style="position: relative;">
                                        <input type="number" 
                                               id="oxygenSaturation" 
                                               name="oxygenSaturation" 
                                               class="form-control" 
                                               placeholder="98"
                                               min="70" 
                                               max="100">
                                        <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #666;">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Chief Complaint -->
                        <div class="card-section">
                            <div class="section-title">
                                <i class="fas fa-comments"></i> Chief Complaint
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required" for="chiefComplaint">Patient's Primary Concern</label>
                                <textarea id="chiefComplaint" 
                                          name="chiefComplaint" 
                                          class="form-control" 
                                          placeholder="Describe the patient's main symptoms or reason for visit..."
                                          required></textarea>
                            </div>
                            
                            <div class="form-grid two-column">
                                <div class="form-group">
                                    <label class="form-label" for="painLevel">Pain Level (0-10)</label>
                                    <select id="painLevel" name="painLevel" class="form-control">
                                        <option value="">Select pain level</option>
                                        <option value="0">0 - No pain</option>
                                        <option value="1">1 - Mild pain</option>
                                        <option value="2">2 - Mild pain</option>
                                        <option value="3">3 - Moderate pain</option>
                                        <option value="4">4 - Moderate pain</option>
                                        <option value="5">5 - Moderate pain</option>
                                        <option value="6">6 - Severe pain</option>
                                        <option value="7">7 - Severe pain</option>
                                        <option value="8">8 - Very severe pain</option>
                                        <option value="9">9 - Extreme pain</option>
                                        <option value="10">10 - Worst possible pain</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="symptomDuration">Symptom Duration</label>
                                    <input type="text" 
                                           id="symptomDuration" 
                                           name="symptomDuration" 
                                           class="form-control" 
                                           placeholder="e.g., 3 days, 1 week, 2 hours">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Triage Priority -->
                        <div class="card-section">
                            <div class="section-title">
                                <i class="fas fa-exclamation-triangle"></i> Triage Priority Assessment
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required" for="triagePriority">Priority Level</label>
                                <select id="triagePriority" name="triagePriority" class="form-control" required>
                                    <option value="">Select priority level</option>
                                    <option value="emergency" style="color: #dc3545; font-weight: bold;">🔴 Emergency - Immediate attention required</option>
                                    <option value="urgent" style="color: #fd7e14; font-weight: bold;">🟠 Urgent - Within 30 minutes</option>
                                    <option value="semi-urgent" style="color: #ffc107; font-weight: bold;">🟡 Semi-Urgent - Within 1 hour</option>
                                    <option value="standard" style="color: #28a745; font-weight: bold;">🟢 Standard - Routine care</option>
                                    <option value="non-urgent" style="color: #6c757d; font-weight: bold;">⚪ Non-Urgent - Can wait</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="triageNotes">Triage Notes</label>
                                <textarea id="triageNotes" 
                                          name="triageNotes" 
                                          class="form-control" 
                                          placeholder="Additional observations, concerns, or special instructions..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="btn-group">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Save Triage Assessment
                            </button>
                            <button type="button" id="printTriageBtn" class="btn btn-secondary">
                                <i class="fas fa-print"></i> Print Assessment
                            </button>
                            <button type="button" class="btn btn-warning">
                                <i class="fas fa-user-md"></i> Send to Doctor
                            </button>
                            <a href="../dashboard.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                        
                        <!-- Hidden fields for form submission -->
                        <input type="hidden" id="patientId" name="patientId" value="<?= $patient_data ? $patient_data['patient_id'] : '' ?>">
                        <input type="hidden" name="nurseId" value="<?= get_employee_session('employee_id') ?>">
                        <input type="hidden" name="triageDate" value="<?= date('Y-m-d H:i:s') ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Include JavaScript -->
    <script src="../../../assets/js/clinical-encounter/triage.js"></script>
    
    <script>
        // Page-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Triage Assessment page loaded');
            console.log('Current user:', '<?= $employee_name ?> (<?= $employee_role ?>)');
            
            // Auto-calculate BMI when weight and height are entered
            function calculateBMI() {
                const weight = parseFloat(document.getElementById('weight').value);
                const height = parseFloat(document.getElementById('height').value);
                
                if (weight && height) {
                    const heightInMeters = height / 100;
                    const bmi = (weight / (heightInMeters * heightInMeters)).toFixed(1);
                    document.getElementById('bmi').value = bmi;
                    
                    // Add BMI category
                    let category = '';
                    if (bmi < 18.5) category = ' (Underweight)';
                    else if (bmi < 25) category = ' (Normal)';
                    else if (bmi < 30) category = ' (Overweight)';
                    else category = ' (Obese)';
                    
                    document.getElementById('bmi').value = bmi + category;
                }
            }
            
            document.getElementById('weight').addEventListener('input', calculateBMI);
            document.getElementById('height').addEventListener('input', calculateBMI);
            
            // Form validation and submission
            document.getElementById('triageForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Basic validation
                const patientId = document.getElementById('patientId').value;
                const chiefComplaint = document.getElementById('chiefComplaint').value;
                const triagePriority = document.getElementById('triagePriority').value;
                
                if (!patientId) {
                    alert('Please select a patient first.');
                    return;
                }
                
                if (!chiefComplaint.trim()) {
                    alert('Please enter the chief complaint.');
                    document.getElementById('chiefComplaint').focus();
                    return;
                }
                
                if (!triagePriority) {
                    alert('Please select a triage priority level.');
                    document.getElementById('triagePriority').focus();
                    return;
                }
                
                // Simulate form submission
                alert('Triage assessment saved successfully!\n\nIn a real application, this data would be saved to the database and the patient would be added to the appropriate queue.');
                
                // Optionally redirect to queue or encounter list
                // window.location.href = 'encounter-history.php';
            });
        });
    </script>
</body>
</html>