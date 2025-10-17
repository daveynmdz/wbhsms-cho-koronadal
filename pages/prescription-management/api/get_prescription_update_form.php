<?php
// Include employee session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo '<div class="alert alert-error">Unauthorized access</div>';
    exit();
}

// Check permissions
$canUpdateMedications = isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 9]); // Admin or Pharmacist

$prescription_id = isset($_GET['prescription_id']) ? intval($_GET['prescription_id']) : 0;

if (!$prescription_id) {
    echo '<div class="alert alert-error">Invalid prescription ID</div>';
    exit();
}

try {
    // Get prescription details with patient and consultation info
    $prescriptionQuery = "
        SELECT p.*, 
               pt.first_name, pt.last_name, pt.middle_name, pt.username as patient_id_display, 
               pt.date_of_birth, pt.sex, pt.contact_number,
               b.barangay_name as barangay,
               e.first_name as doctor_first_name, e.last_name as doctor_last_name,
               c.consultation_id, c.consultation_date, c.chief_complaint, c.diagnosis, c.treatment_plan
        FROM prescriptions p
        LEFT JOIN patients pt ON p.patient_id = pt.patient_id  
        LEFT JOIN barangay b ON pt.barangay_id = b.barangay_id
        LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
        LEFT JOIN consultations c ON p.consultation_id = c.consultation_id
        WHERE p.prescription_id = ?";
    
    $stmt = $conn->prepare($prescriptionQuery);
    if (!$stmt) {
        throw new Exception('Failed to prepare prescription query: ' . $conn->error);
    }
    $stmt->bind_param("i", $prescription_id);
    $stmt->execute();
    $prescription = $stmt->get_result()->fetch_assoc();
    
    if (!$prescription) {
        echo '<div class="alert alert-error">Prescription not found</div>';
        exit();
    }
    
    // Get prescribed medications
    $medicationsQuery = "
        SELECT prescribed_medication_id, prescription_id, medication_name, dosage, frequency, duration, instructions, status
        FROM prescribed_medications 
        WHERE prescription_id = ?
        ORDER BY created_at";
    
    $medStmt = $conn->prepare($medicationsQuery);
    if (!$medStmt) {
        throw new Exception('Failed to prepare medications query: ' . $conn->error);
    }
    $medStmt->bind_param("i", $prescription_id);
    $medStmt->execute();
    $medications = $medStmt->get_result();
    
    // Debug: Log what medications are found for this prescription
    $medCount = $medications->num_rows;
    error_log("Found $medCount medications for prescription $prescription_id");
    
    // Store medications in array for reuse
    $medicationsArray = [];
    while ($med = $medications->fetch_assoc()) {
        $medicationsArray[] = $med;
        error_log("Medication found: ID {$med['prescribed_medication_id']} - {$med['medication_name']} - Status: {$med['status']}");
    }
    
    $patientName = trim($prescription['first_name'] . ' ' . $prescription['middle_name'] . ' ' . $prescription['last_name']);
    $doctorName = trim($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']);
    
} catch (Exception $e) {
    echo '<div class="alert alert-error">Error loading prescription data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit();
}
?>

<style>
.prescription-form {
    padding: 20px;
    max-height: 80vh;
    overflow-y: auto;
}

.patient-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #0077b6;
}

.consultation-info {
    background: #e8f4f8;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #17a2b8;
}

.medications-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.medications-table th,
.medications-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    font-size: 0.9em;
}

