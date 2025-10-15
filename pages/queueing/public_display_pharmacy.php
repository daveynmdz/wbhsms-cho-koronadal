<?php
/**
 * Public Pharmacy Queue Display - Waiting Area Display
 * Purpose: Public display showing pharmacy queue status for patients in waiting area
 */

// Include database connection and queue management service
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

// Initialize queue management service
$queueService = new QueueManagementService($pdo);

// Include queue code formatter helper
require_once __DIR__ . '/queue_code_formatter.php';

// Get current date for display
$current_date = date('Y-m-d');
$display_date = date('F j, Y');
$current_time = date('g:i A');

// Get all pharmacy stations with current assignments and queue data for CHO (facility_id = 1)
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
    WHERE s.station_type = 'pharmacy'
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
    WHERE s.station_type = 'pharmacy'
    AND DATE(qe.time_in) = ?
    AND qe.time_started IS NOT NULL
    ORDER BY qe.time_started DESC
    LIMIT 20
";

$stmt_called = $pdo->prepare($called_queue_query);
$stmt_called->execute([$current_date]);
$called_queues = $stmt_called->fetchAll(PDO::FETCH_ASSOC);

// Get overall pharmacy statistics for today
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
    <title>Pharmacy Queue Display - CHO Koronadal</title>
    
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
            padding: 36px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(2, 1fr);
            grid-column-gap: 10px;
            grid-row-gap: 10px;
            height: calc(100vh - 200px);
        }

        /* Left Side - Grid Layout */
        .div1 {
            grid-area: 1 / 1 / 3 / 3;
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 10px;
        }

        .div2 {
            grid-area: 1 / 3 / 3 / 5;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .div3, .div4 {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .div3 {
            grid-area: 1 / 1 / 2 / 3;
        }

        .div4 {
            grid-area: 2 / 1 / 3 / 3;
        }

        .pharmacy-display {
            text-align: center;
        }

        .pharmacy-station-title {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 20px;
            border-bottom: 2px solid var(--accent-blue);
            padding-bottom: 10px;
        }

        .display-numbers {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
        }

        .ticket-number {
            font-size: 4em;
            font-weight: 900;
            color: var(--warning-orange);
            font-family: 'Courier New', monospace;
            background: rgba(251, 188, 4, 0.1);
            padding: 15px 20px;
            border-radius: 10px;
            border: 2px solid var(--warning-orange);
            min-width: 200px;
        }

        .arrow {
            font-size: 3em;
            color: var(--primary-blue);
            font-weight: bold;
        }

        .counter-number {
            font-size: 4em;
            font-weight: 900;
            color: var(--primary-blue);
            font-family: 'Courier New', monospace;
            background: var(--accent-blue);
            padding: 15px 20px;
            border-radius: 10px;
            border: 2px solid var(--primary-blue);
            min-width: 100px;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
            }
            
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
            .current-number {
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
                        <i class="fas fa-pills"></i>
                        Pharmacy Services
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
            <!-- Left Side - Pharmacy Stations Grid -->
            <div class="div1">
                <?php 
                // Ensure we have exactly 2 pharmacy stations displayed
                $pharmacy_stations = array_slice($stations, 0, 2);
                $station_titles = ['PHARMACY 1', 'PHARMACY 2'];
                
                for ($i = 0; $i < 2; $i++): 
                    $station = isset($pharmacy_stations[$i]) ? $pharmacy_stations[$i] : null;
                    $div_class = 'div' . ($i + 3); // div3, div4
                ?>
                    <div class="<?php echo $div_class; ?>">
                        <div class="pharmacy-display">
                            <div class="pharmacy-station-title">
                                <i class="fas fa-pills"></i> 
                                <?php echo $station_titles[$i]; ?>
                            </div>
                            
                            <div class="display-numbers">
                                <div class="ticket-number">
                                    <?php 
                                    if ($station && $station['current_queue_code']):
                                        // Display the complete queue code (e.g., "08A-001")
                                        echo htmlspecialchars($station['current_queue_code']);
                                    else:
                                        echo "---";
                                    endif;
                                    ?>
                                </div>
                                <div class="arrow">→</div>
                                <div class="counter-number"><?php echo ($i + 1); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Right Side - Current Call Display -->
            <div class="div2">
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
                        #<?php echo $current_call['station_id']; ?> - <?php echo htmlspecialchars($current_call['station_name']); ?> for Pharmacy
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
            console.log('Pharmacy Queue Display initialized with new layout');
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