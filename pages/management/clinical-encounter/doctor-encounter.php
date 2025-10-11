<?php
/**
 * Doctor Encounter Screen - Clinical Encounter Module
 * CHO Koronadal Healthcare Management System
 */

// Include configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/mock/mock_config.php';

// Check if user is logged in and has appropriate role
if (!is_employee_logged_in() || !in_array(get_employee_session('employee_role'), ['doctor', 'admin'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$employee_name = get_employee_session('employee_name');
$employee_role = get_employee_session('employee_role');

// Mock encounter data (in real app, this would come from database)
$encounter_id = $_GET['encounter_id'] ?? 1;
$patient_id = $_GET['patient_id'] ?? 1;

// Get mock data
$patients = getMockPatients();
$patient_data = $patients[0]; // Default to first patient

// Mock triage data
$triage_data = [
    'blood_pressure' => '120/80',
    'heart_rate' => '72',
    'temperature' => '36.5',
    'respiratory_rate' => '18',
    'weight' => '65.0',
    'height' => '165',
    'bmi' => '23.9 (Normal)',
    'oxygen_saturation' => '98',
    'chief_complaint' => 'Patient complains of persistent headache for the past 3 days, accompanied by mild nausea.',
    'pain_level' => '5',
    'symptom_duration' => '3 days',
    'triage_priority' => 'standard',
    'triage_notes' => 'Patient appears alert and oriented. No signs of acute distress.',
    'triage_date' => '2024-12-10 09:30:00',
    'nurse_name' => 'Alice Johnson'
];

// Mock lab tests and services
$available_lab_tests = [
    'Complete Blood Count (CBC)',
    'Blood Chemistry Panel',
    'Lipid Profile',
    'Liver Function Tests',
    'Kidney Function Tests',
    'Thyroid Function Tests',
    'Blood Sugar (FBS/RBS)',
    'Hemoglobin A1c',
    'Urinalysis',
    'Stool Examination',
    'Chest X-ray',
    'ECG/EKG',
    'Pregnancy Test',
    'Hepatitis B Surface Antigen',
    'Sputum Examination'
];

$available_services = [
    ['name' => 'TB DOTS Treatment', 'description' => 'Tuberculosis Directly Observed Treatment'],
    ['name' => 'Animal Bite Treatment', 'description' => 'Post-exposure prophylaxis and wound care'],
    ['name' => 'Immunization', 'description' => 'Vaccination services'],
    ['name' => 'Family Planning', 'description' => 'Contraceptive counseling and services'],
    ['name' => 'Prenatal Care', 'description' => 'Pregnancy monitoring and care'],
    ['name' => 'Wound Care', 'description' => 'Dressing and wound management'],
    ['name' => 'Blood Pressure Monitoring', 'description' => 'Hypertension management'],
    ['name' => 'Diabetes Management', 'description' => 'Blood sugar monitoring and education'],
    ['name' => 'Mental Health Counseling', 'description' => 'Psychological support services']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Encounter - CHO Koronadal</title>
    
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
            'title' => 'Clinical Encounter',
            'back_url' => '../dashboard.php',
            'user_type' => 'employee'
        ]);
        ?>
        
        <div class="clinical-encounter-container">
            <div class="encounter-header">
                <h1><i class="fas fa-user-md"></i> Clinical Encounter</h1>
                <p>Patient consultation and medical documentation</p>
            </div>
            
            <!-- Patient Information Card -->
            <div class="encounter-card">
                <div class="card-header">
                    <i class="fas fa-user"></i> Patient Information
                </div>
                <div class="card-body">
                    <div class="patient-info-card">
                        <div class="patient-info-grid">
                            <div class="patient-info-item">
                                <span class="patient-info-label">Patient ID</span>
                                <span class="patient-info-value">P<?= str_pad($patient_data['patient_id'], 4, '0', STR_PAD_LEFT) ?></span>
                            </div>
                            <div class="patient-info-item">
                                <span class="patient-info-label">Full Name</span>
                                <span class="patient-info-value"><?= $patient_data['first_name'] . ' ' . ($patient_data['middle_name'] ? $patient_data['middle_name'] . ' ' : '') . $patient_data['last_name'] ?></span>
                            </div>
                            <div class="patient-info-item">
                                <span class="patient-info-label">Age</span>
                                <span class="patient-info-value"><?= date('Y') - date('Y', strtotime($patient_data['birthdate'])) ?> years</span>
                            </div>
                            <div class="patient-info-item">
                                <span class="patient-info-label">Gender</span>
                                <span class="patient-info-value"><?= $patient_data['gender'] ?></span>
                            </div>
                            <div class="patient-info-item">
                                <span class="patient-info-label">Blood Type</span>
                                <span class="patient-info-value"><?= $patient_data['blood_type'] ?></span>
                            </div>
                            <div class="patient-info-item">
                                <span class="patient-info-label">Contact</span>
                                <span class="patient-info-value"><?= $patient_data['contact_number'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Triage Results Card -->
            <div class="encounter-card">
                <div class="card-header">
                    <i class="fas fa-stethoscope"></i> Triage Assessment Results
                </div>
                <div class="card-body">
                    <div class="card-section">
                        <div class="section-title">Vital Signs</div>
                        <div class="vitals-grid">
                            <div class="vital-sign-group">
                                <div class="vital-sign-label">Blood Pressure</div>
                                <div class="vital-sign-input readonly" style="background: #f8f9fa;"><?= $triage_data['blood_pressure'] ?></div>
                                <div class="vital-sign-unit">mmHg</div>
                            </div>
                            <div class="vital-sign-group">
                                <div class="vital-sign-label">Heart Rate</div>
                                <div class="vital-sign-input readonly" style="background: #f8f9fa;"><?= $triage_data['heart_rate'] ?></div>
                                <div class="vital-sign-unit">bpm</div>
                            </div>
                            <div class="vital-sign-group">
                                <div class="vital-sign-label">Temperature</div>
                                <div class="vital-sign-input readonly" style="background: #f8f9fa;"><?= $triage_data['temperature'] ?></div>
                                <div class="vital-sign-unit">°C</div>
                            </div>
                            <div class="vital-sign-group">
                                <div class="vital-sign-label">Respiratory Rate</div>
                                <div class="vital-sign-input readonly" style="background: #f8f9fa;"><?= $triage_data['respiratory_rate'] ?></div>
                                <div class="vital-sign-unit">breaths/min</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-grid two-column">
                        <div class="form-group">
                            <label class="form-label">Chief Complaint</label>
                            <div class="form-control readonly"><?= $triage_data['chief_complaint'] ?></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Triage Priority</label>
                            <div class="status-badge status-<?= $triage_data['triage_priority'] === 'standard' ? 'completed' : 'pending' ?>">
                                <?= ucfirst($triage_data['triage_priority']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Clinical Assessment Form -->
            <form id="encounterForm" class="encounter-form">
                <!-- History and Examination -->
                <div class="encounter-card">
                    <div class="card-header">
                        <i class="fas fa-clipboard-list"></i> History and Physical Examination
                    </div>
                    <div class="card-body">
                        <div class="card-section">
                            <div class="section-title">History of Present Illness</div>
                            <div class="form-group">
                                <label class="form-label" for="presentIllness">Detailed History</label>
                                <textarea id="presentIllness" 
                                          name="presentIllness" 
                                          class="form-control" 
                                          placeholder="Provide detailed history of the present illness, including onset, duration, severity, associated symptoms, aggravating/relieving factors..."
                                          rows="4"></textarea>
                            </div>
                        </div>
                        
                        <div class="card-section">
                            <div class="section-title">Physical Examination</div>
                            <div class="form-grid two-column">
                                <div class="form-group">
                                    <label class="form-label" for="generalAppearance">General Appearance</label>
                                    <textarea id="generalAppearance" 
                                              name="generalAppearance" 
                                              class="form-control" 
                                              placeholder="Overall appearance, alertness, distress level..."
                                              rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="physicalFindings">System-specific Findings</label>
                                    <textarea id="physicalFindings" 
                                              name="physicalFindings" 
                                              class="form-control" 
                                              placeholder="HEENT, Cardiovascular, Respiratory, Abdominal, Neurological findings..."
                                              rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Diagnosis and Treatment -->
                <div class="encounter-card">
                    <div class="card-header">
                        <i class="fas fa-diagnoses"></i> Diagnosis and Treatment Plan
                    </div>
                    <div class="card-body">
                        <div class="card-section">
                            <div class="section-title">Clinical Diagnosis</div>
                            <div class="form-grid two-column">
                                <div class="form-group">
                                    <label class="form-label required" for="primaryDiagnosis">Primary Diagnosis</label>
                                    <input type="text" 
                                           id="primaryDiagnosis" 
                                           name="primaryDiagnosis" 
                                           class="form-control" 
                                           placeholder="Enter primary diagnosis (ICD-10 code if available)"
                                           required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="secondaryDiagnosis">Secondary Diagnosis</label>
                                    <input type="text" 
                                           id="secondaryDiagnosis" 
                                           name="secondaryDiagnosis" 
                                           class="form-control" 
                                           placeholder="Enter secondary diagnosis (if applicable)">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="diagnosticNotes">Diagnostic Notes</label>
                                <textarea id="diagnosticNotes" 
                                          name="diagnosticNotes" 
                                          class="form-control" 
                                          placeholder="Additional diagnostic considerations, differential diagnoses..."
                                          rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="card-section">
                            <div class="section-title">Treatment Plan</div>
                            <div class="form-group">
                                <label class="form-label required" for="treatmentPlan">Treatment Recommendations</label>
                                <textarea id="treatmentPlan" 
                                          name="treatmentPlan" 
                                          class="form-control" 
                                          placeholder="Detailed treatment plan, lifestyle modifications, follow-up instructions..."
                                          rows="4"
                                          required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Prescriptions -->
                <div class="encounter-card">
                    <div class="card-header">
                        <i class="fas fa-pills"></i> Prescriptions
                        <button type="button" id="addPrescriptionBtn" class="btn btn-success" style="margin-left: auto; font-size: 0.9rem; padding: 8px 16px;">
                            <i class="fas fa-plus"></i> Add Medication
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="prescriptionsList" class="prescription-list">
                            <!-- Prescriptions will be dynamically added here -->
                        </div>
                        
                        <div id="noPrescriptions" class="text-center" style="color: #666; padding: 20px;">
                            <i class="fas fa-pills" style="font-size: 3rem; margin-bottom: 10px; opacity: 0.3;"></i>
                            <p>No medications prescribed yet. Click "Add Medication" to start.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Lab Tests and Services -->
                <div class="encounter-card">
                    <div class="card-header">
                        <i class="fas fa-flask"></i> Laboratory Tests and Additional Services
                    </div>
                    <div class="card-body">
                        <div class="card-section">
                            <div class="section-title">
                                Laboratory Tests
                                <button type="button" id="orderLabTestsBtn" class="btn btn-primary" style="margin-left: auto; font-size: 0.9rem; padding: 8px 16px;">
                                    <i class="fas fa-flask"></i> Order Lab Tests
                                </button>
                            </div>
                            
                            <div id="selectedLabTests" class="lab-tests-display" style="display: none;">
                                <div class="form-group">
                                    <label class="form-label">Selected Laboratory Tests</label>
                                    <div id="labTestsList" class="selected-tests-list"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-section">
                            <div class="section-title">
                                Additional Services
                                <button type="button" id="requestServicesBtn" class="btn btn-warning" style="margin-left: auto; font-size: 0.9rem; padding: 8px 16px;">
                                    <i class="fas fa-hand-holding-medical"></i> Request Services
                                </button>
                            </div>
                            
                            <div id="selectedServices" class="services-display" style="display: none;">
                                <div class="form-group">
                                    <label class="form-label">Requested Services</label>
                                    <div id="servicesList" class="selected-services-list"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Referral Section -->
                <div class="encounter-card">
                    <div class="card-header">
                        <i class="fas fa-share-square"></i> Referral (Optional)
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" id="needsReferral" name="needsReferral" style="margin-right: 8px;">
                                This patient needs referral to another provider
                            </label>
                        </div>
                        
                        <div id="referralSection" style="display: none;">
                            <div class="form-grid two-column">
                                <div class="form-group">
                                    <label class="form-label" for="referralDestination">Refer to</label>
                                    <select id="referralDestination" name="referralDestination" class="form-control">
                                        <option value="">Select referral destination</option>
                                        <option value="specialist">Specialist (Hospital)</option>
                                        <option value="laboratory">Laboratory</option>
                                        <option value="radiology">Radiology Department</option>
                                        <option value="physical_therapy">Physical Therapy</option>
                                        <option value="mental_health">Mental Health Services</option>
                                        <option value="other_facility">Other Healthcare Facility</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="referralUrgency">Urgency Level</label>
                                    <select id="referralUrgency" name="referralUrgency" class="form-control">
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent (within 24 hours)</option>
                                        <option value="emergent">Emergent (immediate)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="referralReason">Reason for Referral</label>
                                <textarea id="referralReason" 
                                          name="referralReason" 
                                          class="form-control" 
                                          placeholder="Describe the reason for referral and specific services needed..."
                                          rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Encounter Status and Actions -->
                <div class="encounter-card">
                    <div class="card-header">
                        <i class="fas fa-tasks"></i> Encounter Status and Next Steps
                    </div>
                    <div class="card-body">
                        <div class="form-grid two-column">
                            <div class="form-group">
                                <label class="form-label required" for="encounterStatus">Encounter Status</label>
                                <select id="encounterStatus" name="encounterStatus" class="form-control" required>
                                    <option value="">Select status</option>
                                    <option value="completed">Completed - No follow-up needed</option>
                                    <option value="pending_followup">Pending Follow-up</option>
                                    <option value="referred">Referred to another provider</option>
                                    <option value="pending_results">Pending lab/diagnostic results</option>
                                </select>
                            </div>
                            <div class="form-group" id="followupGroup" style="display: none;">
                                <label class="form-label" for="followupDate">Follow-up Date</label>
                                <input type="date" 
                                       id="followupDate" 
                                       name="followupDate" 
                                       class="form-control"
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                        </div>
                        
                        <div id="followupActions" style="display: none;">
                            <button type="button" id="scheduleFollowupBtn" class="btn btn-primary">
                                <i class="fas fa-calendar-plus"></i> Schedule Follow-up Appointment
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="doctorNotes">Doctor's Additional Notes</label>
                            <textarea id="doctorNotes" 
                                      name="doctorNotes" 
                                      class="form-control" 
                                      placeholder="Any additional notes, patient education provided, special instructions..."
                                      rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="btn-group">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Save Encounter
                    </button>
                    <button type="button" id="printEncounterBtn" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print Record
                    </button>
                    <button type="button" id="saveAndCompleteBtn" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Save & Complete Encounter
                    </button>
                    <a href="encounter-history.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
                
                <!-- Hidden fields -->
                <input type="hidden" name="encounterId" value="<?= $encounter_id ?>">
                <input type="hidden" name="patientId" value="<?= $patient_data['patient_id'] ?>">
                <input type="hidden" name="doctorId" value="<?= get_employee_session('employee_id') ?>">
                <input type="hidden" name="encounterDate" value="<?= date('Y-m-d H:i:s') ?>">
            </form>
        </div>
    </div>

    <!-- Lab Tests Modal -->
    <div id="labTestsModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <span>Select Laboratory Tests</span>
                <button type="button" class="modal-close" onclick="closeModal('labTestsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="lab-tests-grid">
                    <?php foreach ($available_lab_tests as $test): ?>
                    <div class="lab-test-item" onclick="toggleLabTest(this, '<?= $test ?>')">
                        <input type="checkbox" class="lab-test-checkbox">
                        <span class="lab-test-label"><?= $test ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="btn-group" style="margin-top: 20px;">
                    <button type="button" class="btn btn-primary" onclick="confirmLabTests()">
                        <i class="fas fa-check"></i> Confirm Selected Tests
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('labTestsModal')">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Services Modal -->
    <div id="servicesModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <span>Request Additional Services</span>
                <button type="button" class="modal-close" onclick="closeModal('servicesModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="services-grid">
                    <?php foreach ($available_services as $service): ?>
                    <div class="service-item" onclick="toggleService(this, '<?= $service['name'] ?>', '<?= $service['description'] ?>')">
                        <input type="checkbox" class="service-checkbox">
                        <div class="service-info">
                            <div class="service-name"><?= $service['name'] ?></div>
                            <div class="service-description"><?= $service['description'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="btn-group" style="margin-top: 20px;">
                    <button type="button" class="btn btn-primary" onclick="confirmServices()">
                        <i class="fas fa-check"></i> Confirm Selected Services
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('servicesModal')">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include JavaScript -->
    <script src="../../../assets/js/clinical-encounter/doctor-encounter.js"></script>
</body>
</html>