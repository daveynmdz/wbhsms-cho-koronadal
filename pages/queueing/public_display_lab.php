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
$queueService = new QueueManagementService($conn);

// Get current date for display
$current_date = date('Y-m-d');
$display_date = date('F j, Y');
$current_time = date('g:i A');

// Get laboratory stations with current assignments and queue data for CHO (facility_id = 1)
$stations_query = "
    SELECT DISTINCT
        s.station_id,
        s.station_name,
        s.service_id,
        s.station_type,
        s.station_number,
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
    AND s.is_open = 1
    AND fs.facility_id = 1
    ORDER BY s.station_number
";

$stmt = $conn->prepare($stations_query);
$stmt->bind_param("ssssssss", $current_date, $current_date, $current_date, $current_date, $current_date, $current_date, $current_date, $current_date);
$stmt->execute();
$result = $stmt->get_result();
$stations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
            display: flex;
            background: #f8f9fa;
        }

        .sidebar {
            width: 300px;
            background: var(--text-light);
            border-right: 1px solid var(--border-light);
            padding: 2rem;
        }

        .sidebar h2 {
            color: var(--text-dark);
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
        }

        .queue-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .queue-item {
            background: var(--primary-blue);
            color: var(--text-light);
            padding: 1.5rem 1rem;
            border-radius: var(--border-radius);
            text-align: center;
            font-weight: 600;
        }

        .queue-item.waiting {
            background: var(--secondary-blue);
        }

        .queue-item.completed {
            background: var(--text-muted);
            opacity: 0.7;
        }

        .queue-item .station-name {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .queue-item .queue-number {
            font-size: 2rem;
            font-weight: 700;
        }

        .display-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                order: 2;
                padding: 1rem;
            }
            
            .display-area {
                padding: 1.5rem;
            }
            
            .current-number {
                font-size: 6rem;
            }
            
            .counter-info {
                font-size: 2rem;
                padding: 0.75rem 1.5rem;
            }
            
            .stats-bar {
                gap: 2rem;
                padding: 1rem;
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
            
            .proceed-message {
                font-size: 1.4rem;
            }
            
            .counter-info {
                font-size: 1.6rem;
            }
            
            .stats-bar {
                flex-direction: column;
                gap: 1rem;
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
            <!-- Sidebar with waiting queue -->
            <div class="sidebar">
                <h2>Waiting Queue</h2>
                <div class="queue-list">
                    <?php 
                    $waiting_count = 0;
                    foreach ($stations as $station): 
                        if ($station['waiting_count'] > 0):
                            $waiting_count += $station['waiting_count'];
                    ?>
                        <div class="queue-item waiting">
                            <div class="station-name"><?php echo htmlspecialchars($station['station_name']); ?></div>
                            <div class="queue-number"><?php echo $station['waiting_count']; ?></div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    if ($waiting_count == 0):
                    ?>
                        <div class="queue-item completed">
                            <div class="station-name">No Patients</div>
                            <div class="queue-number">0</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Display Area -->
            <div class="display-area">
                <?php 
                $current_serving = null;
                foreach ($stations as $station) {
                    if ($station['current_queue_code']) {
                        $current_serving = $station;
                        break;
                    }
                }
                
                if ($current_serving): ?>
                    <div class="current-serving-card">
                        <div class="serving-header">Now Serving</div>
                        <div class="current-number"><?php echo htmlspecialchars($current_serving['current_queue_code']); ?></div>
                        <div class="proceed-message">Please Proceed To</div>
                        <div class="counter-info"><?php echo htmlspecialchars($current_serving['station_name']); ?></div>
                        
                        <?php 
                        // Get next 3 queue numbers
                        $next_patients = [];
                        foreach ($stations as $station) {
                            if ($station['next_queue_code']) {
                                $next_patients[] = $station['next_queue_code'];
                            }
                        }
                        if (!empty($next_patients)): 
                        ?>
                            <div class="next-patients">
                                <div class="next-header">Next in Queue</div>
                                <div class="next-list">
                                    <?php foreach (array_slice($next_patients, 0, 3) as $next): ?>
                                        <div class="next-number"><?php echo htmlspecialchars($next); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-current-serving">
                        <i class="fas fa-clock"></i>
                        <div>No Patient Currently Being Served</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Bar -->
        <div class="stats-bar">
            <div class="stat-item waiting">
                <span class="stat-number"><?php echo $total_waiting; ?></span>
                <span class="stat-label">Waiting</span>
            </div>
            <div class="stat-item in-progress">
                <span class="stat-number"><?php echo $total_in_progress; ?></span>
                <span class="stat-label">In Progress</span>
            </div>
            <div class="stat-item completed">
                <span class="stat-number"><?php echo $total_completed; ?></span>
                <span class="stat-label">Completed</span>
            </div>
    </div>

    <script>
        // Update current time every second
        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            document.getElementById('current-time').textContent = timeString;
        }

        // Update time every second
        setInterval(updateCurrentTime, 1000);

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Laboratory Queue Display initialized');
        });
    </script>
</body>
</html>