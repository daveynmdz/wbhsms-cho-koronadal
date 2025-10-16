<?php
/**
 * Feedback Helper Utilities - Common Operations and Formatters
 * Patient Satisfaction & Feedback System for WBHSMS CHO Koronadal
 */

class FeedbackHelper {
    
    /**
     * Format rating as stars or text
     */
    public static function formatRating($rating, $format = 'stars') {
        if ($rating === null || $rating === '') {
            return 'No rating';
        }
        
        $rating = floatval($rating);
        
        switch ($format) {
            case 'stars':
                $stars = str_repeat('★', floor($rating));
                $halfStar = ($rating - floor($rating)) >= 0.5 ? '☆' : '';
                $emptyStars = str_repeat('☆', 5 - ceil($rating));
                return $stars . $halfStar . $emptyStars . ' (' . number_format($rating, 1) . ')';
                
            case 'text':
                if ($rating >= 4.5) return 'Excellent';
                if ($rating >= 3.5) return 'Good';
                if ($rating >= 2.5) return 'Fair';
                if ($rating >= 1.5) return 'Poor';
                return 'Very Poor';
                
            case 'percentage':
                return number_format(($rating / 5) * 100, 1) . '%';
                
            case 'decimal':
            default:
                return number_format($rating, 1);
        }
    }
    
    /**
     * Get rating color class for CSS styling
     */
    public static function getRatingColorClass($rating) {
        if ($rating === null || $rating === '') {
            return 'rating-none';
        }
        
        $rating = floatval($rating);
        
        if ($rating >= 4.5) return 'rating-excellent';
        if ($rating >= 3.5) return 'rating-good';
        if ($rating >= 2.5) return 'rating-fair';
        if ($rating >= 1.5) return 'rating-poor';
        return 'rating-very-poor';
    }
    
    /**
     * Format date for feedback display
     */
    public static function formatFeedbackDate($date, $format = 'relative') {
        if (!$date) return 'Unknown date';
        
        $timestamp = strtotime($date);
        $now = time();
        $diff = $now - $timestamp;
        
        switch ($format) {
            case 'relative':
                if ($diff < 60) return 'Just now';
                if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
                if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
                if ($diff < 604800) return floor($diff / 86400) . ' days ago';
                if ($diff < 2629746) return floor($diff / 604800) . ' weeks ago';
                return floor($diff / 2629746) . ' months ago';
                
            case 'short':
                return date('M j, Y', $timestamp);
                
            case 'long':
                return date('F j, Y \a\t g:i A', $timestamp);
                
            case 'full':
            default:
                return date('Y-m-d H:i:s', $timestamp);
        }
    }
    
    /**
     * Generate feedback statistics summary
     */
    public static function generateStatsSummary($feedbackData) {
        if (empty($feedbackData)) {
            return [
                'total_responses' => 0,
                'average_rating' => 0,
                'rating_distribution' => [],
                'response_rate_trend' => 'stable'
            ];
        }
        
        $totalResponses = count($feedbackData);
        $ratings = array_column($feedbackData, 'overall_rating');
        $ratings = array_filter($ratings, function($r) { return $r !== null && $r !== ''; });
        
        $averageRating = !empty($ratings) ? array_sum($ratings) / count($ratings) : 0;
        
        // Rating distribution
        $distribution = [
            'excellent' => 0, // 4.5-5.0
            'good' => 0,      // 3.5-4.4
            'fair' => 0,      // 2.5-3.4
            'poor' => 0,      // 1.5-2.4
            'very_poor' => 0  // 0-1.4
        ];
        
        foreach ($ratings as $rating) {
            $rating = floatval($rating);
            if ($rating >= 4.5) $distribution['excellent']++;
            elseif ($rating >= 3.5) $distribution['good']++;
            elseif ($rating >= 2.5) $distribution['fair']++;
            elseif ($rating >= 1.5) $distribution['poor']++;
            else $distribution['very_poor']++;
        }
        
        // Calculate percentages
        if ($totalResponses > 0) {
            foreach ($distribution as &$count) {
                $count = [
                    'count' => $count,
                    'percentage' => round(($count / count($ratings)) * 100, 1)
                ];
            }
        }
        
        return [
            'total_responses' => $totalResponses,
            'average_rating' => round($averageRating, 2),
            'rating_distribution' => $distribution,
            'satisfaction_rate' => count($ratings) > 0 ? round((($distribution['excellent']['count'] + $distribution['good']['count']) / count($ratings)) * 100, 1) : 0
        ];
    }
    
