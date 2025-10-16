<?php
/**
 * Feedback Validation Service - Input Validation and Security
 * Patient Satisfaction & Feedback System for WBHSMS CHO Koronadal
 */

class FeedbackValidationService {
    
    /**
     * Validate feedback submission data
     */
    public static function validateFeedbackSubmission($data) {
        $errors = [];
        
        // Required field validation
        if (empty($data['user_id'])) {
            $errors[] = 'User ID is required';
        }
        
        if (empty($data['user_type'])) {
            $errors[] = 'User type is required';
        } elseif (!in_array($data['user_type'], ['Patient', 'BHW', 'Employee'])) {
            $errors[] = 'Invalid user type';
        }
        
        if (empty($data['facility_id'])) {
            $errors[] = 'Facility ID is required';
        } elseif (!is_numeric($data['facility_id']) || $data['facility_id'] <= 0) {
            $errors[] = 'Invalid facility ID';
        }
        
        if (empty($data['answers']) || !is_array($data['answers'])) {
            $errors[] = 'Answers are required and must be an array';
        }
        
        // Validate overall rating if provided
        if (isset($data['overall_rating'])) {
            if (!is_numeric($data['overall_rating']) || $data['overall_rating'] < 1 || $data['overall_rating'] > 5) {
                $errors[] = 'Overall rating must be between 1 and 5';
            }
        }
        
        // Validate answers array
        if (!empty($data['answers'])) {
            foreach ($data['answers'] as $index => $answer) {
                $answerErrors = self::validateAnswer($answer, $index);
                $errors = array_merge($errors, $answerErrors);
            }
        }
        
        // Validate visit ID if provided
        if (isset($data['visit_id'])) {
            if (!is_numeric($data['visit_id']) || $data['visit_id'] <= 0) {
                $errors[] = 'Invalid visit ID';
            }
        }
        
        // Validate service category
        if (isset($data['service_category'])) {
            $validCategories = [
                'General', 'Consultation', 'Laboratory', 'Pharmacy', 
                'Dental', 'Maternal', 'Immunization', 'Family Planning'
            ];
            if (!in_array($data['service_category'], $validCategories)) {
                $errors[] = 'Invalid service category';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate individual answer
     */
    private static function validateAnswer($answer, $index) {
        $errors = [];
        
        // Question ID is required
        if (empty($answer['question_id'])) {
            $errors[] = "Question ID is required for answer #{$index}";
        } elseif (!is_numeric($answer['question_id']) || $answer['question_id'] <= 0) {
            $errors[] = "Invalid question ID for answer #{$index}";
        }
        
        // At least one answer type should be provided
        $hasChoice = !empty($answer['choice_id']);
        $hasText = !empty($answer['answer_text']);
        $hasRating = !empty($answer['answer_rating']);
        
        if (!$hasChoice && !$hasText && !$hasRating) {
            $errors[] = "Answer #{$index} must have at least one response (choice, text, or rating)";
        }
        
        // Validate choice ID if provided
        if ($hasChoice) {
            if (!is_numeric($answer['choice_id']) || $answer['choice_id'] <= 0) {
                $errors[] = "Invalid choice ID for answer #{$index}";
            }
        }
        
        // Validate answer text if provided
        if ($hasText) {
            $answer['answer_text'] = trim($answer['answer_text']);
            if (strlen($answer['answer_text']) > 1000) {
                $errors[] = "Answer text for question #{$index} exceeds 1000 characters";
            }
            
            // Basic XSS protection
            if (self::containsSuspiciousContent($answer['answer_text'])) {
                $errors[] = "Answer text for question #{$index} contains invalid content";
            }
        }
        
        // Validate rating if provided
        if ($hasRating) {
            if (!is_numeric($answer['answer_rating']) || $answer['answer_rating'] < 1 || $answer['answer_rating'] > 5) {
                $errors[] = "Rating for answer #{$index} must be between 1 and 5";
            }
        }
        
        return $errors;
    }
    
    /**
     * Check for suspicious content (basic XSS protection)
     */
    private static function containsSuspiciousContent($text) {
        // Basic patterns to detect potential XSS attempts
        $suspiciousPatterns = [
            '/<script[^>]*>/i',
            '/<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/<form/i'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            // Remove potential XSS content
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            // Trim whitespace
            $data = trim($data);
        }
        
        return $data;
    }
    
    /**
     * Validate question data for admin operations
     */
    public static function validateQuestionData($questionData) {
        $errors = [];
        
        if (empty($questionData['question_text'])) {
            $errors[] = 'Question text is required';
        } elseif (strlen($questionData['question_text']) > 500) {
            $errors[] = 'Question text exceeds 500 characters';
        }
        
        if (empty($questionData['question_type'])) {
            $errors[] = 'Question type is required';
        } elseif (!in_array($questionData['question_type'], ['multiple_choice', 'text', 'rating', 'yes_no'])) {
            $errors[] = 'Invalid question type';
        }
        
        if (empty($questionData['role_target'])) {
            $errors[] = 'Role target is required';
        } elseif (!in_array($questionData['role_target'], ['Patient', 'BHW', 'Employee', 'All'])) {
            $errors[] = 'Invalid role target';
        }
        
        if (isset($questionData['display_order'])) {
            if (!is_numeric($questionData['display_order']) || $questionData['display_order'] < 1) {
                $errors[] = 'Display order must be a positive number';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate choice data for questions
     */
    public static function validateChoiceData($choiceData) {
        $errors = [];
        
        if (empty($choiceData['choice_text'])) {
            $errors[] = 'Choice text is required';
        } elseif (strlen($choiceData['choice_text']) > 200) {
            $errors[] = 'Choice text exceeds 200 characters';
        }
        
        if (isset($choiceData['choice_value'])) {
            if (!is_numeric($choiceData['choice_value'])) {
                $errors[] = 'Choice value must be numeric';
            }
        }
        
        if (isset($choiceData['choice_order'])) {
            if (!is_numeric($choiceData['choice_order']) || $choiceData['choice_order'] < 1) {
                $errors[] = 'Choice order must be a positive number';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate analytics filters
     */
    public static function validateAnalyticsFilters($filters) {
        $errors = [];
        
        if (isset($filters['facility_id'])) {
            if (!is_numeric($filters['facility_id']) || $filters['facility_id'] <= 0) {
                $errors[] = 'Invalid facility ID';
            }
        }
        
        if (isset($filters['user_type'])) {
            if (!in_array($filters['user_type'], ['Patient', 'BHW', 'Employee'])) {
                $errors[] = 'Invalid user type';
            }
        }
        
        if (isset($filters['date_from'])) {
            if (!self::isValidDate($filters['date_from'])) {
                $errors[] = 'Invalid date_from format (use YYYY-MM-DD)';
            }
        }
        
        if (isset($filters['date_to'])) {
            if (!self::isValidDate($filters['date_to'])) {
                $errors[] = 'Invalid date_to format (use YYYY-MM-DD)';
            }
        }
        
        // Validate date range
        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            if (strtotime($filters['date_from']) > strtotime($filters['date_to'])) {
                $errors[] = 'Date from cannot be later than date to';
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if date is valid
     */
    private static function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($userId, $userType, $action = 'submit') {
        // Simple rate limiting - max 5 submissions per user per hour
        $cacheKey = "feedback_rate_limit_{$action}_{$userType}_{$userId}";
        
        // In a real implementation, you would use Redis or database
        // For now, we'll use session-based tracking
        if (!isset($_SESSION['feedback_rate_limits'])) {
            $_SESSION['feedback_rate_limits'] = [];
        }
        
        $currentTime = time();
        $hourAgo = $currentTime - 3600;
        
        // Clean old entries
        foreach ($_SESSION['feedback_rate_limits'] as $key => $timestamps) {
            $_SESSION['feedback_rate_limits'][$key] = array_filter($timestamps, function($timestamp) use ($hourAgo) {
                return $timestamp > $hourAgo;
            });
        }
        
        // Check current user's rate
        if (!isset($_SESSION['feedback_rate_limits'][$cacheKey])) {
            $_SESSION['feedback_rate_limits'][$cacheKey] = [];
        }
        
        $userRequests = $_SESSION['feedback_rate_limits'][$cacheKey];
        
        if (count($userRequests) >= 5) {
            return false; // Rate limit exceeded
        }
        
        // Add current request
        $_SESSION['feedback_rate_limits'][$cacheKey][] = $currentTime;
        
        return true; // Request allowed
    }
    
    /**
     * Validate user permissions for specific actions
     */
    public static function validateUserAccess($userId, $userType, $action, $resourceId = null) {
        // Define permission matrix
        $permissions = [
            'Patient' => ['submit'],
            'BHW' => ['submit', 'view_own'],
            'Employee' => ['submit', 'view_own'],
            'Nurse' => ['submit', 'view_own', 'view_department'],
            'Doctor' => ['submit', 'view_own', 'view_department'],
            'Admin' => ['submit', 'view_own', 'view_all', 'manage', 'analytics'],
            'Manager' => ['submit', 'view_own', 'view_department', 'analytics']
        ];
        
        if (!isset($permissions[$userType])) {
            return false;
        }
        
        return in_array($action, $permissions[$userType]);
    }
}
?>