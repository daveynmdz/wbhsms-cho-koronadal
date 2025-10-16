<?php
/**
 * Dynamic Feedback Form Component
 * Renders feedback forms based on question types and handles submissions
 * WBHSMS CHO Koronadal
 */

// Include session and database configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';
require_once $root_path . '/config/db.php';

// Authentication check
if (!is_patient_logged_in()) {
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

$patient_id = $_SESSION['patient_id'];
$visit_id = $_POST['visit_id'] ?? null;
$mode = $_POST['mode'] ?? 'new'; // new, view, edit

if (!$visit_id) {
    echo '<div class="error">Invalid visit ID</div>';
    exit();
}

// Verify visit belongs to patient
try {
    $visit_check = $conn->prepare("SELECT visit_id, visit_date, purpose, facility_id FROM visits WHERE visit_id = ? AND patient_id = ?");
    $visit_check->bind_param("ii", $visit_id, $patient_id);
    $visit_check->execute();
    $visit_result = $visit_check->get_result();
    $visit = $visit_result->fetch_assoc();
    
    if (!$visit) {
        echo '<div class="error">Visit not found or access denied</div>';
        exit();
    }
} catch (Exception $e) {
    echo '<div class="error">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit();
}

// Get existing feedback if in view/edit mode
$existing_feedback = null;
$existing_answers = [];

if ($mode === 'view' || $mode === 'edit') {
    try {
        // Get submission
        $feedback_query = "SELECT * FROM feedback_submissions WHERE visit_id = ? AND user_id = ? AND user_type = 'Patient'";
        $feedback_stmt = $conn->prepare($feedback_query);
        $feedback_stmt->bind_param("ii", $visit_id, $patient_id);
        $feedback_stmt->execute();
        $feedback_result = $feedback_stmt->get_result();
        $existing_feedback = $feedback_result->fetch_assoc();
        
        if ($existing_feedback) {
            // Get answers
            $answers_query = "
                SELECT 
                    fa.question_id,
                    fa.choice_id,
                    fa.answer_text,
                    fa.answer_rating,
                    fq.question_text,
                    fq.question_type,
                    fqc.choice_text
                FROM feedback_answers fa
                JOIN feedback_questions fq ON fa.question_id = fq.question_id
                LEFT JOIN feedback_question_choices fqc ON fa.choice_id = fqc.choice_id
                WHERE fa.submission_id = ?
                ORDER BY fq.display_order
            ";
            $answers_stmt = $conn->prepare($answers_query);
            $answers_stmt->bind_param("i", $existing_feedback['submission_id']);
            $answers_stmt->execute();
            $answers_result = $answers_stmt->get_result();
            
            while ($row = $answers_result->fetch_assoc()) {
                $existing_answers[$row['question_id']] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching existing feedback: " . $e->getMessage());
    }
}

// Fetch questions for the form
try {
    $questions_query = "
        SELECT 
            fq.question_id,
            fq.question_text,
            fq.question_type,
            fq.is_required,
            fq.display_order,
            fqc.choice_id,
            fqc.choice_text,
            fqc.choice_value,
            fqc.choice_order
        FROM feedback_questions fq
        LEFT JOIN feedback_question_choices fqc ON fq.question_id = fqc.question_id AND fqc.is_active = 1
        WHERE fq.is_active = 1 
        AND (fq.role_target = 'Patient' OR fq.role_target = 'All')
        ORDER BY fq.display_order ASC, fq.question_id ASC, fqc.choice_order ASC
    ";
    
    $questions_stmt = $conn->prepare($questions_query);
    $questions_stmt->execute();
    $questions_result = $questions_stmt->get_result();
    
    // Group questions with choices
    $questions = [];
    while ($row = $questions_result->fetch_assoc()) {
        $question_id = $row['question_id'];
        
        if (!isset($questions[$question_id])) {
            $questions[$question_id] = [
                'question_id' => $row['question_id'],
                'question_text' => $row['question_text'],
                'question_type' => $row['question_type'],
                'is_required' => $row['is_required'],
                'display_order' => $row['display_order'],
                'choices' => []
            ];
        }
        
        if ($row['choice_id']) {
            $questions[$question_id]['choices'][] = [
                'choice_id' => $row['choice_id'],
                'choice_text' => $row['choice_text'],
                'choice_value' => $row['choice_value'],
                'choice_order' => $row['choice_order']
            ];
        }
    }
    
} catch (Exception $e) {
    echo '<div class="error">Error loading questions: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit();
}

$is_readonly = ($mode === 'view');
$form_action = $is_readonly ? '' : 'submit_feedback.php';

?>

<div class="feedback-form-container">
    <!-- Visit Information -->
    <div class="visit-summary">
        <h4><i class="fas fa-info-circle"></i> Visit Information</h4>
        <div class="visit-details">
            <div><strong>Date:</strong> <?php echo date('F j, Y', strtotime($visit['visit_date'])); ?></div>
            <div><strong>Purpose:</strong> <?php echo htmlspecialchars($visit['purpose']); ?></div>
            <?php if ($existing_feedback): ?>
                <div><strong>Feedback Submitted:</strong> <?php echo date('F j, Y g:i A', strtotime($existing_feedback['submitted_at'])); ?></div>
                <?php if ($existing_feedback['overall_rating']): ?>
                    <div><strong>Overall Rating:</strong> 
                        <span class="rating-display">
                            <?php
                            $rating = floatval($existing_feedback['overall_rating']);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $rating) {
                                    echo '<i class="fas fa-star" style="color: #f6e05e;"></i>';
                                } elseif ($i - 0.5 <= $rating) {
                                    echo '<i class="fas fa-star-half-alt" style="color: #f6e05e;"></i>';
                                } else {
                                    echo '<i class="far fa-star" style="color: #f6e05e;"></i>';
                                }
                            }
                            echo ' (' . number_format($rating, 1) . '/5)';
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feedback Form -->
    <?php if (!$is_readonly): ?>
        <form id="feedbackForm" method="POST" action="<?php echo $form_action; ?>">
            <input type="hidden" name="visit_id" value="<?php echo $visit_id; ?>">
            <input type="hidden" name="facility_id" value="<?php echo $visit['facility_id']; ?>">
            <?php if ($existing_feedback): ?>
                <input type="hidden" name="submission_id" value="<?php echo $existing_feedback['submission_id']; ?>">
                <input type="hidden" name="mode" value="edit">
            <?php else: ?>
                <input type="hidden" name="mode" value="new">
            <?php endif; ?>
    <?php endif; ?>

    <div class="questions-container">
        <?php if (empty($questions)): ?>
            <div class="no-questions">
                <p>No feedback questions are currently available. Please try again later.</p>
            </div>
        <?php else: ?>
            <?php foreach ($questions as $question): ?>
                <div class="question-group" data-question-id="<?php echo $question['question_id']; ?>">
                    <div class="question-header">
                        <label class="question-text">
                            <?php echo htmlspecialchars($question['question_text']); ?>
                            <?php if ($question['is_required'] && !$is_readonly): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <div class="question-input">
                        <?php 
                        $existing_answer = $existing_answers[$question['question_id']] ?? null;
                        
                        switch ($question['question_type']):
                            case 'rating': 
                        ?>
                            <div class="rating-input">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="star-label">
                                        <input type="radio" 
                                               name="answers[<?php echo $question['question_id']; ?>][rating]" 
                                               value="<?php echo $i; ?>"
                                               <?php echo ($existing_answer && $existing_answer['answer_rating'] == $i) ? 'checked' : ''; ?>
                                               <?php echo $is_readonly ? 'disabled' : ''; ?>
                                               class="star-input">
                                        <i class="fas fa-star star-icon"></i>
                                    </label>
                                <?php endfor; ?>
                                <span class="rating-text">Click to rate (1-5 stars)</span>
                            </div>
                            
                        <?php break; case 'choice': ?>
                            <div class="choices-container">
                                <?php foreach ($question['choices'] as $choice): ?>
                                    <label class="choice-label">
                                        <input type="radio" 
                                               name="answers[<?php echo $question['question_id']; ?>][choice_id]" 
                                               value="<?php echo $choice['choice_id']; ?>"
                                               <?php echo ($existing_answer && $existing_answer['choice_id'] == $choice['choice_id']) ? 'checked' : ''; ?>
                                               <?php echo $is_readonly ? 'disabled' : ''; ?>
                                               class="choice-input">
                                        <span class="choice-text"><?php echo htmlspecialchars($choice['choice_text']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                        <?php break; case 'text': ?>
                            <div class="text-input-container">
                                <textarea name="answers[<?php echo $question['question_id']; ?>][text]" 
                                          class="text-input"
                                          rows="4"
                                          placeholder="Please share your thoughts..."
                                          <?php echo $is_readonly ? 'readonly' : ''; ?>
                                          maxlength="1000"><?php echo $existing_answer ? htmlspecialchars($existing_answer['answer_text']) : ''; ?></textarea>
                                <div class="char-count">
                                    <span class="current">0</span>/<span class="max">1000</span> characters
                                </div>
                            </div>
                            
                        <?php break; case 'yesno': ?>
                            <div class="yesno-container">
                                <label class="choice-label">
                                    <input type="radio" 
                                           name="answers[<?php echo $question['question_id']; ?>][choice_value]" 
                                           value="1"
                                           <?php echo ($existing_answer && $existing_answer['choice_id'] && 
                                                      isset($question['choices'][0]) && 
                                                      $question['choices'][0]['choice_value'] == '1') ? 'checked' : ''; ?>
                                           <?php echo $is_readonly ? 'disabled' : ''; ?>>
                                    <span class="choice-text">Yes</span>
                                </label>
                                <label class="choice-label">
                                    <input type="radio" 
                                           name="answers[<?php echo $question['question_id']; ?>][choice_value]" 
                                           value="0"
                                           <?php echo ($existing_answer && $existing_answer['choice_id'] && 
                                                      isset($question['choices'][1]) && 
                                                      $question['choices'][1]['choice_value'] == '0') ? 'checked' : ''; ?>
                                           <?php echo $is_readonly ? 'disabled' : ''; ?>>
                                    <span class="choice-text">No</span>
                                </label>
                            </div>
                            
                        <?php break; endswitch; ?>
                        
                        <input type="hidden" name="answers[<?php echo $question['question_id']; ?>][question_id]" value="<?php echo $question['question_id']; ?>">
                        <?php if ($question['is_required']): ?>
                            <input type="hidden" name="required_questions[]" value="<?php echo $question['question_id']; ?>">
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Overall Rating Section (for new submissions) -->
    <?php if (!$is_readonly && !$existing_feedback): ?>
        <div class="overall-rating-section">
            <h4>Overall Experience Rating</h4>
            <div class="overall-rating-input">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label class="star-label overall-star">
                        <input type="radio" name="overall_rating" value="<?php echo $i; ?>" class="star-input">
                        <i class="fas fa-star star-icon"></i>
                    </label>
                <?php endfor; ?>
                <span class="rating-text">Rate your overall experience</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form Actions -->
    <div class="form-actions">
        <?php if (!$is_readonly): ?>
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="fas fa-paper-plane"></i>
                <?php echo $existing_feedback ? 'Update Feedback' : 'Submit Feedback'; ?>
            </button>
        <?php endif; ?>
        
        <button type="button" class="btn btn-secondary" onclick="closeFeedbackModal()">
            <i class="fas fa-times"></i>
            <?php echo $is_readonly ? 'Close' : 'Cancel'; ?>
        </button>
    </div>

    <?php if (!$is_readonly): ?>
        </form>
    <?php endif; ?>
</div>

<style>
    .feedback-form-container {
        max-width: 100%;
    }
    
    .visit-summary {
        background: #f7fafc;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #667eea;
    }
    
    .visit-summary h4 {
        margin: 0 0 10px 0;
        color: #2d3748;
    }
    
    .visit-details {
        display: grid;
        gap: 8px;
        font-size: 0.9em;
        color: #4a5568;
    }
    
    .questions-container {
        margin-bottom: 30px;
    }
    
    .question-group {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .question-header {
        margin-bottom: 15px;
    }
    
    .question-text {
        font-weight: 600;
        color: #2d3748;
        display: block;
        margin-bottom: 10px;
    }
    
    .required {
        color: #e53e3e;
        margin-left: 4px;
    }
    
    /* Rating Input Styles */
    .rating-input {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .star-label {
        cursor: pointer;
        font-size: 1.5em;
        transition: all 0.2s;
    }
    
    .star-input {
        display: none;
    }
    
    .star-icon {
        color: #e2e8f0;
        transition: color 0.2s;
    }
    
    .star-label:hover .star-icon,
    .star-input:checked ~ .star-icon,
    .star-input:checked + .star-icon {
        color: #f6e05e;
    }
    
    .rating-text {
        margin-left: 10px;
        font-size: 0.9em;
        color: #718096;
    }
    
    /* Choice Input Styles */
    .choices-container,
    .yesno-container {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .choice-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        padding: 8px;
        border-radius: 4px;
        transition: background 0.2s;
    }
    
    .choice-label:hover {
        background: #f7fafc;
    }
    
    .choice-input {
        margin: 0;
    }
    
    .choice-text {
        font-size: 0.95em;
    }
    
    /* Text Input Styles */
    .text-input-container {
        position: relative;
    }
    
    .text-input {
        width: 100%;
        padding: 12px;
        border: 1px solid #cbd5e0;
        border-radius: 6px;
        font-size: 0.95em;
        resize: vertical;
        font-family: inherit;
    }
    
    .text-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .char-count {
        text-align: right;
        font-size: 0.8em;
        color: #718096;
        margin-top: 5px;
    }
    
    /* Overall Rating */
    .overall-rating-section {
        background: #f0f4ff;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #c3dafe;
    }
    
    .overall-rating-section h4 {
        margin: 0 0 15px 0;
        color: #1a202c;
    }
    
    .overall-star .star-icon {
        font-size: 1.8em;
    }
    
    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-size: 0.95em;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: #667eea;
        color: white;
    }
    
    .btn-primary:hover:not(:disabled) {
        background: #5a67d8;
    }
    
    .btn-secondary {
        background: #e2e8f0;
        color: #4a5568;
    }
    
    .btn-secondary:hover {
        background: #cbd5e0;
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .no-questions {
        text-align: center;
        padding: 40px;
        color: #718096;
        background: #f7fafc;
        border-radius: 8px;
    }
    
    @media (max-width: 768px) {
        .form-actions {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<script>
    // Star rating functionality
    document.addEventListener('DOMContentLoaded', function() {
        initializeStarRatings();
        initializeTextCounters();
        initializeFormValidation();
    });
    
    function initializeStarRatings() {
        const starGroups = document.querySelectorAll('.rating-input, .overall-rating-input');
        
        starGroups.forEach(group => {
            const stars = group.querySelectorAll('.star-label');
            const inputs = group.querySelectorAll('.star-input');
            
            stars.forEach((star, index) => {
                star.addEventListener('mouseenter', () => {
                    highlightStars(stars, index + 1);
                });
                
                star.addEventListener('click', () => {
                    inputs[index].checked = true;
                    highlightStars(stars, index + 1, true);
                });
            });
            
            group.addEventListener('mouseleave', () => {
                const checkedIndex = Array.from(inputs).findIndex(input => input.checked);
                highlightStars(stars, checkedIndex + 1, true);
            });
        });
    }
    
    function highlightStars(stars, count, permanent = false) {
        stars.forEach((star, index) => {
            const icon = star.querySelector('.star-icon');
            if (index < count) {
                icon.style.color = '#f6e05e';
            } else if (!permanent) {
                icon.style.color = '#e2e8f0';
            }
        });
    }
    
    function initializeTextCounters() {
        const textareas = document.querySelectorAll('.text-input');
        
        textareas.forEach(textarea => {
            const container = textarea.closest('.text-input-container');
            const counter = container.querySelector('.char-count .current');
            
            if (counter) {
                // Initial count
                counter.textContent = textarea.value.length;
                
                textarea.addEventListener('input', function() {
                    counter.textContent = this.value.length;
                    
                    // Color feedback
                    if (this.value.length > 900) {
                        counter.style.color = '#e53e3e';
                    } else if (this.value.length > 800) {
                        counter.style.color = '#dd6b20';
                    } else {
                        counter.style.color = '#718096';
                    }
                });
            }
        });
    }
    
    function initializeFormValidation() {
        const form = document.getElementById('feedbackForm');
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateForm()) {
                submitFeedback();
            }
        });
    }
    
    function validateForm() {
        const requiredQuestions = document.querySelectorAll('input[name="required_questions[]"]');
        let isValid = true;
        let errors = [];
        
        requiredQuestions.forEach(hiddenInput => {
            const questionId = hiddenInput.value;
            const questionGroup = document.querySelector(`[data-question-id="${questionId}"]`);
            const questionText = questionGroup.querySelector('.question-text').textContent.replace('*', '').trim();
            
            // Check if question has any answer
            const hasRating = questionGroup.querySelector('input[type="radio"][name*="[rating]"]:checked');
            const hasChoice = questionGroup.querySelector('input[type="radio"][name*="[choice_id]"]:checked');
            const hasChoiceValue = questionGroup.querySelector('input[type="radio"][name*="[choice_value]"]:checked');
            const hasText = questionGroup.querySelector('textarea[name*="[text]"]');
            
            let hasAnswer = false;
            
            if (hasRating || hasChoice || hasChoiceValue) {
                hasAnswer = true;
            } else if (hasText && hasText.value.trim().length > 0) {
                hasAnswer = true;
            }
            
            if (!hasAnswer) {
                isValid = false;
                errors.push(questionText);
                questionGroup.style.borderColor = '#e53e3e';
                questionGroup.style.backgroundColor = '#fed7d7';
            } else {
                questionGroup.style.borderColor = '#e2e8f0';
                questionGroup.style.backgroundColor = 'white';
            }
        });
        
        if (!isValid) {
            alert('Please answer the following required questions:\\n\\n• ' + errors.join('\\n• '));
        }
        
        return isValid;
    }
    
    function submitFeedback() {
        const form = document.getElementById('feedbackForm');
        const submitBtn = document.getElementById('submitBtn');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        const formData = new FormData(form);
        
        fetch('submit_feedback.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Feedback submitted successfully! Thank you for your input.');
                closeFeedbackModal();
                refreshPage();
            } else {
                alert('Error submitting feedback: ' + (data.message || 'Unknown error'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Feedback';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error submitting feedback. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Feedback';
        });
    }
</script>