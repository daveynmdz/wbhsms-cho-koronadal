<?php
// pages/queueing/manage_queue.php
// Employee interface for managing their assigned station's queue

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// Check if role is authorized for queue management
$allowed_roles = ['doctor', 'nurse', 'pharmacist', 'laboratory_tech', 'cashier', 'records_officer', 'bhw'];
if (!in_array(strtolower($_SESSION['role']), $allowed_roles)) {
    header('Location: ../management/' . strtolower($_SESSION['role']) . '/dashboard.php');
    exit();
}

require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

// Initialize queue management service
$queueService = new QueueManagementService($conn);

$employee_id = $_SESSION['employee_id'];
$message = '';
$error = '';

// Get employee's station assignment for today
$station_assignment = $queueService->getActiveStationByEmployee($employee_id);

if (!$station_assignment) {
    $error = 'You are not assigned to any station today. Please contact the administrator.';
} else {
    $station_id = $station_assignment['station_id'];
    
    // Handle queue actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $queue_entry_id = intval($_POST['queue_entry_id']);
        
        if (isset($_POST['next_patient'])) {
            $result = $queueService->updateQueueStatus($queue_entry_id, 'in_progress', 'waiting', $employee_id);
            if ($result['success']) {
                $message = 'Patient called to station.';
            } else {
                $error = $result['error'];
            }
        } elseif (isset($_POST['complete_patient'])) {
            $remarks = $_POST['remarks'] ?? null;
            $result = $queueService->updateQueueStatus($queue_entry_id, 'done', 'in_progress', $employee_id, $remarks);
            if ($result['success']) {
                $message = 'Patient completed successfully.';
            } else {
                $error = $result['error'];
            }
        } elseif (isset($_POST['skip_patient'])) {
            $remarks = $_POST['remarks'] ?? 'Skipped by staff';
            $result = $queueService->updateQueueStatus($queue_entry_id, 'skipped', 'waiting', $employee_id, $remarks);
            if ($result['success']) {
                $message = 'Patient skipped.';
            } else {
                $error = $result['error'];
            }
        } elseif (isset($_POST['reinstate_patient'])) {
            $result = $queueService->updateQueueStatus($queue_entry_id, 'waiting', 'skipped', $employee_id, 'Reinstated by staff');
            if ($result['success']) {
                $message = 'Patient reinstated to queue.';
            } else {
                $error = $result['error'];
            }
        } elseif (isset($_POST['no_show_patient'])) {
            $result = $queueService->updateQueueStatus($queue_entry_id, 'no_show', 'waiting', $employee_id, 'Marked as no-show');
            if ($result['success']) {
                $message = 'Patient marked as no-show.';
            } else {
                $error = $result['error'];
            }
        }
    }
    
    // Get queue entries for this station
    $waiting_queue = $queueService->getStationQueue($station_id, 'waiting');
    $in_progress_queue = $queueService->getStationQueue($station_id, 'in_progress');
    $skipped_queue = $queueService->getStationQueue($station_id, 'skipped');
    $completed_queue = $queueService->getStationQueue($station_id, 'done');
    
    // Get queue statistics
    $queue_stats = $queueService->getStationQueueStats($station_id);
}

