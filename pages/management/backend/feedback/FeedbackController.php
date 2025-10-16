<?php
/**
 * Feedback Controller - Core Backend Logic for Patient Satisfaction & Feedback System
 * Pure PHP and MySQL implementation for WBHSMS CHO Koronadal
 * 
 * Tables: feedback_questions, feedback_question_choices, feedback_answers, visits, facilities, patients, employees
 */

class FeedbackController {
    private $conn;
    private $pdo;
    
    public function __construct($connection = null, $pdoConnection = null) {
        // Use provided connections or include default config
        if ($connection) {
            $this->conn = $connection;
        } else {
            require_once __DIR__ . '/../../../../config/db.php';
            $this->conn = $conn ?? null;
        }
        
        if ($pdoConnection) {
            $this->pdo = $pdoConnection;
        } else {
            $this->pdo = $pdo ?? null;
        }
    }
    
    /**
     * Fetch active feedback questions for a given role
     * @param string $role - 'Patient', 'BHW', or 'Employee'
     * @param string $service_type - Optional service filter
     * @return array Questions with choices
     */
    public function getActiveFeedbackQuestions($role = 'Patient', $service_type = null) {
        try {
            $sql = "SELECT fq.question_id, fq.question_text, fq.question_type, fq.role_target, 
                           fq.service_category, fq.is_required, fq.display_order,
                           fqc.choice_id, fqc.choice_text, fqc.choice_value, fqc.choice_order
                    FROM feedback_questions fq
                    LEFT JOIN feedback_question_choices fqc ON fq.question_id = fqc.question_id
                    WHERE fq.is_active = 1 
                    AND (fq.role_target = ? OR fq.role_target = 'All')";
            
            $params = [$role];
            $types = "s";
            
            if ($service_type) {
                $sql .= " AND (fq.service_category = ? OR fq.service_category IS NULL OR fq.service_category = 'General')";
                $params[] = $service_type;
                $types .= "s";
            }
            
            $sql .= " ORDER BY fq.display_order ASC, fq.question_id ASC, fqc.choice_order ASC";
            
            if ($this->conn) {
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                return $this->formatQuestionsWithChoices($result);
            }
            
            return [];
            
        } catch (Exception $e) {
            error_log("Error fetching feedback questions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Format questions result into structured array with choices
     */
    private function formatQuestionsWithChoices($result) {
        $questions = [];
        $currentQuestionId = null;
        
        while ($row = $result->fetch_assoc()) {
            if ($row['question_id'] !== $currentQuestionId) {
                $currentQuestionId = $row['question_id'];
                $questions[$currentQuestionId] = [
                    'question_id' => $row['question_id'],
                    'question_text' => $row['question_text'],
                    'question_type' => $row['question_type'],
                    'role_target' => $row['role_target'],
                    'service_category' => $row['service_category'],
                    'is_required' => $row['is_required'],
                    'display_order' => $row['display_order'],
                    'choices' => []
                ];
            }
            
            // Add choice if it exists
            if ($row['choice_id']) {
                $questions[$currentQuestionId]['choices'][] = [
                    'choice_id' => $row['choice_id'],
                    'choice_text' => $row['choice_text'],
                    'choice_value' => $row['choice_value'],
                    'choice_order' => $row['choice_order']
                ];
            }
        }
        
        return array_values($questions); // Return as indexed array
    }
    
    /**
     * Submit feedback - validates no duplicates per visit/facility/user
     * @param array $feedbackData - Feedback submission data
     * @return array Result with success status and message
     */
    public function submitFeedback($feedbackData) {
        try {
            // Validate required fields
            $required = ['user_id', 'user_type', 'facility_id', 'answers'];
            foreach ($required as $field) {
                if (empty($feedbackData[$field])) {
                    return ['success' => false, 'message' => "Missing required field: {$field}"];
                }
            }
            
            // Check for duplicate feedback
            if ($this->isDuplicateFeedback($feedbackData)) {
                return ['success' => false, 'message' => 'Feedback already submitted for this visit/facility'];
            }
            
            // Start transaction
            $this->conn->begin_transaction();
            
            try {
                $submissionId = $this->createFeedbackSubmission($feedbackData);
                
                if (!$submissionId) {
                    throw new Exception("Failed to create feedback submission");
                }
                
                // Insert individual answers
                $answersInserted = $this->insertFeedbackAnswers($submissionId, $feedbackData['answers']);
                
                if (!$answersInserted) {
                    throw new Exception("Failed to insert feedback answers");
                }
                
                $this->conn->commit();
                
                return [
                    'success' => true, 
                    'message' => 'Feedback submitted successfully',
                    'submission_id' => $submissionId
                ];
                
            } catch (Exception $e) {
                $this->conn->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Error submitting feedback: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error submitting feedback: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check for duplicate feedback submission
     */
    private function isDuplicateFeedback($feedbackData) {
        try {
            $sql = "SELECT COUNT(*) as count FROM feedback_answers fa
                    WHERE fa.user_id = ? AND fa.user_type = ? AND fa.facility_id = ?";
            
            $params = [$feedbackData['user_id'], $feedbackData['user_type'], $feedbackData['facility_id']];
            $types = "ssi";
            
            // Add visit_id check if provided
            if (!empty($feedbackData['visit_id'])) {
                $sql .= " AND fa.visit_id = ?";
                $params[] = $feedbackData['visit_id'];
                $types .= "i";
            }
            
            // Check within last 24 hours to prevent spam
            $sql .= " AND fa.submitted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return $row['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Error checking duplicate feedback: " . $e->getMessage());
            return false; // Allow submission if check fails
        }
    }
    
    /**
     * Create main feedback submission record
     */
    private function createFeedbackSubmission($feedbackData) {
        try {
            $sql = "INSERT INTO feedback_submissions 
                    (user_id, user_type, facility_id, visit_id, service_category, 
                     overall_rating, submitted_at, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $visitId = $feedbackData['visit_id'] ?? null;
            $serviceCategory = $feedbackData['service_category'] ?? 'General';
            $overallRating = $feedbackData['overall_rating'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssiisis", 
                $feedbackData['user_id'],
                $feedbackData['user_type'], 
                $feedbackData['facility_id'],
                $visitId,
                $serviceCategory,
                $overallRating,
                $ipAddress
            );
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error creating feedback submission: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert individual feedback answers
     */
    private function insertFeedbackAnswers($submissionId, $answers) {
        try {
            $sql = "INSERT INTO feedback_answers 
                    (submission_id, question_id, choice_id, answer_text, answer_rating, user_id, user_type, facility_id, visit_id, submitted_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($answers as $answer) {
                $choiceId = $answer['choice_id'] ?? null;
                $answerText = $answer['answer_text'] ?? null;
                $answerRating = $answer['answer_rating'] ?? null;
                $userId = $answer['user_id'] ?? null;
                $userType = $answer['user_type'] ?? null;
                $facilityId = $answer['facility_id'] ?? null;
                $visitId = $answer['visit_id'] ?? null;
                
                $stmt->bind_param("iiisisiii", 
                    $submissionId,
                    $answer['question_id'],
                    $choiceId,
                    $answerText,
                    $answerRating,
                    $userId,
                    $userType,
                    $facilityId,
                    $visitId
                );
                
                if (!$stmt->execute()) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error inserting feedback answers: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get feedback analytics summary
     * @param array $filters - Optional filters (facility_id, service_category, date_range, role)
     * @return array Analytics data
     */
    public function getFeedbackAnalytics($filters = []) {
        try {
            $baseQuery = "
                SELECT 
                    f.name as facility_name,
                    fa.facility_id,
                    fa.user_type,
                    fs.service_category,
                    COUNT(DISTINCT fs.submission_id) as total_submissions,
                    AVG(fs.overall_rating) as avg_overall_rating,
                    AVG(fa.answer_rating) as avg_question_rating,
                    COUNT(fa.answer_id) as total_answers,
                    DATE(fa.submitted_at) as submission_date
                FROM feedback_answers fa
                LEFT JOIN feedback_submissions fs ON fa.submission_id = fs.submission_id
                LEFT JOIN facilities f ON fa.facility_id = f.facility_id
                WHERE 1=1
            ";
            
            $params = [];
            $types = "";
            
            // Apply filters
            if (!empty($filters['facility_id'])) {
                $baseQuery .= " AND fa.facility_id = ?";
                $params[] = $filters['facility_id'];
                $types .= "i";
            }
            
            if (!empty($filters['service_category'])) {
                $baseQuery .= " AND fs.service_category = ?";
                $params[] = $filters['service_category'];
                $types .= "s";
            }
            
            if (!empty($filters['user_type'])) {
                $baseQuery .= " AND fa.user_type = ?";
                $params[] = $filters['user_type'];
                $types .= "s";
            }
            
            if (!empty($filters['date_from'])) {
                $baseQuery .= " AND DATE(fa.submitted_at) >= ?";
                $params[] = $filters['date_from'];
                $types .= "s";
            }
            
            if (!empty($filters['date_to'])) {
                $baseQuery .= " AND DATE(fa.submitted_at) <= ?";
                $params[] = $filters['date_to'];
                $types .= "s";
            }
            
            $baseQuery .= " GROUP BY fa.facility_id, fa.user_type, fs.service_category, DATE(fa.submitted_at)
                           ORDER BY fa.submitted_at DESC";
            
            $stmt = $this->conn->prepare($baseQuery);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $analytics = [];
            while ($row = $result->fetch_assoc()) {
                $analytics[] = $row;
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            error_log("Error getting feedback analytics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get question-specific analytics
     */
    public function getQuestionAnalytics($questionId, $filters = []) {
        try {
            $sql = "
                SELECT 
                    fq.question_text,
                    fqc.choice_text,
                    fqc.choice_value,
                    COUNT(fa.answer_id) as response_count,
                    AVG(fa.answer_rating) as avg_rating,
                    fa.user_type,
                    f.name as facility_name
                FROM feedback_answers fa
                LEFT JOIN feedback_questions fq ON fa.question_id = fq.question_id
                LEFT JOIN feedback_question_choices fqc ON fa.choice_id = fqc.choice_id
                LEFT JOIN facilities f ON fa.facility_id = f.facility_id
                WHERE fa.question_id = ?
            ";
            
            $params = [$questionId];
            $types = "i";
            
            // Apply filters
            if (!empty($filters['facility_id'])) {
                $sql .= " AND fa.facility_id = ?";
                $params[] = $filters['facility_id'];
                $types .= "i";
            }
            
            if (!empty($filters['user_type'])) {
                $sql .= " AND fa.user_type = ?";
                $params[] = $filters['user_type'];
                $types .= "s";
            }
            
            $sql .= " GROUP BY fa.choice_id, fa.user_type, fa.facility_id
                     ORDER BY response_count DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $analytics = [];
            while ($row = $result->fetch_assoc()) {
                $analytics[] = $row;
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            error_log("Error getting question analytics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get facilities list for feedback
     */
    public function getFacilities() {
        try {
            $sql = "SELECT facility_id, name, type, address, is_active 
                    FROM facilities 
                    WHERE is_active = 1 
                    ORDER BY name ASC";
            
            $result = $this->conn->query($sql);
            
            $facilities = [];
            while ($row = $result->fetch_assoc()) {
                $facilities[] = $row;
            }
            
            return $facilities;
            
        } catch (Exception $e) {
            error_log("Error getting facilities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate user permissions for feedback management
     */
    public function validateUserPermissions($userId, $userType, $action = 'view') {
        // Define permission matrix
        $permissions = [
            'Admin' => ['view', 'create', 'edit', 'delete', 'analytics'],
            'Manager' => ['view', 'create', 'edit', 'analytics'],
            'BHW' => ['view', 'create'],
            'Patient' => ['create']
        ];
        
        if (!isset($permissions[$userType])) {
            return false;
        }
        
        return in_array($action, $permissions[$userType]);
    }
}
?>
