<?php
// Include patient session configuration FIRST
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// Check if user is logged in
if (!isset($_SESSION['patient_id'])) {
    header('Location: ../auth/patient_login.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

$patient_id = $_SESSION['patient_id'];
$prescription_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$prescription_id) {
    echo "Invalid prescription ID";
    exit();
}

// Fetch prescription details
$prescription = null;
$medications = [];
$patient_info = null;

try {
    // Get patient info
    $stmt = $conn->prepare("
        SELECT p.*, b.barangay_name
        FROM patients p
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        WHERE p.patient_id = ?
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_info = $result->fetch_assoc();
    $stmt->close();

    // Get prescription details
    $stmt = $conn->prepare("
        SELECT p.*,
               CONCAT(e.first_name, ' ', e.last_name) as doctor_name,
               e.position, e.license_number,
               a.appointment_date, a.appointment_time
        FROM prescriptions p
        LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
        LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
        WHERE p.prescription_id = ? AND p.patient_id = ?
    ");
    $stmt->bind_param("ii", $prescription_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $prescription = $result->fetch_assoc();
    $stmt->close();

    if (!$prescription) {
        echo "Prescription not found";
        exit();
    }

    // Get prescribed medications
    $stmt = $conn->prepare("
        SELECT *
        FROM prescribed_medications
        WHERE prescription_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("i", $prescription_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    echo "Error loading prescription: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription #<?php echo htmlspecialchars($prescription['prescription_id']); ?> - CHO Koronadal</title>
    <style>
        @media print {
            @page {
                margin: 0.5in;
                size: A4;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }

        .prescription-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #0077b6;
        }

        .header h1 {
            color: #0077b6;
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }

        .header p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }

        .prescription-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .patient-section, .prescription-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #0077b6;
        }

        .section-title {
            font-weight: bold;
            color: #0077b6;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .info-row {
            margin-bottom: 8px;
            display: flex;
        }

        .info-label {
            font-weight: 600;
            width: 120px;
            color: #333;
        }

        .info-value {
            color: #666;
            flex: 1;
        }

        .medications-section {
            margin-top: 30px;
        }

        .medications-title {
            background: #0077b6;
            color: white;
            padding: 15px 20px;
            margin: 0 -40px 20px -40px;
            font-size: 18px;
            font-weight: bold;
        }

        .medication-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .medication-name {
            font-size: 18px;
            font-weight: bold;
            color: #0077b6;
            margin-bottom: 10px;
        }

        .medication-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .medication-detail {
            display: flex;
            align-items: center;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            width: 80px;
        }

        .detail-value {
            color: #666;
            flex: 1;
        }

        .remarks-section {
            margin-top: 30px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
        }

        .remarks-title {
            font-weight: bold;
            color: #856404;
            margin-bottom: 10px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0077b6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 119, 182, 0.3);
            transition: all 0.3s ease;
        }

        .print-button:hover {
            background: #005f8a;
            transform: translateY(-2px);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-dispensed {
            background: #cce7ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .prescription-container {
                padding: 20px;
                margin: 10px;
            }

            .prescription-info {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .medication-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Print Prescription
    </button>

    <div class="prescription-container">
        <div class="header">
            <h1>City Health Office - Koronadal</h1>
            <p>Medical Prescription</p>
            <p>Zone IV, Poblacion, Koronadal City, South Cotabato</p>
            <p>Contact: (083) 228-3531</p>
        </div>

        <div class="prescription-info">
            <div class="patient-section">
                <div class="section-title">Patient Information</div>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient_info['first_name'] . ' ' . $patient_info['middle_name'] . ' ' . $patient_info['last_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age:</span>
                    <span class="info-value"><?php echo date('Y') - date('Y', strtotime($patient_info['date_of_birth'])); ?> years old</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Sex:</span>
                    <span class="info-value"><?php echo htmlspecialchars(ucfirst($patient_info['sex'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient_info['address'] . ', ' . ($patient_info['barangay_name'] ?? 'N/A')); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact:</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient_info['phone_number'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <div class="prescription-section">
                <div class="section-title">Prescription Details</div>
                <div class="info-row">
                    <span class="info-label">Prescription #:</span>
                    <span class="info-value"><?php echo htmlspecialchars($prescription['prescription_id']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span class="info-value"><?php echo date('F j, Y - g:i A', strtotime($prescription['prescription_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Prescribed by:</span>
                    <span class="info-value">Dr. <?php echo htmlspecialchars($prescription['doctor_name'] ?? 'Unknown'); ?></span>
                </div>
                <?php if (!empty($prescription['position'])): ?>
                <div class="info-row">
                    <span class="info-label">Position:</span>
                    <span class="info-value"><?php echo htmlspecialchars($prescription['position']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($prescription['license_number'])): ?>
                <div class="info-row">
                    <span class="info-label">License #:</span>
                    <span class="info-value"><?php echo htmlspecialchars($prescription['license_number']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?php echo htmlspecialchars($prescription['status']); ?>">
                            <?php echo htmlspecialchars(strtoupper($prescription['status'])); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div class="medications-section">
            <div class="medications-title">℞ Prescribed Medications</div>
            
            <?php if (empty($medications)): ?>
                <div class="medication-item">
                    <p style="text-align: center; color: #6c757d; margin: 0;">No medications prescribed</p>
                </div>
            <?php else: ?>
                <?php foreach ($medications as $index => $medication): ?>
                    <div class="medication-item">
                        <div class="medication-name">
                            <?php echo ($index + 1) . '. ' . htmlspecialchars($medication['medication_name']); ?>
                            <span class="status-badge status-<?php echo htmlspecialchars($medication['status']); ?>" style="margin-left: 10px; font-size: 10px;">
                                <?php echo htmlspecialchars(strtoupper($medication['status'])); ?>
                            </span>
                        </div>
                        <div class="medication-details">
                            <div class="medication-detail">
                                <span class="detail-label">Dosage:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($medication['dosage']); ?></span>
                            </div>
                            <?php if (!empty($medication['frequency'])): ?>
                            <div class="medication-detail">
                                <span class="detail-label">Frequency:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($medication['frequency']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($medication['duration'])): ?>
                            <div class="medication-detail">
                                <span class="detail-label">Duration:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($medication['duration']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($medication['instructions'])): ?>
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                            <strong>Instructions:</strong> <?php echo htmlspecialchars($medication['instructions']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($prescription['remarks'])): ?>
        <div class="remarks-section">
            <div class="remarks-title">📝 Additional Remarks</div>
            <p style="margin: 0;"><?php echo nl2br(htmlspecialchars($prescription['remarks'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p><strong>Important:</strong> This prescription is valid only when presented with proper identification.</p>
            <p>Please follow the prescribed dosage and consult your doctor if you experience any adverse reactions.</p>
            <p>Printed on: <?php echo date('F j, Y - g:i A'); ?></p>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>