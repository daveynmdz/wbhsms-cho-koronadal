<?php
/**
 * Feedback Backend API - Main Entry Point
 * Patient Satisfaction & Feedback System for WBHSMS CHO Koronadal
 * 
 * Pure PHP and MySQL implementation
 * Handles all feedback-related API requests
 */

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Include database and session configuration
    $root_path = dirname(dirname(dirname(dirname(__DIR__))));
    require_once $root_path . '/config/db.php';
    require_once $root_path . '/config/session/employee_session.php';
    require_once __DIR__ . '/FeedbackController.php';
    
    // Initialize feedback controller
    $feedbackController = new FeedbackController($conn, $pdo);
    
    // Get request method and action
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // Route requests based on action
    switch ($action) {
        case 'get_questions':
            handleGetQuestions($feedbackController);
            break;
            
        case 'submit_feedback':
            handleSubmitFeedback($feedbackController);
            break;
            
        case 'get_analytics':
            handleGetAnalytics($feedbackController);
            break;
            
        case 'get_question_analytics':
            handleGetQuestionAnalytics($feedbackController);
            break;
            
        case 'get_facilities':
            handleGetFacilities($feedbackController);
            break;
            
        case 'validate_permissions':
            handleValidatePermissions($feedbackController);
            break;
            
        default:
            sendResponse(400, false, 'Invalid action specified');
            break;
    }
    
} catch (Exception $e) {
    error_log("Feedback API Error: " . $e->getMessage());
    sendResponse(500, false, 'Internal server error: ' . $e->getMessage());
}

/**
 * Handle get questions request
 */
function handleGetQuestions($controller) {
    try {
        $role = $_GET['role'] ?? 'Patient';
        $service_type = $_GET['service_type'] ?? null;
        
        $questions = $controller->getActiveFeedbackQuestions($role, $service_type);
        
        sendResponse(200, true, 'Questions retrieved successfully', [
            'questions' => $questions,
            'role' => $role,
            'service_type' => $service_type
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, false, 'Error retrieving questions: ' . $e->getMessage());
    }
}

/**
 * Handle feedback submission
 */
function handleSubmitFeedback($controller) {
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendResponse(405, false, 'Method not allowed');
            return;
        }
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback to POST data
            $input = $_POST;
        }
        
        // Validate required fields
        $required = ['user_id', 'user_type', 'facility_id', 'answers'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                sendResponse(400, false, "Missing required field: {$field}");
                return;
            }
        }
        
        // Ensure answers is an array
        if (is_string($input['answers'])) {
            $input['answers'] = json_decode($input['answers'], true);
        }
        
        if (!is_array($input['answers'])) {
            sendResponse(400, false, 'Invalid answers format');
            return;
        }
        
        $result = $controller->submitFeedback($input);
        
        if ($result['success']) {
            sendResponse(200, true, $result['message'], [
                'submission_id' => $result['submission_id'] ?? null
            ]);
        } else {
            sendResponse(400, false, $result['message']);
        }
        
    } catch (Exception $e) {
        sendResponse(500, false, 'Error submitting feedback: ' . $e->getMessage());
    }
}

/**
 * Handle analytics request
 */
function handleGetAnalytics($controller) {
    try {
        // Check employee permissions
        if (!is_employee_logged_in()) {
            sendResponse(401, false, 'Authentication required');
            return;
        }
        
        $employee = get_employee_session('employee_data');
        if (!$employee || !$controller->validateUserPermissions($employee['employee_id'], $employee['role'], 'analytics')) {
            sendResponse(403, false, 'Insufficient permissions');
            return;
        }
        
        // Get filters
        $filters = [
            'facility_id' => $_GET['facility_id'] ?? null,
            'service_category' => $_GET['service_category'] ?? null,
            'user_type' => $_GET['user_type'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null
        ];
        
        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return !empty($value);
        });
        
        $analytics = $controller->getFeedbackAnalytics($filters);
        
        sendResponse(200, true, 'Analytics retrieved successfully', [
            'analytics' => $analytics,
            'filters' => $filters
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, false, 'Error retrieving analytics: ' . $e->getMessage());
    }
}

/**
 * Handle question-specific analytics
 */
function handleGetQuestionAnalytics($controller) {
    try {
        // Check employee permissions
        if (!is_employee_logged_in()) {
            sendResponse(401, false, 'Authentication required');
            return;
        }
        
        $employee = get_employee_session('employee_data');
        if (!$employee || !$controller->validateUserPermissions($employee['employee_id'], $employee['role'], 'analytics')) {
            sendResponse(403, false, 'Insufficient permissions');
            return;
        }
        
        $questionId = $_GET['question_id'] ?? null;
        if (!$questionId) {
            sendResponse(400, false, 'Question ID is required');
            return;
        }
        
        // Get filters
        $filters = [
            'facility_id' => $_GET['facility_id'] ?? null,
            'user_type' => $_GET['user_type'] ?? null
        ];
        
        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return !empty($value);
        });
        
        $analytics = $controller->getQuestionAnalytics($questionId, $filters);
        
        sendResponse(200, true, 'Question analytics retrieved successfully', [
            'analytics' => $analytics,
            'question_id' => $questionId,
            'filters' => $filters
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, false, 'Error retrieving question analytics: ' . $e->getMessage());
    }
}

/**
 * Handle get facilities request
 */
function handleGetFacilities($controller) {
    try {
        $facilities = $controller->getFacilities();
        
        sendResponse(200, true, 'Facilities retrieved successfully', [
            'facilities' => $facilities
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, false, 'Error retrieving facilities: ' . $e->getMessage());
    }
}

/**
 * Handle permission validation
 */
function handleValidatePermissions($controller) {
    try {
        $userId = $_GET['user_id'] ?? null;
        $userType = $_GET['user_type'] ?? null;
        $action = $_GET['permission_action'] ?? 'view';
        
        if (!$userId || !$userType) {
            sendResponse(400, false, 'User ID and type are required');
            return;
        }
        
        $hasPermission = $controller->validateUserPermissions($userId, $userType, $action);
        
        sendResponse(200, true, 'Permission validated', [
            'has_permission' => $hasPermission,
            'user_id' => $userId,
            'user_type' => $userType,
            'action' => $action
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, false, 'Error validating permissions: ' . $e->getMessage());
    }
}

/**
 * Send JSON response
 */
function sendResponse($statusCode, $success, $message, $data = null) {
    http_response_code($statusCode);
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}
?>
