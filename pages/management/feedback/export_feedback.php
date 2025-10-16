<?php
/**
 * Feedback Export Handler
 * CSV and detailed report export with role-based access control
 * WBHSMS CHO Koronadal
 */

// Include employee session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

// Authentication check
if (!isset($_SESSION['employee_id']) || empty($_SESSION['employee_id'])) {
    header('Location: ../auth/employee_login.php');
    exit();
}

// Role-based access control
$allowed_roles = ['Admin', 'Manager', 'Doctor', 'Nurse', 'DHO'];
$user_role = $_SESSION['role'] ?? '';

if (!in_array($user_role, $allowed_roles)) {
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to export feedback data.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}

// Database and backend services
require_once $root_path . '/config/db.php';
require_once $root_path . '/pages/management/backend/feedback/FeedbackDataService.php';
require_once $root_path . '/pages/management/backend/feedback/FeedbackHelper.php';

$feedbackDataService = new FeedbackDataService($conn, $pdo);

// Get export parameters
$format = $_GET['format'] ?? 'csv';
$filters = [
    'facility_id' => $_GET['facility_id'] ?? null,
    'service_category' => $_GET['service_category'] ?? null,
    'user_type' => $_GET['user_type'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null
];

// Remove empty filters
$active_filters = array_filter($filters, function($value) {
    return !empty($value);
});

// Role-based data restrictions
$is_dho = ($user_role === 'DHO');
$can_export_detailed = !$is_dho; // DHO cannot export detailed individual responses

try {
    // Get export data
    $export_data = $feedbackDataService->getExportData($active_filters);
    
    // Generate filename
    $timestamp = date('Y-m-d_H-i-s');
    $facility_filter = '';
    if (!empty($active_filters['facility_id'])) {
        // Get facility name for filename
        $facility_query = "SELECT name FROM facilities WHERE facility_id = ?";
        $facility_stmt = $conn->prepare($facility_query);
        $facility_stmt->bind_param("i", $active_filters['facility_id']);
        $facility_stmt->execute();
        $facility_result = $facility_stmt->get_result();
        $facility_row = $facility_result->fetch_assoc();
        $facility_filter = '_' . preg_replace('/[^a-zA-Z0-9]/', '', $facility_row['name'] ?? 'facility');
    }
    
    switch ($format) {
        case 'csv':
            exportCSV($export_data, $timestamp, $facility_filter, $is_dho);
            break;
            
        case 'detailed':
            if (!$can_export_detailed) {
                throw new Exception('Detailed reports are not available for your access level.');
            }
            exportDetailedReport($export_data, $active_filters, $timestamp, $facility_filter);
            break;
            
        default:
            throw new Exception('Invalid export format specified.');
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Export failed: ' . $e->getMessage());
    header('Location: index.php?' . http_build_query($_GET));
    exit();
}

/**
 * Export data as CSV
 */
function exportCSV($data, $timestamp, $facility_filter, $is_dho) {
    $filename = "feedback_export{$facility_filter}_{$timestamp}.csv";
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 handling in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($is_dho) {
        // DHO gets aggregated data only
        $headers = [
            'Facility', 'Service Category', 'Submission Date', 'Overall Rating', 
            'Question Type', 'Average Rating', 'Response Count'
        ];
        fputcsv($output, $headers);
        
        // Group data for aggregation
        $aggregated = [];
        foreach ($data as $row) {
            $key = $row['facility_name'] . '|' . $row['service_category'] . '|' . 
                   date('Y-m-d', strtotime($row['submitted_at'])) . '|' . $row['question_type'];
            
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'facility' => $row['facility_name'],
                    'service_category' => $row['service_category'],
                    'date' => date('Y-m-d', strtotime($row['submitted_at'])),
                    'overall_rating' => $row['overall_rating'],
                    'question_type' => $row['question_type'],
                    'ratings' => [],
                    'count' => 0
                ];
            }
            
            if (!empty($row['answer_rating'])) {
                $aggregated[$key]['ratings'][] = floatval($row['answer_rating']);
            }
            $aggregated[$key]['count']++;
        }
        
        foreach ($aggregated as $agg_row) {
            $avg_rating = !empty($agg_row['ratings']) ? 
                         array_sum($agg_row['ratings']) / count($agg_row['ratings']) : 0;
            
            fputcsv($output, [
                $agg_row['facility'],
                $agg_row['service_category'] ?? 'General',
                $agg_row['date'],
                number_format($agg_row['overall_rating'], 1),
                $agg_row['question_type'],
                number_format($avg_rating, 1),
                $agg_row['count']
            ]);
        }
        
    } else {
        // Full data for non-DHO users
        $headers = [
            'Submission ID', 'Date Submitted', 'Respondent Name', 'User Type',
            'Facility', 'Service Category', 'Overall Rating', 'Question',
            'Question Type', 'Answer', 'Answer Rating', 'Visit Date'
        ];
        fputcsv($output, $headers);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['submission_id'] ?? '',
                $row['submitted_at'] ?? '',
                $row['respondent_name'] ?? 'Anonymous',
                $row['user_type'] ?? '',
                $row['facility_name'] ?? '',
                $row['service_category'] ?? 'General',
                $row['overall_rating'] ?? '',
                $row['question_text'] ?? '',
                $row['question_type'] ?? '',
                $row['selected_choice'] ?? $row['answer_text'] ?? '',
                $row['answer_rating'] ?? '',
                $row['visit_date'] ?? ''
            ]);
        }
    }
    
    fclose($output);
}

