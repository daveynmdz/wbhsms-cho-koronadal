<?php
/**
 * Patient Feedback System - Main Interface
 * Allows patients to provide feedback on their healthcare experience
 * WBHSMS CHO Koronadal
 */

// Start output buffering and session management
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Cache control headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include patient session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/env.php';
require_once $root_path . '/config/session/patient_session.php';

// Ensure session is properly started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!is_patient_logged_in()) {
    ob_clean();
    header('Location: ../auth/patient_login.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';
$patient_id = $_SESSION['patient_id'];

// Get patient information
$patient_info = [
    'patient_id' => $patient_id,
    'name' => $_SESSION['patient_name'] ?? 'Patient',
    'patient_number' => $_SESSION['patient_number'] ?? '-'
];

// Fetch patient's completed visits that can have feedback
try {
    $visits_query = "
        SELECT 
            v.visit_id,
            v.visit_date,
            v.purpose,
            v.status,
            v.doctor_id,
            CONCAT(e.first_name, ' ', e.last_name) as doctor_name,
            f.name as facility_name,
            f.facility_id,
            
            -- Check if feedback already exists
            fs.submission_id,
            fs.overall_rating,
            fs.submitted_at as feedback_date,
            COUNT(fa.answer_id) as feedback_answers_count
            
        FROM visits v
        LEFT JOIN employees e ON v.doctor_id = e.employee_id
        LEFT JOIN facilities f ON v.facility_id = f.facility_id
        LEFT JOIN feedback_submissions fs ON v.visit_id = fs.visit_id AND fs.user_type = 'Patient' AND fs.user_id = ?
        LEFT JOIN feedback_answers fa ON fs.submission_id = fa.submission_id
        WHERE v.patient_id = ? 
        AND v.status = 'Completed'
        AND v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)  -- Only last 6 months
        GROUP BY v.visit_id
        ORDER BY v.visit_date DESC
        LIMIT 20
    ";
    
    $visits_stmt = $conn->prepare($visits_query);
    $visits_stmt->bind_param("ii", $patient_id, $patient_id);
    $visits_stmt->execute();
    $visits_result = $visits_stmt->get_result();
    $visits = $visits_result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching patient visits: " . $e->getMessage());
    $visits = [];
}

