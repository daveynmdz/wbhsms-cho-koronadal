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
if ($employee_stmt) {
    $employee_stmt->bind_param("i", $employee_id);
    $employee_stmt->execute();
    $employee_result = $employee_stmt->get_result();
    $employee_details = $employee_result->fetch_assoc();
} else {
    $employee_details = null;
}

if (!$employee_details) {
    header("Location: /wbhsms-cho-koronadal/pages/management/login.php");
    exit();
}

$employee_role = strtolower($employee_details['role']);
$employee_name = $employee_details['first_name'] . ' ' . $employee_details['last_name'];

// Get visit_id from URL
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
if (!$visit_id) {
    header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=invalid_visit");
    exit();
}

// Initialize variables
$patient_data = null;
$visit_data = null;
$consultation_data = null;
$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_lab_tests'])) {
    $selected_tests = $_POST['lab_tests'] ?? [];
    $special_instructions = trim($_POST['special_instructions'] ?? '');
    
    if (empty($selected_tests)) {
        $error_message = "Please select at least one lab test.";
    } else {
        try {
            $conn->begin_transaction();
            
            // Get consultation_id if it exists
            $consultation_id = null;
            $consult_stmt = $conn->prepare("SELECT consultation_id FROM consultations WHERE visit_id = ?");
            $consult_stmt->bind_param("i", $visit_id);
            $consult_stmt->execute();
            $consult_result = $consult_stmt->get_result();
            if ($consult_row = $consult_result->fetch_assoc()) {
                $consultation_id = $consult_row['consultation_id'];
            }
            
            // Insert lab requests
            $insert_stmt = $conn->prepare("
                INSERT INTO lab_orders (
                    visit_id, patient_id, consultation_id, lab_test_name, 
                    ordered_by, special_instructions, order_status, 
                    order_date, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW(), NOW())
            ");
            
            foreach ($selected_tests as $test_name) {
                $insert_stmt->bind_param(
                    "iiisis", 
                    $visit_id, 
                    $patient_data['patient_id'], 
                    $consultation_id, 
                    $test_name, 
                    $employee_id, 
                    $special_instructions
                );
                $insert_stmt->execute();
            }
            
            $conn->commit();
            
            // Redirect back to consultation with success message
            $success_param = urlencode("Lab tests ordered successfully: " . implode(", ", $selected_tests));
            header("Location: ../consultation.php?visit_id=$visit_id&success=" . $success_param);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error ordering lab tests: " . $e->getMessage();
        }
    }
}