// Set active page for sidebar highlighting
$activePage = 'queue_management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Management | CHO Koronadal</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Additional styles for queue management */
        :root {
            --primary: #0077b6;
            --primary-dark: #03045e;
            --secondary: #6c757d;
            --success: #2d6a4f;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #d00000;
            --light: #f8f9fa;
            --border: #dee2e6;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
        }

        .queue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
            border-left: 4px solid var(--primary);
        }

        .stat-card.waiting { border-left-color: var(--info); }
        .stat-card.in-progress { border-left-color: var(--warning); }
        .stat-card.completed { border-left-color: var(--success); }
        .stat-card.skipped { border-left-color: var(--danger); }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .queue-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .queue-header {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .queue-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .queue-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .queue-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .queue-item:hover {
            background-color: rgba(240, 247, 255, 0.6);
        }

        .queue-item:last-child {
            border-bottom: none;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }

        .queue-details {
            display: flex;
            gap: 15px;
            color: var(--secondary);
            font-size: 14px;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-normal { background-color: #e3f2fd; color: #1976d2; }
        .priority-priority { background-color: #fff3e0; color: #f57c00; }
        .priority-emergency { background-color: #ffebee; color: #d32f2f; }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            margin: 2px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: #000;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-secondary {
            background-color: var(--secondary);
            color: white;
        }
        
        .btn-info {
            background-color: var(--info);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .station-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid var(--primary);
        }

        .station-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }

        .station-details {
            color: var(--secondary);
            display: flex;
            gap: 20px;
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 20px;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .refresh-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .empty-queue {
            text-align: center;
            padding: 40px 20px;
            color: var(--secondary);
        }

        .empty-queue i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: var(--border-radius);
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-dialog {
            position: relative;
            margin: 50px auto;
            max-width: 500px;
            animation: slideDown 0.3s ease-out;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--secondary);
        }
        
        .btn-close:hover {
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            transition: var(--transition);
            resize: vertical;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 182, 0.25);
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .current-patient {
            background: linear-gradient(135deg, #fff3e0, #ffcc02);
            border: 2px solid var(--warning);
        }

        .d-flex { display: flex; }
        .me-2 { margin-right: 8px; }
        .mb-2 { margin-bottom: 8px; }
        .text-center { text-align: center; }
        .text-muted { color: var(--secondary) !important; }
    </style>
</head>
<body>
    <!-- Include sidebar based on role -->
    <?php 
    $sidebar_file = '../../includes/sidebar_' . strtolower($_SESSION['role']) . '.php';
    if (file_exists($sidebar_file)) {
        include $sidebar_file;
    } else {
        include '../../includes/sidebar_admin.php';
    }
    ?>
    
    <div class="homepage">
        <div class="main-content">
            <!-- Include topbar -->
            <?php include '../../includes/topbar.php'; ?>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$station_assignment): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>No Station Assignment:</strong> You are not assigned to any station today. Please contact the administrator to get your station assignment.
                </div>
            <?php else: ?>
                <!-- Station Information -->
                <div class="station-info">
                    <div class="station-name">
                        <i class="fas fa-hospital me-2"></i><?php echo htmlspecialchars($station_assignment['station_name']); ?>
                    </div>
                    <div class="station-details">
                        <span><i class="fas fa-cogs me-2"></i><strong>Type:</strong> <?php echo ucfirst($station_assignment['station_type']); ?></span>
                        <span><i class="fas fa-medical-kit me-2"></i><strong>Service:</strong> <?php echo htmlspecialchars($station_assignment['service_name']); ?></span>
                        <span><i class="fas fa-clock me-2"></i><strong>Shift:</strong> <?php echo date('g:i A', strtotime($station_assignment['shift_start'])); ?> - <?php echo date('g:i A', strtotime($station_assignment['shift_end'])); ?></span>
                    </div>
                </div>

                <!-- Queue Statistics -->
                <div class="queue-stats">
                    <div class="stat-card waiting">
                        <div class="stat-number"><?php echo $queue_stats['waiting_count'] ?? 0; ?></div>
                        <div class="stat-label">Waiting</div>
                    </div>
                    <div class="stat-card in-progress">
                        <div class="stat-number"><?php echo $queue_stats['in_progress_count'] ?? 0; ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-card completed">
                        <div class="stat-number"><?php echo $queue_stats['completed_count'] ?? 0; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card skipped">
                        <div class="stat-number"><?php echo $queue_stats['skipped_count'] ?? 0; ?></div>
                        <div class="stat-label">Skipped</div>
                    </div>
                </div>

                <!-- In Progress Queue -->
                <?php if (!empty($in_progress_queue)): ?>
                    <div class="queue-section">
                        <div class="queue-header">
                            <h3 class="queue-title"><i class="fas fa-user-clock me-2"></i>Current Patient</h3>
                            <span class="queue-count"><?php echo count($in_progress_queue); ?></span>
                        </div>
                        <?php foreach ($in_progress_queue as $patient): ?>
                            <div class="queue-item current-patient">
                                <div class="patient-info">
                                    <div class="patient-name">
                                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($patient['patient_name']); ?>
                                    </div>
                                    <div class="queue-details">
                                        <span><i class="fas fa-hashtag me-2"></i>Queue #<?php echo $patient['queue_number']; ?></span>
                                        <span class="priority-badge priority-<?php echo $patient['priority_level']; ?>">
                                            <?php echo ucfirst($patient['priority_level']); ?>
                                        </span>
                                        <span><i class="fas fa-clock me-2"></i>Started: <?php echo date('g:i A', strtotime($patient['time_started'])); ?></span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <button type="button" class="action-btn btn-success" onclick="openCompleteModal(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name']); ?>')">
                                        <i class="fas fa-check me-2"></i>Complete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Waiting Queue -->
                <div class="queue-section">
                    <div class="queue-header">
                        <h3 class="queue-title"><i class="fas fa-hourglass-half me-2"></i>Waiting Queue</h3>
                        <span class="queue-count"><?php echo count($waiting_queue); ?></span>
                    </div>
                    <?php if (!empty($waiting_queue)): ?>
                        <?php foreach ($waiting_queue as $patient): ?>
                            <div class="queue-item">
                                <div class="patient-info">
                                    <div class="patient-name">
                                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($patient['patient_name']); ?>
                                    </div>
                                    <div class="queue-details">
                                        <span><i class="fas fa-hashtag me-2"></i>Queue #<?php echo $patient['queue_number']; ?></span>
                                        <span class="priority-badge priority-<?php echo $patient['priority_level']; ?>">
                                            <?php echo ucfirst($patient['priority_level']); ?>
                                        </span>
                                        <span><i class="fas fa-clock me-2"></i>Waiting: <?php echo date('g:i A', strtotime($patient['time_in'])); ?></span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <?php if (empty($in_progress_queue)): // Only allow next if no current patient ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="queue_entry_id" value="<?php echo $patient['queue_entry_id']; ?>">
                                            <button type="submit" name="next_patient" class="action-btn btn-primary">
                                                <i class="fas fa-arrow-right me-2"></i>Next
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="action-btn btn-warning" onclick="openSkipModal(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name']); ?>')">
                                        <i class="fas fa-forward me-2"></i>Skip
                                    </button>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Mark this patient as no-show?');">
                                        <input type="hidden" name="queue_entry_id" value="<?php echo $patient['queue_entry_id']; ?>">
                                        <button type="submit" name="no_show_patient" class="action-btn btn-danger">
                                            <i class="fas fa-user-times me-2"></i>No Show
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-queue">
                            <i class="fas fa-clipboard-list"></i>
                            <p>No patients waiting in queue</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Skipped Queue -->
                <?php if (!empty($skipped_queue)): ?>
                    <div class="queue-section">
                        <div class="queue-header">
                            <h3 class="queue-title"><i class="fas fa-user-minus me-2"></i>Skipped Patients</h3>
                            <span class="queue-count"><?php echo count($skipped_queue); ?></span>
                        </div>
                        <?php foreach ($skipped_queue as $patient): ?>
                            <div class="queue-item">
                                <div class="patient-info">
                                    <div class="patient-name">
                                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($patient['patient_name']); ?>
                                    </div>
                                    <div class="queue-details">
                                        <span><i class="fas fa-hashtag me-2"></i>Queue #<?php echo $patient['queue_number']; ?></span>
                                        <span class="priority-badge priority-<?php echo $patient['priority_level']; ?>">
                                            <?php echo ucfirst($patient['priority_level']); ?>
                                        </span>
                                        <span><i class="fas fa-comment me-2"></i><?php echo htmlspecialchars($patient['remarks'] ?: 'No remarks'); ?></span>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="queue_entry_id" value="<?php echo $patient['queue_entry_id']; ?>">
                                        <button type="submit" name="reinstate_patient" class="action-btn btn-info">
                                            <i class="fas fa-undo me-2"></i>Reinstate
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Completed Queue (last 10) -->
                <?php if (!empty($completed_queue)): ?>
                    <div class="queue-section">
                        <div class="queue-header">
                            <h3 class="queue-title"><i class="fas fa-check-circle me-2"></i>Completed Today</h3>
                            <span class="queue-count"><?php echo count($completed_queue); ?></span>
                        </div>
                        <?php foreach (array_slice($completed_queue, -10) as $patient): ?>
                            <div class="queue-item">
                                <div class="patient-info">
                                    <div class="patient-name">
                                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($patient['patient_name']); ?>
                                    </div>
                                    <div class="queue-details">
                                        <span><i class="fas fa-hashtag me-2"></i>Queue #<?php echo $patient['queue_number']; ?></span>
                                        <span><i class="fas fa-check me-2"></i>Completed: <?php echo date('g:i A', strtotime($patient['time_completed'])); ?></span>
                                        <?php if ($patient['turnaround_time']): ?>
                                            <span><i class="fas fa-stopwatch me-2"></i>Duration: <?php echo $patient['turnaround_time']; ?> min</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <!-- Refresh button -->
            <button type="button" class="refresh-btn" onclick="refreshQueue()" title="Refresh Queue">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>
    
    <!-- Complete Patient Modal -->
    <div class="modal" id="completeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5>Complete Patient</h5>
                        <button type="button" class="btn-close" onclick="closeModal('completeModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="queue_entry_id" id="complete_queue_entry_id">
                        
                        <p>Complete service for: <strong id="complete_patient_name"></strong></p>
                        
                        <div class="mb-2">
                            <label>Completion Notes (Optional):</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Add any notes about the service provided..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn btn-secondary" onclick="closeModal('completeModal')">Cancel</button>
                        <button type="submit" name="complete_patient" class="action-btn btn-success">
                            <i class="fas fa-check me-2"></i>Complete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Skip Patient Modal -->
    <div class="modal" id="skipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5>Skip Patient</h5>
                        <button type="button" class="btn-close" onclick="closeModal('skipModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="queue_entry_id" id="skip_queue_entry_id">
                        
                        <p>Skip patient: <strong id="skip_patient_name"></strong></p>
                        
                        <div class="mb-2">
                            <label>Reason for skipping:</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Please provide a reason for skipping this patient..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn btn-secondary" onclick="closeModal('skipModal')">Cancel</button>
                        <button type="submit" name="skip_patient" class="action-btn btn-warning">
                            <i class="fas fa-forward me-2"></i>Skip
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCompleteModal(queueEntryId, patientName) {
            document.getElementById('complete_queue_entry_id').value = queueEntryId;
            document.getElementById('complete_patient_name').textContent = patientName;
            document.getElementById('completeModal').classList.add('show');
        }
        
        function openSkipModal(queueEntryId, patientName) {
            document.getElementById('skip_queue_entry_id').value = queueEntryId;
            document.getElementById('skip_patient_name').textContent = patientName;
            document.getElementById('skipModal').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function refreshQueue() {
            // Add rotation animation to refresh button
            const btn = document.querySelector('.refresh-btn i');
            btn.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                btn.style.transform = 'rotate(0deg)';
            }, 500);
            
            // Reload the page to refresh queue
            window.location.reload();
        }
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            refreshQueue();
        }, 30000);
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>