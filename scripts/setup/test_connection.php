<?php
/**
 * Quick Database Connection Test
 * Tests both PDO and MySQLi connections to verify environment setup
 */
$root_path = dirname(__DIR__, 2);

// Include the database configuration
require_once $root_path . '/config/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 5px 0 0; }
    </style>
</head>
<body>
    <h1>🔍 Database Connection Test</h1>";

// Determine environment
$env_file = file_exists($root_path . '/.env.local') ? '.env.local (Local Dev)' : '.env (Production)';
echo "<div class='info'><strong>Environment:</strong> $env_file</div>";

// Test PDO Connection
echo "<h3>PDO Connection Test</h3>";
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT 
            CONNECTION_ID() as connection_id, 
            DATABASE() as database_name, 
            USER() as user_info,
            @@hostname as hostname,
            @@port as port
        ");
        $result = $stmt->fetch();
        
        echo "<div class='success'>
            <strong>✅ PDO Connection Successful!</strong><br>
            <strong>Database:</strong> " . htmlspecialchars($result['database_name']) . "<br>
            <strong>User:</strong> " . htmlspecialchars($result['user_info']) . "<br>
            <strong>Host:</strong> " . htmlspecialchars($result['hostname']) . "<br>
            <strong>Port:</strong> " . htmlspecialchars($result['port']) . "<br>
            <strong>Connection ID:</strong> " . htmlspecialchars($result['connection_id']) . "
        </div>";
    } catch (Exception $e) {
        echo "<div class='error'><strong>❌ PDO Query Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    $error_msg = isset($db_connection_error) ? $db_connection_error : 'Unknown error';
    echo "<div class='error'><strong>❌ PDO Connection Failed:</strong> " . htmlspecialchars($error_msg) . "</div>";
}

// Test MySQLi Connection  
echo "<h3>MySQLi Connection Test</h3>";
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $result = $conn->query("SELECT 
        CONNECTION_ID() as connection_id, 
        DATABASE() as database_name, 
        USER() as user_info,
        @@hostname as hostname,
        @@port as port
    ");
    
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<div class='success'>
            <strong>✅ MySQLi Connection Successful!</strong><br>
            <strong>Database:</strong> " . htmlspecialchars($row['database_name']) . "<br>
            <strong>User:</strong> " . htmlspecialchars($row['user_info']) . "<br>
            <strong>Host:</strong> " . htmlspecialchars($row['hostname']) . "<br>
            <strong>Port:</strong> " . htmlspecialchars($row['port']) . "<br>
            <strong>Connection ID:</strong> " . htmlspecialchars($row['connection_id']) . "
        </div>";
    } else {
        echo "<div class='error'><strong>❌ MySQLi Query Error:</strong> " . htmlspecialchars($conn->error) . "</div>";
    }
} else {
    $error_msg = isset($conn->connect_error) ? $conn->connect_error : 'Connection not established';
    echo "<div class='error'><strong>❌ MySQLi Connection Failed:</strong> " . htmlspecialchars($error_msg) . "</div>";
}

// Environment Variables Info
echo "<h3>Environment Variables</h3>";
$env_vars = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'APP_DEBUG'];
echo "<div class='info'>";
foreach ($env_vars as $var) {
    $value = getenv($var) ?: 'Not set';
    if ($var === 'DB_PASSWORD') {
        $value = getenv($var) ? '[Hidden]' : 'Not set';
    }
    echo "<strong>$var:</strong> $value<br>";
}
echo "</div>";

echo "<p>
    <a href='environment_switcher.php' class='btn'>🔄 Environment Switcher</a>
    <a href='../../index.php' class='btn'>🏠 Back to App</a>
</p>

</body>
</html>";
?>