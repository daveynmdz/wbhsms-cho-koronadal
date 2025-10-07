<?php
/**
 * Check-In Summary API Endpoint
 * Returns real-time JSON data for checkin.php summary cards
 * 
 * WBHSMS - City Health Office Queueing System
 * Created: October 2025
 */

// Start session and set JSON headers immediately
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Include database configuration
require_once '../../config/db.php';

// ==========================================
// ACCESS CONTROL VALIDATION
// ==========================================

// Check if user is logged in
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Access denied. Authentication required.']);
    exit;
}

// Define allowed roles for check-in summary access
$allowed_roles = ['admin', 'records_officer', 'dho', 'bhw'];
$user_role = strtolower($_SESSION['role']);

if (!in_array($user_role, $allowed_roles)) {
    echo json_encode(['error' => 'Access denied. Insufficient permissions.']);
    exit;
}

// ==========================================
// DATABASE OPERATIONS
// ==========================================

try {
    // Validate database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // ==========================================
    // QUERY 1: Total Appointments Today
    // ==========================================
    
    $stmt_today = $conn->prepare("
        SELECT COUNT(*) as total_today 
        FROM appointments 
        WHERE DATE(scheduled_date) = CURDATE()
    ");
    
    if (!$stmt_today) {
        throw new Exception('Failed to prepare today appointments query');
    }
    
    $stmt_today->execute();
    $result_today = $stmt_today->get_result();
    $row_today = $result_today->fetch_assoc();
    $total_today = (int)$row_today['total_today'];
    $stmt_today->close();
    
    // ==========================================
    // QUERY 2: Total Checked-In Patients Today
    // ==========================================
    
    $stmt_checkedin = $conn->prepare("
        SELECT COUNT(*) as total_checkedin 
        FROM appointments 
        WHERE DATE(scheduled_date) = CURDATE() 
        AND status = 'checked_in'
    ");
    
    if (!$stmt_checkedin) {
        throw new Exception('Failed to prepare checked-in appointments query');
    }
    
    $stmt_checkedin->execute();
    $result_checkedin = $stmt_checkedin->get_result();
    $row_checkedin = $result_checkedin->fetch_assoc();
    $total_checkedin = (int)$row_checkedin['total_checkedin'];
    $stmt_checkedin->close();
    
    // ==========================================
    // QUERY 3: Total Completed Appointments Today
    // ==========================================
    
    $stmt_completed = $conn->prepare("
        SELECT COUNT(*) as total_completed 
        FROM appointments 
        WHERE DATE(scheduled_date) = CURDATE() 
        AND status = 'completed'
    ");
    
    if (!$stmt_completed) {
        throw new Exception('Failed to prepare completed appointments query');
    }
    
    $stmt_completed->execute();
    $result_completed = $stmt_completed->get_result();
    $row_completed = $result_completed->fetch_assoc();
    $total_completed = (int)$row_completed['total_completed'];
    $stmt_completed->close();
    
    // ==========================================
    // OPTIONAL: Additional Metrics for Enhanced Summary
    // ==========================================
    
    // Get pending appointments (confirmed but not checked in)
    $stmt_pending = $conn->prepare("
        SELECT COUNT(*) as total_pending 
        FROM appointments 
        WHERE DATE(scheduled_date) = CURDATE() 
        AND status = 'confirmed'
    ");
    
    if ($stmt_pending) {
        $stmt_pending->execute();
        $result_pending = $stmt_pending->get_result();
        $row_pending = $result_pending->fetch_assoc();
        $total_pending = (int)$row_pending['total_pending'];
        $stmt_pending->close();
    } else {
        $total_pending = 0;
    }
    
    // Get cancelled appointments today
    $stmt_cancelled = $conn->prepare("
        SELECT COUNT(*) as total_cancelled 
        FROM appointments 
        WHERE DATE(scheduled_date) = CURDATE() 
        AND status = 'cancelled'
    ");
    
    if ($stmt_cancelled) {
        $stmt_cancelled->execute();
        $result_cancelled = $stmt_cancelled->get_result();
        $row_cancelled = $result_cancelled->fetch_assoc();
        $total_cancelled = (int)$row_cancelled['total_cancelled'];
        $stmt_cancelled->close();
    } else {
        $total_cancelled = 0;
    }
    
    // ==========================================
    // SUCCESS RESPONSE
    // ==========================================
    
    $summary_data = [
        'today' => $total_today,
        'checkedIn' => $total_checkedin,
        'completed' => $total_completed,
        'pending' => $total_pending,
        'cancelled' => $total_cancelled,
        'last_updated' => date('Y-m-d H:i:s'),
        'status' => 'success'
    ];
    
    // Output JSON response
    echo json_encode($summary_data);
    
} catch (mysqli_sql_exception $e) {
    // Handle MySQL specific errors
    error_log("Check-in Summary SQL Error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database query failed',
        'status' => 'error',
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Handle general errors
    error_log("Check-in Summary Error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database query failed',
        'status' => 'error',
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
} finally {
    // Close database connection if still open
    if (isset($conn) && $conn && !$conn->connect_error) {
        $conn->close();
    }
}

?>