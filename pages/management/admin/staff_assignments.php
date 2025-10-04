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
require_once $root_path . '/utils/staff_assignment.php';

$date = $_GET['date'] ?? date('Y-m-d');
$assignments = getAllAssignmentsForDate($conn, $date);

// Fetch all employees for assignment dropdown
$employees = [];
$res = $conn->query("SELECT employee_id, first_name, last_name, role FROM employees WHERE status = 'active' ORDER BY last_name, first_name");
while ($row = $res->fetch_assoc()) {
    $employees[] = $row;
}

// Handle assignment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $employee_id = intval($_POST['employee_id']);
    $station_type = $_POST['station_type'];
    $station_number = intval($_POST['station_number']);
    $assigned_date = $_POST['assigned_date'];
    $shift_start = $_POST['shift_start'] ?? '08:00:00';
    $shift_end = $_POST['shift_end'] ?? '17:00:00';
    $assigned_by = $_SESSION['employee_id'];
    if (assignStaffToStation($conn, $employee_id, $station_type, $station_number, $assigned_date, $shift_start, $shift_end, $assigned_by)) {
        header('Location: staff_assignments.php?date=' . urlencode($assigned_date));
        exit();
    } else {
        $error = 'Failed to assign staff.';
    }
}

// Handle unassign action
if (isset($_GET['unassign'])) {
    $assignment_id = intval($_GET['unassign']);
    $res = $conn->query("SELECT * FROM staff_assignments WHERE id = $assignment_id");
    $row = $res->fetch_assoc();
    if ($row) {
        unassignStaffFromStation($conn, $row['employee_id'], $row['station_type'], $row['station_number'], $row['assigned_date']);
        header('Location: staff_assignments.php?date=' . urlencode($row['assigned_date']));
        exit();
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
                    <option value="<?php echo $emp['employee_id']; ?>"><?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' (' . $emp['role'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Station Type:</label>
            <select name="station_type" required>
                <option value="doctor">Doctor</option>
                <option value="nurse">Nurse</option>
                <option value="pharmacist">Pharmacist</option>
                <option value="laboratory_tech">Laboratory Tech</option>
                <option value="cashier">Cashier</option>
                <option value="records_officer">Records Officer</option>
                <option value="dho">DHO</option>
                <option value="bhw">BHW</option>
            </select>
        </div>
        <div class="form-row">
            <label>Station Number:</label>
            <input type="number" name="station_number" min="1" required>
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
                    <th>Employee</th>
                    <th>Role</th>
                    <th>Station Type</th>
                    <th>Station #</th>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['last_name'] . ', ' . $a['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($a['role']); ?></td>
                        <td><?php echo htmlspecialchars($a['station_type']); ?></td>
                        <td><?php echo htmlspecialchars($a['station_number']); ?></td>
                        <td><?php echo htmlspecialchars($a['assigned_date']); ?></td>
                        <td><?php echo htmlspecialchars($a['shift_start'] . ' - ' . $a['shift_end']); ?></td>
                        <td><?php echo htmlspecialchars($a['status']); ?></td>
                        <td>
                            <?php if ($a['status'] === 'active'): ?>
                                <a href="?unassign=<?php echo $a['id']; ?>&date=<?php echo urlencode($a['assigned_date']); ?>" onclick="return confirm('Unassign this staff?');">Unassign</a>
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
</body>
</html>