    /**
     * Generate question analysis
     */
    public static function analyzeQuestionResponses($questionData) {
        if (empty($questionData)) {
            return ['total_responses' => 0, 'analysis' => 'No responses'];
        }
        
        $totalResponses = count($questionData);
        $questionType = $questionData[0]['question_type'] ?? 'unknown';
        
        $analysis = ['total_responses' => $totalResponses];
        
        switch ($questionType) {
            case 'rating':
                $ratings = array_column($questionData, 'answer_rating');
                $ratings = array_filter($ratings, function($r) { return $r !== null; });
                
                if (!empty($ratings)) {
                    $analysis['average_rating'] = round(array_sum($ratings) / count($ratings), 2);
                    $analysis['highest_rating'] = max($ratings);
                    $analysis['lowest_rating'] = min($ratings);
                    $analysis['rating_summary'] = self::formatRating($analysis['average_rating'], 'text');
                }
                break;
                
            case 'choice':
            case 'multiple_choice':
                $choices = [];
                foreach ($questionData as $response) {
                    $choiceText = $response['choice_text'] ?? 'Other';
                    $choices[$choiceText] = ($choices[$choiceText] ?? 0) + 1;
                }
                
                arsort($choices); // Sort by frequency
                
                $analysis['choice_distribution'] = [];
                foreach ($choices as $choice => $count) {
                    $analysis['choice_distribution'][] = [
                        'choice' => $choice,
                        'count' => $count,
                        'percentage' => round(($count / $totalResponses) * 100, 1)
                    ];
                }
                
                $analysis['most_popular_choice'] = array_keys($choices)[0] ?? 'None';
                break;
                
            case 'text':
                $textResponses = array_column($questionData, 'answer_text');
                $textResponses = array_filter($textResponses, function($t) { return !empty(trim($t)); });
                
                $analysis['text_response_count'] = count($textResponses);
                $analysis['response_rate'] = round((count($textResponses) / $totalResponses) * 100, 1);
                
                // Simple sentiment analysis (basic keywords)
                $positiveKeywords = ['good', 'excellent', 'great', 'satisfied', 'happy', 'recommend', 'helpful', 'clean', 'professional'];
                $negativeKeywords = ['bad', 'poor', 'terrible', 'dissatisfied', 'unhappy', 'not recommend', 'rude', 'dirty', 'unprofessional'];
                
                $sentiment = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
                
                foreach ($textResponses as $text) {
                    $text = strtolower($text);
                    $positiveScore = 0;
                    $negativeScore = 0;
                    
                    foreach ($positiveKeywords as $keyword) {
                        if (strpos($text, $keyword) !== false) $positiveScore++;
                    }
                    
                    foreach ($negativeKeywords as $keyword) {
                        if (strpos($text, $keyword) !== false) $negativeScore++;
                    }
                    
                    if ($positiveScore > $negativeScore) $sentiment['positive']++;
                    elseif ($negativeScore > $positiveScore) $sentiment['negative']++;
                    else $sentiment['neutral']++;
                }
                
                $analysis['sentiment_analysis'] = $sentiment;
                break;
                
            case 'yes_no':
                $yesCount = 0;
                $noCount = 0;
                
                foreach ($questionData as $response) {
                    $choiceValue = $response['choice_value'] ?? null;
                    if ($choiceValue === '1' || $choiceValue === 1) $yesCount++;
                    elseif ($choiceValue === '0' || $choiceValue === 0) $noCount++;
                }
                
                $analysis['yes_responses'] = $yesCount;
                $analysis['no_responses'] = $noCount;
                $analysis['yes_percentage'] = $totalResponses > 0 ? round(($yesCount / $totalResponses) * 100, 1) : 0;
                break;
        }
        
        return $analysis;
    }
    
