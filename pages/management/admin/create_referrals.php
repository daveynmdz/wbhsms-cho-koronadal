<?php
// create_referrals.php - Admin Side Referral Creation with Patient Search
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
// Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check if role is authorized
$authorized_roles = ['doctor', 'bhw', 'dho', 'records_officer', 'admin'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    header('Location: dashboard.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get employee's facility information
$employee_facility_id = null;
$employee_facility_name = '';
try {
    $stmt = $conn->prepare("
        SELECT e.facility_id, f.name as facility_name 
        FROM employees e 
        JOIN facilities f ON e.facility_id = f.facility_id 
        WHERE e.employee_id = ?
    ");
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $employee_facility_id = $row['facility_id'];
        $employee_facility_name = $row['facility_name'];
    }
} catch (Exception $e) {
    $error_message = "Unable to retrieve employee facility information.";
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_referral') {
        try {
            $conn->begin_transaction();
            
            // Get form data
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $referral_reason = trim($_POST['referral_reason'] ?? '');
            $destination_type = trim($_POST['destination_type'] ?? ''); // barangay_center, district_office, city_office, external
            $referred_to_facility_id = !empty($_POST['referred_to_facility_id']) ? (int)$_POST['referred_to_facility_id'] : null;
            $external_facility_type = trim($_POST['external_facility_type'] ?? '');
            $external_facility_name = trim($_POST['external_facility_name'] ?? '');
            $hospital_name = trim($_POST['hospital_name'] ?? '');
            $other_facility_name = trim($_POST['other_facility_name'] ?? '');
            $service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
            
            // Determine final external facility name based on type
            if ($destination_type === 'external') {
                if ($external_facility_type === 'hospital' && !empty($hospital_name)) {
                    $external_facility_name = $hospital_name;
                } elseif ($external_facility_type === 'other' && !empty($other_facility_name)) {
                    $external_facility_name = $other_facility_name;
                }
            }
            
            // Vitals data
            $systolic_bp = !empty($_POST['systolic_bp']) ? (int)$_POST['systolic_bp'] : null;
            $diastolic_bp = !empty($_POST['diastolic_bp']) ? (int)$_POST['diastolic_bp'] : null;
            $heart_rate = !empty($_POST['heart_rate']) ? (int)$_POST['heart_rate'] : null;
            $respiratory_rate = !empty($_POST['respiratory_rate']) ? (int)$_POST['respiratory_rate'] : null;
            $temperature = !empty($_POST['temperature']) ? (float)$_POST['temperature'] : null;
            $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
            $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
            $vitals_remarks = trim($_POST['vitals_remarks'] ?? '');
            
            // Calculate BMI if both weight and height are provided
            $bmi = null;
            if ($weight && $height) {
                $height_m = $height / 100; // Convert cm to meters
                $bmi = round($weight / ($height_m * $height_m), 2);
            }
            
            // Validation
            if (!$patient_id) {
                throw new Exception('Please select a patient from the list above.');
            }
            if (empty($referral_reason)) {
                throw new Exception('Referral reason is required.');
            }
            if (empty($destination_type)) {
                throw new Exception('Please select a referral destination type.');
            }
            
            // Validate based on destination type
            if (in_array($destination_type, ['barangay_center', 'district_office', 'city_office']) && !$referred_to_facility_id) {
                throw new Exception('Destination facility could not be determined. Please contact administrator.');
            }
            
            if ($destination_type === 'external') {
                if (empty($external_facility_type)) {
                    throw new Exception('Please select external facility type.');
                }
                if ($external_facility_type === 'hospital' && empty($hospital_name)) {
                    throw new Exception('Please select a hospital.');
                }
                if ($external_facility_type === 'other' && empty($other_facility_name)) {
                    throw new Exception('Please specify the other facility name.');
                }
                if ($external_facility_type === 'other' && strlen($other_facility_name) < 3) {
                    throw new Exception('Other facility name must be at least 3 characters.');
                }
            }
            
            if (!$employee_facility_id) {
                throw new Exception('Employee facility information not found. Please contact administrator.');
            }
            
            // Insert vitals first (if any vitals data provided)
            $vitals_id = null;
            if ($systolic_bp || $diastolic_bp || $heart_rate || $respiratory_rate || $temperature || $weight || $height) {
                $stmt = $conn->prepare("
                    INSERT INTO vitals (
                        patient_id, systolic_bp, diastolic_bp, heart_rate, 
                        respiratory_rate, temperature, weight, height, bmi, 
                        recorded_by, remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'iiiiddddiis', 
                    $patient_id, $systolic_bp, $diastolic_bp, $heart_rate, 
                    $respiratory_rate, $temperature, $weight, $height, $bmi, 
                    $employee_id, $vitals_remarks
                );
                $stmt->execute();
                $vitals_id = $conn->insert_id;
            }
            
            // Generate referral number
            $date_prefix = date('Ymd');
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals WHERE DATE(referral_date) = CURDATE()");
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            $referral_num = 'REF-' . $date_prefix . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            
            // Insert referral
            $stmt = $conn->prepare("
                INSERT INTO referrals (
                    referral_num, patient_id, referring_facility_id, referred_to_facility_id, 
                    external_facility_name, vitals_id, service_id, referral_reason, 
                    destination_type, referred_by, referral_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
            ");
            $stmt->bind_param(
                'siiisisssi',
                $referral_num, $patient_id, $employee_facility_id, $referred_to_facility_id,
                $external_facility_name, $vitals_id, $service_id, $referral_reason,
                $destination_type, $employee_id
            );
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['snackbar_message'] = "Referral created successfully! Referral #: $referral_num";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Patient search functionality
$search_query = $_GET['search'] ?? '';
$first_name = $_GET['first_name'] ?? '';
$last_name = $_GET['last_name'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';

$patients = [];
if ($search_query || $first_name || $last_name || $barangay_filter) {
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    if (!empty($search_query)) {
        $where_conditions[] = "(p.username LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?)";
        $search_term = "%$search_query%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
        $param_types .= 'ssss';
    }
    
    if (!empty($first_name)) {
        $where_conditions[] = "p.first_name LIKE ?";
        $params[] = "%$first_name%";
        $param_types .= 's';
    }
    
    if (!empty($last_name)) {
        $where_conditions[] = "p.last_name LIKE ?";
        $params[] = "%$last_name%";
        $param_types .= 's';
    }
    
    if (!empty($barangay_filter)) {
        $where_conditions[] = "b.barangay_name LIKE ?";
        $params[] = "%$barangay_filter%";
        $param_types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT p.patient_id, p.username, p.first_name, p.middle_name, p.last_name, 
               p.date_of_birth, p.sex, p.contact_number, b.barangay_name,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
        FROM patients p 
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
        $where_clause
        AND p.status = 'active'
        ORDER BY p.last_name, p.first_name
        LIMIT 5
    ";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $patients = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Get barangays for filter dropdown
$barangays = [];
try {
    $stmt = $conn->prepare("SELECT barangay_name FROM barangay WHERE status = 'active' ORDER BY barangay_name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $barangays = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for barangays
}

// Get available facilities for referral (excluding current employee's facility)
$available_facilities = [];
try {
    $stmt = $conn->prepare("
        SELECT f.facility_id, f.name, f.type, b.barangay_name 
        FROM facilities f 
        JOIN barangay b ON f.barangay_id = b.barangay_id 
        WHERE f.status = 'active' AND f.facility_id != ?
        ORDER BY f.type, f.name
    ");
    $stmt->bind_param('i', $employee_facility_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $available_facilities = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for facilities
}

// Get all services for dropdown
$all_services = [];
try {
    $stmt = $conn->prepare("SELECT service_id, name, description FROM services ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    $all_services = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for services
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Create Referral | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .search-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
        }

        .patient-table {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .patient-table table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .patient-table th,
        .patient-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .patient-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #0077b6;
        }

        .patient-table tbody tr:hover {
            background: #f8f9fa;
        }

        .patient-checkbox {
            width: 18px;
            height: 18px;
            margin-right: 0.5rem;
        }

        .referral-form {
            opacity: 0.5;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .referral-form.enabled {
            opacity: 1;
            pointer-events: auto;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #0077b6;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        .selected-patient {
            background: #d4edda !important;
            border-left: 4px solid #28a745;
        }

        .empty-search {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
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
        }

        .btn-primary {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
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
        
        .auto-populated-field {
            background: #f8f9fa !important;
            border: 2px solid #e9ecef !important;
            cursor: not-allowed;
        }
        
        .facility-info-box {
            background: #e8f5e8;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin: 0.5rem 0;
        }
        
        .loading-spinner {
            display: inline-block;
            margin-right: 8px;
        }
        
        .validation-error {
            border: 2px solid #dc3545 !important;
            background: #fff5f5 !important;
        }
        
        .validation-success {
            border: 2px solid #28a745 !important;
            background: #f8fff8 !important;
        }
        
        .field-help-text {
            color: #666;
            font-size: 0.85em;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        @media (max-width: 768px) {
            .search-grid {
                grid-template-columns: 1fr;
            }

            .vitals-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Snackbar notification -->
    <div id="snackbar" style="display:none;position:fixed;left:50%;bottom:40px;transform:translateX(-50%);background:#323232;color:#fff;padding:1em 2em;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.18);font-size:1.1em;z-index:99999;opacity:0;transition:opacity 0.3s;">
        <span id="snackbar-text"></span>
    </div>

    <!-- Top Bar -->
    <header class="topbar">
        <div>
            <a href="dashboard.php" class="topbar-logo" style="pointer-events: none; cursor: default;">
                <picture>
                    <source media="(max-width: 600px)"
                        srcset="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128">
                    <img src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527"
                        alt="City Health Logo" class="responsive-logo" />
                </picture>
            </a>
        </div>
        <div class="topbar-title" style="color: #ffffff;">Create New Referral</div>
        <div class="topbar-userinfo">
            <div class="topbar-usertext">
                <strong style="color: #ffffff;">
                    <?= htmlspecialchars($employee_name) ?>
                </strong><br>
                <small style="color: #ffffff;"><?= htmlspecialchars($_SESSION['role']) ?></small>
            </div>
            <img src="../../../vendor/photo_controller.php?employee_id=<?= urlencode($employee_id) ?>" alt="User Profile"
                class="topbar-userphoto"
                onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';" />
        </div>
    </header>

    <section class="homepage">
        <div class="edit-profile-toolbar-flex">
            <button type="button" class="btn btn-cancel floating-back-btn" id="backCancelBtn">&#8592; Back / Cancel</button>
            <!-- Custom Back/Cancel Confirmation Modal -->
            <div id="backCancelModal" class="custom-modal" style="display:none;">
                <div class="custom-modal-content">
                    <h3>Cancel Creating Referral?</h3>
                    <p>Are you sure you want to go back/cancel? Unsaved changes will be lost.</p>
                    <div class="custom-modal-actions">
                        <button type="button" class="btn btn-danger" id="modalCancelBtn">Yes, Cancel</button>
                        <button type="button" class="btn btn-secondary" id="modalStayBtn">Stay</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-wrapper">
            <!-- Reminders Box -->
            <div class="reminders-box">
                <strong>Reminders:</strong>
                <ul>
                    <li>Search and select a patient from the list below before creating a referral.</li>
                    <li>You can search by patient ID, name, or barangay.</li>
                    <li>Patient vitals are optional but recommended for medical referrals.</li>
                    <li>All referral information should be accurate and complete.</li>
                    <li>Fields marked with * are required.</li>
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

            <!-- Patient Search Section -->
            <div class="form-section">
                <div class="search-container">
                    <h3><i class="fas fa-search"></i> Search Patient</h3>
                    <form method="GET" class="search-grid">
                        <div class="form-group">
                            <label for="search">General Search</label>
                            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>"
                                placeholder="Patient ID, Name...">
                        </div>
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($first_name) ?>"
                                placeholder="Enter first name...">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($last_name) ?>"
                                placeholder="Enter last name...">
                        </div>
                        <div class="form-group">
                            <label for="barangay">Barangay</label>
                            <select id="barangay" name="barangay">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $brgy): ?>
                                    <option value="<?= htmlspecialchars($brgy['barangay_name']) ?>" 
                                        <?= $barangay_filter === $brgy['barangay_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brgy['barangay_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Patient Results Table -->
            <div class="form-section">
                <div class="patient-table">
                    <h3><i class="fas fa-users"></i> Patient Search Results</h3>
                    <?php if (empty($patients) && ($search_query || $first_name || $last_name || $barangay_filter)): ?>
                        <div class="empty-search">
                            <i class="fas fa-user-times fa-2x"></i>
                            <p>No patients found matching your search criteria.</p>
                            <p>Try adjusting your search terms or check the spelling.</p>
                        </div>
                    <?php elseif (!empty($patients)): ?>
                        <p>Found <?= count($patients) ?> patient(s). Select one to create a referral:</p>
                        <table>
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Patient ID</th>
                                    <th>Name</th>
                                    <th>Age/Sex</th>
                                    <th>Contact</th>
                                    <th>Barangay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $patient): ?>
                                    <tr class="patient-row" data-patient-id="<?= $patient['patient_id'] ?>">
                                        <td>
                                            <input type="radio" name="selected_patient" value="<?= $patient['patient_id'] ?>" 
                                                class="patient-checkbox" data-patient-name="<?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>">
                                        </td>
                                        <td><?= htmlspecialchars($patient['username']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($patient['first_name'] . ' ' . 
                                                ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . 
                                                $patient['last_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($patient['age']) ?> / <?= htmlspecialchars($patient['sex']) ?></td>
                                        <td><?= htmlspecialchars($patient['contact_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($patient['barangay_name'] ?? 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-search">
                            <i class="fas fa-search fa-2x"></i>
                            <p>Use the search form above to find patients.</p>
                            <p>Search results will appear here (maximum 5 results).</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Referral Form -->
            <div class="form-section">
                <form class="profile-card referral-form" id="referralForm" method="post">
                    <input type="hidden" name="action" value="create_referral">
                    <input type="hidden" name="patient_id" id="selectedPatientId">
                    
                    <h3><i class="fas fa-share"></i> Create Referral</h3>
                    <div class="facility-info" style="background: #e8f4fd; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #0077b6;">
                        <p style="margin: 0; color: #0077b6; font-weight: 600;">
                            <i class="fas fa-hospital"></i> Referring From: <?= htmlspecialchars($employee_facility_name ?: 'Unknown Facility') ?>
                        </p>
                    </div>
                    <div id="selectedPatientInfo" class="selected-patient-info" style="display:none;">
                        <p><strong>Selected Patient:</strong> <span id="selectedPatientName"></span></p>
                    </div>

                    <!-- Patient Vitals Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-heartbeat"></i> Patient Vitals (Optional)</h4>
                        <div class="vitals-grid">
                            <div class="form-group">
                                <label for="systolic_bp">Systolic BP</label>
                                <input type="number" id="systolic_bp" name="systolic_bp" min="60" max="300" placeholder="120">
                            </div>
                            <div class="form-group">
                                <label for="diastolic_bp">Diastolic BP</label>
                                <input type="number" id="diastolic_bp" name="diastolic_bp" min="40" max="200" placeholder="80">
                            </div>
                            <div class="form-group">
                                <label for="heart_rate">Heart Rate</label>
                                <input type="number" id="heart_rate" name="heart_rate" min="30" max="200" placeholder="72">
                            </div>
                            <div class="form-group">
                                <label for="respiratory_rate">Respiratory Rate</label>
                                <input type="number" id="respiratory_rate" name="respiratory_rate" min="8" max="60" placeholder="18">
                            </div>
                            <div class="form-group">
                                <label for="temperature">Temperature (°C)</label>
                                <input type="number" id="temperature" name="temperature" step="0.1" min="30" max="45" placeholder="36.5">
                            </div>
                            <div class="form-group">
                                <label for="weight">Weight (kg)</label>
                                <input type="number" id="weight" name="weight" step="0.1" min="1" max="500" placeholder="70.0">
                            </div>
                            <div class="form-group">
                                <label for="height">Height (cm)</label>
                                <input type="number" id="height" name="height" step="0.1" min="50" max="250" placeholder="170.0">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="vitals_remarks">Vitals Remarks</label>
                            <textarea id="vitals_remarks" name="vitals_remarks" rows="3" 
                                placeholder="Any additional notes about the patient's vitals..."></textarea>
                        </div>
                    </div>

                    <!-- Referral Information -->
                    <div class="form-section">
                        <h4><i class="fas fa-clipboard-list"></i> Referral Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="referral_reason">Reason for Referral *</label>
                                <textarea id="referral_reason" name="referral_reason" rows="4" required
                                    placeholder="Describe the medical condition, symptoms, and reason for referral..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Intelligent Destination Selection -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="destination_type">Referral Destination Type *</label>
                                <select id="destination_type" name="destination_type" required>
                                    <option value="">Select referral destination...</option>
                                    <option value="barangay_center">Barangay Health Center</option>
                                    <option value="district_office">District Health Office</option>
                                    <option value="city_office">City Health Office (Main District)</option>
                                    <option value="external">External Facility</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Auto-populated Barangay Health Center -->
                        <div class="form-row conditional-field" id="barangayFacilityField">
                            <div class="form-group">
                                <label for="barangay_facility_display">Facility Name</label>
                                <input type="text" id="barangay_facility_display" readonly 
                                    placeholder="Will be auto-populated based on patient's barangay"
                                    style="background: #f8f9fa; border: 2px solid #e9ecef;">
                                <input type="hidden" id="barangay_facility_id" name="referred_to_facility_id">
                                <small style="color: #666; font-size: 0.85em;">
                                    <i class="fas fa-info-circle"></i> Auto-selected based on patient's barangay
                                </small>
                            </div>
                        </div>
                        
                        <!-- Auto-populated District Office -->
                        <div class="form-row conditional-field" id="districtOfficeField">
                            <div class="form-group">
                                <label for="district_office_display">District Office</label>
                                <input type="text" id="district_office_display" readonly 
                                    placeholder="Will be auto-populated based on patient's district"
                                    style="background: #f8f9fa; border: 2px solid #e9ecef;">
                                <input type="hidden" id="district_office_id" name="referred_to_facility_id">
                                <small style="color: #666; font-size: 0.85em;">
                                    <i class="fas fa-info-circle"></i> Auto-selected based on patient's district
                                </small>
                            </div>
                        </div>
                        
                        <!-- City Health Office (no additional fields needed) -->
                        <div class="form-row conditional-field" id="cityOfficeField">
                            <div class="form-group">
                                <input type="hidden" id="city_office_id" name="referred_to_facility_id" value="1">
                                <div style="background: #e8f5e8; padding: 1rem; border-radius: 8px; border-left: 4px solid #28a745;">
                                    <i class="fas fa-hospital"></i> <strong>City Health Office (Main District)</strong>
                                    <br><small style="color: #666;">Direct referral to main city facility</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- External Facility Selection -->
                        <div class="form-row conditional-field" id="externalFacilityTypeField">
                            <div class="form-group">
                                <label for="external_facility_type">External Facility Type *</label>
                                <select id="external_facility_type" name="external_facility_type">
                                    <option value="">Select external facility type...</option>
                                    <option value="hospital">Hospital</option>
                                    <option value="other">Other Facility</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Hospital Selection -->
                        <div class="form-row conditional-field" id="hospitalSelectionField">
                            <div class="form-group">
                                <label for="hospital_name">Hospital Name *</label>
                                <select id="hospital_name" name="hospital_name">
                                    <option value="">Select hospital...</option>
                                    <option value="South Cotabato Provincial Hospital (SCPH)">South Cotabato Provincial Hospital (SCPH)</option>
                                    <option value="Dr. Arturo P. Pingoy Medical Center">Dr. Arturo P. Pingoy Medical Center</option>
                                    <option value="Allah Valley Medical Specialists' Center, Inc.">Allah Valley Medical Specialists' Center, Inc.</option>
                                    <option value="Socomedics Medical Center">Socomedics Medical Center</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Other Facility Input -->
                        <div class="form-row conditional-field" id="otherFacilityField">
                            <div class="form-group">
                                <label for="other_facility_name">Specify Other Facility *</label>
                                <input type="text" id="other_facility_name" name="other_facility_name" 
                                    placeholder="Enter other facility name (minimum 3 characters)"
                                    minlength="3">
                                <small style="color: #666; font-size: 0.85em;">
                                    Please provide the full name of the facility
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="service_id">Service Type (Optional)</label>
                                <select id="service_id" name="service_id">
                                    <option value="">Select service (optional)...</option>
                                    <?php foreach ($all_services as $service): ?>
                                        <option value="<?= $service['service_id'] ?>">
                                            <?= htmlspecialchars($service['name']) ?>
                                            <?php if ($service['description']): ?>
                                                - <?= htmlspecialchars(substr($service['description'], 0, 50)) ?>...
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color: #666; font-size: 0.85em;">
                                    Note: Service availability may vary by destination facility
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" disabled>
                            <i class="fas fa-share"></i> Create Referral
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Snackbar logic
            <?php if (isset($_SESSION['snackbar_message'])) { ?>
                var snackbar = document.getElementById('snackbar');
                var snackbarText = document.getElementById('snackbar-text');
                if (snackbar && snackbarText) {
                    snackbarText.textContent = <?= json_encode($_SESSION['snackbar_message']) ?>;
                    snackbar.style.display = 'block';
                    setTimeout(function() {
                        snackbar.style.opacity = '1';
                    }, 100);
                    setTimeout(function() {
                        snackbar.style.opacity = '0';
                        setTimeout(function() {
                            snackbar.style.display = 'none';
                        }, 400);
                    }, 4000);
                }
            <?php unset($_SESSION['snackbar_message']); } ?>

            // Back/Cancel modal logic
            const backBtn = document.getElementById('backCancelBtn');
            const modal = document.getElementById('backCancelModal');
            const modalCancel = document.getElementById('modalCancelBtn');
            const modalStay = document.getElementById('modalStayBtn');
            
            if (backBtn && modal && modalCancel && modalStay) {
                backBtn.addEventListener('click', function() {
                    modal.style.display = 'flex';
                });
                
                modalCancel.addEventListener('click', function() {
                    modal.style.display = 'none';
                    window.location.href = "referrals_management.php";
                });
                
                modalStay.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) modal.style.display = 'none';
                });
            }

            // Patient selection logic
            const patientCheckboxes = document.querySelectorAll('.patient-checkbox');
            const referralForm = document.getElementById('referralForm');
            const submitBtn = referralForm.querySelector('button[type="submit"]');
            const selectedPatientId = document.getElementById('selectedPatientId');
            const selectedPatientInfo = document.getElementById('selectedPatientInfo');
            const selectedPatientName = document.getElementById('selectedPatientName');

            patientCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        // Uncheck other checkboxes
                        patientCheckboxes.forEach(cb => {
                            if (cb !== this) cb.checked = false;
                        });
                        
                        // Remove previous selections
                        document.querySelectorAll('.patient-row').forEach(row => {
                            row.classList.remove('selected-patient');
                        });
                        
                        // Highlight selected row
                        this.closest('.patient-row').classList.add('selected-patient');
                        
                        // Enable form
                        referralForm.classList.add('enabled');
                        submitBtn.disabled = false;
                        selectedPatientId.value = this.value;
                        selectedPatientName.textContent = this.dataset.patientName;
                        selectedPatientInfo.style.display = 'block';
                        
                        // Fetch patient facility information
                        fetchPatientFacilities(this.value);
                    } else {
                        // Disable form if no patient selected
                        referralForm.classList.remove('enabled');
                        submitBtn.disabled = true;
                        selectedPatientId.value = '';
                        selectedPatientInfo.style.display = 'none';
                        this.closest('.patient-row').classList.remove('selected-patient');
                        
                        // Clear facility information
                        currentPatientFacilities = null;
                        hideAllConditionalFields();
                        
                        // Clear all form inputs
                        document.getElementById('destination_type').value = '';
                        document.getElementById('barangay_facility_display').value = '';
                        document.getElementById('district_office_display').value = '';
                        document.getElementById('external_facility_type').value = '';
                        document.getElementById('hospital_name').value = '';
                        document.getElementById('other_facility_name').value = '';
                    }
                });
            });

            // Intelligent Destination Selection Logic
            const destinationType = document.getElementById('destination_type');
            const barangayFacilityField = document.getElementById('barangayFacilityField');
            const districtOfficeField = document.getElementById('districtOfficeField');
            const cityOfficeField = document.getElementById('cityOfficeField');
            const externalFacilityTypeField = document.getElementById('externalFacilityTypeField');
            const hospitalSelectionField = document.getElementById('hospitalSelectionField');
            const otherFacilityField = document.getElementById('otherFacilityField');
            
            const externalFacilityType = document.getElementById('external_facility_type');
            const hospitalName = document.getElementById('hospital_name');
            const otherFacilityName = document.getElementById('other_facility_name');
            
            let currentPatientFacilities = null;

            // Hide all conditional fields initially
            function hideAllConditionalFields() {
                const fields = [barangayFacilityField, districtOfficeField, cityOfficeField, 
                              externalFacilityTypeField, hospitalSelectionField, otherFacilityField];
                fields.forEach(field => {
                    if (field) field.classList.remove('show');
                });
                
                // Clear required attributes
                if (externalFacilityType) externalFacilityType.required = false;
                if (hospitalName) hospitalName.required = false;
                if (otherFacilityName) otherFacilityName.required = false;
            }

            // Handle destination type change
            if (destinationType) {
                destinationType.addEventListener('change', function() {
                    hideAllConditionalFields();
                    
                    const selectedPatientId = document.getElementById('selectedPatientId').value;
                    
                    switch(this.value) {
                        case 'barangay_center':
                            if (selectedPatientId && currentPatientFacilities) {
                                populateBarangayFacility();
                            }
                            if (barangayFacilityField) barangayFacilityField.classList.add('show');
                            break;
                            
                        case 'district_office':
                            if (selectedPatientId && currentPatientFacilities) {
                                populateDistrictOffice();
                            }
                            if (districtOfficeField) districtOfficeField.classList.add('show');
                            break;
                            
                        case 'city_office':
                            if (cityOfficeField) cityOfficeField.classList.add('show');
                            break;
                            
                        case 'external':
                            if (externalFacilityTypeField) {
                                externalFacilityTypeField.classList.add('show');
                                externalFacilityType.required = true;
                            }
                            break;
                    }
                });
            }
            
            // Handle external facility type change
            if (externalFacilityType) {
                externalFacilityType.addEventListener('change', function() {
                    // Hide hospital and other facility fields first
                    if (hospitalSelectionField) hospitalSelectionField.classList.remove('show');
                    if (otherFacilityField) otherFacilityField.classList.remove('show');
                    
                    if (hospitalName) hospitalName.required = false;
                    if (otherFacilityName) otherFacilityName.required = false;
                    
                    if (this.value === 'hospital') {
                        if (hospitalSelectionField) {
                            hospitalSelectionField.classList.add('show');
                            hospitalName.required = true;
                        }
                    } else if (this.value === 'other') {
                        if (otherFacilityField) {
                            otherFacilityField.classList.add('show');
                            otherFacilityName.required = true;
                        }
                    }
                });
            }
            
            // Function to populate barangay facility
            function populateBarangayFacility() {
                const barangayDisplay = document.getElementById('barangay_facility_display');
                const barangayIdInput = document.getElementById('barangay_facility_id');
                
                if (currentPatientFacilities && currentPatientFacilities.facilities.barangay_center) {
                    const facility = currentPatientFacilities.facilities.barangay_center;
                    if (barangayDisplay) barangayDisplay.value = facility.name;
                    if (barangayIdInput) barangayIdInput.value = facility.facility_id;
                } else {
                    if (barangayDisplay) barangayDisplay.value = 'No barangay health center found';
                    if (barangayIdInput) barangayIdInput.value = '';
                }
            }
            
            // Function to populate district office
            function populateDistrictOffice() {
                const districtDisplay = document.getElementById('district_office_display');
                const districtIdInput = document.getElementById('district_office_id');
                
                if (currentPatientFacilities && currentPatientFacilities.facilities.district_office) {
                    const facility = currentPatientFacilities.facilities.district_office;
                    if (districtDisplay) districtDisplay.value = facility.name;
                    if (districtIdInput) districtIdInput.value = facility.facility_id;
                } else {
                    if (districtDisplay) districtDisplay.value = 'No district office found';
                    if (districtIdInput) districtIdInput.value = '';
                }
            }
            
            // Function to fetch patient facility information
            function fetchPatientFacilities(patientId) {
                if (!patientId) return;
                
                // Show loading indicator
                showLoadingIndicator();
                
                fetch(`get_patient_facilities.php?patient_id=${patientId}`)
                    .then(response => response.json())
                    .then(data => {
                        hideLoadingIndicator();
                        
                        if (data.success) {
                            currentPatientFacilities = data;
                            
                            // Update destination type selection based on current selection
                            const currentDestinationType = destinationType.value;
                            if (currentDestinationType === 'barangay_center') {
                                populateBarangayFacility();
                            } else if (currentDestinationType === 'district_office') {
                                populateDistrictOffice();
                            }
                            
                            // Add patient barangay info to selected patient display
                            const barangayInfo = document.createElement('small');
                            barangayInfo.style.display = 'block';
                            barangayInfo.style.color = '#666';
                            barangayInfo.innerHTML = `<i class="fas fa-map-marker-alt"></i> Barangay: ${data.patient.barangay_name}`;
                            selectedPatientInfo.appendChild(barangayInfo);
                            
                        } else {
                            console.error('Error fetching patient facilities:', data.error);
                            alert('Error loading patient facility information: ' + data.error);
                        }
                    })
                    .catch(error => {
                        hideLoadingIndicator();
                        console.error('Network error:', error);
                        alert('Network error while loading patient information.');
                    });
            }
            
            // Loading indicator functions
            function showLoadingIndicator() {
                // Create or show loading indicator
                let loader = document.getElementById('facilityLoader');
                if (!loader) {
                    loader = document.createElement('div');
                    loader.id = 'facilityLoader';
                    loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading facility information...';
                    loader.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #0077b6; color: white; padding: 10px 15px; border-radius: 5px; z-index: 9999; font-size: 14px;';
                    document.body.appendChild(loader);
                }
                loader.style.display = 'block';
            }
            
            function hideLoadingIndicator() {
                const loader = document.getElementById('facilityLoader');
                if (loader) loader.style.display = 'none';
            }
            
            // Function to show referral confirmation dialog
            function showReferralConfirmation() {
                const destinationType = document.getElementById('destination_type').value;
                const patientName = document.getElementById('selectedPatientName').textContent;
                const referralReason = document.getElementById('referral_reason').value;
                
                let destinationName = '';
                
                switch(destinationType) {
                    case 'barangay_center':
                        destinationName = document.getElementById('barangay_facility_display').value;
                        break;
                    case 'district_office':
                        destinationName = document.getElementById('district_office_display').value;
                        break;
                    case 'city_office':
                        destinationName = 'City Health Office (Main District)';
                        break;
                    case 'external':
                        const externalType = document.getElementById('external_facility_type').value;
                        if (externalType === 'hospital') {
                            destinationName = document.getElementById('hospital_name').value;
                        } else if (externalType === 'other') {
                            destinationName = document.getElementById('other_facility_name').value;
                        }
                        break;
                }
                
                const confirmationMessage = `
Please confirm the referral details:

Patient: ${patientName}
Destination: ${destinationName}
Reason: ${referralReason}

Do you want to create this referral?`;
                
                return confirm(confirmationMessage);
            }

            // Form validation
            if (referralForm) {
                referralForm.addEventListener('submit', function(e) {
                    const patientId = document.getElementById('selectedPatientId').value;
                    if (!patientId) {
                        e.preventDefault();
                        alert('Please select a patient from the search results above.');
                        return false;
                    }

                    const destinationType = document.getElementById('destination_type').value;
                    
                    // Validate based on destination type
                    if (destinationType === 'barangay_center') {
                        const facilityId = document.getElementById('barangay_facility_id').value;
                        if (!facilityId) {
                            e.preventDefault();
                            alert('No barangay health center available for this patient. Please select a different destination type.');
                            return false;
                        }
                    } else if (destinationType === 'district_office') {
                        const facilityId = document.getElementById('district_office_id').value;
                        if (!facilityId) {
                            e.preventDefault();
                            alert('No district health office available for this patient. Please select a different destination type.');
                            return false;
                        }
                    } else if (destinationType === 'external') {
                        const externalType = document.getElementById('external_facility_type').value;
                        
                        if (!externalType) {
                            e.preventDefault();
                            alert('Please select external facility type.');
                            return false;
                        }
                        
                        if (externalType === 'hospital') {
                            const hospitalName = document.getElementById('hospital_name').value;
                            if (!hospitalName) {
                                e.preventDefault();
                                alert('Please select a hospital.');
                                return false;
                            }
                        } else if (externalType === 'other') {
                            const otherFacility = document.getElementById('other_facility_name').value;
                            if (!otherFacility.trim()) {
                                e.preventDefault();
                                alert('Please specify the other facility name.');
                                return false;
                            }
                            if (otherFacility.trim().length < 3) {
                                e.preventDefault();
                                alert('Other facility name must be at least 3 characters.');
                                return false;
                            }
                        }
                    } else if (!destinationType) {
                        e.preventDefault();
                        alert('Please select a referral destination type.');
                        return false;
                    }

                    const referralReason = document.getElementById('referral_reason').value;
                    if (!referralReason.trim()) {
                        e.preventDefault();
                        alert('Please enter the reason for referral.');
                        return false;
                    }
                    
                    // Show confirmation dialog with referral details
                    if (!showReferralConfirmation()) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
            
            // Service filtering based on selected facility
            if (internalFacilityInput) {
                internalFacilityInput.addEventListener('change', function() {
                    const facilityId = this.value;
                    const serviceSelect = document.getElementById('service_id');
                    
                    if (facilityId && serviceSelect) {
                        // Fetch available services for the selected facility
                        fetch(`get_facility_services.php?facility_id=${facilityId}`)
                            .then(response => response.json())
                            .then(data => {
                                // Clear current options except the first one
                                serviceSelect.innerHTML = '<option value="">Select service (optional)...</option>';
                                
                                // Add available services for this facility
                                data.services.forEach(service => {
                                    const option = document.createElement('option');
                                    option.value = service.service_id;
                                    option.textContent = service.name;
                                    if (service.description) {
                                        option.textContent += ' - ' + service.description.substring(0, 50) + '...';
                                    }
                                    serviceSelect.appendChild(option);
                                });
                                
                                // Show message if no services available
                                if (data.services.length === 0) {
                                    const option = document.createElement('option');
                                    option.value = '';
                                    option.textContent = 'No services available at selected facility';
                                    option.disabled = true;
                                    serviceSelect.appendChild(option);
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching facility services:', error);
                                // Fallback: show all services
                                serviceSelect.innerHTML = `
                                    <option value="">Select service (optional)...</option>
                                    <?php foreach ($all_services as $service): ?>
                                        <option value="<?= $service['service_id'] ?>">
                                            <?= htmlspecialchars($service['name']) ?>
                                            <?php if ($service['description']): ?>
                                                - <?= htmlspecialchars(substr($service['description'], 0, 50)) ?>...
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                `;
                            });
                    } else {
                        // Reset to all services if no facility selected
                        if (serviceSelect) {
                            serviceSelect.innerHTML = `
                                <option value="">Select service (optional)...</option>
                                <?php foreach ($all_services as $service): ?>
                                    <option value="<?= $service['service_id'] ?>">
                                        <?= htmlspecialchars($service['name']) ?>
                                        <?php if ($service['description']): ?>
                                            - <?= htmlspecialchars(substr($service['description'], 0, 50)) ?>...
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            `;
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>