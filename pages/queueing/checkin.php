<?php
/**
 * Queueing - Patient Check-in Page
 * Purpose: Check-in patients who have scheduled appointments
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Include queue management service
require_once $root_path . '/utils/queue_management_service.php';

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];
$message = '';
$error = '';
$appointment_data = null;

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please contact administrator.");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Lookup appointment
        if ($_POST['action'] === 'lookup') {
            $appointment_id = trim($_POST['appointment_id'] ?? '');
            
            if (empty($appointment_id)) {
                $error = "Please enter an appointment ID";
            } else {
                try {
                    // Fetch today's appointment with queue entry status 'waiting'
                    $stmt = $conn->prepare("
                        SELECT a.appointment_id, a.patient_id, a.facility_id, a.service_id,
                               a.scheduled_date, a.scheduled_time, a.status as appointment_status,
                               p.first_name, p.last_name, p.patient_number, p.isPWD, p.isSenior,
                               s.name as service_name, s.description as service_description,
                               f.name as facility_name, f.type as facility_type,
                               qe.queue_entry_id, qe.queue_number, qe.queue_type, qe.priority_level,
                               qe.status as queue_status, qe.visit_id,
                               (SELECT COUNT(*) FROM queue_entries qe2 
                                WHERE qe2.queue_type = qe.queue_type 
                                AND DATE(qe2.created_at) = DATE(qe.created_at)
                                AND qe2.queue_number < qe.queue_number 
                                AND qe2.status = 'waiting') as position_in_queue
                        FROM appointments a
                        INNER JOIN patients p ON a.patient_id = p.patient_id
                        INNER JOIN services s ON a.service_id = s.service_id
                        INNER JOIN facilities f ON a.facility_id = f.facility_id
                        LEFT JOIN queue_entries qe ON a.appointment_id = qe.appointment_id
                        WHERE a.appointment_id = ? 
                        AND DATE(a.scheduled_date) = CURDATE()
                        AND qe.status = 'waiting'
                    ");
                    
                    $stmt->bind_param("i", $appointment_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $appointment_data = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$appointment_data) {
                        $error = "No waiting appointment found for ID: $appointment_id on today's date";
                    }
                    
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
        
        // Confirm check-in
        if ($_POST['action'] === 'checkin') {
            $queue_entry_id = trim($_POST['queue_entry_id'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            
            if (empty($queue_entry_id)) {
                $error = "Invalid queue entry ID";
            } else {
                try {
                    $queue_service = new QueueManagementService($conn);
                    $result = $queue_service->checkInQueueEntry($queue_entry_id, $employee_id, $remarks);
                    
                    if ($result['success']) {
                        $queue_number = $result['queue_details']['queue_number'];
                        $message = "✅ Checked in successfully — Queue #$queue_number — Proceed to Triage";
                        $appointment_data = null; // Clear the data to hide the form
                    } else {
                        $error = "Check-in failed: " . $result['error'];
                    }
                    
                } catch (Exception $e) {
                    $error = "System error: " . $e->getMessage();
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Check-in - CHO Koronadal WBHSMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    
    <style>
        .content-wrapper {
            margin-left: 300px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.2rem;
            color: #0077b6;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0 0 1rem 0;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .breadcrumb a {
            color: #6c757d;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: #0077b6;
        }

        .section-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-icon {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .section-title {
            margin: 0;
            font-size: 1.5rem;
            color: #0077b6;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #0077b6;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
            background: white;
            transform: translateY(-1px);
        }

        .btn {
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 1rem;
        }

        .btn-primary {
            background: #007BFF;
            color: #fff;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .btn-primary i {
            margin-right: 8px;
            font-size: 18px;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #16a085, #0f6b5c);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #0f6b5c, #0a4f44);
            transform: translateY(-2px);
        }

        .btn-link {
            background: transparent;
            color: #6c757d;
            border: 2px solid #e9ecef;
            box-shadow: none;
        }

        .btn-link:hover {
            color: #495057;
            border-color: #ced4da;
            background: #f8f9fa;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid transparent;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .alert i {
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f1b2b7);
            color: #721c24;
            border-color: #f1b2b7;
        }

        .appointment-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 2rem;
            margin: 1.5rem 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        .appointment-card:hover {
            border-color: #0077b6;
            box-shadow: 0 12px 30px rgba(0, 119, 182, 0.2);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f1f3f4;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0077b6;
            margin: 0;
            line-height: 1.3;
        }

        .queue-number-badge {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 15px;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 119, 182, 0.3);
        }

        .queue-number-label {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .priority-indicator {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            position: absolute;
            top: -10px;
            right: 20px;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .card-info {
            margin-bottom: 1.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #0077b6;
        }

        .info-item i {
            color: #0077b6;
            width: 20px;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .info-item .label {
            font-weight: 600;
            color: #495057;
            min-width: 80px;
            font-size: 0.9rem;
        }

        .info-item .value {
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .card-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f3f4;
        }

        .lookup-form {
            max-width: 500px;
            margin: 0 auto;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .card-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .queue-number-badge {
                font-size: 1.5rem;
                padding: 0.75rem 1rem;
            }

            .card-actions {
                flex-direction: column;
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
    // Tell the sidebar which menu item to highlight
    $activePage = 'queue_checkin';
    include '../../includes/sidebar_' . strtolower($employee_role) . '.php';
    ?>

    <section class="homepage">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Queue Dashboard</a>
            <span> / </span>
            <span style="color: #0077b6; font-weight: 600;">Patient Check-in</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-user-check" style="margin-right: 0.5rem;"></i>Patient Check-in</h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Lookup Form Section -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h2 class="section-title">Lookup Appointment</h2>
            </div>

            <form method="POST" class="lookup-form">
                <input type="hidden" name="action" value="lookup">
                
                <div class="form-group">
                    <label for="appointment_id">Appointment ID</label>
                    <input type="number" 
                           id="appointment_id" 
                           name="appointment_id" 
                           class="form-control" 
                           placeholder="Enter appointment ID..." 
                           value="<?php echo htmlspecialchars($_POST['appointment_id'] ?? ''); ?>"
                           required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Lookup Appointment
                    </button>
                </div>
            </form>
        </div>

        <!-- Appointment Details Section (shown when appointment is found) -->
        <?php if ($appointment_data): ?>
            <div class="section-container">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h2 class="section-title">Appointment Details</h2>
                </div>

                <div class="appointment-card">
                    <?php if (($appointment_data['isPWD'] || $appointment_data['isSenior'])): ?>
                        <div class="priority-indicator">
                            <i class="fas fa-star"></i> Priority
                        </div>
                    <?php endif; ?>

                    <div class="card-header">
                        <div>
                            <h3 class="card-title">
                                <?php echo htmlspecialchars($appointment_data['first_name'] . ' ' . $appointment_data['last_name']); ?>
                            </h3>
                            <p style="margin: 0.5rem 0 0 0; color: #6c757d; font-size: 0.9rem;">
                                Patient #<?php echo htmlspecialchars($appointment_data['patient_number']); ?>
                            </p>
                        </div>
                        <div class="queue-number-badge">
                            <div class="queue-number-label">Queue Number</div>
                            <div>#<?php echo htmlspecialchars($appointment_data['queue_number']); ?></div>
                        </div>
                    </div>

                    <div class="card-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <i class="fas fa-stethoscope"></i>
                                <span class="label">Service:</span>
                                <span class="value"><?php echo htmlspecialchars($appointment_data['service_name']); ?></span>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-hospital-symbol"></i>
                                <span class="label">Facility:</span>
                                <span class="value"><?php echo htmlspecialchars($appointment_data['facility_name']); ?></span>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span class="label">Time:</span>
                                <span class="value"><?php echo date('g:i A', strtotime($appointment_data['scheduled_time'])); ?></span>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-flag"></i>
                                <span class="label">Priority:</span>
                                <span class="value">
                                    <?php 
                                    if ($appointment_data['isPWD'] || $appointment_data['isSenior']) {
                                        echo 'Priority';
                                        if ($appointment_data['isPWD'] && $appointment_data['isSenior']) {
                                            echo ' (PWD + Senior)';
                                        } elseif ($appointment_data['isPWD']) {
                                            echo ' (PWD)';
                                        } else {
                                            echo ' (Senior)';
                                        }
                                    } else {
                                        echo 'Regular';
                                    }
                                    ?>
                                </span>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-users"></i>
                                <span class="label">Position:</span>
                                <span class="value">#<?php echo (int)$appointment_data['position_in_queue'] + 1; ?> in queue</span>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-list"></i>
                                <span class="label">Queue Type:</span>
                                <span class="value"><?php echo ucfirst(htmlspecialchars($appointment_data['queue_type'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" class="card-actions">
                        <input type="hidden" name="action" value="checkin">
                        <input type="hidden" name="queue_entry_id" value="<?php echo htmlspecialchars($appointment_data['queue_entry_id']); ?>">
                        
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label for="remarks" style="margin-bottom: 0.5rem;">Check-in Notes (Optional)</label>
                            <input type="text" 
                                   id="remarks" 
                                   name="remarks" 
                                   class="form-control" 
                                   placeholder="Any special notes or observations..."
                                   style="margin-bottom: 1rem;">
                        </div>
                        
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i>
                                Confirm Check-in
                            </button>
                            
                            <button type="button" class="btn btn-secondary" onclick="printTicket()">
                                <i class="fas fa-print"></i>
                                Print Ticket
                            </button>
                            
                            <a href="checkin.php" class="btn btn-link">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </section>

    <script>
        // Print ticket functionality
        function printTicket() {
            <?php if ($appointment_data): ?>
                const printContent = `
                    <div style="text-align: center; font-family: monospace; width: 300px; margin: 0 auto;">
                        <h2>CHO KORONADAL</h2>
                        <h3>Queue Ticket</h3>
                        <hr>
                        <div style="font-size: 2em; font-weight: bold; margin: 20px 0;">
                            Queue #<?php echo htmlspecialchars($appointment_data['queue_number']); ?>
                        </div>
                        <hr>
                        <p><strong>Patient:</strong> <?php echo htmlspecialchars($appointment_data['first_name'] . ' ' . $appointment_data['last_name']); ?></p>
                        <p><strong>Service:</strong> <?php echo htmlspecialchars($appointment_data['service_name']); ?></p>
                        <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment_data['scheduled_time'])); ?></p>
                        <p><strong>Position:</strong> #<?php echo (int)$appointment_data['position_in_queue'] + 1; ?> in queue</p>
                        <?php if ($appointment_data['isPWD'] || $appointment_data['isSenior']): ?>
                        <p><strong>Priority Patient</strong></p>
                        <?php endif; ?>
                        <hr>
                        <p style="font-size: 0.8em;">Please wait for your number to be called</p>
                        <p style="font-size: 0.8em;"><?php echo date('M j, Y g:i A'); ?></p>
                    </div>
                `;
                
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head><title>Queue Ticket</title></head>
                        <body>${printContent}</body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
                printWindow.close();
            <?php endif; ?>
        }

        // Auto-focus on appointment ID input
        document.addEventListener('DOMContentLoaded', function() {
            const appointmentIdInput = document.getElementById('appointment_id');
            if (appointmentIdInput && !appointmentIdInput.value) {
                appointmentIdInput.focus();
            }
        });

        // Handle Enter key in appointment ID input
        document.getElementById('appointment_id').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    </script>
</body>
</html>