<?php
/**
 * Feedback Submission Handler
 * Processes patient feedback submissions with validation
 * WBHSMS CHO Koronadal
 */

header('Content-Type: application/json');

// Include required files
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';
require_once $root_path . '/config/db.php';
require_once $root_path . '/pages/management/backend/feedback/FeedbackController.php';
require_once $root_path . '/pages/management/backend/feedback/FeedbackValidationService.php';

// Authentication check
if (!is_patient_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$patient_id = $_SESSION['patient_id'];
$visit_id = $_POST['visit_id'] ?? null;
$facility_id = $_POST['facility_id'] ?? null;
$mode = $_POST['mode'] ?? 'new'; // new or edit
$submission_id = $_POST['submission_id'] ?? null;
$overall_rating = $_POST['overall_rating'] ?? null;
$answers = $_POST['answers'] ?? [];

try {
    // Validate basic inputs
    if (!$visit_id || !$facility_id) {
        throw new Exception('Missing required visit or facility information');
    }
    
    // Verify visit belongs to patient
    $visit_check = $conn->prepare("SELECT visit_id, visit_date, purpose FROM visits WHERE visit_id = ? AND patient_id = ?");
    $visit_check->bind_param("ii", $visit_id, $patient_id);
    $visit_check->execute();
    $visit_result = $visit_check->get_result();
    $visit = $visit_result->fetch_assoc();
    
    if (!$visit) {
        throw new Exception('Visit not found or access denied');
    }
    
    // Process answers into the format expected by FeedbackController
    $processed_answers = [];
    
    foreach ($answers as $question_id => $answer_data) {
        $processed_answer = [
            'question_id' => intval($question_id),
            'user_id' => $patient_id,
            'user_type' => 'Patient',
            'facility_id' => intval($facility_id),
            'visit_id' => intval($visit_id)
        ];
        
        // Handle different answer types
        if (!empty($answer_data['rating'])) {
            $processed_answer['answer_rating'] = floatval($answer_data['rating']);
        }
        
        if (!empty($answer_data['choice_id'])) {
            $processed_answer['choice_id'] = intval($answer_data['choice_id']);
        }
        
        if (!empty($answer_data['choice_value'])) {
            // Handle yes/no questions - find the appropriate choice_id
            $choice_query = "SELECT choice_id FROM feedback_question_choices WHERE question_id = ? AND choice_value = ?";
            $choice_stmt = $conn->prepare($choice_query);
            $choice_stmt->bind_param("is", $question_id, $answer_data['choice_value']);
            $choice_stmt->execute();
            $choice_result = $choice_stmt->get_result();
            $choice_row = $choice_result->fetch_assoc();
            
            if ($choice_row) {
                $processed_answer['choice_id'] = intval($choice_row['choice_id']);
            }
        }
        
        if (!empty($answer_data['text'])) {
            $processed_answer['answer_text'] = trim($answer_data['text']);
        }
        
        // Only add answers that have actual content
        if (!empty($processed_answer['answer_rating']) || 
            !empty($processed_answer['choice_id']) || 
            !empty($processed_answer['answer_text'])) {
            $processed_answers[] = $processed_answer;
        }
    }
    
    // Prepare submission data
    $submission_data = [
        'user_id' => $patient_id,
        'user_type' => 'Patient',
        'facility_id' => intval($facility_id),
        'visit_id' => intval($visit_id),
        'service_category' => 'General', // Could be enhanced to detect from visit
        'answers' => $processed_answers
    ];
    
    if ($overall_rating) {
        $submission_data['overall_rating'] = floatval($overall_rating);
    }
    
    // Validate submission data
    $validation_errors = FeedbackValidationService::validateFeedbackSubmission($submission_data);
    if (!empty($validation_errors)) {
        throw new Exception('Validation failed: ' . implode(', ', $validation_errors));
    }
    
    // Initialize feedback controller
    $feedbackController = new FeedbackController($conn, $pdo);
    
    if ($mode === 'edit' && $submission_id) {
        // Handle edit mode - delete existing answers and create new ones
        $conn->begin_transaction();
        
        try {
            // Verify submission belongs to patient
            $submission_check = $conn->prepare("SELECT submission_id FROM feedback_submissions WHERE submission_id = ? AND user_id = ? AND user_type = 'Patient'");
            $submission_check->bind_param("ii", $submission_id, $patient_id);
            $submission_check->execute();
            $submission_result = $submission_check->get_result();
            
            if ($submission_result->num_rows === 0) {
                throw new Exception('Feedback submission not found or access denied');
            }
            
            // Delete existing answers
            $delete_answers = $conn->prepare("DELETE FROM feedback_answers WHERE submission_id = ?");
            $delete_answers->bind_param("i", $submission_id);
            $delete_answers->execute();
            
            // Update submission metadata
            $update_submission = $conn->prepare("UPDATE feedback_submissions SET overall_rating = ?, submitted_at = NOW() WHERE submission_id = ?");
            $update_submission->bind_param("di", $submission_data['overall_rating'], $submission_id);
            $update_submission->execute();
            
            // Insert new answers
            $insert_answer = $conn->prepare("
                INSERT INTO feedback_answers 
                (submission_id, question_id, choice_id, answer_text, answer_rating, user_id, user_type, facility_id, visit_id, submitted_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            foreach ($processed_answers as $answer) {
                $insert_answer->bind_param("iiisdssii",
                    $submission_id,
                    $answer['question_id'],
                    $answer['choice_id'],
                    $answer['answer_text'],
                    $answer['answer_rating'],
                    $answer['user_id'],
                    $answer['user_type'],
                    $answer['facility_id'],
                    $answer['visit_id']
                );
                $insert_answer->execute();
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Feedback updated successfully',
                'submission_id' => $submission_id
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } else {
        // Handle new submission mode
        $result = $feedbackController->submitFeedback($submission_data);
        
        if ($result['success']) {
            // Log successful submission
            error_log("Feedback submitted successfully - Patient: $patient_id, Visit: $visit_id, Submission: " . ($result['submission_id'] ?? 'unknown'));
            
            echo json_encode([
                'success' => true,
                'message' => 'Feedback submitted successfully',
                'submission_id' => $result['submission_id'] ?? null
            ]);
        } else {
            throw new Exception($result['message'] ?? 'Unknown error occurred');
        }
    }
    
} catch (Exception $e) {
    error_log("Feedback submission error: " . $e->getMessage() . " - Patient: $patient_id, Visit: " . ($visit_id ?? 'unknown'));
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>