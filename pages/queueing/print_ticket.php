<?php
/**
 * Print Queue Ticket Page
 * Purpose: Generate printable queue tickets for patients
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

$employee_id = $_SESSION['employee_id'];
$queue_entry_id = $_GET['queue_entry_id'] ?? null;
$ticket_data = null;
$error = '';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please contact administrator.");
}

// Validate queue_entry_id
if (empty($queue_entry_id) || !is_numeric($queue_entry_id)) {
    $error = "Invalid queue entry ID provided";
} else {
    try {
        // Fetch queue entry data with related information
        $stmt = $conn->prepare("
            SELECT qe.queue_entry_id, qe.queue_number, qe.queue_type, qe.priority_level,
                   qe.status, qe.created_at, qe.time_in,
                   p.first_name, p.last_name, p.patient_number, p.isPWD, p.isSenior,
                   s.name as service_name, s.description as service_description,
                   f.name as facility_name, f.type as facility_type,
                   a.scheduled_date, a.scheduled_time, a.appointment_id,
                   (SELECT COUNT(*) FROM queue_entries qe2 
                    WHERE qe2.queue_type = qe.queue_type 
                    AND DATE(qe2.created_at) = DATE(qe.created_at)
                    AND qe2.queue_number < qe.queue_number 
                    AND qe2.status IN ('waiting', 'arrived')) as position_in_queue
            FROM queue_entries qe
            INNER JOIN patients p ON qe.patient_id = p.patient_id
            INNER JOIN services s ON qe.service_id = s.service_id
            INNER JOIN appointments a ON qe.appointment_id = a.appointment_id
            INNER JOIN facilities f ON a.facility_id = f.facility_id
            WHERE qe.queue_entry_id = ?
        ");
        
        $stmt->bind_param("i", $queue_entry_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket_data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$ticket_data) {
            $error = "Queue entry not found";
        }
        
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// If there's an error, show error page
if (!empty($error) || !$ticket_data) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Print Error - CHO Koronadal</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                padding: 50px;
                background: #f8f9fa;
            }
            .error-container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                max-width: 400px;
                margin: 0 auto;
            }
            .error-icon {
                color: #dc3545;
                font-size: 3rem;
                margin-bottom: 1rem;
            }
            h1 {
                color: #dc3545;
                margin-bottom: 1rem;
            }
            p {
                color: #6c757d;
                margin-bottom: 1.5rem;
            }
            .btn {
                background: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                text-decoration: none;
                cursor: pointer;
            }
            .btn:hover {
                background: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Print Error</h1>
            <p><?php echo htmlspecialchars($error); ?></p>
            <a href="checkin.php" class="btn">Return to Check-in</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Calculate priority information
$is_priority = ($ticket_data['isPWD'] || $ticket_data['isSenior']);
$priority_text = '';
if ($is_priority) {
    $priority_reasons = [];
    if ($ticket_data['isPWD']) $priority_reasons[] = 'PWD';
    if ($ticket_data['isSenior']) $priority_reasons[] = 'Senior';
    $priority_text = implode(' + ', $priority_reasons);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Ticket #<?php echo htmlspecialchars($ticket_data['queue_number']); ?> - CHO Koronadal</title>
    <style>
        /* Print-specific styles */
        @media print {
            @page {
                margin: 0.5in;
                size: A5 portrait;
            }
            
            .no-print {
                display: none !important;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            line-height: 1.4;
            color: #000;
            background: white;
            font-size: 12pt;
            padding: 20px;
        }

        .ticket-container {
            max-width: 400px;
            margin: 0 auto;
            border: 2px solid #0077b6;
            border-radius: 10px;
            padding: 30px 20px;
            text-align: center;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .header {
            border-bottom: 2px solid #0077b6;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .clinic-title {
            font-size: 18pt;
            font-weight: bold;
            color: #0077b6;
            margin-bottom: 5px;
            font-family: Arial, sans-serif;
        }

        .clinic-subtitle {
            font-size: 14pt;
            color: #0077b6;
            margin-bottom: 5px;
            font-family: Arial, sans-serif;
        }

        .ticket-title {
            font-size: 16pt;
            font-weight: bold;
            color: #333;
            margin-top: 10px;
        }

        .queue-number {
            font-size: 48pt;
            font-weight: bold;
            color: #0077b6;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            font-family: Arial, sans-serif;
        }

        .queue-label {
            font-size: 14pt;
            color: #666;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .patient-info {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            align-items: center;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: bold;
            color: #333;
            font-size: 11pt;
            text-align: left;
            min-width: 120px;
        }

        .info-value {
            color: #0077b6;
            font-weight: bold;
            font-size: 11pt;
            text-align: right;
            flex: 1;
        }

        .priority-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 10pt;
            font-weight: bold;
            margin: 15px auto;
            display: inline-block;
            text-transform: uppercase;
        }

        .instructions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
            font-size: 10pt;
            color: #666;
            line-height: 1.5;
        }

        .timestamp {
            margin-top: 15px;
            font-size: 9pt;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        .control-buttons {
            margin: 20px 0;
            text-align: center;
        }

        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            margin: 0 5px;
            font-size: 11pt;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        /* Mobile responsive */
        @media (max-width: 480px) {
            .ticket-container {
                margin: 10px;
                padding: 20px 15px;
            }
            
            .queue-number {
                font-size: 36pt;
            }
            
            .info-row {
                flex-direction: column;
                text-align: center;
                gap: 5px;
            }
            
            .info-label, .info-value {
                text-align: center;
                min-width: unset;
            }
        }
    </style>
</head>

<body>
    <div class="ticket-container">
        <!-- Header -->
        <div class="header">
            <div class="clinic-title">CHO KORONADAL</div>
            <div class="clinic-subtitle">City Health Office</div>
            <div class="ticket-title">QUEUE TICKET</div>
        </div>

        <!-- Queue Number -->
        <div class="queue-label">Your Queue Number</div>
        <div class="queue-number">#<?php echo htmlspecialchars($ticket_data['queue_number']); ?></div>

        <!-- Priority Badge -->
        <?php if ($is_priority): ?>
            <div class="priority-badge">
                ⭐ PRIORITY PATIENT
                <?php if ($priority_text): ?>
                    <br><small>(<?php echo htmlspecialchars($priority_text); ?>)</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Patient Information -->
        <div class="patient-info">
            <div class="info-row">
                <span class="info-label">Patient:</span>
                <span class="info-value"><?php echo htmlspecialchars($ticket_data['first_name'] . ' ' . $ticket_data['last_name']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Patient #:</span>
                <span class="info-value"><?php echo htmlspecialchars($ticket_data['patient_number']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Service:</span>
                <span class="info-value"><?php echo htmlspecialchars($ticket_data['service_name']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Facility:</span>
                <span class="info-value"><?php echo htmlspecialchars($ticket_data['facility_name']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value"><?php echo date('M j, Y', strtotime($ticket_data['scheduled_date'])); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Time:</span>
                <span class="info-value"><?php echo date('g:i A', strtotime($ticket_data['scheduled_time'])); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Position:</span>
                <span class="info-value">#<?php echo (int)$ticket_data['position_in_queue'] + 1; ?> in queue</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Queue Type:</span>
                <span class="info-value"><?php echo ucfirst(htmlspecialchars($ticket_data['queue_type'])); ?></span>
            </div>
        </div>

        <!-- Instructions -->
        <div class="instructions">
            <strong>IMPORTANT INSTRUCTIONS:</strong><br>
            • Please wait for your number to be called<br>
            • Keep this ticket with you at all times<br>
            • Report to the designated service area when called<br>
            • Present this ticket to the healthcare provider<br>
            <?php if ($is_priority): ?>
            • <strong>Priority patients will be called first</strong><br>
            <?php endif; ?>
        </div>

        <!-- Timestamp -->
        <div class="timestamp">
            Printed: <?php echo date('M j, Y g:i A'); ?><br>
            Appointment ID: <?php echo htmlspecialchars($ticket_data['appointment_id']); ?><br>
            Ticket ID: <?php echo htmlspecialchars($ticket_data['queue_entry_id']); ?>
        </div>

        <!-- Control Buttons (hidden when printing) -->
        <div class="control-buttons no-print">
            <button onclick="window.print()" class="btn">
                🖨️ Print Ticket
            </button>
            <a href="checkin.php" class="btn btn-secondary">
                ← Back to Check-in
            </a>
        </div>
    </div>

    <script>
        // Auto-print when page loads
        window.addEventListener('load', function() {
            // Small delay to ensure page is fully rendered
            setTimeout(function() {
                window.print();
            }, 500);
        });

        // Handle after print actions
        window.addEventListener('afterprint', function() {
            // Optional: You can add logic here to close the window or redirect
            console.log('Print dialog closed');
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl+P to print
            if (event.ctrlKey && event.key === 'p') {
                event.preventDefault();
                window.print();
            }
            
            // Escape to go back
            if (event.key === 'Escape') {
                window.location.href = 'checkin.php';
            }
        });
    </script>
</body>
</html>