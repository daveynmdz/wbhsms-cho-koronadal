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
               pt.date_of_birth, pt.sex, pt.address, pt.barangay, pt.contact_number,
               e.first_name as doctor_first_name, e.last_name as doctor_last_name,
               c.consultation_id, c.consultation_date, c.chief_complaint, c.diagnosis, c.recommendations
        FROM prescriptions p
        LEFT JOIN patients pt ON p.patient_id = pt.patient_id  
        LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
        LEFT JOIN consultations c ON p.consultation_id = c.consultation_id
        WHERE p.prescription_id = ? AND p.status = 'dispensed'";
    
    $stmt = $conn->prepare($prescriptionQuery);
    $stmt->bind_param("i", $prescription_id);
    $stmt->execute();
    $prescription = $stmt->get_result()->fetch_assoc();
    
    if (!$prescription) {
        echo '<div class="alert alert-error">Dispensed prescription not found</div>';
        exit();
    }
    
    // Get prescribed medications
    $medicationsQuery = "
        SELECT pm.*, m.medication_name, m.dosage, m.form
        FROM prescribed_medications pm
        LEFT JOIN medications m ON pm.medication_id = m.medication_id
        WHERE pm.prescription_id = ?
        ORDER BY pm.created_at";
    
    $medStmt = $conn->prepare($medicationsQuery);
    $medStmt->bind_param("i", $prescription_id);
    $medStmt->execute();
    $medications = $medStmt->get_result();
    
    $patientName = trim($prescription['first_name'] . ' ' . $prescription['middle_name'] . ' ' . $prescription['last_name']);
    $doctorName = trim($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']);
    
} catch (Exception $e) {
    echo '<div class="alert alert-error">Error loading prescription data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit();
}
?>

<style>
.dispensed-form {
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

.prescription-summary {
    background: #d1ecf1;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
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

.status-dispensed {
    color: #000;
    font-weight: bold;
}

.status-unavailable {
    color: #dc3545;
    font-weight: bold;
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

.dispensed-badge {
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}
</style>

<div class="dispensed-form">
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

    <!-- Doctor Details -->
    <div class="consultation-info">
        <h4><i class="fas fa-user-md"></i> Doctor Details</h4>
        <div class="info-grid">
            <?php if ($prescription['consultation_id']): ?>
            <div class="info-item">
                <div class="info-label">Consultation Date</div>
                <div class="info-value"><?= date('M d, Y', strtotime($prescription['consultation_date'])) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <div class="info-label">Prescribing Doctor</div>
                <div class="info-value"><?= htmlspecialchars($doctorName) ?></div>
            </div>
            <?php if (!empty($prescription['chief_complaint'])): ?>
            <div class="info-item">
                <div class="info-label">Chief Complaint</div>
                <div class="info-value"><?= htmlspecialchars($prescription['chief_complaint']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($prescription['diagnosis'])): ?>
            <div class="info-item">
                <div class="info-label">Diagnosis</div>
                <div class="info-value"><?= htmlspecialchars($prescription['diagnosis']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($prescription['recommendations'])): ?>
        <div class="info-item" style="margin-top: 10px;">
            <div class="info-label">Recommendations</div>
            <div class="info-value"><?= htmlspecialchars($prescription['recommendations']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Prescription Summary -->
    <div class="prescription-summary">
        <h4><i class="fas fa-prescription"></i> Prescription Summary</h4>
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
                <div class="info-label">Date Dispensed</div>
                <div class="info-value"><?= date('M d, Y g:i A', strtotime($prescription['updated_at'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="dispensed-badge">
                        <i class="fas fa-check"></i> Dispensed
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

    <!-- Dispensed Medications -->
    <div>
        <h4><i class="fas fa-pills"></i> Dispensed Medications</h4>
        
        <?php if ($medications && $medications->num_rows > 0): ?>
        <table class="medications-table">
            <thead>
                <tr>
                    <th>Medication Name</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Instructions</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($med = $medications->fetch_assoc()): ?>
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
                        if ($status === 'dispensed'): ?>
                            <span class="status-dispensed">✓ Dispensed</span>
                        <?php elseif ($status === 'unavailable'): ?>
                            <span class="status-unavailable">✗ Unavailable</span>
                        <?php else: ?>
                            <span class="status-pending">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; padding: 15px; background: #d4edda; border-radius: 8px; border-left: 4px solid #28a745;">
            <p style="margin: 0; color: #155724;">
                <i class="fas fa-check-circle"></i> <strong>All medications have been processed.</strong> 
                Medications marked as "Dispensed" are in <strong>black text</strong>, and those marked as "Unavailable" are in <strong style="color: #dc3545;">bold red text</strong>.
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button class="btn btn-success" onclick="printPrescription(<?= $prescription_id ?>)">
                <i class="fas fa-print"></i> Print Prescription
            </button>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-pills"></i>
            <h3>No Medications Prescribed</h3>
            <p>No medications have been prescribed for this consultation.</p>
        </div>
        <?php endif; ?>
    </div>
</div>