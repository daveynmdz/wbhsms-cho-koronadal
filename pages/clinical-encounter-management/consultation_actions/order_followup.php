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

// Check if role is authorized (only doctors can order follow-ups)
if ($employee_role !== 'doctor') {
    header("Location: ../consultation.php?visit_id=" . ($_GET['visit_id'] ?? '') . "&error=unauthorized");
    exit();
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_followup'])) {
    $followup_date = $_POST['followup_date'] ?? '';
    $followup_time = $_POST['followup_time'] ?? '';
    $followup_reason = $_POST['followup_reason'] ?? '';
    $followup_notes = $_POST['followup_notes'] ?? '';
    
    if (empty($followup_date) || empty($followup_time) || empty($followup_reason)) {
        $error_message = "Please fill in all required fields.";
    } else {
        try {
            $conn->begin_transaction();
            
            // Combine date and time
            $followup_datetime = $followup_date . ' ' . $followup_time . ':00';
            
            // Validate that the follow-up is in the future
            if (strtotime($followup_datetime) <= time()) {
                throw new Exception("Follow-up date and time must be in the future.");
            }
            
            // Get consultation_id if it exists
            $consultation_id = null;
            $consult_stmt = $conn->prepare("SELECT consultation_id FROM consultations WHERE visit_id = ?");
            $consult_stmt->bind_param("i", $visit_id);
            $consult_stmt->execute();
            $consult_result = $consult_stmt->get_result();
            if ($consult_row = $consult_result->fetch_assoc()) {
                $consultation_id = $consult_row['consultation_id'];
            }
            
            // Create appointment for follow-up
            $appointment_stmt = $conn->prepare("
                INSERT INTO appointments (
                    patient_id, appointment_date, appointment_time, appointment_type, 
                    status, reason, notes, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, 'Follow-up', 'Scheduled', ?, ?, ?, NOW(), NOW())
            ");
            
            $appointment_stmt->bind_param(
                "issssi", 
                $patient_data['patient_id'], 
                $followup_date,
                $followup_time,
                $followup_reason,
                $followup_notes,
                $employee_id
            );
            $appointment_stmt->execute();
            $appointment_id = $conn->insert_id;
            
            // Update consultation status if consultation exists
            if ($consultation_id) {
                $update_stmt = $conn->prepare("
                    UPDATE consultations 
                    SET consultation_status = 'follow-up scheduled', 
                        updated_at = NOW() 
                    WHERE consultation_id = ?
                ");
                $update_stmt->bind_param("i", $consultation_id);
                $update_stmt->execute();
            }
            
            // Log the follow-up scheduling
            $log_stmt = $conn->prepare("
                INSERT INTO appointment_logs (
                    appointment_id, patient_id, action, details, 
                    performed_by, created_at
                ) VALUES (?, ?, 'follow_up_scheduled', ?, ?, NOW())
            ");
            
            $log_details = "Follow-up scheduled for " . date('M j, Y g:i A', strtotime($followup_datetime)) . " - " . $followup_reason;
            $log_stmt->bind_param("iisi", $appointment_id, $patient_data['patient_id'], $log_details, $employee_id);
            $log_stmt->execute();
            
            $conn->commit();
            
            // Redirect back to consultation with success message
            $success_param = urlencode("Follow-up appointment scheduled successfully for " . date('M j, Y g:i A', strtotime($followup_datetime)));
            header("Location: ../consultation.php?visit_id=$visit_id&success=" . $success_param);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error scheduling follow-up: " . $e->getMessage();
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

// Get existing follow-up appointments for this patient
$existing_followups = [];
try {
    $followup_stmt = $conn->prepare("
        SELECT a.*, e.first_name as doctor_first_name, e.last_name as doctor_last_name
        FROM appointments a
        LEFT JOIN employees e ON a.created_by = e.employee_id
        WHERE a.patient_id = ? AND a.appointment_type = 'Follow-up' 
        AND a.status IN ('Scheduled', 'Confirmed')
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ");
    $followup_stmt->bind_param("i", $patient_data['patient_id']);
    $followup_stmt->execute();
    $followup_result = $followup_stmt->get_result();
    $existing_followups = $followup_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for existing follow-ups
}

// Include topbar for consistent navigation
require_once $root_path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'topbar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Schedule Follow-up | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .followup-container {
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

        .existing-followups {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            margin-bottom: 1.5rem;
        }

        .followup-item {
            background: white;
            border: 1px solid #bbdefb;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: center;
            font-size: 0.9rem;
        }

        .followup-item:last-child {
            margin-bottom: 0;
        }

        .followup-date {
            font-weight: 600;
            color: #1976d2;
        }

        .followup-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-scheduled {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }

        .form-grid {
            display: grid;
            gap: 1.5rem;
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
            min-height: 100px;
        }

        .datetime-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
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

        .time-suggestions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .time-suggestion {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .time-suggestion:hover {
            background: #0077b6;
            color: white;
            border-color: #0077b6;
        }

        @media (max-width: 768px) {
            .form-grid.two-column,
            .datetime-grid {
                grid-template-columns: 1fr;
            }

            .followup-item {
                grid-template-columns: 1fr;
                gap: 0.5rem;
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

            .time-suggestions {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php 
    // Render topbar
    renderTopbar([
        'title' => 'Schedule Follow-up',
        'back_url' => '../consultation.php?visit_id=' . $visit_id,
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <div class="followup-container">

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
                            <div class="info-label">Scheduled by</div>
                            <div class="info-value">Dr. <?= htmlspecialchars($employee_name) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Existing Follow-ups -->
            <?php if (!empty($existing_followups)): ?>
                <div class="existing-followups">
                    <h4><i class="fas fa-calendar-check"></i> Recent Follow-up Appointments (<?= count($existing_followups) ?>)</h4>
                    <?php foreach ($existing_followups as $followup): ?>
                        <div class="followup-item">
                            <div class="followup-date"><?= date('M j, Y g:i A', strtotime($followup['appointment_date'] . ' ' . $followup['appointment_time'])) ?></div>
                            <div><strong>Reason:</strong> <?= htmlspecialchars($followup['reason']) ?></div>
                            <div><span class="followup-status status-<?= strtolower($followup['status']) ?>"><?= htmlspecialchars($followup['status']) ?></span></div>
                            <div><small>by Dr. <?= htmlspecialchars($followup['doctor_first_name'] . ' ' . $followup['doctor_last_name']) ?></small></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Follow-up Scheduling Form -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-calendar-plus"></i> Schedule Follow-up Appointment
                </h3>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Scheduling a follow-up will update the consultation status to "follow-up scheduled" and create a new appointment for this patient.
                </div>

                <form method="POST" id="followupForm">
                    <div class="form-grid">
                        
                        <div class="datetime-grid">
                            <div class="form-group">
                                <label for="followup_date">Follow-up Date <span class="required">*</span></label>
                                <input type="date" 
                                       id="followup_date" 
                                       name="followup_date" 
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                       max="<?= date('Y-m-d', strtotime('+6 months')) ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="followup_time">Follow-up Time <span class="required">*</span></label>
                                <input type="time" 
                                       id="followup_time" 
                                       name="followup_time" 
                                       min="08:00" 
                                       max="17:00"
                                       required>
                                <div class="time-suggestions">
                                    <span class="time-suggestion" onclick="setTime('09:00')">9:00 AM</span>
                                    <span class="time-suggestion" onclick="setTime('10:00')">10:00 AM</span>
                                    <span class="time-suggestion" onclick="setTime('13:00')">1:00 PM</span>
                                    <span class="time-suggestion" onclick="setTime('14:00')">2:00 PM</span>
                                    <span class="time-suggestion" onclick="setTime('15:00')">3:00 PM</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="followup_reason">Reason for Follow-up <span class="required">*</span></label>
                            <select id="followup_reason" name="followup_reason" required>
                                <option value="">Select reason for follow-up</option>
                                <option value="Treatment Review">Treatment Review</option>
                                <option value="Lab Results Review">Lab Results Review</option>
                                <option value="Medication Adjustment">Medication Adjustment</option>
                                <option value="Progress Evaluation">Progress Evaluation</option>
                                <option value="Chronic Condition Management">Chronic Condition Management</option>
                                <option value="Post-procedure Check-up">Post-procedure Check-up</option>
                                <option value="Vital Signs Monitoring">Vital Signs Monitoring</option>
                                <option value="Referral Follow-up">Referral Follow-up</option>
                                <option value="Blood Pressure Follow-up">Blood Pressure Follow-up</option>
                                <option value="Diabetes Management">Diabetes Management</option>
                                <option value="Wound Care Follow-up">Wound Care Follow-up</option>
                                <option value="Immunization Follow-up">Immunization Follow-up</option>
                                <option value="Family Planning Consultation">Family Planning Consultation</option>
                                <option value="Other">Other (specify in notes)</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="followup_notes">Additional Notes</label>
                            <textarea id="followup_notes" 
                                      name="followup_notes" 
                                      placeholder="Additional instructions, special requirements, or notes for the follow-up appointment"></textarea>
                        </div>

                    </div>

                    <div class="form-actions">
                        <a href="../consultation.php?visit_id=<?= $visit_id ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Consultation
                        </a>
                        <button type="submit" name="schedule_followup" class="btn btn-success">
                            <i class="fas fa-calendar-plus"></i> Schedule Follow-up
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </section>

    <script>
        function setTime(time) {
            document.getElementById('followup_time').value = time;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to tomorrow
            const dateInput = document.getElementById('followup_date');
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            // Auto-resize textarea
            const textarea = document.getElementById('followup_notes');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Form validation
            document.getElementById('followupForm').addEventListener('submit', function(e) {
                const date = document.getElementById('followup_date').value;
                const time = document.getElementById('followup_time').value;
                const reason = document.getElementById('followup_reason').value;

                if (!date || !time || !reason) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }

                // Check if date/time is in the future
                const selectedDateTime = new Date(date + 'T' + time);
                const now = new Date();
                
                if (selectedDateTime <= now) {
                    e.preventDefault();
                    alert('Follow-up date and time must be in the future.');
                    return false;
                }

                // Check if time is within business hours (8 AM - 5 PM)
                const timeHour = parseInt(time.split(':')[0]);
                if (timeHour < 8 || timeHour >= 17) {
                    e.preventDefault();
                    alert('Please select a time between 8:00 AM and 5:00 PM.');
                    return false;
                }

                // Confirm scheduling
                const confirmMessage = `Schedule follow-up for ${selectedDateTime.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                })} at ${selectedDateTime.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                })}?`;
                
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                    return false;
                }
            });

            // Auto-dismiss alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });

            // Time suggestion interactions
            document.querySelectorAll('.time-suggestion').forEach(suggestion => {
                suggestion.addEventListener('click', function() {
                    // Remove active class from all suggestions
                    document.querySelectorAll('.time-suggestion').forEach(s => s.classList.remove('active'));
                    // Add active class to clicked suggestion
                    this.classList.add('active');
                });
            });

            // Highlight weekends in date picker (visual cue)
            dateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const dayOfWeek = selectedDate.getDay();
                
                // Warn if weekend is selected
                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    const weekendWarning = document.createElement('div');
                    weekendWarning.className = 'alert alert-info';
                    weekendWarning.innerHTML = '<i class="fas fa-info-circle"></i> Note: You have selected a weekend date. Please confirm if the facility operates on weekends.';
                    
                    // Remove existing weekend warnings
                    const existingWarning = document.querySelector('.weekend-warning');
                    if (existingWarning) {
                        existingWarning.remove();
                    }
                    
                    weekendWarning.classList.add('weekend-warning');
                    this.parentNode.insertBefore(weekendWarning, this.nextSibling);
                }
            });
        });
    </script>
</body>

</html>