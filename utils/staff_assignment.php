<?php
// utils/staff_assignment.php
// Utility functions for dynamic staff-station assignments

function getStaffAssignment($conn, $employee_id, $date = null) {
    if (!$date) $date = date('Y-m-d');
    $sql = "SELECT * FROM staff_assignments WHERE employee_id = ? AND assigned_date = ? AND status = 'active' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $employee_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();
    return $assignment;
}

function getAllAssignmentsForDate($conn, $date = null) {
    if (!$date) $date = date('Y-m-d');
$sql = "SELECT sa.*, e.first_name, e.last_name, r.role_name
        FROM staff_assignments sa
        JOIN employees e ON sa.employee_id = e.employee_id
        JOIN roles r ON e.role_id = r.role_id
        WHERE sa.assigned_date = ?
        ORDER BY sa.station_type, sa.station_number";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $assignments;
}

function assignStaffToStation($conn, $employee_id, $station_type, $station_number, $assigned_date, $shift_start = '08:00:00', $shift_end = '17:00:00', $assigned_by = null) {
    $sql = "INSERT INTO staff_assignments (employee_id, station_type, station_number, assigned_date, shift_start, shift_end, status, assigned_by) VALUES (?, ?, ?, ?, ?, ?, 'active', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isisssi', $employee_id, $station_type, $station_number, $assigned_date, $shift_start, $shift_end, $assigned_by);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function unassignStaffFromStation($conn, $employee_id, $station_type, $station_number, $assigned_date) {
    $sql = "UPDATE staff_assignments SET status = 'inactive' WHERE employee_id = ? AND station_type = ? AND station_number = ? AND assigned_date = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isis', $employee_id, $station_type, $station_number, $assigned_date);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
