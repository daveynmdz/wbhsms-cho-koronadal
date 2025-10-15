<?php
/**
 * Public Consultation Queue Display - Waiting Area Display
 * Purpose: Public display showing consultation queue status for patients in waiting area
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

// Get all consultation stations with current assignments and queue data for CHO (facility_id = 1)
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
    WHERE s.station_type = 'consultation'
    AND s.is_active = 1 
    AND fs.facility_id = 1
    ORDER BY s.station_number
";

$stmt = $pdo->prepare($stations_query);
$stmt->execute([$current_date, $current_date, $current_date, $current_date, $current_date, $current_date, $current_date, $current_date]);
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all called queue entries for today for carousel (last 5 recent calls)
$called_queue_query = "
    SELECT 
        qe.queue_code,
        qe.status,
        qe.time_started,
        qe.time_completed,
        s.station_name,
        s.station_id,
        s.station_number,
        CASE 
            WHEN qe.status = 'done' THEN 'Complete'
            WHEN qe.status = 'in_progress' THEN 'In Progress'
            WHEN qe.status = 'skipped' THEN 'Skipped'
            ELSE 'Waiting'
        END as status_display,
        CASE 
            WHEN qe.status = 'done' THEN 'success'
            WHEN qe.status = 'in_progress' THEN 'warning'
            WHEN qe.status = 'skipped' THEN 'danger'
            ELSE 'secondary'
        END as status_class
    FROM queue_entries qe
    JOIN stations s ON qe.station_id = s.station_id
    WHERE s.station_type = 'consultation'
    AND DATE(qe.time_in) = ?
    AND qe.time_started IS NOT NULL
    ORDER BY qe.time_started DESC
    LIMIT 5
";

$stmt_called = $pdo->prepare($called_queue_query);
$stmt_called->execute([$current_date]);
$called_queues = $stmt_called->fetchAll(PDO::FETCH_ASSOC);

// Get overall consultation statistics for today
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
    <title>Consultation Queue Display - CHO Koronadal</title>
    
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
            grid-template-rows: repeat(4, 1fr);
            grid-column-gap: 10px;
            grid-row-gap: 10px;
            height: calc(100vh - 200px);
        }

        /* Grid Layout Areas */
        .div1 { grid-area: 1 / 1 / 2 / 2; }
        .div2 { grid-area: 2 / 1 / 3 / 2; }
        .div3 { grid-area: 3 / 1 / 4 / 2; }
        .div4 { grid-area: 4 / 1 / 5 / 2; }
        .div5 { grid-area: 4 / 2 / 5 / 3; }
        .div6 { grid-area: 4 / 3 / 5 / 4; }
        .div7 { grid-area: 4 / 4 / 5 / 5; }
        .div8 { grid-area: 1 / 2 / 4 / 5; }

        /* Consultation Station Styles */
        .div1, .div2, .div3, .div4, .div5, .div6, .div7 {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .consultation-display {
            text-align: center;
        }

        .consultation-station-title {
            font-size: 1.4em;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 15px;
            border-bottom: 2px solid var(--accent-blue);
            padding-bottom: 8px;
        }

        .display-numbers {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 10px;
        }

        .ticket-number {
            font-size: 3em;
            font-weight: 900;
            color: var(--warning-orange);
            font-family: 'Courier New', monospace;
            background: rgba(251, 188, 4, 0.1);
            padding: 10px 15px;
            border-radius: 8px;
            border: 2px solid var(--warning-orange);
            min-width: 150px;
        }

        .arrow {
            font-size: 2.5em;
            color: var(--primary-blue);
            font-weight: bold;
        }

        .counter-number {
            font-size: 3em;
            font-weight: 900;
            color: var(--primary-blue);
            font-family: 'Courier New', monospace;
            background: var(--accent-blue);
            padding: 10px 15px;
            border-radius: 8px;
            border: 2px solid var(--primary-blue);
            min-width: 80px;
        }

        /* Large Call Display */
        .div8 {
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
                padding: 20px;
                grid-template-columns: repeat(2, 1fr);
                grid-template-rows: repeat(6, 1fr);
                height: auto;
                min-height: calc(100vh - 200px);
            }
            
            .div1 { grid-area: 1 / 1 / 2 / 2; }
            .div2 { grid-area: 2 / 1 / 3 / 2; }
            .div3 { grid-area: 3 / 1 / 4 / 2; }
            .div4 { grid-area: 4 / 1 / 5 / 2; }
            .div5 { grid-area: 5 / 1 / 6 / 2; }
            .div6 { grid-area: 6 / 1 / 7 / 2; }
            .div7 { grid-area: 1 / 2 / 3 / 3; }
            .div8 { grid-area: 3 / 2 / 7 / 3; }
            
            .ticket-number, .counter-number {
                font-size: 2em;
                min-width: 100px;
            }
            
            .arrow {
                font-size: 1.8em;
            }
            
            .called-queue-code {
                font-size: 3.5em;
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
            .main-content {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(8, 1fr);
            }
            
            .div1 { grid-area: 1 / 1 / 2 / 2; }
            .div2 { grid-area: 2 / 1 / 3 / 2; }
            .div3 { grid-area: 3 / 1 / 4 / 2; }
            .div4 { grid-area: 4 / 1 / 5 / 2; }
            .div5 { grid-area: 5 / 1 / 6 / 2; }
            .div6 { grid-area: 6 / 1 / 7 / 2; }
            .div7 { grid-area: 7 / 1 / 8 / 2; }
            .div8 { grid-area: 8 / 1 / 9 / 2; }
            
            .called-queue-code {
                font-size: 2.5em;
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
                        <i class="fas fa-user-md"></i>
                        Consultation Services
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
            <?php 
            $currentCallQueue = null;
            $currentCallStation = null;
            
            // Find the first in-progress queue for display
            foreach ($stations as $station) {
                if ($station['current_queue_code']) {
                    $currentCallQueue = $station['current_queue_code'];
                    $currentCallStation = $station['station_name'];
                    break;
                }
            }
            
            // Display first 7 stations
            for ($i = 1; $i <= 7; $i++): 
                $station = isset($stations[$i-1]) ? $stations[$i-1] : null;
            ?>
                <div class="div<?php echo $i; ?>">
                    <div class="consultation-display">
                        <div class="consultation-station-title">
                            <?php if ($station): ?>
                                <i class="fas fa-user-md"></i> <?php echo htmlspecialchars($station['station_name']); ?>
                            <?php else: ?>
                                <i class="fas fa-ban"></i> INACTIVE
                            <?php endif; ?>
                        </div>
                        <div class="display-numbers">
                            <div class="ticket-number">
                                <?php echo $station && $station['current_queue_code'] ? htmlspecialchars($station['current_queue_code']) : '---'; ?>
                            </div>
                            <div class="arrow">→</div>
                            <div class="counter-number">
                                <?php echo $station ? $station['station_id'] : '--'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <!-- Large Call Display -->
            <div class="div8">
                <?php if ($currentCallQueue && $currentCallStation): ?>
                    <div class="call-header">
                        <i class="fas fa-bullhorn"></i> NOW CALLING
                    </div>
                    <div class="called-queue-code flashing">
                        <?php echo htmlspecialchars($currentCallQueue); ?>
                    </div>
                    <div class="proceed-instruction">
                        Please proceed to
                    </div>
                    <div class="station-instruction">
                        <?php echo htmlspecialchars($currentCallStation); ?>
                    </div>
                <?php else: ?>
                    <div class="no-current-call">
                        <i class="fas fa-clock"></i><br>
                        Waiting for next patient call...
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Update current time and date every second
        function updateDateTime() {
            const now = new Date();
            const currentTimeElement = document.getElementById('current-time');
            if (currentTimeElement) {
                const timeString = String(now.getHours()).padStart(2, '0') + ':' + 
                    String(now.getMinutes()).padStart(2, '0') + ':' + 
                    String(now.getSeconds()).padStart(2, '0');
                currentTimeElement.textContent = timeString;
            }
        }

        // Flash the currently called queue code
        function flashCalledQueue() {
            const calledQueueCode = document.querySelector('.called-queue-code');
            if (calledQueueCode) {
                calledQueueCode.classList.add('flashing');
                setTimeout(() => {
                    calledQueueCode.classList.remove('flashing');
                }, 1500); // Flash for 1.5 seconds
            }
        }

        // Auto-refresh the page every 10 seconds to get latest data
        function autoRefresh() {
            window.location.reload();
        }

        // Update time every second
        setInterval(updateDateTime, 1000);
        
        // Auto-refresh every 10 seconds
        setInterval(autoRefresh, 10000);

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Consultation Grid Display initialized');
            updateDateTime();
            
            // Flash the called queue after initial load
            setTimeout(flashCalledQueue, 1000);
        });
    </script>
</body>
</html>