/**
 * Export detailed HTML report
 */
function exportDetailedReport($data, $filters, $timestamp, $facility_filter) {
    $filename = "feedback_detailed_report{$facility_filter}_{$timestamp}.html";
    
    // Set headers for HTML download
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Generate filter summary
    $filter_summary = [];
    if (!empty($filters['facility_id'])) {
        $facility_query = "SELECT name FROM facilities WHERE facility_id = ?";
        $facility_stmt = $GLOBALS['conn']->prepare($facility_query);
        $facility_stmt->bind_param("i", $filters['facility_id']);
        $facility_stmt->execute();
        $facility_result = $facility_stmt->get_result();
        $facility_row = $facility_result->fetch_assoc();
        $filter_summary[] = 'Facility: ' . ($facility_row['name'] ?? 'Unknown');
    }
    if (!empty($filters['service_category'])) {
        $filter_summary[] = 'Service: ' . $filters['service_category'];
    }
    if (!empty($filters['user_type'])) {
        $filter_summary[] = 'User Type: ' . $filters['user_type'];
    }
    if (!empty($filters['date_from'])) {
        $filter_summary[] = 'From: ' . date('F j, Y', strtotime($filters['date_from']));
    }
    if (!empty($filters['date_to'])) {
        $filter_summary[] = 'To: ' . date('F j, Y', strtotime($filters['date_to']));
    }
    
    // Generate statistics
    $stats = FeedbackHelper::generateStatsSummary($data);
    
    // Output HTML report
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Detailed Feedback Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .report-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #667eea; padding-bottom: 20px; }
        .report-title { color: #2d3748; font-size: 2em; margin-bottom: 10px; }
        .report-subtitle { color: #718096; }
        .filters { background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 1.8em; font-weight: bold; color: #4a5568; }
        .stat-label { color: #718096; font-size: 0.9em; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        .data-table th { background: #667eea; color: white; padding: 12px 8px; text-align: left; }
        .data-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .data-table tr:nth-child(even) { background: #f8f9fa; }
        .rating-stars { color: #f6e05e; }
        .footer { margin-top: 40px; text-align: center; color: #718096; font-size: 0.8em; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <div class="report-header">
        <h1 class="report-title">Patient Feedback Detailed Report</h1>
        <p class="report-subtitle">WBHSMS CHO Koronadal - Generated on ' . date('F j, Y g:i A') . '</p>
    </div>';
    
    if (!empty($filter_summary)) {
        echo '<div class="filters">
            <strong>Applied Filters:</strong> ' . implode(' | ', $filter_summary) . '
        </div>';
    }
    
    echo '<div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number">' . $stats['total_responses'] . '</div>
            <div class="stat-label">Total Responses</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">' . number_format($stats['average_rating'], 1) . '</div>
            <div class="stat-label">Average Rating</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">' . $stats['satisfaction_rate'] . '%</div>
            <div class="stat-label">Satisfaction Rate</div>
        </div>
    </div>';
    
    if (!empty($data)) {
        echo '<table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Respondent</th>
                    <th>Type</th>
                    <th>Facility</th>
                    <th>Service</th>
                    <th>Question</th>
                    <th>Answer</th>
                    <th>Rating</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($data as $row) {
            $rating_display = '';
            if (!empty($row['answer_rating'])) {
                $rating = floatval($row['answer_rating']);
                $stars = str_repeat('★', floor($rating)) . str_repeat('☆', 5 - ceil($rating));
                $rating_display = '<span class="rating-stars">' . $stars . '</span> ' . number_format($rating, 1);
            }
            
            echo '<tr>
                <td>' . date('M j, Y', strtotime($row['submitted_at'])) . '</td>
                <td>' . htmlspecialchars($row['respondent_name'] ?? 'Anonymous') . '</td>
                <td>' . htmlspecialchars($row['user_type'] ?? '') . '</td>
                <td>' . htmlspecialchars($row['facility_name'] ?? '') . '</td>
                <td>' . htmlspecialchars($row['service_category'] ?? 'General') . '</td>
                <td>' . htmlspecialchars($row['question_text'] ?? '') . '</td>
                <td>' . htmlspecialchars($row['selected_choice'] ?? $row['answer_text'] ?? '') . '</td>
                <td>' . $rating_display . '</td>
            </tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<p style="text-align: center; color: #718096; padding: 40px;">No feedback data available for the selected criteria.</p>';
    }
    
    echo '<div class="footer">
        <p>Report generated by WBHSMS Feedback System | Total records: ' . count($data) . '</p>
    </div>
</body>
</html>';
}
?>