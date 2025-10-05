<?php
// pages/management/admin/staff_assignments.php
// Admin interface for assigning/unassigning staff to stations for a given date

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check if role is authorized for admin functions
if (strtolower($_SESSION['role']) !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

$date = $_GET['date'] ?? date('Y-m-d');
$queueService = new QueueManagementService($conn);
$assignments = $queueService->getAllStationsWithAssignments($date);

// Fetch all employees for assignment dropdown using new service
$employees = $queueService->getActiveEmployees(1);

// Handle assignment form submission using new system
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $employee_id = intval($_POST['employee_id']);
    $station_id = intval($_POST['station_id']);
    $assigned_date = $_POST['assigned_date'];
    $assignment_type = $_POST['assignment_type'] ?? 'permanent';
    $end_date = $_POST['end_date'] ?? null;
    $shift_start = $_POST['shift_start'] ?? '08:00:00';
    $shift_end = $_POST['shift_end'] ?? '17:00:00';
    $assigned_by = $_SESSION['employee_id'];
    
    $result = $queueService->assignEmployeeToStation(
        $employee_id, $station_id, $assigned_date, $assignment_type,
        $shift_start, $shift_end, $assigned_by, $end_date
    );
    
    if ($result['success']) {
        header('Location: staff_assignments.php?date=' . urlencode($assigned_date));
        exit();
    } else {
        $error = $result['error'] ?? 'Failed to assign staff.';
    }
}

// Handle unassign action using new system
if (isset($_GET['unassign'])) {
    $station_id = intval($_GET['unassign']);
    $removal_date = $_GET['date'] ?? date('Y-m-d');
    
    $result = $queueService->removeEmployeeAssignment($station_id, $removal_date, 'end_assignment');
    
    if ($result['success']) {
        header('Location: staff_assignments.php?date=' . urlencode($removal_date));
        exit();
    } else {
        $error = $result['error'] ?? 'Failed to remove assignment.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Assignments Management</title>
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 1em; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .form-row { margin-bottom: 1em; }
    </style>
</head>
<body>
    <h2>Staff Assignments for <?php echo htmlspecialchars($date); ?></h2>
    
    <?php if (isset($error)): ?>
        <div style="color: red; background: #ffe6e6; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form method="get" style="margin-bottom:1em;">
        <label for="date">Date:</label>
        <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($date); ?>">
        <button type="submit">View</button>
    </form>
    <form method="post" style="margin-bottom:2em;">
        <div class="form-row">
            <label>Employee:</label>
            <select name="employee_id" required>
                <option value="">Select...</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp['employee_id']; ?>"><?php echo htmlspecialchars($emp['full_name'] . ' (' . $emp['role_name'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Station:</label>
            <select name="station_id" required>
                <option value="">Select...</option>
                <?php 
                $stations_result = $conn->query("SELECT station_id, station_name, station_type FROM stations WHERE is_active = 1 ORDER BY station_name");
                while ($station = $stations_result->fetch_assoc()): 
                ?>
                    <option value="<?php echo $station['station_id']; ?>"><?php echo htmlspecialchars($station['station_name'] . ' (' . $station['station_type'] . ')'); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Assignment Type:</label>
            <select name="assignment_type" required onchange="toggleEndDate()">
                <option value="permanent">Permanent Assignment</option>
                <option value="temporary">Temporary Assignment</option>
            </select>
        </div>
        <div class="form-row" id="end_date_field" style="display: none;">
            <label>End Date:</label>
            <input type="date" name="end_date" min="<?php echo htmlspecialchars($date); ?>">
        </div>
        <div class="form-row">
            <label>Date:</label>
            <input type="date" name="assigned_date" value="<?php echo htmlspecialchars($date); ?>" required>
        </div>
        <div class="form-row">
            <label>Shift Start:</label>
            <input type="time" name="shift_start" value="08:00:00">
            <label>Shift End:</label>
            <input type="time" name="shift_end" value="17:00:00">
        </div>
        <button type="submit" name="assign">Assign Staff</button>
    </form>
    <?php if (!empty($assignments)): ?>
        <table>
            <thead>
                <tr>
                    <th>Station</th>
                    <th>Employee</th>
                    <th>Role</th>
                    <th>Assignment Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['station_name'] ?? 'Unknown'); ?></td>
                        <td><?php echo htmlspecialchars($a['employee_name'] ?? 'Unassigned'); ?></td>
                        <td><?php echo htmlspecialchars($a['employee_role'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($a['assignment_type'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($a['start_date'] ?? '-'); ?></td>
                        <td><?php echo $a['end_date'] ? htmlspecialchars($a['end_date']) : 'Permanent'; ?></td>
                        <td><?php 
                            if ($a['shift_start_time'] && $a['shift_end_time']) {
                                echo htmlspecialchars($a['shift_start_time'] . ' - ' . $a['shift_end_time']);
                            } else {
                                echo '-';
                            }
                        ?></td>
                        <td><?php echo ($a['is_active'] && $a['employee_name']) ? 'Active' : 'Inactive'; ?></td>
                        <td>
                            <?php if ($a['employee_name'] && $a['assignment_status']): ?>
                                <a href="?unassign=<?php echo $a['station_id']; ?>&date=<?php echo urlencode($date); ?>" onclick="return confirm('Remove this assignment?');">Remove</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No assignments for this date.</p>
    <?php endif; ?>
    <script>
        function toggleEndDate() {
            const assignmentType = document.querySelector('select[name="assignment_type"]').value;
            const endDateField = document.getElementById('end_date_field');
            const endDateInput = document.querySelector('input[name="end_date"]');
            
            if (assignmentType === 'temporary') {
                endDateField.style.display = 'block';
                endDateInput.required = true;
            } else {
                endDateField.style.display = 'none';
                endDateInput.required = false;
                endDateInput.value = '';
            }
        }
    </script>
</body>
</html>
