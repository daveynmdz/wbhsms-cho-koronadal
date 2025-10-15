<?php
/**
 * Public Laboratory Queue Display - Waiting Area Display
 * Purpose: Public display showing laboratory queue status for patients in waiting area
 */

// Include database connection and queue management service
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

// Initialize queue management service
$queueService = new QueueManagementService($pdo);

// Get current date for display
$current_date = date('Y-m-d');
$display_date = date('F j, Y');
$current_time = date('g:i A');

// Get all laboratory stations with current assignments and queue data for CHO (facility_id = 1)
$stations_query = "
    SELECT 
        s.station_id,
        s.station_name,
        s.service_id,
        s.station_type,
        s.station_number,
        s.is_active,
        s.is_open,
        srv.name as service_name,
        -- Get assigned employee info
        e.first_name,
        e.last_name,
        r.role_name as role,
        -- Get current patient (in progress)
        (SELECT CONCAT(LEFT(p.first_name, 1), '.', LEFT(p.last_name, 1), '.') 
         FROM queue_entries qe 
         JOIN patients p ON qe.patient_id = p.patient_id 
         WHERE qe.station_id = s.station_id 
         AND qe.status = 'in_progress' 
         AND DATE(qe.time_in) = ? 
         LIMIT 1) as current_patient_initials,
        -- Get current patient queue code
        (SELECT qe.queue_code 
         FROM queue_entries qe 
         WHERE qe.station_id = s.station_id 
         AND qe.status = 'in_progress' 
         AND DATE(qe.time_in) = ? 
         LIMIT 1) as current_queue_code,
        -- Get current patient start time
        (SELECT qe.time_started 
         FROM queue_entries qe 
         WHERE qe.station_id = s.station_id 
         AND qe.status = 'in_progress' 
         AND DATE(qe.time_in) = ? 
         LIMIT 1) as current_start_time,
        -- Get waiting count
        (SELECT COUNT(*) 
         FROM queue_entries qe 
         WHERE qe.station_id = s.station_id 
         AND qe.status = 'waiting' 
         AND DATE(qe.time_in) = ?) as waiting_count,
        -- Get in progress count
        (SELECT COUNT(*) 
         FROM queue_entries qe 
         WHERE qe.station_id = s.station_id 
         AND qe.status = 'in_progress' 
         AND DATE(qe.time_in) = ?) as in_progress_count,
        -- Get completed count
        (SELECT COUNT(*) 
         FROM queue_entries qe 
         WHERE qe.station_id = s.station_id 
         AND qe.status = 'done' 
         AND DATE(qe.time_in) = ?) as completed_count,
        -- Get next patient queue code
        (SELECT qe.queue_code 
         FROM queue_entries qe 
         WHERE qe.station_id = s.station_id 
         AND qe.status = 'waiting' 
         AND DATE(qe.time_in) = ? 
         ORDER BY qe.queue_entry_id 
         LIMIT 1) as next_queue_code
    FROM stations s
    JOIN services srv ON s.service_id = srv.service_id
    JOIN facility_services fs ON srv.service_id = fs.service_id
    LEFT JOIN assignment_schedules asg ON s.station_id = asg.station_id 
        AND ? BETWEEN asg.start_date AND COALESCE(asg.end_date, '9999-12-31') 
        AND asg.is_active = 1
    LEFT JOIN employees e ON asg.employee_id = e.employee_id
    LEFT JOIN roles r ON e.role_id = r.role_id
    WHERE s.station_type = 'lab'
    AND s.is_active = 1 
    AND fs.facility_id = 1
    ORDER BY s.station_number
";

$stmt = $pdo->prepare($stations_query);
$stmt->execute([$current_date, $current_date, $current_date, $current_date, $current_date, $current_date, $current_date, $current_date]);
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all called queue entries for today with their status
$called_queue_query = "
    SELECT 
        qe.queue_code,
        qe.status,
        qe.time_started,
        qe.time_completed,
        s.station_name,
        s.station_number,
        CASE 
            WHEN qe.status = 'done' THEN 'Completed'
            WHEN qe.status = 'skipped' THEN 'Skipped'
            WHEN qe.status = 'in_progress' THEN 'In Progress'
            WHEN qe.status = 'no_show' THEN 'No Show'
            ELSE 'Waiting'
        END as status_display,
        CASE 
            WHEN qe.status = 'done' THEN 'success'
            WHEN qe.status = 'skipped' THEN 'warning'
            WHEN qe.status = 'in_progress' THEN 'info'
            WHEN qe.status = 'no_show' THEN 'danger'
            ELSE 'secondary'
        END as status_class
    FROM queue_entries qe
    JOIN stations s ON qe.station_id = s.station_id
    WHERE s.station_type = 'lab'
    AND DATE(qe.time_in) = ?
    AND qe.time_started IS NOT NULL
    ORDER BY qe.time_started DESC
    LIMIT 20