// Load patient and visit data
try {
    // Get patient and visit data
    $data_stmt = $conn->prepare("
        SELECT v.*, p.first_name, p.last_name, p.username, p.date_of_birth, p.sex, p.contact_number,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               b.barangay_name, d.district_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN districts d ON b.district_id = d.district_id
        WHERE v.visit_id = ?
    ");
    $data_stmt->bind_param("i", $visit_id);
    $data_stmt->execute();
    $result = $data_stmt->get_result();
    $combined_data = $result->fetch_assoc();
    
    if (!$combined_data) {
        header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=visit_not_found");
        exit();
    }
    
    $patient_data = $combined_data;
    $visit_data = $combined_data;
    
    // Get consultation data if exists
    $consultation_stmt = $conn->prepare("SELECT * FROM consultations WHERE visit_id = ?");
    $consultation_stmt->bind_param("i", $visit_id);
    $consultation_stmt->execute();
    $consultation_result = $consultation_stmt->get_result();
    $consultation_data = $consultation_result->fetch_assoc();
    
} catch (Exception $e) {
    $error_message = "Error loading data: " . $e->getMessage();
}

// Get available lab tests
$lab_tests = [];
try {
    // First check if lab_tests table exists, if not use predefined list
    $check_table = $conn->query("SHOW TABLES LIKE 'lab_tests'");
    if ($check_table->num_rows > 0) {
        $lab_stmt = $conn->prepare("SELECT test_name, test_description FROM lab_tests WHERE is_active = 1 ORDER BY test_name");
        $lab_stmt->execute();
        $lab_result = $lab_stmt->get_result();
        $lab_tests = $lab_result->fetch_all(MYSQLI_ASSOC);
    } else {
        // Predefined lab tests if table doesn't exist
        $lab_tests = [
            ['test_name' => 'Complete Blood Count (CBC)', 'test_description' => 'Blood test to evaluate overall health'],
            ['test_name' => 'Urinalysis', 'test_description' => 'Urine test to detect infections and kidney problems'],
            ['test_name' => 'Fecalysis', 'test_description' => 'Stool examination for parasites and infections'],
            ['test_name' => 'Blood Sugar (FBS)', 'test_description' => 'Fasting blood sugar test'],
            ['test_name' => 'Blood Sugar (RBS)', 'test_description' => 'Random blood sugar test'],
            ['test_name' => 'Lipid Profile', 'test_description' => 'Cholesterol and triglycerides test'],
            ['test_name' => 'Liver Function Test', 'test_description' => 'Tests to check liver health'],
            ['test_name' => 'Kidney Function Test', 'test_description' => 'Tests to check kidney health'],
            ['test_name' => 'Thyroid Function Test', 'test_description' => 'Tests to check thyroid hormone levels'],
            ['test_name' => 'Chest X-Ray', 'test_description' => 'Imaging of chest and lungs'],
            ['test_name' => 'ECG/EKG', 'test_description' => 'Electrocardiogram for heart rhythm'],
            ['test_name' => 'Hepatitis B Surface Antigen', 'test_description' => 'Test for Hepatitis B infection'],
            ['test_name' => 'Pregnancy Test', 'test_description' => 'Test to detect pregnancy'],
            ['test_name' => 'Blood Typing', 'test_description' => 'Determine blood group and Rh factor']
        ];
    }
} catch (Exception $e) {
    // Use predefined tests if query fails
    $lab_tests = [
        ['test_name' => 'Complete Blood Count (CBC)', 'test_description' => 'Blood test to evaluate overall health'],
        ['test_name' => 'Urinalysis', 'test_description' => 'Urine test to detect infections and kidney problems'],
        ['test_name' => 'Fecalysis', 'test_description' => 'Stool examination for parasites and infections'],
        ['test_name' => 'Blood Sugar (FBS)', 'test_description' => 'Fasting blood sugar test'],
        ['test_name' => 'Chest X-Ray', 'test_description' => 'Imaging of chest and lungs']
    ];
}

// Include topbar for consistent navigation
require_once $root_path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'topbar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Order Lab Tests | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .order-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 1rem;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0077b6;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
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

        .lab-tests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .test-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .test-item:hover {
            border-color: #0077b6;
            background: #f0f8ff;
        }

        .test-item.selected {
            border-color: #28a745;
            background: #f8fff8;
        }

        .test-checkbox {
            margin-right: 0.5rem;
        }

        .test-name {
            font-weight: 600;
            color: #0077b6;
            margin-bottom: 0.25rem;
        }

        .test-description {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 1.5rem;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            padding-top: 2rem;
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

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .selection-summary {
            background: #e8f5e8;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-bottom: 1rem;
            display: none;
        }

        .selection-summary.show {
            display: block;
        }

        .selected-tests-list {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0 0;
        }

        .selected-tests-list li {
            padding: 0.25rem 0;
            color: #28a745;
            font-weight: 500;
        }

        .selected-tests-list li::before {
            content: "✓ ";
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .lab-tests-grid {
                grid-template-columns: 1fr;
            }

            .patient-info-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php 
    // Render topbar
    renderTopbar([
        'title' => 'Order Lab Tests',
        'back_url' => '../consultation.php?visit_id=' . $visit_id,
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <div class="order-container">

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

            <!-- Patient Summary -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-user"></i> Patient Information
                </h3>

                <?php if ($patient_data): ?>
                    <div class="patient-info-grid">
                        <div class="info-item">
                            <div class="info-label">Patient Name</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Patient ID</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['username']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Age / Sex</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['age']) ?> years / <?= htmlspecialchars($patient_data['sex']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Visit ID</div>
                            <div class="info-value">#<?= htmlspecialchars($visit_data['visit_id']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Visit Date</div>
                            <div class="info-value"><?= date('M j, Y g:i A', strtotime($visit_data['created_at'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ordered by</div>
                            <div class="info-value"><?= htmlspecialchars($employee_name) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Lab Tests Selection -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-flask"></i> Select Lab Tests
                </h3>

                <form method="POST" id="labTestForm">
                    <div class="selection-summary" id="selectionSummary">
                        <strong><i class="fas fa-list-check"></i> Selected Tests:</strong>
                        <ul class="selected-tests-list" id="selectedTestsList"></ul>
                    </div>

                    <div class="lab-tests-grid">
                        <?php foreach ($lab_tests as $test): ?>
                            <div class="test-item" onclick="toggleTest(this, '<?= htmlspecialchars($test['test_name']) ?>')">
                                <label style="display: flex; align-items: flex-start; cursor: pointer;">
                                    <input type="checkbox" name="lab_tests[]" value="<?= htmlspecialchars($test['test_name']) ?>" 
                                           class="test-checkbox" onchange="updateSelection()">
                                    <div>
                                        <div class="test-name"><?= htmlspecialchars($test['test_name']) ?></div>
                                        <div class="test-description"><?= htmlspecialchars($test['test_description']) ?></div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group">
                        <label for="special_instructions">Special Instructions (Optional)</label>
                        <textarea id="special_instructions" name="special_instructions" 
                                  placeholder="Any special instructions for the laboratory technician..."></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="../consultation.php?visit_id=<?= $visit_id ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Consultation
                        </a>
                        <button type="submit" name="order_lab_tests" class="btn btn-success" id="submitBtn" disabled>
                            <i class="fas fa-flask"></i> Order Selected Tests
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </section>

    <script>
        function toggleTest(element, testName) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            updateSelection();
        }

        function updateSelection() {
            const checkboxes = document.querySelectorAll('input[name="lab_tests[]"]');
            const selectedTests = [];
            const submitBtn = document.getElementById('submitBtn');
            const selectionSummary = document.getElementById('selectionSummary');
            const selectedTestsList = document.getElementById('selectedTestsList');

            // Update visual selection
            checkboxes.forEach(checkbox => {
                const testItem = checkbox.closest('.test-item');
                if (checkbox.checked) {
                    testItem.classList.add('selected');
                    selectedTests.push(checkbox.value);
                } else {
                    testItem.classList.remove('selected');
                }
            });

            // Update selection summary
            if (selectedTests.length > 0) {
                selectionSummary.classList.add('show');
                selectedTestsList.innerHTML = selectedTests.map(test => `<li>${test}</li>`).join('');
                submitBtn.disabled = false;
            } else {
                selectionSummary.classList.remove('show');
                submitBtn.disabled = true;
            }
        }

        // Prevent form submission if no tests selected
        document.getElementById('labTestForm').addEventListener('submit', function(e) {
            const selectedTests = document.querySelectorAll('input[name="lab_tests[]"]:checked');
            if (selectedTests.length === 0) {
                e.preventDefault();
                alert('Please select at least one lab test.');
            }
        });

        // Auto-resize textarea
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('special_instructions');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Auto-dismiss alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>

</html>