.medications-table th {
    background-color: #f8f9fa;
    font-weight: bold;
    color: #03045e;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #28a745;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

input:disabled + .slider {
    background-color: #e9ecef;
    cursor: not-allowed;
}

.status-dispensed {
    color: #28a745;
    font-weight: bold;
}

.status-unavailable {
    color: #dc3545;
    font-weight: bold;
}

.status-pending {
    color: #ffc107;
    font-weight: bold;
}

.update-actions {
    margin-top: 20px;
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-weight: bold;
    color: #495057;
    font-size: 0.85em;
    margin-bottom: 3px;
}

.info-value {
    color: #212529;
    font-size: 0.9em;
}
</style>

<div class="prescription-form">
    <!-- Patient Summary -->
    <div class="patient-summary">
        <h4><i class="fas fa-user"></i> Patient Summary</h4>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Full Name</div>
                <div class="info-value"><?= htmlspecialchars($patientName) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Patient ID</div>
                <div class="info-value"><?= htmlspecialchars($prescription['patient_id_display']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Date of Birth</div>
                <div class="info-value"><?= $prescription['date_of_birth'] ? date('M d, Y', strtotime($prescription['date_of_birth'])) : 'Not specified' ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Sex</div>
                <div class="info-value"><?= htmlspecialchars($prescription['sex'] ?? 'Not specified') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Address</div>
                <div class="info-value"><?= htmlspecialchars($prescription['address'] ?? 'Not specified') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Barangay</div>
                <div class="info-value"><?= htmlspecialchars($prescription['barangay'] ?? 'Not specified') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Contact Number</div>
                <div class="info-value"><?= htmlspecialchars($prescription['contact_number'] ?? 'Not specified') ?></div>
            </div>
        </div>
    </div>

    <!-- Consultation Information -->
    <?php if ($prescription['consultation_id']): ?>
    <div class="consultation-info">
        <h4><i class="fas fa-stethoscope"></i> Consultation Information</h4>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Consultation Date</div>
                <div class="info-value"><?= date('M d, Y', strtotime($prescription['consultation_date'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Prescribing Doctor</div>
                <div class="info-value"><?= htmlspecialchars($doctorName) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Chief Complaint</div>
                <div class="info-value"><?= htmlspecialchars($prescription['chief_complaint'] ?? 'Not specified') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Diagnosis</div>
                <div class="info-value"><?= htmlspecialchars($prescription['diagnosis'] ?? 'Not specified') ?></div>
            </div>
        </div>
        <?php if (!empty($prescription['treatment_plan'])): ?>
        <div class="info-item" style="margin-top: 10px;">
            <div class="info-label">Treatment Plan</div>
            <div class="info-value"><?= htmlspecialchars($prescription['treatment_plan']) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Prescription Details -->
    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
        <h4><i class="fas fa-prescription"></i> Prescription Details</h4>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Prescription ID</div>
                <div class="info-value">RX-<?= sprintf('%06d', $prescription['prescription_id']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Date Prescribed</div>
                <div class="info-value"><?= date('M d, Y', strtotime($prescription['prescription_date'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="status-badge status-<?= $prescription['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $prescription['status'])) ?>
                    </span>
                </div>
            </div>
        </div>
        <?php if (!empty($prescription['instructions'])): ?>
        <div class="info-item" style="margin-top: 10px;">
            <div class="info-label">Instructions</div>
            <div class="info-value"><?= htmlspecialchars($prescription['instructions']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Prescribed Medications -->
    <div>
        <h4><i class="fas fa-pills"></i> Prescribed Medications</h4>
        <form id="updateMedicationsForm" onsubmit="updateMedicationStatuses(event)">
            <input type="hidden" name="prescription_id" value="<?= $prescription_id ?>">
            
            <?php if (!empty($medicationsArray)): ?>
            <table class="medications-table">
                <thead>
                    <tr>
                        <th>Medication Name</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Duration</th>
                        <th>Instructions</th>
                        <th>Status</th>
                        <?php if ($canUpdateMedications): ?>
                        <th>Dispensed</th>
                        <th>Unavailable</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicationsArray as $med): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($med['medication_name'] ?? 'Custom Medication') ?></strong>
                            <?php if (!empty($med['generic_name'])): ?>
                            <br><small>Generic: <?= htmlspecialchars($med['generic_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($med['dosage'] ?? 'As prescribed') ?></td>
                        <td><?= htmlspecialchars($med['frequency'] ?? 'As needed') ?></td>
                        <td><?= htmlspecialchars($med['duration'] ?? 'Complete course') ?></td>
                        <td><?= htmlspecialchars($med['instructions'] ?? 'Take as directed') ?></td>
                        <td>
                            <?php 
                            $status = $med['status'] ?? 'pending';
                            $statusClass = 'status-' . str_replace(' ', '-', strtolower($status));
                            ?>
                            <span class="<?= $statusClass ?>" id="status_<?= $med['prescribed_medication_id'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $status)) ?>
                            </span>
                        </td>
                        <?php if ($canUpdateMedications): ?>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       id="dispensed_<?= $med['prescribed_medication_id'] ?>"
                                       name="dispensed[]" 
                                       value="<?= $med['prescribed_medication_id'] ?>"
                                       <?= $status === 'dispensed' ? 'checked' : '' ?>
                                       onchange="updateMedicationStatus(<?= $med['prescribed_medication_id'] ?>, 'dispensed', this.checked)">
                                <span class="slider"></span>
                            </label>
                        </td>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       id="unavailable_<?= $med['prescribed_medication_id'] ?>"
                                       name="unavailable[]" 
                                       value="<?= $med['prescribed_medication_id'] ?>"
                                       <?= $status === 'unavailable' ? 'checked' : '' ?>
                                       onchange="updateMedicationStatus(<?= $med['prescribed_medication_id'] ?>, 'unavailable', this.checked)">
                                <span class="slider"></span>
                            </label>
                        </td>
                        <?php else: ?>
                        <td colspan="2">
                            <small class="text-muted">Only pharmacists can update medication status</small>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($canUpdateMedications): ?>
            <div class="update-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Medication Statuses
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewUpdatePrescriptionModal')">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-pills"></i>
                <h3>No Medications Prescribed</h3>
                <p>No medications have been prescribed for this consultation.</p>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
window.updateMedicationStatus = function(medicationId, statusType, isChecked) {
    // Prevent both dispensed and unavailable from being checked at the same time
    if (isChecked) {
        const otherType = statusType === 'dispensed' ? 'unavailable' : 'dispensed';
        const otherCheckbox = document.getElementById(otherType + '_' + medicationId);
        if (otherCheckbox.checked) {
            otherCheckbox.checked = false;
        }
    }
    
    // Update the status display
    const statusElement = document.getElementById('status_' + medicationId);
    if (isChecked) {
        statusElement.textContent = statusType === 'dispensed' ? 'Dispensed' : 'Unavailable';
        statusElement.className = 'status-' + statusType;
    } else {
        // Check if the other status is checked
        const otherType = statusType === 'dispensed' ? 'unavailable' : 'dispensed';
        const otherCheckbox = document.getElementById(otherType + '_' + medicationId);
        if (otherCheckbox.checked) {
            statusElement.textContent = otherType === 'dispensed' ? 'Dispensed' : 'Unavailable';
            statusElement.className = 'status-' + otherType;
        } else {
            statusElement.textContent = 'Pending';
            statusElement.className = 'status-pending';
        }
    }
};

window.updateMedicationStatuses = function(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const prescriptionId = formData.get('prescription_id');
    
    // Collect all medication statuses
    const medicationStatuses = [];
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    
    checkboxes.forEach(checkbox => {
        const parts = checkbox.id.split('_');
        const statusType = parts[0];
        const prescribedMedicationId = parts[1];
        
        if (checkbox.checked) {
            medicationStatuses.push({
                prescribed_medication_id: prescribedMedicationId,
                status: statusType
            });
        }
    });
    
    // Send update request
    fetch('../api/update_prescription_medications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            prescription_id: prescriptionId,
            medication_statuses: medicationStatuses
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Medication statuses updated successfully!', 'success');
            
            // If all medications are dispensed, the prescription status should be updated
            if (data.prescription_status_updated) {
                showAlert('Prescription status updated to ' + data.new_status + '!', 'info');
                
                // Refresh the main page after a delay
                setTimeout(() => {
                    closeModal('viewUpdatePrescriptionModal');
                    window.location.reload();
                }, 2000);
            }
        } else {
            showAlert('Error updating medication statuses: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showAlert('Error updating medication statuses: ' + error.message, 'error');
    });
};
</script>