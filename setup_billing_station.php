<?php
/**
 * Setup Script for Billing Stations
 * This script creates billing stations if none exist and assigns admin access
 */

// Include database connection
require_once 'config/db.php';

$message = '';
$error = '';

try {
    // Check if billing stations exist
    $check_query = "SELECT COUNT(*) as count FROM stations WHERE station_type = 'billing'";
    $stmt = $pdo->prepare($check_query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        // Create default billing stations
        $stations = [
            ['Billing Station 1', 'Primary billing and payment processing station'],
            ['Billing Station 2', 'Secondary billing station for peak hours'],
            ['Cashier Station', 'Main cashier station for payments and receipts']
        ];
        
        foreach ($stations as $station_data) {
            $insert_query = "INSERT INTO stations (station_name, station_type, station_description, is_active, created_at) 
                           VALUES (?, 'billing', ?, 1, NOW())";
            $stmt = $pdo->prepare($insert_query);
            $stmt->execute([$station_data[0], $station_data[1]]);
        }
        
        $message = "Successfully created " . count($stations) . " billing stations!";
        
        // Get admin users and assign to first billing station
        $admin_query = "SELECT employee_id FROM employees WHERE role = 'admin' AND status = 'active'";
        $stmt = $pdo->prepare($admin_query);
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get the first billing station
        $station_query = "SELECT station_id FROM stations WHERE station_type = 'billing' ORDER BY station_id LIMIT 1";
        $stmt = $pdo->prepare($station_query);
        $stmt->execute();
        $station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($station && !empty($admins)) {
            foreach ($admins as $admin) {
                // Check if assignment already exists
                $check_assignment = "SELECT assignment_id FROM station_assignments 
                                   WHERE employee_id = ? AND station_id = ? AND status = 'active'";
                $stmt = $pdo->prepare($check_assignment);
                $stmt->execute([$admin['employee_id'], $station['station_id']]);
                
                if (!$stmt->fetch()) {
                    $assignment_query = "INSERT INTO station_assignments 
                                       (employee_id, station_id, assigned_date, status, created_at) 
                                       VALUES (?, ?, CURDATE(), 'active', NOW())";
                    $stmt = $pdo->prepare($assignment_query);
                    $stmt->execute([$admin['employee_id'], $station['station_id']]);
                }
            }
            $message .= " Admin users have been assigned to the main billing station.";
        }
        
    } else {
        $message = "Billing stations already exist (" . $result['count'] . " stations found).";
        
        // Check if admin has access to any billing station
        $admin_check = "SELECT COUNT(*) as count FROM station_assignments sa 
                       JOIN stations s ON sa.station_id = s.station_id 
                       JOIN employees e ON sa.employee_id = e.employee_id 
                       WHERE s.station_type = 'billing' AND e.role = 'admin' AND sa.status = 'active'";
        $stmt = $pdo->prepare($admin_check);
        $stmt->execute();
        $admin_access = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin_access['count'] == 0) {
            // Assign admin to first billing station
            $admin_query = "SELECT employee_id FROM employees WHERE role = 'admin' AND status = 'active' LIMIT 1";
            $stmt = $pdo->prepare($admin_query);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $station_query = "SELECT station_id FROM stations WHERE station_type = 'billing' AND is_active = 1 LIMIT 1";
            $stmt = $pdo->prepare($station_query);
            $stmt->execute();
            $station = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && $station) {
                $assignment_query = "INSERT INTO station_assignments 
                                   (employee_id, station_id, assigned_date, status, created_at) 
                                   VALUES (?, ?, CURDATE(), 'active', NOW())";
                $stmt = $pdo->prepare($assignment_query);
                $stmt->execute([$admin['employee_id'], $station['station_id']]);
                $message .= " Admin access has been granted to billing stations.";
            }
        }
    }
    
} catch (Exception $e) {
    $error = "Error setting up billing stations: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Station Setup - CHO Koronadal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            color: #27ae60;
            background: #d5f4e6;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #27ae60;
        }
        .error {
            color: #e74c3c;
            background: #fadbd8;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #e74c3c;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .info {
            background: #d6eaf8;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏥 Billing Station Setup</h1>
        <p>This setup script ensures that billing stations are properly configured in the system.</p>
        
        <?php if ($message): ?>
        <div class="success">
            <strong>✅ Success:</strong> <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="error">
            <strong>❌ Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="info">
            <strong>ℹ️ What this setup does:</strong>
            <ul>
                <li>Creates default billing stations if none exist</li>
                <li>Assigns admin users to billing stations for access</li>
                <li>Ensures proper station configuration for billing operations</li>
            </ul>
        </div>
        
        <div>
            <a href="pages/queueing/billing_station.php" class="btn">🏥 Go to Billing Station</a>
            <a href="pages/queueing/billing_station.php?debug=1" class="btn">🔍 Debug Mode</a>
            <a href="pages/management/admin/dashboard.php" class="btn">📊 Admin Dashboard</a>
        </div>
    </div>
</body>
</html>