    /**
     * Export feedback data to CSV format
     */
    public static function exportToCSV($feedbackData, $filename = null) {
        if (!$filename) {
            $filename = 'feedback_export_' . date('Y-m-d_H-i-s') . '.csv';
        }
        
        if (empty($feedbackData)) {
            return false;
        }
        
        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        $headers = [
            'Submission ID', 'Date Submitted', 'Respondent Name', 'User Type',
            'Facility', 'Service Category', 'Overall Rating', 'Question',
            'Question Type', 'Answer', 'Answer Rating', 'Visit Date'
        ];
        
        fputcsv($output, $headers);
        
        // Add data rows
        foreach ($feedbackData as $row) {
            $csvRow = [
                $row['submission_id'] ?? '',
                $row['submitted_at'] ?? '',
                $row['respondent_name'] ?? '',
                $row['user_type'] ?? '',
                $row['facility_name'] ?? '',
                $row['service_category'] ?? '',
                $row['overall_rating'] ?? '',
                $row['question_text'] ?? '',
                $row['question_type'] ?? '',
                $row['selected_choice'] ?? $row['answer_text'] ?? '',
                $row['answer_rating'] ?? '',
                $row['visit_date'] ?? ''
            ];
            
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        return true;
    }
    
    /**
     * Generate feedback report HTML
     */
    public static function generateReportHTML($analyticsData, $title = 'Feedback Report') {
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>{$title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .report-header { text-align: center; margin-bottom: 30px; }
                .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
                .stat-card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
                .stat-value { font-size: 2em; font-weight: bold; color: #333; }
                .stat-label { color: #666; margin-top: 5px; }
                .rating-excellent { color: #28a745; }
                .rating-good { color: #6c757d; }
                .rating-fair { color: #ffc107; }
                .rating-poor { color: #fd7e14; }
                .rating-very-poor { color: #dc3545; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; }
                .generated-date { text-align: center; color: #666; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='report-header'>
                <h1>{$title}</h1>
                <p>Generated on " . date('F j, Y \a\t g:i A') . "</p>
            </div>
        ";
        
        if (!empty($analyticsData)) {
            $stats = self::generateStatsSummary($analyticsData);
            
            $html .= "
            <div class='stats-grid'>
                <div class='stat-card'>
                    <div class='stat-value'>{$stats['total_responses']}</div>
                    <div class='stat-label'>Total Responses</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-value " . self::getRatingColorClass($stats['average_rating']) . "'>" . self::formatRating($stats['average_rating'], 'decimal') . "</div>
                    <div class='stat-label'>Average Rating</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-value'>{$stats['satisfaction_rate']}%</div>
                    <div class='stat-label'>Satisfaction Rate</div>
                </div>
            </div>
            
            <h2>Rating Distribution</h2>
            <table>
                <tr>
                    <th>Rating Category</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>";
            
            foreach ($stats['rating_distribution'] as $category => $data) {
                $categoryName = ucfirst(str_replace('_', ' ', $category));
                $html .= "
                <tr>
                    <td>{$categoryName}</td>
                    <td>{$data['count']}</td>
                    <td>{$data['percentage']}%</td>
                </tr>";
            }
            
            $html .= "</table>";
        } else {
            $html .= "<p>No feedback data available for the selected criteria.</p>";
        }
        
        $html .= "
            <div class='generated-date'>
                <small>Report generated by WBHSMS Feedback System - CHO Koronadal</small>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Validate and sanitize feedback input
     */
    public static function sanitizeFeedbackInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeFeedbackInput'], $input);
        }
        
        if (is_string($input)) {
            // Remove HTML tags and encode special characters
            $input = strip_tags($input);
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            $input = trim($input);
            
            // Remove excessive whitespace
            $input = preg_replace('/\s+/', ' ', $input);
        }
        
        return $input;
    }
    
    /**
     * Get service category icon/color
     */
    public static function getServiceCategoryStyle($category) {
        $styles = [
            'General' => ['icon' => 'fas fa-hospital', 'color' => '#6c757d'],
            'Consultation' => ['icon' => 'fas fa-stethoscope', 'color' => '#007bff'],
            'Laboratory' => ['icon' => 'fas fa-flask', 'color' => '#28a745'],
            'Pharmacy' => ['icon' => 'fas fa-pills', 'color' => '#dc3545'],
            'Dental' => ['icon' => 'fas fa-tooth', 'color' => '#17a2b8'],
            'Maternal' => ['icon' => 'fas fa-baby', 'color' => '#e83e8c'],
            'Immunization' => ['icon' => 'fas fa-syringe', 'color' => '#6f42c1'],
            'Family Planning' => ['icon' => 'fas fa-heart', 'color' => '#fd7e14']
        ];
        
        return $styles[$category] ?? ['icon' => 'fas fa-question', 'color' => '#6c757d'];
    }
    
    /**
     * Generate notification message for feedback submission
     */
    public static function generateNotificationMessage($submissionData) {
        $rating = $submissionData['overall_rating'] ?? null;
        $userType = $submissionData['user_type'] ?? 'User';
        $facilityName = $submissionData['facility_name'] ?? 'the facility';
        
        if ($rating && $rating >= 4) {
            return "Thank you for your excellent feedback! Your {$rating}-star rating helps us maintain quality service at {$facilityName}.";
        } elseif ($rating && $rating >= 3) {
            return "Thank you for your feedback! We appreciate your {$rating}-star rating and will continue improving our services.";
        } elseif ($rating && $rating < 3) {
            return "Thank you for your honest feedback. Your concerns are important to us and we will work to address the issues you've identified.";
        } else {
            return "Thank you for taking the time to provide feedback. Your input helps us improve our services.";
        }
    }
}
?>