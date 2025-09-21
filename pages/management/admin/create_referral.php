<?php
// create_referral.php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Security check - Only allow authorized healthcare personnel
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/employee_login.php');
    exit();
}

// Check if role is authorized (Doctors, BHW, DHO, Records Officers)
$authorized_roles = ['Doctor', 'BHW', 'DHO', 'Records Officer', 'Admin'];
if (!in_array($_SESSION['role'], $authorized_roles)) {
    header('Location: ../../dashboard/dashboard_admin.php');
    exit();
}

// Database connection
require_once '../../../config/db.php';
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $patient_id = trim($_POST['patient_id'] ?? '');
        $issued_by = $employee_id;
        $chief_complaint = trim($_POST['chief_complaint'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $assessment = trim($_POST['assessment'] ?? '');
        $reason_for_referral = trim($_POST['reason_for_referral'] ?? '');
        $destination_type = trim($_POST['destination_type'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        
        // Validate required fields
        if (empty($patient_id) || empty($chief_complaint) || empty($destination_type)) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Generate referral number
        $date_prefix = date('Y-m-d');
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals WHERE DATE(date_of_referral) = CURDATE()");
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'] + 1;
        $referral_num = 'REF-' . $date_prefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
        $stmt->close();
        
        // Handle destination and service based on type
        $referred_to_facility = null;
        $referred_to_external = null;
        $service_id = null;
        $service_description = null;
        
        if ($destination_type === 'city' || $destination_type === 'district') {
            $referred_to_facility = trim($_POST['facility_id'] ?? '');
            $service_id = trim($_POST['service_id'] ?? '');
            if (empty($referred_to_facility) || empty($service_id)) {
                throw new Exception('Please select both facility and service.');
            }
        } else if ($destination_type === 'external') {
            $referred_to_external = trim($_POST['external_facility'] ?? '');
            $service_description = trim($_POST['external_service'] ?? '');
            if (empty($referred_to_external) || empty($service_description)) {
                throw new Exception('Please specify external facility and service.');
            }
        }
        
        // Insert referral
        $stmt = $conn->prepare("
            INSERT INTO referrals (
                referral_num, patient_id, issued_by, chief_complaint, symptoms, 
                assessment, reason_for_referral, destination_type, 
                referred_to_facility, referred_to_external, service_id, 
                service_description, barangay, date_of_referral, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
        ");
        
        $stmt->bind_param("siisssssissss", 
            $referral_num, $patient_id, $issued_by, $chief_complaint, $symptoms,
            $assessment, $reason_for_referral, $destination_type,
            $referred_to_facility, $referred_to_external, $service_id,
            $service_description, $barangay
        );
        
        if ($stmt->execute()) {
            $success_message = "Referral created successfully! Referral Number: " . $referral_num;
        } else {
            throw new Exception('Failed to create referral.');
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get defaults for display
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role
];

// Fetch employee info if needed
$stmt = $conn->prepare('SELECT first_name, middle_name, last_name, employee_number, role FROM employees WHERE employee_id = ?');
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row) {
    $full_name = $row['first_name'];
    if (!empty($row['middle_name'])) $full_name .= ' ' . $row['middle_name'];
    $full_name .= ' ' . $row['last_name'];
    $defaults['name'] = trim($full_name);
    $defaults['employee_number'] = $row['employee_number'];
    $defaults['role'] = $row['role'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Create Patient Referral</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <style>
        .content-wrapper {
            display: block;
            margin-left: var(--sidebar-width, 260px);
            padding: 1.25rem;
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
            }
        }

        .form-container {
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin: 1rem auto;
            max-width: 1000px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-header h1 {
            color: #03045e;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }

        .form-header p {
            color: #666;
            margin: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-grid.full-width {
            grid-template-columns: 1fr;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #03045e;
            font-size: 0.9rem;
        }

        .form-group label .required {
            color: #d00000;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
            font-family: inherit;
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

        .radio-group {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .radio-option input[type="radio"] {
            margin: 0;
            transform: scale(1.2);
        }

        .radio-option label {
            margin: 0;
            font-weight: normal;
        }

        .conditional-field {
            display: none;
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #0077b6;
            margin-top: 1rem;
        }

        .conditional-field.show {
            display: block;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
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

        .btn-primary:hover {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 119, 182, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: 2px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            border-color: #ccc;
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

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .breadcrumb a {
            color: #0077b6;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 1rem;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0077b6;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
    <?php
    $activePage = 'referrals';
    include '../../../includes/sidebar_admin.php';
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="../../dashboard/dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <a href="referrals_management.php">Referrals</a>
            <i class="fas fa-chevron-right"></i>
            <span>Create Referral</span>
        </div>

        <div class="form-container">
            <div class="form-header">
                <h1><i class="fas fa-share"></i> Create Patient Referral</h1>
                <p>Healthcare Provider: <strong><?php echo htmlspecialchars($defaults['name']); ?></strong> | 
                   Role: <strong><?php echo htmlspecialchars($defaults['role']); ?></strong></p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="referralForm">
                <!-- Patient and Basic Information -->
                <div class="form-grid">
                    <div class="form-group">
                        <label for="patient_id">Select Patient <span class="required">*</span></label>
                        <select name="patient_id" id="patient_id" required>
                            <option value="">-- Select Patient --</option>
                            <?php
                            $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, username FROM patients ORDER BY last_name, first_name");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($patient = $result->fetch_assoc()) {
                                $full_name = $patient['first_name'];
                                if (!empty($patient['middle_name'])) $full_name .= ' ' . $patient['middle_name'];
                                $full_name .= ' ' . $patient['last_name'];
                                $display_name = $full_name . ' (' . $patient['username'] . ')';
                                echo '<option value="' . $patient['id'] . '">' . htmlspecialchars($display_name) . '</option>';
                            }
                            $stmt->close();
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="barangay">Barangay</label>
                        <select name="barangay" id="barangay">
                            <option value="">-- Select Barangay --</option>
                            <option value="Assumption">Assumption</option>
                            <option value="Caceres">Caceres</option>
                            <option value="Carpenter Hill">Carpenter Hill</option>
                            <option value="Concepcion">Concepcion</option>
                            <option value="Esperanza">Esperanza</option>
                            <option value="General Paulino Santos (Banisilan)">General Paulino Santos (Banisilan)</option>
                            <option value="Magsaysay">Magsaysay</option>
                            <option value="Mambucal">Mambucal</option>
                            <option value="Morales">Morales</option>
                            <option value="New Pangasinan">New Pangasinan</option>
                            <option value="Paraiso">Paraiso</option>
                            <option value="Rotonda">Rotonda</option>
                            <option value="San Isidro">San Isidro</option>
                            <option value="San Jose">San Jose</option>
                            <option value="San Roque">San Roque</option>
                            <option value="Santa Cruz">Santa Cruz</option>
                            <option value="Santo Niño">Santo Niño</option>
                            <option value="Saravia">Saravia</option>
                            <option value="Topland">Topland</option>
                            <option value="Zone I (Poblacion)">Zone I (Poblacion)</option>
                            <option value="Zone II (Poblacion)">Zone II (Poblacion)</option>
                            <option value="Zone III (Poblacion)">Zone III (Poblacion)</option>
                            <option value="Zone IV (Poblacion)">Zone IV (Poblacion)</option>
                        </select>
                    </div>
                </div>

                <!-- Chief Complaint -->
                <div class="form-grid full-width">
                    <div class="form-group">
                        <label for="chief_complaint">Chief Complaint <span class="required">*</span></label>
                        <textarea name="chief_complaint" id="chief_complaint" maxlength="255" required 
                                  placeholder="Brief description of the main health concern..."></textarea>
                    </div>
                </div>

                <!-- Symptoms, Assessment, Reason -->
                <div class="form-grid">
                    <div class="form-group">
                        <label for="symptoms">Symptoms</label>
                        <textarea name="symptoms" id="symptoms" 
                                  placeholder="Detailed symptoms observed..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="assessment">Assessment</label>
                        <textarea name="assessment" id="assessment" 
                                  placeholder="Clinical assessment and findings..."></textarea>
                    </div>
                </div>

                <div class="form-grid full-width">
                    <div class="form-group">
                        <label for="reason_for_referral">Reason for Referral</label>
                        <textarea name="reason_for_referral" id="reason_for_referral" 
                                  placeholder="Specific reason why patient is being referred..."></textarea>
                    </div>
                </div>

                <!-- Destination Type -->
                <div class="form-grid full-width">
                    <div class="form-group">
                        <label>Destination Type <span class="required">*</span></label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="destination_type" value="city" id="dest_city" required>
                                <label for="dest_city">City Health Office</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="destination_type" value="district" id="dest_district" required>
                                <label for="dest_district">District Health Office</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="destination_type" value="external" id="dest_external" required>
                                <label for="dest_external">External Facility</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Internal Facility and Service (City/District) -->
                <div id="internal_fields" class="conditional-field">
                    <h4 style="margin-top: 0; color: #03045e;">Internal Facility & Service</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="facility_id">Select Facility <span class="required">*</span></label>
                            <select name="facility_id" id="facility_id">
                                <option value="">-- Select Facility --</option>
                                <?php
                                $stmt = $conn->prepare("SELECT id, name, type FROM facilities ORDER BY type, name");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($facility = $result->fetch_assoc()) {
                                    echo '<option value="' . $facility['id'] . '" data-type="' . $facility['type'] . '">' . 
                                         htmlspecialchars($facility['name'] . ' (' . ucfirst($facility['type']) . ')') . '</option>';
                                }
                                $stmt->close();
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="service_id">Select Service <span class="required">*</span></label>
                            <select name="service_id" id="service_id">
                                <option value="">-- Select Service --</option>
                                <?php
                                $stmt = $conn->prepare("SELECT id, name, description FROM services ORDER BY name");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($service = $result->fetch_assoc()) {
                                    $display = $service['name'];
                                    if (!empty($service['description'])) {
                                        $display .= ' - ' . $service['description'];
                                    }
                                    echo '<option value="' . $service['id'] . '">' . htmlspecialchars($display) . '</option>';
                                }
                                $stmt->close();
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- External Facility and Service -->
                <div id="external_fields" class="conditional-field">
                    <h4 style="margin-top: 0; color: #03045e;">External Facility & Service</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="external_facility">External Facility Name <span class="required">*</span></label>
                            <input type="text" name="external_facility" id="external_facility" 
                                   placeholder="e.g., South Cotabato Provincial Hospital">
                        </div>

                        <div class="form-group">
                            <label for="external_service">Service Description <span class="required">*</span></label>
                            <input type="text" name="external_service" id="external_service" 
                                   placeholder="e.g., Cardiology Consultation">
                        </div>
                    </div>
                </div>

                <!-- Patient Vitals Reference (Optional Display) -->
                <div class="form-grid full-width">
                    <div class="form-group">
                        <div id="patient_vitals" style="background-color: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #28a745;">
                            <h4 style="margin-top: 0; color: #03045e;">Patient Vitals (Reference)</h4>
                            <div id="vitals_display">
                                <p style="color: #666; font-style: italic;">Select a patient to view latest vitals</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Buttons -->
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Referral
                    </button>
                    <a href="referrals_management.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Loading indicator -->
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>Processing referral...</p>
        </div>
    </section>

    <script>
        // Form validation and dynamic behavior
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('referralForm');
            const destinationRadios = document.querySelectorAll('input[name="destination_type"]');
            const internalFields = document.getElementById('internal_fields');
            const externalFields = document.getElementById('external_fields');
            const facilitySelect = document.getElementById('facility_id');
            const patientSelect = document.getElementById('patient_id');

            // Handle destination type changes
            destinationRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'city' || this.value === 'district') {
                        internalFields.classList.add('show');
                        externalFields.classList.remove('show');
                        
                        // Filter facilities by type
                        filterFacilitiesByType(this.value);
                        
                        // Make internal fields required
                        document.getElementById('facility_id').required = true;
                        document.getElementById('service_id').required = true;
                        document.getElementById('external_facility').required = false;
                        document.getElementById('external_service').required = false;
                    } else if (this.value === 'external') {
                        internalFields.classList.remove('show');
                        externalFields.classList.add('show');
                        
                        // Make external fields required
                        document.getElementById('facility_id').required = false;
                        document.getElementById('service_id').required = false;
                        document.getElementById('external_facility').required = true;
                        document.getElementById('external_service').required = true;
                    }
                });
            });

            // Filter facilities based on destination type
            function filterFacilitiesByType(type) {
                const options = facilitySelect.querySelectorAll('option');
                options.forEach(option => {
                    if (option.value === '') return; // Keep default option
                    
                    const facilityType = option.getAttribute('data-type');
                    if (facilityType === type) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
                facilitySelect.value = ''; // Reset selection
            }

            // Load patient vitals when patient is selected
            patientSelect.addEventListener('change', function() {
                const patientId = this.value;
                if (patientId) {
                    loadPatientVitals(patientId);
                } else {
                    document.getElementById('vitals_display').innerHTML = 
                        '<p style="color: #666; font-style: italic;">Select a patient to view latest vitals</p>';
                }
            });

            // Load patient vitals via AJAX
            function loadPatientVitals(patientId) {
                const vitalsDisplay = document.getElementById('vitals_display');
                vitalsDisplay.innerHTML = '<p style="color: #666;">Loading vitals...</p>';

                fetch('get_patient_vitals.php?patient_id=' + patientId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.vitals) {
                            const vitals = data.vitals;
                            vitalsDisplay.innerHTML = `
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem; font-size: 0.9rem;">
                                    <div><strong>Height:</strong> ${vitals.height || 'N/A'}</div>
                                    <div><strong>Weight:</strong> ${vitals.weight || 'N/A'}</div>
                                    <div><strong>BP:</strong> ${vitals.bp || 'N/A'}</div>
                                    <div><strong>Heart Rate:</strong> ${vitals.cardiac_rate || 'N/A'}</div>
                                    <div><strong>Temperature:</strong> ${vitals.temperature || 'N/A'}</div>
                                    <div><strong>Resp. Rate:</strong> ${vitals.resp_rate || 'N/A'}</div>
                                </div>
                                <p style="font-size: 0.8rem; color: #666; margin-top: 0.5rem;">
                                    Last recorded: ${vitals.date || 'Unknown'}
                                </p>
                            `;
                        } else {
                            vitalsDisplay.innerHTML = '<p style="color: #666; font-style: italic;">No vitals data available</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading vitals:', error);
                        vitalsDisplay.innerHTML = '<p style="color: #d00000;">Error loading vitals data</p>';
                    });
            }

            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                const loadingDiv = document.getElementById('loading');
                loadingDiv.style.display = 'block';
                form.style.display = 'none';
            });

            // Client-side validation
            function validateForm() {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#d00000';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#e0e0e0';
                    }
                });
                
                return isValid;
            }

            // Add validation on form submit
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    document.getElementById('loading').style.display = 'none';
                    form.style.display = 'block';
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>

</html>
