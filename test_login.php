<?php
require_once 'config/session/employee_session.php';
require_once 'config/db.php';

if (isset($_POST['login'])) {
    $employee_id = intval($_POST['employee_id']);
    
    // Get employee details
    $query = "SELECT * FROM employees WHERE employee_id = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        
        // Set session variables
        $_SESSION['employee_id'] = $employee['employee_id'];
        $_SESSION['role_id'] = $employee['role_id'];
        $_SESSION['first_name'] = $employee['first_name'];
        $_SESSION['last_name'] = $employee['last_name'];
        $_SESSION['email'] = $employee['email'];
        
        echo "<div style='color: green; margin: 10px;'>Successfully logged in as " . $employee['first_name'] . " " . $employee['last_name'] . "</div>";
        echo "<p><a href='pages/prescription-management/prescription_management.php'>Go to Prescription Management</a></p>";
    } else {
        echo "<div style='color: red; margin: 10px;'>Invalid employee ID</div>";
    }
}

// Check current session
if (isset($_SESSION['employee_id'])) {
    echo "<div style='background: #e7f3ff; padding: 10px; margin: 10px; border-left: 4px solid #007cba;'>";
    echo "<h3>Current Session:</h3>";
    echo "Employee ID: " . $_SESSION['employee_id'] . "<br>";
    echo "Name: " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . "<br>";
    echo "Role ID: " . $_SESSION['role_id'] . "<br>";
    echo "</div>";
    echo "<p><a href='pages/prescription-management/prescription_management.php'>Go to Prescription Management</a></p>";
    echo "<p><a href='?logout=1'>Logout</a></p>";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get available employees
$query = "SELECT employee_id, first_name, last_name, role_id FROM employees WHERE status = 'active' ORDER BY role_id";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quick Login for Testing</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-group { margin: 10px 0; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>Quick Login for Testing</h1>
    
    <form method="POST">
        <div class="form-group">
            <label for="employee_id">Select Employee to Login As:</label>
            <select name="employee_id" id="employee_id" required>
                <option value="">Choose an employee...</option>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <option value="<?= $row['employee_id'] ?>">
                        <?= $row['first_name'] . " " . $row['last_name'] ?> (Role: <?= $row['role_id'] ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" name="login">Login</button>
    </form>
    
    <h2>Role Reference:</h2>
    <table>
        <tr><th>Role ID</th><th>Role Name</th><th>Prescription Permissions</th></tr>
        <tr><td>1</td><td>Admin</td><td>View, Create, Update</td></tr>
        <tr><td>2</td><td>Doctor</td><td>View, Create</td></tr>
        <tr><td>3</td><td>Nurse</td><td>View only</td></tr>
        <tr><td>9</td><td>Pharmacist</td><td>View, Create, Update (dispense)</td></tr>
    </table>
    
</body>
</html>