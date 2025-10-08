<?php
// Resolve path to root directory using realpath for consistent path format
$root_path = realpath(dirname(dirname(dirname(dirname(__FILE__)))));

// Include authentication and config
require_once $root_path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'session' . DIRECTORY_SEPARATOR . 'employee_session.php';
require_once $root_path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

// Check if employee is logged in
if (!is_employee_logged_in()) {
    header("Location: /wbhsms-cho-koronadal/pages/management/login.php");
    exit();
}

// Get employee details with role lookup
$employee_id = get_employee_session('employee_id');
$employee_stmt = $conn->prepare("SELECT e.*, r.role_name as role FROM employees e LEFT JOIN roles r ON e.role_id = r.role_id WHERE e.employee_id = ?");
$employee_stmt->bind_param("i", $employee_id);
$employee_stmt->execute();
$employee_result = $employee_stmt->get_result();
$employee_details = $employee_result->fetch_assoc();

if (!$employee_details) {
    header("Location: /wbhsms-cho-koronadal/pages/management/login.php");
    exit();
}

$employee_role = strtolower($employee_details['role']);
$employee_name = $employee_details['first_name'] . ' ' . $employee_details['last_name'];

// Include reusable topbar component
require_once $root_path . '/includes/topbar.php';

// Get encounter ID
$encounter_id = $_GET['encounter_id'] ?? '';
if (!$encounter_id || !is_numeric($encounter_id)) {
    header('Location: ../index.php');
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';
$encounter = null;

// Fetch encounter details
try {
    $stmt = $conn->prepare("
        SELECT e.*, 
               p.first_name as patient_first_name, p.middle_name as patient_middle_name, 
               p.last_name as patient_last_name, p.username as patient_id_display,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as patient_age, p.sex
        FROM clinical_encounters e
        JOIN patients p ON e.patient_id = p.patient_id
        WHERE e.encounter_id = ?
    ");
    $stmt->bind_param('i', $encounter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $encounter = $result->fetch_assoc();
    
    if (!$encounter) {
        header('Location: ../index.php');
        exit();
    }
    
} catch (Exception $e) {
    header('Location: ../index.php');
    exit();
}

// Get existing lab tests for this encounter
$existing_tests = [];
try {
    $stmt = $conn->prepare("
        SELECT l.*, orderer.first_name as orderer_first_name, orderer.last_name as orderer_last_name
        FROM lab_tests l
        LEFT JOIN employees orderer ON l.ordered_by = orderer.employee_id
        WHERE l.encounter_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->bind_param('i', $encounter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_tests = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for existing tests
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'order_lab_test') {
        try {
            $conn->begin_transaction();
            
            // Get form data
            $test_name = trim($_POST['test_name'] ?? '');
            $test_type = trim($_POST['test_type'] ?? '');
            $custom_test_name = trim($_POST['custom_test_name'] ?? '');
            $instructions = trim($_POST['instructions'] ?? '');
            $priority = $_POST['priority'] ?? 'routine';
            $sample_type = trim($_POST['sample_type'] ?? '');
            $fasting_required = isset($_POST['fasting_required']) ? 1 : 0;
            $expected_date = !empty($_POST['expected_date']) ? $_POST['expected_date'] : null;
            $notes = trim($_POST['notes'] ?? '');
            
            // Determine final test name
            $final_test_name = $test_name;
            if ($test_type === 'other' && !empty($custom_test_name)) {
                $final_test_name = $custom_test_name;
            }
            
            // Validation
            if (empty($final_test_name)) {
                throw new Exception('Please select a test or specify a custom test name.');
            }
            if (empty($test_type)) {
                throw new Exception('Please select a test type.');
            }
            if ($test_type === 'other' && strlen($custom_test_name) < 3) {
                throw new Exception('Custom test name must be at least 3 characters long.');
            }
            if (empty($sample_type)) {
                throw new Exception('Please specify the sample type required.');
            }
            
            // Insert lab test order
            $stmt = $conn->prepare("
                INSERT INTO lab_tests (
                    encounter_id, patient_id, test_name, test_type, instructions, 
                    priority, sample_type, fasting_required, expected_result_date,
                    notes, ordered_by, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ordered', NOW())
            ");
            $stmt->bind_param(
                'iisssssissi',
                $encounter_id, $encounter['patient_id'], $final_test_name, $test_type, $instructions,
                $priority, $sample_type, $fasting_required, $expected_date,
                $notes, $employee_id
            );
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['snackbar_message'] = "Lab test ordered successfully!";
            
            // Redirect back to this page to show updated list
            header("Location: order_lab_test.php?encounter_id=$encounter_id");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Common lab tests
$common_tests = [
    'hematology' => [
        'Complete Blood Count (CBC)',
        'Hemoglobin & Hematocrit',
        'Platelet Count',
        'White Blood Cell Count',
        'Erythrocyte Sedimentation Rate (ESR)'
    ],
    'chemistry' => [
        'Fasting Blood Sugar (FBS)',
        'Random Blood Sugar (RBS)',
        'HbA1c',
        'Lipid Profile',
        'Liver Function Tests',
        'Kidney Function Tests',
        'Electrolytes (Na, K, Cl)',
        'Uric Acid',
        'Total Protein & Albumin'
    ],
    'urinalysis' => [
        'Complete Urinalysis',
        'Urine Microscopy',
        'Urine Culture & Sensitivity'
    ],
    'microbiology' => [
        'Blood Culture',
        'Stool Culture',
        'Wound Culture',
        'Throat Culture',
        'Gram Stain'
    ],
    'serology' => [
        'Hepatitis B Surface Antigen',
        'HIV Screening',
        'VDRL/RPR',
        'Dengue NS1/IgM/IgG',
        'Typhoid Test (Widal)'
    ],
    'other' => [
        'Custom Test (Specify)'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Order Lab Test - <?= htmlspecialchars($encounter['patient_first_name'] . ' ' . $encounter['patient_last_name']) ?> | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .encounter-info-card {
            background: #e8f5e8;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-bottom: 2rem;
        }

        .encounter-info-header {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .encounter-info-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            font-size: 0.9rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.8rem;
            color: #666;
            font-weight: 600;
        }

        .info-value {
            color: #333;
            font-weight: 500;
        }

        .existing-tests-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #6610f2;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #6610f2;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .test-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .test-name {
            font-weight: 600;
            color: #0077b6;
            font-size: 1.1rem;
        }

        .test-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-ordered {
            background: #fff3cd;
            color: #856404;
        }

        .status-collected {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .test-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-sections {
            display: grid;
            gap: 2rem;
        }

        .form-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .form-grid {
            display: grid;
            gap: 1rem;
        }

        .form-grid.two-column {
            grid-template-columns: 1fr 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .test-selection {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            margin-bottom: 1rem;
        }

        .test-categories {
            display: grid;
            gap: 1rem;
        }

        .test-category {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }

        .category-header {
            background: #e9ecef;
            padding: 1rem;
            font-weight: 600;
            color: #495057;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-content {
            display: none;
            padding: 1rem;
            background: white;
        }

        .category-content.active {
            display: block;
        }

        .test-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.5rem;
        }

        .test-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .test-option:hover {
            background: #f8f9fa;
        }

        .test-option input[type="radio"] {
            margin: 0;
        }

        .conditional-field {
            display: none;
            margin-top: 1rem;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .conditional-field.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f1b2b7;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .encounter-info-details {
                grid-template-columns: 1fr;
            }

            .test-details {
                grid-template-columns: 1fr;
            }

            .form-grid.two-column {
                grid-template-columns: 1fr;
            }

            .test-options {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php 
    // Render snackbar notification system
    renderSnackbar();
    
    // Render topbar
    renderTopbar([
        'title' => 'Order Lab Test',
        'back_url' => "../view_consultation.php?id=$encounter_id",
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <?php 
        // Render back button
        renderBackButton([
            'back_url' => "../view_consultation.php?id=$encounter_id",
            'button_text' => '← Back to Consultation'
        ]);
        ?>

        <div class="profile-wrapper">
            <!-- Reminders Box -->
            <div class="reminders-box">
                <strong>Lab Test Ordering Guidelines:</strong>
                <ul>
                    <li>Select appropriate tests based on clinical findings and differential diagnosis.</li>
                    <li>Provide clear instructions for sample collection and patient preparation.</li>
                    <li>Specify priority level (STAT for urgent, routine for standard processing).</li>
                    <li>Include relevant clinical information to assist laboratory interpretation.</li>
                    <li>Consider fasting requirements for certain tests (glucose, lipids).</li>
                </ul>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Encounter Information -->
            <div class="encounter-info-card">
                <div class="encounter-info-header">
                    <i class="fas fa-user-check"></i> Patient & Encounter Information
                </div>
                <div class="encounter-info-details">
                    <div class="info-item">
                        <div class="info-label">Patient Name</div>
                        <div class="info-value"><?= htmlspecialchars($encounter['patient_first_name'] . ' ' . 
                            ($encounter['patient_middle_name'] ? $encounter['patient_middle_name'] . ' ' : '') . 
                            $encounter['patient_last_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Patient ID</div>
                        <div class="info-value"><?= htmlspecialchars($encounter['patient_id_display']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Age / Sex</div>
                        <div class="info-value"><?= htmlspecialchars($encounter['patient_age']) ?> years / <?= htmlspecialchars($encounter['sex']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Encounter Date</div>
                        <div class="info-value"><?= date('M j, Y g:i A', strtotime($encounter['created_at'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Chief Complaint</div>
                        <div class="info-value"><?= htmlspecialchars(substr($encounter['chief_complaint'], 0, 50)) ?><?= strlen($encounter['chief_complaint']) > 50 ? '...' : '' ?></div>
                    </div>
                </div>
            </div>

            <!-- Existing Lab Tests -->
            <div class="existing-tests-section">
                <h3 class="section-title">
                    <i class="fas fa-vial"></i> Existing Lab Tests (<?= count($existing_tests) ?>)
                </h3>
                <?php if (!empty($existing_tests)): ?>
                    <?php foreach ($existing_tests as $test): ?>
                        <div class="test-card">
                            <div class="test-header">
                                <div class="test-name"><?= htmlspecialchars($test['test_name']) ?></div>
                                <div class="test-status status-<?= htmlspecialchars($test['status']) ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $test['status']))) ?>
                                </div>
                            </div>
                            <div class="test-details">
                                <div class="info-item">
                                    <div class="info-label">Type</div>
                                    <div class="info-value"><?= htmlspecialchars(ucwords($test['test_type'])) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Sample Type</div>
                                    <div class="info-value"><?= htmlspecialchars($test['sample_type']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Priority</div>
                                    <div class="info-value"><?= htmlspecialchars(ucwords($test['priority'])) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Ordered By</div>
                                    <div class="info-value"><?= htmlspecialchars($test['orderer_first_name'] . ' ' . $test['orderer_last_name']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Ordered Date</div>
                                    <div class="info-value"><?= date('M j, Y g:i A', strtotime($test['created_at'])) ?></div>
                                </div>
                                <?php if ($test['fasting_required']): ?>
                                <div class="info-item">
                                    <div class="info-label">Special Requirements</div>
                                    <div class="info-value">Fasting Required</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($test['instructions'])): ?>
                            <div class="info-item" style="margin-top: 1rem;">
                                <div class="info-label">Instructions</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($test['instructions'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-vial fa-2x"></i>
                        <p>No lab tests have been ordered for this consultation yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Order New Lab Test Form -->
            <form method="POST" class="lab-test-form">
                <input type="hidden" name="action" value="order_lab_test">

                <div class="form-sections">
                    <!-- Test Selection -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-plus-circle"></i> Order New Lab Test
                        </h3>
                        
                        <div class="form-group">
                            <label>Select Laboratory Test <span class="required">*</span></label>
                            <div class="test-selection">
                                <div class="test-categories">
                                    <?php foreach ($common_tests as $category => $tests): ?>
                                        <div class="test-category">
                                            <div class="category-header" onclick="toggleCategory('<?= $category ?>')">
                                                <span><?= htmlspecialchars(ucwords(str_replace('_', ' ', $category))) ?></span>
                                                <i class="fas fa-chevron-down" id="icon-<?= $category ?>"></i>
                                            </div>
                                            <div class="category-content" id="content-<?= $category ?>">
                                                <div class="test-options">
                                                    <?php foreach ($tests as $test): ?>
                                                        <div class="test-option" onclick="selectTest('<?= htmlspecialchars($test) ?>', '<?= $category ?>')">
                                                            <input type="radio" name="test_name" value="<?= htmlspecialchars($test) ?>" id="test-<?= md5($test) ?>">
                                                            <label for="test-<?= md5($test) ?>"><?= htmlspecialchars($test) ?></label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Test Type Selection -->
                        <div class="form-group">
                            <label for="test_type">Test Type <span class="required">*</span></label>
                            <select id="test_type" name="test_type" required>
                                <option value="">Select test type...</option>
                                <option value="hematology">Hematology</option>
                                <option value="chemistry">Clinical Chemistry</option>
                                <option value="urinalysis">Urinalysis</option>
                                <option value="microbiology">Microbiology</option>
                                <option value="serology">Serology/Immunology</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Custom Test Name (appears when "other" is selected) -->
                        <div class="conditional-field" id="customTestField">
                            <div class="form-group">
                                <label for="custom_test_name">Custom Test Name <span class="required">*</span></label>
                                <input type="text" id="custom_test_name" name="custom_test_name" 
                                       placeholder="Enter the specific test name" minlength="3">
                            </div>
                        </div>
                    </div>

                    <!-- Test Details -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-clipboard-list"></i> Test Details
                        </h3>
                        
                        <div class="form-grid two-column">
                            <div class="form-group">
                                <label for="sample_type">Sample Type <span class="required">*</span></label>
                                <select id="sample_type" name="sample_type" required>
                                    <option value="">Select sample type...</option>
                                    <option value="Blood">Blood</option>
                                    <option value="Serum">Serum</option>
                                    <option value="Plasma">Plasma</option>
                                    <option value="Urine">Urine</option>
                                    <option value="Stool">Stool</option>
                                    <option value="Sputum">Sputum</option>
                                    <option value="Wound swab">Wound Swab</option>
                                    <option value="Throat swab">Throat Swab</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="priority">Priority Level</label>
                                <select id="priority" name="priority">
                                    <option value="routine">Routine</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="stat">STAT (Emergency)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="expected_date">Expected Result Date</label>
                                <input type="date" id="expected_date" name="expected_date" 
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="fasting_required" name="fasting_required">
                                    <label for="fasting_required">Fasting Required</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="instructions">Instructions for Lab Technician</label>
                            <textarea id="instructions" name="instructions" 
                                      placeholder="Special handling instructions, clinical information, or collection notes"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea id="notes" name="notes"
                                      placeholder="Clinical indication, differential diagnosis, or other relevant information"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../view_consultation.php?id=<?= $encounter_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-vial"></i> Order Lab Test
                    </button>
                </div>
            </form>
        </div>
    </section>

    <script>
        // Toggle category visibility
        function toggleCategory(category) {
            const content = document.getElementById(`content-${category}`);
            const icon = document.getElementById(`icon-${category}`);
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.style.transform = 'rotate(0deg)';
            } else {
                // Close all other categories
                document.querySelectorAll('.category-content').forEach(c => c.classList.remove('active'));
                document.querySelectorAll('.category-header i').forEach(i => i.style.transform = 'rotate(0deg)');
                
                // Open selected category
                content.classList.add('active');
                icon.style.transform = 'rotate(180deg)';
            }
        }

        // Select test and set appropriate test type
        function selectTest(testName, category) {
            const radioBtn = document.querySelector(`input[value="${testName}"]`);
            if (radioBtn) {
                radioBtn.checked = true;
            }
            
            // Auto-select test type based on category
            const testTypeSelect = document.getElementById('test_type');
            if (testTypeSelect) {
                testTypeSelect.value = category === 'other' ? 'other' : category;
                handleTestTypeChange(); // Trigger the change handler
            }
        }

        // Handle test type change
        function handleTestTypeChange() {
            const testType = document.getElementById('test_type').value;
            const customTestField = document.getElementById('customTestField');
            const customTestInput = document.getElementById('custom_test_name');
            
            if (testType === 'other') {
                customTestField.classList.add('show');
                customTestInput.required = true;
            } else {
                customTestField.classList.remove('show');
                customTestInput.required = false;
                customTestInput.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Setup test type change handler
            const testTypeSelect = document.getElementById('test_type');
            if (testTypeSelect) {
                testTypeSelect.addEventListener('change', handleTestTypeChange);
            }

            // Auto-resize textareas
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            });

            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const testName = document.querySelector('input[name="test_name"]:checked');
                    const testType = document.getElementById('test_type').value;
                    const sampleType = document.getElementById('sample_type').value;
                    
                    if (!testName && testType !== 'other') {
                        e.preventDefault();
                        alert('Please select a laboratory test.');
                        return false;
                    }
                    
                    if (!testType) {
                        e.preventDefault();
                        alert('Please select a test type.');
                        document.getElementById('test_type').focus();
                        return false;
                    }
                    
                    if (testType === 'other') {
                        const customTestName = document.getElementById('custom_test_name').value.trim();
                        if (!customTestName || customTestName.length < 3) {
                            e.preventDefault();
                            alert('Please specify the custom test name (minimum 3 characters).');
                            document.getElementById('custom_test_name').focus();
                            return false;
                        }
                    }
                    
                    if (!sampleType) {
                        e.preventDefault();
                        alert('Please select the sample type.');
                        document.getElementById('sample_type').focus();
                        return false;
                    }
                });
            }
        });
    </script>
</body>

</html>