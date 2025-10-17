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
        SELECT pm.*
        FROM prescribed_medications pm
        WHERE pm.prescription_id = ?
        ORDER BY pm.created_at";
    
    $medStmt = $conn->prepare($medicationsQuery);
    if (!$medStmt) {
        throw new Exception('Failed to prepare medications query: ' . $conn->error);
    }
    $medStmt->bind_param("i", $prescription_id);
    $medStmt->execute();
    $medications = $medStmt->get_result();
    
    // Get pharmacist details from logs (optional - may not exist)
    $pharmacistQuery = "
        SELECT e.first_name, e.last_name, e.employee_id
        FROM prescription_logs pl
        LEFT JOIN employees e ON pl.changed_by_employee_id = e.employee_id
        WHERE pl.prescription_id = ? AND pl.action_type = 'medication_updated'
        ORDER BY pl.created_at DESC
        LIMIT 1";
    
    $pharmStmt = $conn->prepare($pharmacistQuery);
    if (!$pharmStmt) {
        // If prescription_logs table doesn't exist or has issues, continue without pharmacist info
        $pharmacist = null;
    } else {
        try {
            $pharmStmt->bind_param("i", $prescription_id);
            $pharmStmt->execute();
            $pharmacistResult = $pharmStmt->get_result();
            $pharmacist = $pharmacistResult->fetch_assoc();
        } catch (Exception $e) {
            // If there's an error with the logs query, continue without pharmacist info
            $pharmacist = null;
        }
    }
    
    $patientName = trim($prescription['first_name'] . ' ' . $prescription['middle_name'] . ' ' . $prescription['last_name']);
    $doctorName = trim($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']);
    $pharmacistName = $pharmacist ? trim($pharmacist['first_name'] . ' ' . $pharmacist['last_name']) : 'System';
    
} catch (Exception $e) {
    echo '<div class="alert alert-error">Error loading prescription data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit();
}
?>

