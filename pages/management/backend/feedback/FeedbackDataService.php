<?php
/**
 * Feedback Data Service - Database Query Helper
 * Advanced queries and data operations for Patient Satisfaction & Feedback System
 * WBHSMS CHO Koronadal
 */

class FeedbackDataService {
    private $conn;
    private $pdo;
    
    public function __construct($connection = null, $pdoConnection = null) {
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
     * Get feedback analytics summary (alias for compatibility)
     * @param array $filters - Optional filters (facility_id, service_category, date_range, role)
     * @return array Analytics data
     */
    public function getFeedbackAnalytics($filters = []) {
        // This is an alias to getExportData for compatibility with dashboard
        return $this->getExportData($filters);
    }
    
    /**
     * Get comprehensive feedback summary by facility
     */
    public function getFacilitySummary($facilityId, $dateFrom = null, $dateTo = null) {
        try {
            $sql = "
                SELECT 
                    f.name as facility_name,
                    f.type as facility_type,
                    COUNT(DISTINCT fs.submission_id) as total_submissions,
                    AVG(fs.overall_rating) as avg_overall_rating,
                    COUNT(DISTINCT fa.user_id) as unique_respondents,
                    
                    -- Rating distribution
                    SUM(CASE WHEN fs.overall_rating >= 4.5 THEN 1 ELSE 0 END) as excellent_count,
                    SUM(CASE WHEN fs.overall_rating >= 3.5 AND fs.overall_rating < 4.5 THEN 1 ELSE 0 END) as good_count,
                    SUM(CASE WHEN fs.overall_rating >= 2.5 AND fs.overall_rating < 3.5 THEN 1 ELSE 0 END) as fair_count,
                    SUM(CASE WHEN fs.overall_rating < 2.5 THEN 1 ELSE 0 END) as poor_count,
                    
                    -- Response by user type
                    SUM(CASE WHEN fa.user_type = 'Patient' THEN 1 ELSE 0 END) as patient_responses,
                    SUM(CASE WHEN fa.user_type = 'BHW' THEN 1 ELSE 0 END) as bhw_responses,
                    SUM(CASE WHEN fa.user_type = 'Employee' THEN 1 ELSE 0 END) as employee_responses,
                    
                    -- Service categories
                    fs.service_category,
                    COUNT(CASE WHEN fs.service_category IS NOT NULL THEN 1 END) as service_responses
                    
                FROM facilities f
                LEFT JOIN feedback_submissions fs ON f.facility_id = fs.facility_id
                LEFT JOIN feedback_answers fa ON fs.submission_id = fa.submission_id
                WHERE f.facility_id = ?
            ";
            
            $params = [$facilityId];
            $types = "i";
            
            if ($dateFrom) {
                $sql .= " AND DATE(fs.submitted_at) >= ?";
                $params[] = $dateFrom;
                $types .= "s";
            }
            
            if ($dateTo) {
                $sql .= " AND DATE(fs.submitted_at) <= ?";
                $params[] = $dateTo;
                $types .= "s";
            }
            
            $sql .= " GROUP BY f.facility_id, fs.service_category ORDER BY fs.service_category";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting facility summary: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get trending feedback patterns over time
     */
    public function getTrendingData($facilityId = null, $months = 6) {
        try {
            $sql = "
                SELECT 
                    DATE_FORMAT(fs.submitted_at, '%Y-%m') as month_year,
                    COUNT(DISTINCT fs.submission_id) as submission_count,
                    AVG(fs.overall_rating) as avg_rating,
                    f.name as facility_name,
                    fs.service_category,
                    fa.user_type,
                    
                    -- Calculate month-over-month change
                    LAG(COUNT(DISTINCT fs.submission_id), 1) OVER (
                        PARTITION BY f.facility_id, fs.service_category 
                        ORDER BY DATE_FORMAT(fs.submitted_at, '%Y-%m')
                    ) as prev_month_submissions,
                    
                    LAG(AVG(fs.overall_rating), 1) OVER (
                        PARTITION BY f.facility_id, fs.service_category 
                        ORDER BY DATE_FORMAT(fs.submitted_at, '%Y-%m')
                    ) as prev_month_rating
                    
                FROM feedback_submissions fs
                JOIN feedback_answers fa ON fs.submission_id = fa.submission_id
                JOIN facilities f ON fs.facility_id = f.facility_id
                WHERE fs.submitted_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            ";
            
            $params = [$months];
            $types = "i";
            
            if ($facilityId) {
                $sql .= " AND f.facility_id = ?";
                $params[] = $facilityId;
                $types .= "i";
            }
            
            $sql .= " GROUP BY DATE_FORMAT(fs.submitted_at, '%Y-%m'), f.facility_id, fs.service_category, fa.user_type
                     ORDER BY month_year DESC, f.name, fs.service_category";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting trending data: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get detailed question performance analysis
     */
    public function getQuestionPerformance($facilityId = null, $serviceCategory = null) {
        try {
            $sql = "
                SELECT 
                    fq.question_id,
                    fq.question_text,
                    fq.question_type,
                    fq.service_category,
                    
                    -- Response statistics
                    COUNT(fa.answer_id) as total_responses,
                    AVG(CASE WHEN fa.answer_rating IS NOT NULL THEN fa.answer_rating END) as avg_rating,
                    
                    -- Choice distribution for multiple choice questions
                    fqc.choice_text,
                    fqc.choice_value,
                    COUNT(CASE WHEN fa.choice_id = fqc.choice_id THEN 1 END) as choice_count,
                    
                    -- Text response analysis
                    COUNT(CASE WHEN fa.answer_text IS NOT NULL AND fa.answer_text != '' THEN 1 END) as text_responses,
                    
                    -- Response rate by user type
                    COUNT(CASE WHEN fa.user_type = 'Patient' THEN 1 END) as patient_responses,
                    COUNT(CASE WHEN fa.user_type = 'BHW' THEN 1 END) as bhw_responses,
                    COUNT(CASE WHEN fa.user_type = 'Employee' THEN 1 END) as employee_responses
                    
                FROM feedback_questions fq
                LEFT JOIN feedback_question_choices fqc ON fq.question_id = fqc.question_id
                LEFT JOIN feedback_answers fa ON fq.question_id = fa.question_id
                WHERE fq.is_active = 1
            ";
            
            $params = [];
            $types = "";
            
            if ($facilityId) {
                $sql .= " AND fa.facility_id = ?";
                $params[] = $facilityId;
                $types .= "i";
            }
            
            if ($serviceCategory) {
                $sql .= " AND fq.service_category = ?";
                $params[] = $serviceCategory;
                $types .= "s";
            }
            
            $sql .= " GROUP BY fq.question_id, fqc.choice_id
                     ORDER BY fq.display_order ASC, fqc.choice_order ASC";
            
            $stmt = $this->conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $this->groupQuestionResults($result->fetch_all(MYSQLI_ASSOC));
            
        } catch (Exception $e) {
            error_log("Error getting question performance: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Group question results by question ID
     */
    private function groupQuestionResults($results) {
        $grouped = [];
        
        foreach ($results as $row) {
            $questionId = $row['question_id'];
            
            if (!isset($grouped[$questionId])) {
                $grouped[$questionId] = [
                    'question_id' => $row['question_id'],
                    'question_text' => $row['question_text'],
                    'question_type' => $row['question_type'],
                    'service_category' => $row['service_category'],
                    'total_responses' => $row['total_responses'],
                    'avg_rating' => $row['avg_rating'],
                    'text_responses' => $row['text_responses'],
                    'patient_responses' => $row['patient_responses'],
                    'bhw_responses' => $row['bhw_responses'],
                    'employee_responses' => $row['employee_responses'],
                    'choices' => []
                ];
            }
            
            // Add choice data if it exists
            if ($row['choice_text']) {
                $grouped[$questionId]['choices'][] = [
                    'choice_text' => $row['choice_text'],
                    'choice_value' => $row['choice_value'],
                    'choice_count' => $row['choice_count']
                ];
            }
        }
        
        return array_values($grouped);
    }
    
    /**
     * Get patient visit feedback correlation
     */
    public function getVisitFeedbackCorrelation($patientId = null) {
        try {
            $sql = "
                SELECT 
                    v.visit_id,
                    v.patient_id,
                    v.visit_date,
                    v.purpose,
                    v.status as visit_status,
                    
                    -- Patient information
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    p.contact_number,
                    
                    -- Visit details
                    e.first_name as doctor_first_name,
                    e.last_name as doctor_last_name,
                    
                    -- Feedback data
                    fs.submission_id,
                    fs.overall_rating,
                    fs.service_category as feedback_service,
                    fs.submitted_at as feedback_date,
                    
                    -- Time between visit and feedback
                    TIMESTAMPDIFF(HOUR, v.visit_date, fs.submitted_at) as feedback_delay_hours,
                    
                    -- Feedback summary
                    COUNT(fa.answer_id) as total_answers,
                    AVG(fa.answer_rating) as avg_question_rating
                    
                FROM visits v
                JOIN patients p ON v.patient_id = p.patient_id
                LEFT JOIN employees e ON v.doctor_id = e.employee_id
                LEFT JOIN feedback_submissions fs ON v.visit_id = fs.visit_id
                LEFT JOIN feedback_answers fa ON fs.submission_id = fa.submission_id
                WHERE v.status = 'Completed'
            ";
            
            $params = [];
            $types = "";
            
            if ($patientId) {
                $sql .= " AND v.patient_id = ?";
                $params[] = $patientId;
                $types .= "i";
            }
            
            $sql .= " GROUP BY v.visit_id, fs.submission_id
                     ORDER BY v.visit_date DESC";
            
            $stmt = $this->conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting visit feedback correlation: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get employee performance feedback
     */
    public function getEmployeeFeedback($employeeId = null, $role = null) {
        try {
            $sql = "
                SELECT 
                    e.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                    e.role,
                    e.department,
                    
                    -- Direct feedback about employee
                    COUNT(DISTINCT fa.answer_id) as feedback_mentions,
                    AVG(fa.answer_rating) as avg_employee_rating,
                    
                    -- Indirect feedback from their service area
                    fs.service_category,
                    AVG(fs.overall_rating) as avg_service_rating,
                    COUNT(DISTINCT fs.submission_id) as service_feedback_count,
                    
                    -- Time period analysis
                    DATE_FORMAT(fa.submitted_at, '%Y-%m') as feedback_month,
                    
                    -- Feedback sources
                    fa.user_type as feedback_from_type,
                    COUNT(CASE WHEN fa.user_type = 'Patient' THEN 1 END) as patient_feedback,
                    COUNT(CASE WHEN fa.user_type = 'BHW' THEN 1 END) as bhw_feedback
                    
                FROM employees e
                LEFT JOIN feedback_answers fa ON FIND_IN_SET(e.employee_id, fa.answer_text) > 0 
                    OR fa.answer_text LIKE CONCAT('%', e.first_name, '%', e.last_name, '%')
                LEFT JOIN feedback_submissions fs ON fa.submission_id = fs.submission_id
                WHERE e.is_active = 1
            ";
            
            $params = [];
            $types = "";
            
            if ($employeeId) {
                $sql .= " AND e.employee_id = ?";
                $params[] = $employeeId;
                $types .= "i";
            }
            
            if ($role) {
                $sql .= " AND e.role = ?";
                $params[] = $role;
                $types .= "s";
            }
            
            $sql .= " GROUP BY e.employee_id, fs.service_category, DATE_FORMAT(fa.submitted_at, '%Y-%m')
                     ORDER BY e.last_name, e.first_name, feedback_month DESC";
            
            $stmt = $this->conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting employee feedback: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get feedback export data for reports
     */
    public function getExportData($filters = []) {
        try {
            $sql = "
                SELECT 
                    -- Submission details
                    fs.submission_id,
                    fs.submitted_at,
                    fs.overall_rating,
                    fs.service_category,
                    
                    -- User details
                    fa.user_id,
                    fa.user_type,
                    CASE 
                        WHEN fa.user_type = 'Patient' THEN CONCAT(p.first_name, ' ', p.last_name)
                        WHEN fa.user_type = 'Employee' THEN CONCAT(e.first_name, ' ', e.last_name)
                        ELSE 'Anonymous'
                    END as respondent_name,
                    
                    -- Facility details
                    f.name as facility_name,
                    f.type as facility_type,
                    f.address as facility_address,
                    
                    -- Question and answer details
                    fq.question_text,
                    fq.question_type,
                    fqc.choice_text as selected_choice,
                    fa.answer_text,
                    fa.answer_rating,
                    
                    -- Visit details (if applicable)
                    v.visit_date,
                    v.purpose as visit_purpose,
                    v.status as visit_status
                    
                FROM feedback_answers fa
                JOIN feedback_submissions fs ON fa.submission_id = fs.submission_id
                JOIN facilities f ON fa.facility_id = f.facility_id
                JOIN feedback_questions fq ON fa.question_id = fq.question_id
                LEFT JOIN feedback_question_choices fqc ON fa.choice_id = fqc.choice_id
                LEFT JOIN patients p ON fa.user_id = p.patient_id AND fa.user_type = 'Patient'
                LEFT JOIN employees e ON fa.user_id = e.employee_id AND fa.user_type = 'Employee'
                LEFT JOIN visits v ON fa.visit_id = v.visit_id
                WHERE 1=1
            ";
            
            $params = [];
            $types = "";
            
            // Apply filters
            if (!empty($filters['facility_id'])) {
                $sql .= " AND fa.facility_id = ?";
                $params[] = $filters['facility_id'];
                $types .= "i";
            }
            
            if (!empty($filters['service_category'])) {
                $sql .= " AND fs.service_category = ?";
                $params[] = $filters['service_category'];
                $types .= "s";
            }
            
            if (!empty($filters['user_type'])) {
                $sql .= " AND fa.user_type = ?";
                $params[] = $filters['user_type'];
                $types .= "s";
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND DATE(fa.submitted_at) >= ?";
                $params[] = $filters['date_from'];
                $types .= "s";
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(fa.submitted_at) <= ?";
                $params[] = $filters['date_to'];
                $types .= "s";
            }
            
            $sql .= " ORDER BY fa.submitted_at DESC, fs.submission_id, fq.display_order";
            
            $stmt = $this->conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting export data: " . $e->getMessage());
            return [];
        }
    }
}
?>