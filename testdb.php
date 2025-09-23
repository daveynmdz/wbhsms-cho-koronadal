<?php
// Set to 'local' or 'remote' to test each connection
$test = 'remote'; // change to 'remote' to test remote server

require_once __DIR__ . '/config/env.php';

$connection_status = '';
$connection_type = '';
$connection_details = array();

if ($test === 'local') {
    if (file_exists(__DIR__ . '/config/.env.local')) {
        loadEnv(__DIR__ . '/config/.env.local');
        $connection_type = 'LOCAL';
    } else {
        $connection_status = 'error';
        $connection_type = 'LOCAL';
        $error_message = '.env.local file not found';
    }
} else {
    if (file_exists(__DIR__ . '/config/.env')) {
        loadEnv(__DIR__ . '/config/.env');
        $connection_type = 'REMOTE';
    } else {
        $connection_status = 'error';
        $connection_type = 'REMOTE';
        $error_message = '.env file not found';
    }
}

if (empty($connection_status)) {
    $host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost';
    $port = isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : '3306';
    $db   = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '';
    $user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : '';
    $pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : '';
    $charset = 'utf8mb4';

    $connection_details = array(
        'Host' => $host,
        'Port' => $port,
        'Database' => $db,
        'Username' => $user,
        'Charset' => $charset
    );

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass);
        $connection_status = 'success';
        
        // Get additional database info
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        $connection_details['MySQL Version'] = $version;
        
    } catch (PDOException $e) {
        $connection_status = 'error';
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .header {
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #2563eb;
        }

        h1 {
            color: #1f2937;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 30px;
        }

        .status-card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px solid #e5e7eb;
        }

        .status-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .status-success {
            border-color: #10b981;
            background: #ecfdf5;
        }

        .status-success .status-icon {
            color: #10b981;
        }

        .status-error {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .status-error .status-icon {
            color: #ef4444;
        }

        .status-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .status-success .status-title {
            color: #065f46;
        }

        .status-error .status-title {
            color: #991b1b;
        }

        .status-message {
            font-size: 1rem;
            line-height: 1.5;
        }

        .status-success .status-message {
            color: #047857;
        }

        .status-error .status-message {
            color: #dc2626;
        }

        .connection-details {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
            text-align: left;
        }

        .details-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 15px;
            text-align: center;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: #6b7280;
        }

        .detail-value {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #374151;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
            border: 2px solid #2563eb;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: #6b7280;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            transform: translateY(-2px);
        }

        .btn-refresh {
            background: #059669;
            color: white;
            border: 2px solid #059669;
        }

        .btn-refresh:hover {
            background: #047857;
            border-color: #047857;
            transform: translateY(-2px);
        }

        .connection-type {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }

            h1 {
                font-size: 1.8rem;
            }

            .actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                justify-content: center;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-database"></i>
            </div>
            <h1>Database Connection Test</h1>
            <p class="subtitle">CHO Koronadal System</p>
        </div>

        <div class="connection-type">
            Testing <?php echo $connection_type; ?> Connection
        </div>

        <div class="status-card <?php echo $connection_status === 'success' ? 'status-success' : 'status-error'; ?>">
            <div class="status-icon">
                <?php if ($connection_status === 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-times-circle"></i>
                <?php endif; ?>
            </div>
            
            <div class="status-title">
                <?php if ($connection_status === 'success'): ?>
                    Connection Successful!
                <?php else: ?>
                    Connection Failed
                <?php endif; ?>
            </div>
            
            <div class="status-message">
                <?php if ($connection_status === 'success'): ?>
                    Database connection established successfully. All systems are operational.
                <?php else: ?>
                    <?php echo isset($error_message) ? htmlspecialchars($error_message) : 'Unknown error occurred'; ?>
                <?php endif; ?>
            </div>

            <?php if ($connection_status === 'success' && !empty($connection_details)): ?>
                <div class="connection-details">
                    <div class="details-title">Connection Details</div>
                    <?php foreach ($connection_details as $label => $value): ?>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo htmlspecialchars($label); ?>:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($value); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="actions">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Back to Home
            </a>
            <a href="testdb.php" class="btn btn-refresh">
                <i class="fas fa-sync-alt"></i> Refresh Test
            </a>
            <a href="pages/auth/patient_login.php" class="btn btn-secondary">
                <i class="fas fa-user"></i> Patient Login
            </a>
        </div>
    </div>
</body>
</html>