";

$stmt_called = $pdo->prepare($called_queue_query);
$stmt_called->execute([$current_date]);
$called_queues = $stmt_called->fetchAll(PDO::FETCH_ASSOC);

// Get overall laboratory statistics for today
$total_waiting = 0;
$total_in_progress = 0;
$total_completed = 0;

foreach ($stations as $station) {
    $total_waiting += $station['waiting_count'];
    $total_in_progress += $station['in_progress_count'];
    $total_completed += $station['completed_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Queue Display - CHO Koronadal</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #1e5aa8;
            --secondary-blue: #4285f4;
            --accent-blue: #e8f0fe;
            --text-dark: #1a1a1a;
            --text-light: #ffffff;
            --text-muted: #666666;
            --success-green: #34a853;
            --warning-orange: #fbbc04;
            --error-red: #ea4335;
            --border-light: #e0e0e0;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
            overflow-x: hidden;
        }

        .display-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--text-light);
        }

        .header {
            background: var(--primary-blue);
            color: var(--text-light);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: relative;
            z-index: 10;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .facility-logo {
            width: 60px;
            height: 60px;
            background: var(--text-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-blue);
            font-weight: 700;
        }

        .facility-info h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .facility-info .subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .header-right {
            text-align: right;
        }

        .current-time {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .current-date {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Full Width Call Display */
        .call-display {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            min-height: 400px;
        }

        .call-header {
            color: var(--text-light);
            font-size: 2em;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .called-queue-code {
            color: var(--text-light);
            font-size: 5em;
            font-weight: 700;
            margin: 30px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .called-queue-code.flashing {
            animation: flash 0.5s ease-in-out 3;
        }

        @keyframes flash {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        .proceed-instruction {
            color: var(--text-light);
            font-size: 1.5em;
            margin-top: 20px;
            font-weight: 500;
            line-height: 1.5;
        }

        .station-instruction {
            background: rgba(255,255,255,0.2);
            padding: 15px 25px;
            border-radius: 25px;
            color: var(--text-light);
            font-weight: 600;
            margin-top: 20px;
            font-size: 1.3em;
        }

        .no-current-call {
            color: var(--text-light);
            font-size: 1.8em;
            font-weight: 500;
            opacity: 0.8;
        }

        /* Bottom DateTime */
        .datetime-bar {
            background: var(--primary-blue);
            color: var(--text-light);
            padding: 15px;
            text-align: center;
            font-size: 1.2em;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }

        .current-serving-card {
            background: var(--text-light);
            border-radius: var(--border-radius);
            padding: 3rem;
            box-shadow: var(--shadow);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .serving-header {
            color: var(--text-muted);
            font-size: 1.2rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .current-number {
            color: var(--error-red);
            font-size: 8rem;
            font-weight: 700;
            margin: 1rem 0;
            line-height: 1;
        }

        .proceed-message {
            color: var(--text-dark);
            font-size: 1.8rem;
            font-weight: 600;
            margin-top: 1.5rem;
        }

        .counter-info {
            background: var(--accent-blue);
            color: var(--primary-blue);
            padding: 1rem 2rem;
            border-radius: 50px;
            font-size: 2.5rem;
            font-weight: 700;
            margin-top: 1.5rem;
        }

        .next-patients {
            margin-top: 2rem;
        }

        .next-header {
            color: var(--text-muted);
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .next-list {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .next-number {
            background: var(--accent-blue);
            color: var(--primary-blue);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
        }

        .no-current-serving {
            text-align: center;
            color: var(--text-muted);
            font-size: 1.5rem;
            padding: 3rem;
        }

        .stats-bar {
            background: var(--text-light);
            padding: 1rem 2rem;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: center;
            gap: 3rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .waiting .stat-number { color: var(--secondary-blue); }
        .in-progress .stat-number { color: var(--warning-orange); }
        .completed .stat-number { color: var(--success-green); }

        /* Station List Styles */
        .stations-container {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .currently-called {
            background: linear-gradient(135deg, var(--warning-orange), #ffd700);
            color: var(--text-dark);
            padding: 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .currently-called h2 {
            margin: 0 0 1rem 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .currently-called .queue-code {
            font-size: 3rem;
            font-weight: 900;
            margin: 1rem 0;
        }

        .currently-called .station-info {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .stations-list {
            background: var(--text-light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .stations-header {
            background: var(--primary-blue);
            color: var(--text-light);
            padding: 1.5rem 2rem;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
        }

        .station-row {
            display: flex;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-light);
            transition: background-color 0.3s ease;
        }

        .station-row:last-child {
            border-bottom: none;
        }

        .station-row:hover {
            background: var(--accent-blue);
        }

        .station-row.active {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-left: 5px solid var(--warning-orange);
        }

        .station-info {
            flex: 1;
        }

        .station-id {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .station-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }

        .station-assignment {
            font-size: 1rem;
            color: var(--text-muted);
        }

        .queue-status {
            text-align: right;
            min-width: 200px;
        }

        .queue-code {
            font-size: 2rem;
            font-weight: 900;
            color: var(--primary-blue);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .header-right {
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .called-queue-code {
                font-size: 4rem;
            }
        }
    </style>
</head>

<body>
    <div class="display-container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="facility-logo">
                    <i class="fas fa-hospital"></i>
                </div>
                <div class="facility-info">
                    <h1>City Health Office of Koronadal</h1>
                    <div class="subtitle">
                        <i class="fas fa-flask"></i>
                        Laboratory Services
                    </div>
                </div>
            </div>
            <div class="header-right">
                <div class="current-time" id="current-time"><?php echo $current_time; ?></div>
                <div class="current-date"><?php echo $display_date; ?></div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Full Width - Current Call Display -->
            <div class="call-display">
                <?php 
                // Find the most recently called patient (the one currently in progress)
                $current_call = null;
                foreach ($stations as $station) {
                    if ($station['current_queue_code']) {
                        $current_call = $station;
                        break; // Get the first one found (most recent)
                    }
                }
                
                if ($current_call): ?>
                    <div class="call-header">
                        <i class="fas fa-bullhorn"></i> NOW CALLING
                    </div>
                    <div class="called-queue-code flashing" id="calledQueueCode">
                        <?php echo htmlspecialchars($current_call['current_queue_code']); ?>
                    </div>
                    <div class="proceed-instruction">
                        Please proceed to
                    </div>
                    <div class="station-instruction">
                        #<?php echo $current_call['station_id']; ?> - <?php echo htmlspecialchars($current_call['station_name']); ?> for Laboratory
                    </div>
                <?php else: ?>
                    <div class="no-current-call">
                        <i class="fas fa-clock"></i><br>
                        No patients currently being called
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        let lastCalledQueue = '';

        // Update current time and date every second
        function updateDateTime() {
            const now = new Date();
            const datetime = now.getFullYear() + '-' + 
                String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                String(now.getDate()).padStart(2, '0') + ' ' + 
                String(now.getHours()).padStart(2, '0') + ':' + 
                String(now.getMinutes()).padStart(2, '0') + ':' + 
                String(now.getSeconds()).padStart(2, '0');
            
            const datetimeBar = document.getElementById('datetimeBar');
            if (datetimeBar) {
                datetimeBar.textContent = datetime;
            }

            const currentTimeElement = document.getElementById('current-time');
            if (currentTimeElement) {
                const timeString = String(now.getHours()).padStart(2, '0') + ':' + 
                    String(now.getMinutes()).padStart(2, '0') + ':' + 
                    String(now.getSeconds()).padStart(2, '0');
                currentTimeElement.textContent = timeString;
            }
        }

        // Check for new queue calls and trigger flash
        function checkNewCalls() {
            const calledElement = document.getElementById('calledQueueCode');
            if (calledElement) {
                const currentQueue = calledElement.textContent.trim();
                if (currentQueue && currentQueue !== lastCalledQueue) {
                    // New call detected, trigger flash
                    calledElement.classList.remove('flashing');
                    // Force reflow
                    void calledElement.offsetWidth;
                    calledElement.classList.add('flashing');
                    lastCalledQueue = currentQueue;
                }
            }
        }

        // Auto-refresh the page every 10 seconds to get latest data
        function autoRefresh() {
            window.location.reload();
        }

        // Update time every second
        setInterval(updateDateTime, 1000);
        
        // Check for new calls every 5 seconds
        setInterval(checkNewCalls, 5000);
        
        // Auto-refresh every 10 seconds
        setInterval(autoRefresh, 10000);

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Laboratory Queue Display initialized with new layout');
            updateDateTime();
            
            // Set initial last called queue
            const calledElement = document.getElementById('calledQueueCode');
            if (calledElement) {
                lastCalledQueue = calledElement.textContent.trim();
            }
        });
    </script>
</body>
</html>