// Count statistics
$total_visits = count($visits);
$visits_with_feedback = count(array_filter($visits, function($v) { return !empty($v['submission_id']); }));
$pending_feedback = $total_visits - $visits_with_feedback;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Feedback - WBHSMS CHO Koronadal</title>
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/clinical-encounter.css">
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Feedback-specific styles */
        .feedback-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .feedback-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #4a5568;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .visits-grid {
            display: grid;
            gap: 20px;
        }
        
        .visit-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease;
        }
        
        .visit-card:hover {
            transform: translateY(-2px);
        }
        
        .visit-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .visit-date {
            font-size: 1.1em;
            font-weight: bold;
            color: #2d3748;
        }
        
        .visit-purpose {
            color: #4a5568;
            margin-top: 5px;
        }
        
        .visit-body {
            padding: 20px;
        }
        
        .visit-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-icon {
            color: #667eea;
        }
        
        .feedback-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-completed {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-pending {
            background: #feebc8;
            color: #c05621;
        }
        
        .visit-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-feedback {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-feedback:hover {
            background: #5a67d8;
        }
        
        .btn-view {
            background: #38b2ac;
            color: white;
        }
        
        .btn-view:hover {
            background: #319795;
        }
        
        .btn-edit {
            background: #ed8936;
            color: white;
        }
        
        .btn-edit:hover {
            background: #dd6b20;
        }
        
        .rating-display {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .stars {
            color: #f6e05e;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #cbd5e0;
        }
        
        @media (max-width: 768px) {
            .feedback-container {
                padding: 10px;
            }
            
            .visit-info {
                grid-template-columns: 1fr;
            }
            
            .visit-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="feedback-container">
        <!-- Header Section -->
        <div class="feedback-header">
            <h1><i class="fas fa-comments"></i> Patient Feedback</h1>
            <p>Share your experience to help us improve our healthcare services</p>
            <p><strong>Welcome, <?php echo htmlspecialchars($patient_info['name']); ?></strong> (ID: <?php echo htmlspecialchars($patient_info['patient_number']); ?>)</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_visits; ?></div>
                <div class="stat-label">Total Visits (6 Months)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $visits_with_feedback; ?></div>
                <div class="stat-label">Feedback Submitted</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_feedback; ?></div>
                <div class="stat-label">Pending Feedback</div>
            </div>
        </div>

        <!-- Visits List -->
        <div class="visits-section">
            <h2><i class="fas fa-history"></i> Your Recent Visits</h2>
            
            <?php if (empty($visits)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Recent Visits</h3>
                    <p>You don't have any completed visits in the last 6 months that can receive feedback.</p>
                    <p><a href="../appointment/" class="btn btn-primary">Schedule an Appointment</a></p>
                </div>
            <?php else: ?>
                <div class="visits-grid">
                    <?php foreach ($visits as $visit): ?>
                        <div class="visit-card">
                            <div class="visit-header">
                                <div class="visit-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('F j, Y', strtotime($visit['visit_date'])); ?>
                                </div>
                                <div class="visit-purpose"><?php echo htmlspecialchars($visit['purpose']); ?></div>
                            </div>
                            
                            <div class="visit-body">
                                <div class="visit-info">
                                    <div class="info-item">
                                        <i class="fas fa-user-md info-icon"></i>
                                        <span>Dr. <?php echo htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-hospital info-icon"></i>
                                        <span><?php echo htmlspecialchars($visit['facility_name'] ?? 'Main Facility'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="feedback-info">
                                    <?php if ($visit['submission_id']): ?>
                                        <span class="feedback-status status-completed">
                                            <i class="fas fa-check-circle"></i>
                                            Feedback Completed
                                        </span>
                                        
                                        <?php if ($visit['overall_rating']): ?>
                                            <div class="rating-display" style="margin-top: 10px;">
                                                <span>Your Rating:</span>
                                                <div class="stars">
                                                    <?php
                                                    $rating = floatval($visit['overall_rating']);
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $rating) {
                                                            echo '<i class="fas fa-star"></i>';
                                                        } elseif ($i - 0.5 <= $rating) {
                                                            echo '<i class="fas fa-star-half-alt"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <span>(<?php echo number_format($rating, 1); ?>/5)</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="visit-actions">
                                            <button class="btn-feedback btn-view" onclick="viewFeedback(<?php echo $visit['visit_id']; ?>)">
                                                <i class="fas fa-eye"></i> View Feedback
                                            </button>
                                            <button class="btn-feedback btn-edit" onclick="editFeedback(<?php echo $visit['visit_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit Feedback
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="feedback-status status-pending">
                                            <i class="fas fa-clock"></i>
                                            Feedback Pending
                                        </span>
                                        
                                        <div class="visit-actions">
                                            <button class="btn-feedback" onclick="startFeedback(<?php echo $visit['visit_id']; ?>)">
                                                <i class="fas fa-plus"></i> Give Feedback
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feedback Modal (will be populated by JavaScript) -->
    <div id="feedbackModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Feedback Form</h3>
                <span class="modal-close" onclick="closeFeedbackModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // JavaScript functions for feedback interactions
        function startFeedback(visitId) {
            // Load feedback form for new feedback
            loadFeedbackForm(visitId, 'new');
        }
        
        function viewFeedback(visitId) {
            // Load existing feedback in read-only mode
            loadFeedbackForm(visitId, 'view');
        }
        
        function editFeedback(visitId) {
            // Load existing feedback in edit mode
            loadFeedbackForm(visitId, 'edit');
        }
        
        function loadFeedbackForm(visitId, mode) {
            document.getElementById('modalBody').innerHTML = '<div class="loading">Loading feedback form...</div>';
            document.getElementById('feedbackModal').style.display = 'block';
            
            // Set modal title based on mode
            const titles = {
                'new': 'Submit New Feedback',
                'view': 'View Your Feedback', 
                'edit': 'Edit Your Feedback'
            };
            document.getElementById('modalTitle').textContent = titles[mode];
            
            // Make AJAX request to load form
            fetch('feedback_form.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `visit_id=${visitId}&mode=${mode}`
            })
            .then(response => response.text())
            .then(html => {
                document.getElementById('modalBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading feedback form:', error);
                document.getElementById('modalBody').innerHTML = 
                    '<div class="error">Error loading feedback form. Please try again.</div>';
            });
        }
        
        function closeFeedbackModal() {
            document.getElementById('feedbackModal').style.display = 'none';
            document.getElementById('modalBody').innerHTML = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('feedbackModal');
            if (event.target == modal) {
                closeFeedbackModal();
            }
        }
        
        // Auto-refresh page after successful feedback submission
        function refreshPage() {
            window.location.reload();
        }
    </script>
    
    <!-- Modal Styles -->
    <style>
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f7fafc;
            border-radius: 10px 10px 0 0;
        }
        
        .modal-close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #a0aec0;
        }
        
        .modal-close:hover {
            color: #2d3748;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        
        .error {
            color: #e53e3e;
            text-align: center;
            padding: 20px;
            background: #fed7d7;
            border-radius: 5px;
        }
    </style>
</body>
</html>