<div class="print-prescription">
    <!-- Header with CHO Logo and Information -->
    <div class="print-header">
        <img src="../../../assets/images/Nav_Logo_Dark.png" alt="City Health Office Logo" class="print-logo" onerror="this.style.display='none'">
        <h2>CITY HEALTH OFFICE - KORONADAL</h2>
        <p>9VP8+8GX, Koronadal, South Cotabato</p>
        <p>Phone: (083) 228-xxxx | Email: cho.koronadal@example.com</p>
        <h3 style="margin-top: 20px; color: #0066cc;">MEDICAL PRESCRIPTION</h3>
    </div>

    <!-- Patient Information -->
    <div class="patient-info-print" style="margin-bottom: 25px;">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 50%; vertical-align: top; border: none; padding: 0;">
                    <strong>PATIENT INFORMATION</strong><br>
                    <strong>Name:</strong> <?= htmlspecialchars($patientName) ?><br>
                    <strong>Patient ID:</strong> <?= htmlspecialchars($prescription['patient_id_display']) ?><br>
                    <strong>Date of Birth:</strong> <?= $prescription['date_of_birth'] ? date('M d, Y', strtotime($prescription['date_of_birth'])) : 'Not specified' ?><br>
                    <strong>Sex:</strong> <?= htmlspecialchars($prescription['sex'] ?? 'Not specified') ?><br>
                    <strong>Address:</strong> <?= htmlspecialchars($prescription['address'] ?? 'Not specified') ?><br>
                    <strong>Barangay:</strong> <?= htmlspecialchars($prescription['barangay'] ?? 'Not specified') ?>
                </td>
                <td style="width: 50%; vertical-align: top; border: none; padding: 0; padding-left: 20px;">
                    <strong>PRESCRIPTION DETAILS</strong><br>
                    <strong>Prescription ID:</strong> RX-<?= sprintf('%06d', $prescription['prescription_id']) ?><br>
                    <strong>Date Prescribed:</strong> <?= date('M d, Y', strtotime($prescription['prescription_date'])) ?><br>
                    <?php if ($prescription['status'] === 'dispensed'): ?>
                    <strong>Date Dispensed:</strong> <?= date('M d, Y', strtotime($prescription['updated_at'])) ?><br>
                    <?php endif; ?>
                    <strong>Prescribing Doctor:</strong> <?= htmlspecialchars($doctorName) ?><br>
                    <?php if (!empty($prescription['diagnosis'])): ?>
                    <strong>Diagnosis:</strong> <?= htmlspecialchars($prescription['diagnosis']) ?><br>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <?php if (!empty($prescription['chief_complaint']) || !empty($prescription['treatment_plan'])): ?>
    <!-- Clinical Information -->
    <div class="clinical-info-print" style="margin-bottom: 25px; border: 1px solid #000; padding: 10px;">
        <?php if (!empty($prescription['chief_complaint'])): ?>
        <p><strong>Chief Complaint:</strong> <?= htmlspecialchars($prescription['chief_complaint']) ?></p>
        <?php endif; ?>
        <?php if (!empty($prescription['treatment_plan'])): ?>
        <p><strong>Clinical Notes:</strong> <?= htmlspecialchars($prescription['treatment_plan']) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Prescribed Medications -->
    <div class="medications-section-print">
        <h4 style="border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 15px;">PRESCRIBED MEDICATIONS</h4>
        
        <?php if ($medications && $medications->num_rows > 0): ?>
        <table class="prescription-medications-print">
            <thead>
                <tr>
                    <th style="width: 25%;">Medication Name</th>
                    <th style="width: 15%;">Dosage</th>
                    <th style="width: 15%;">Frequency</th>
                    <th style="width: 12%;">Duration</th>
                    <th style="width: 23%;">Instructions</th>
                    <th style="width: 10%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $medCount = 1; ?>
                <?php while ($med = $medications->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($med['medication_name'] ?? 'Custom Medication') ?></strong>
                        <?php if (!empty($med['generic_name'])): ?>
                        <br><em>(<?= htmlspecialchars($med['generic_name']) ?>)</em>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($med['dosage'] ?? 'As prescribed') ?></td>
                    <td><?= htmlspecialchars($med['frequency'] ?? 'As directed') ?></td>
                    <td><?= htmlspecialchars($med['duration'] ?? 'Complete course') ?></td>
                    <td><?= htmlspecialchars($med['instructions'] ?? 'Take as directed by doctor') ?></td>
                    <td style="text-align: center;">
                        <?php 
                        $status = $med['status'] ?? 'pending';
                        if ($status === 'dispensed'): ?>
                            <span style="color: #000; font-weight: bold;">✓ DISPENSED</span>
                        <?php elseif ($status === 'unavailable'): ?>
                            <span style="color: #dc3545; font-weight: bold;">✗ UNAVAILABLE</span>
                        <?php else: ?>
                            <span style="color: #666;">PENDING</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php $medCount++; ?>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; font-style: italic; padding: 20px;">No medications prescribed.</p>
        <?php endif; ?>
    </div>

    <?php if (!empty($prescription['instructions'])): ?>
    <!-- General Instructions -->
    <div class="instructions-print" style="margin-top: 25px; border: 1px solid #000; padding: 10px;">
        <strong>GENERAL INSTRUCTIONS:</strong><br>
        <?= nl2br(htmlspecialchars($prescription['instructions'])) ?>
    </div>
    <?php endif; ?>

    <!-- Important Notes -->
    <div class="important-notes-print" style="margin-top: 20px; font-size: 11px; border: 1px dashed #666; padding: 10px;">
        <strong>IMPORTANT NOTES:</strong><br>
        • Take medications exactly as prescribed by the doctor<br>
        • Complete the full course of treatment even if you feel better<br>
        • Contact your doctor if you experience any adverse reactions<br>
        • Store medications in a cool, dry place away from direct sunlight<br>
        • Keep medications out of reach of children<br>
        • Do not share your medications with others
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div style="height: 50px; border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
            <strong>Dr. <?= htmlspecialchars($doctorName) ?></strong><br>
            <em>Prescribing Physician</em><br>
            <small>Date: <?= date('M d, Y', strtotime($prescription['prescription_date'])) ?></small>
        </div>
        
        <div class="signature-box">
            <div style="height: 50px; border-bottom: 1px solid #000; margin-bottom: 5px;"></div>
            <strong><?= htmlspecialchars($pharmacistName) ?></strong><br>
            <em>Licensed Pharmacist</em><br>
            <?php if ($prescription['status'] === 'dispensed'): ?>
            <small>Date Dispensed: <?= date('M d, Y', strtotime($prescription['updated_at'])) ?></small>
            <?php else: ?>
            <small>Date: ________________</small>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="print-footer" style="margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #ccc; padding-top: 10px;">
        <p>This prescription is valid for 30 days from the date of issue | For medical inquiries, contact CHO Koronadal</p>
        <p>Generated on: <?= date('M d, Y g:i A') ?> | Prescription ID: RX-<?= sprintf('%06d', $prescription['prescription_id']) ?></p>
    </div>
</div>

<style>
@media print {
    body {
        margin: 0;
        padding: 20px;
        font-family: 'Times New Roman', serif;
        font-size: 12px;
        line-height: 1.4;
    }
    
    .print-prescription {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 0;
        background: white;
        color: black;
    }
    
    .print-header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 3px double #000;
        padding-bottom: 15px;
    }
    
    .print-header h2 {
        margin: 10px 0 5px 0;
        font-size: 18px;
        font-weight: bold;
    }
    
    .print-header h3 {
        margin: 15px 0 5px 0;
        font-size: 16px;
    }
    
    .print-header p {
        margin: 2px 0;
        font-size: 11px;
    }
    
    .print-logo {
        width: 60px;
        height: auto;
        margin-bottom: 5px;
    }
    
    .prescription-medications-print {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }
    
    .prescription-medications-print th,
    .prescription-medications-print td {
        border: 1px solid #000;
        padding: 8px 4px;
        text-align: left;
        font-size: 11px;
        vertical-align: top;
    }
    
    .prescription-medications-print th {
        background-color: #f5f5f5;
        font-weight: bold;
        text-align: center;
    }
    
    .signature-section {
        margin-top: 40px;
        display: flex;
        justify-content: space-between;
        page-break-inside: avoid;
    }
    
    .signature-box {
        width: 45%;
        text-align: center;
        margin-top: 20px;
    }
    
    .signature-box div {
        margin-bottom: 5px;
    }
    
    @page {
        margin: 0.75in;
        size: A4;
    }
}

/* Screen styles for modal view */
@media screen {
    .print-prescription {
        font-family: 'Times New Roman', serif;
        padding: 20px;
        background: white;
        border: 1px solid #ddd;
        margin: 10px;
    }
    
    .print-header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #000;
        padding-bottom: 20px;
    }
    
    .print-logo {
        width: 80px;
        height: auto;
        margin-bottom: 10px;
    }
    
    .prescription-medications-print {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .prescription-medications-print th,
    .prescription-medications-print td {
        border: 1px solid #000;
        padding: 8px;
        text-align: left;
        font-size: 12px;
    }
    
    .prescription-medications-print th {
        background-color: #f0f0f0;
        font-weight: bold;
    }
    
    .signature-section {
        margin-top: 40px;
        display: flex;
        justify-content: space-between;
    }
    
    .signature-box {
        width: 45%;
        text-align: center;
        border-top: 1px solid #000;
        padding-top: 10px;
        margin-top: 40px;
    }
}
</style>