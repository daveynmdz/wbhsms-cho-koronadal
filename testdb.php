<?php
/**
 * Simple Database Connection Test for XAMPP
 * CHO Koronadal WBHSMS
 */

try {
    require_once __DIR__ . '/config/db.php';
    
    $connection_status = 'success';
    $error_message = '';
    
    // Test PDO connection
    $stmt = $pdo->query("SELECT VERSION() as version, NOW() as current_time");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $mysql_version = $result['version'];
    $current_time = $result['current_time'];
    
    // Test MySQLi connection
    $mysqli_result = $conn->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '{$_ENV['DB_NAME']}'");
    $table_info = $mysqli_result->fetch_assoc();
    $table_count = $table_info['table_count'];
    
} catch (Exception $e) {
    $connection_status = 'error';
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Test - CHO Koronadal WBHSMS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            color: #27ae60;
            border: 2px solid #27ae60;
            background: #d5f4e6;
        }
        .error {
            color: #e74c3c;
            border: 2px solid #e74c3c;
            background: #fdf2f2;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
            font-weight: bold;
        }
        .details {
            margin-top: 20px;
        }
        .detail-row {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏥 CHO Koronadal WBHSMS</h1>
        <h2>Database Connection Test</h2>
        
        <?php if ($connection_status === 'success'): ?>
            <div class="status success">
                ✅ Database Connection Successful!
            </div>
            
            <div class="details">
                <h3>Connection Details:</h3>
                <div class="detail-row">
                    <span class="detail-label">Host:</span>
                    <?= htmlspecialchars($_ENV['DB_HOST'] ?? 'localhost') ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Database:</span>
                    <?= htmlspecialchars($_ENV['DB_NAME'] ?? 'wbhsms_cho') ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">MySQL Version:</span>
                    <?= htmlspecialchars($mysql_version) ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tables Found:</span>
                    <?= $table_count ?>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Current Time:</span>
                    <?= htmlspecialchars($current_time) ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="index.php" class="btn">🏠 Go to Homepage</a>
                <a href="pages/patient/auth/patient_login.php" class="btn">👤 Patient Login</a>
            </div>
            
        <?php else: ?>
            <div class="status error">
                ❌ Database Connection Failed
            </div>
            
            <div class="details">
                <h3>Error Details:</h3>
                <p><strong>Error:</strong> <?= htmlspecialchars($error_message) ?></p>
                
                <h3>XAMPP Setup Instructions:</h3>
                <ol>
                    <li>Make sure XAMPP is running (Apache + MySQL)</li>
                    <li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>
                    <li>Create database named 'wbhsms_cho'</li>
                    <li>Import the file: database/wbhsms_cho.sql</li>
                    <li>Copy .env.example to .env and update settings if needed</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">
            <p>City Health Office of Koronadal - Web-Based Healthcare Services Management System</p>
        </div>
    </div>
</body>
</html>