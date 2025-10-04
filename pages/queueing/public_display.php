<?php
/**
 * Queueing - Public Display Page
 * Purpose: Waiting area display showing "now serving" information for patients
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include basic configuration (no session required for public display)
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/db.php';

// This page can be viewed without authentication as it's for public waiting areas

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Display - CHO Koronadal WBHSMS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        #queue-app {
            padding: 2rem;
            background: white;
            min-height: 90vh;
            border-radius: 15px;
            margin: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .placeholder-message {
            text-align: center;
            color: #6c757d;
            font-size: 1.5rem;
            margin-top: 3rem;
        }
        
        .now-serving {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .queue-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body>
    <div id="queue-app">
        <div class="queue-header">
            <h1><i class="fas fa-hospital"></i> CHO Koronadal</h1>
            <h2>Queue Display System</h2>
            <p class="text-muted">Waiting Area Information</p>
        </div>
        
        <div class="now-serving">
            <h3><i class="fas fa-bell"></i> Now Serving</h3>
            <div class="fs-1 fw-bold">---</div>
            <p>Please wait for your number to be called</p>
        </div>
        
        <div class="placeholder-message">
            <i class="fas fa-tv fa-3x mb-3"></i>
            <h3>Queueing — PUBLIC DISPLAY PAGE</h3>
            <p>(Implementation pending)</p>
            <p class="text-muted">This page will show real-time queue status for waiting patients</p>
        </div>
        
        <div class="row text-center mt-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-primary mb-3"></i>
                        <h5>Waiting</h5>
                        <span class="fs-3 fw-bold">--</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <i class="fas fa-user-check fa-2x text-success mb-3"></i>
                        <h5>Serving</h5>
                        <span class="fs-3 fw-bold">--</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-info mb-3"></i>
                        <h5>Completed</h5>
                        <span class="fs-3 fw-bold">--</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Queueing Module JS -->
    <script src="../../assets/js/queueing.js"></script>
    
    <script>
        // Auto-refresh for public display (placeholder)
        setInterval(function() {
            console.log('Auto-refresh queue display (placeholder)');
        }, 30000); // Refresh every 30 seconds
    </script>
</body>
</html>