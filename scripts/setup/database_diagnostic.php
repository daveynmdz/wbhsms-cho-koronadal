<?php
/**
 * Production Database Connection Diagnostic Tool
 * Use this to test and debug database connectivity issues in production
 */

// Enable error display for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "<h1>🔍 Database Connection Diagnostic</h1>";
echo "<p>Testing database connectivity for production environment...</p>";

// Load environment configuration
$root_path = dirname(__DIR__);
require_once $root_path . '/config/env.php';

echo "<h2>📋 Current Configuration</h2>";
echo "<table border='1' style='border-collapse: collapse; padding: 8px;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_DATABASE') ?: 'wbhsms_database';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ? '[SET]' : '[EMPTY]';

echo "<tr><td>DB_HOST</td><td>" . htmlspecialchars($host) . "</td><td>" . (!empty($host) ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>DB_PORT</td><td>" . htmlspecialchars($port) . "</td><td>✅</td></tr>";
echo "<tr><td>DB_DATABASE</td><td>" . htmlspecialchars($db) . "</td><td>" . (!empty($db) ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>DB_USERNAME</td><td>" . htmlspecialchars($user) . "</td><td>" . (!empty($user) ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>DB_PASSWORD</td><td>" . htmlspecialchars($pass) . "</td><td>" . ($pass === '[SET]' ? "✅" : "❌") . "</td></tr>";
echo "</table>";

// Test 1: Check if host is reachable
echo "<h2>🌐 Network Connectivity Test</h2>";
$actual_host = getenv('DB_HOST');
$actual_port = getenv('DB_PORT') ?: '3306';

echo "<p><strong>Testing connection to:</strong> {$actual_host}:{$actual_port}</p>";

$connection_test = @fsockopen($actual_host, $actual_port, $errno, $errstr, 10);
if ($connection_test) {
    echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> Network connection to database host is reachable</p>";
    fclose($connection_test);
} else {
    echo "<p style='color: red;'>❌ <strong>FAILED:</strong> Cannot reach database host</p>";
    echo "<p><strong>Error:</strong> $errstr (Code: $errno)</p>";
    echo "<p><strong>Possible causes:</strong></p>";
    echo "<ul>";
    echo "<li>Database server is down</li>";
    echo "<li>Incorrect hostname/IP address</li>";
    echo "<li>Firewall blocking connection</li>";
    echo "<li>Network connectivity issues</li>";
    echo "</ul>";
}

// Test 2: PDO Connection Test
echo "<h2>🔌 PDO Connection Test</h2>";
$actual_pass = getenv('DB_PASSWORD') ?: '';

try {
    $dsn = "mysql:host={$actual_host};port={$actual_port};dbname={$db};charset=utf8mb4";
    echo "<p><strong>Connection String:</strong> " . str_replace($actual_pass, '[HIDDEN]', $dsn) . "</p>";
    
    $test_pdo = new PDO($dsn, $user, $actual_pass);
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> PDO connection established</p>";
    
    // Test query
    $stmt = $test_pdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '$db'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>📊 <strong>Database Info:</strong> Found {$result['table_count']} tables in database</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ <strong>FAILED:</strong> PDO connection failed</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Analyze common error patterns
    $error_msg = $e->getMessage();
    echo "<p><strong>Diagnosis:</strong></p><ul>";
    
    if (strpos($error_msg, 'Access denied') !== false) {
        echo "<li>🔐 <strong>Authentication Error:</strong> Username or password is incorrect</li>";
    }
    if (strpos($error_msg, 'Unknown database') !== false) {
        echo "<li>💾 <strong>Database Error:</strong> Database '{$db}' does not exist</li>";
    }
    if (strpos($error_msg, 'Connection refused') !== false) {
        echo "<li>🚫 <strong>Connection Error:</strong> Database server is not accepting connections</li>";
    }
    if (strpos($error_msg, 'timeout') !== false) {
        echo "<li>⏱️ <strong>Timeout Error:</strong> Connection timed out - server may be overloaded</li>";
    }
    if (strpos($error_msg, 'host') !== false) {
        echo "<li>🌐 <strong>Host Error:</strong> Database host is unreachable or incorrect</li>";
    }
    
    echo "</ul>";
}

// Test 3: MySQLi Connection Test
echo "<h2>🔗 MySQLi Connection Test</h2>";
try {
    $test_mysqli = new mysqli($actual_host, $user, $actual_pass, $db, $actual_port);
    
    if ($test_mysqli->connect_error) {
        echo "<p style='color: red;'>❌ <strong>FAILED:</strong> MySQLi connection failed</p>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($test_mysqli->connect_error) . "</p>";
    } else {
        echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> MySQLi connection established</p>";
        echo "<p>📊 <strong>MySQL Version:</strong> " . $test_mysqli->server_info . "</p>";
        $test_mysqli->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>FAILED:</strong> MySQLi connection exception</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: Environment File Check
echo "<h2>📁 Environment File Check</h2>";
$env_file = $root_path . '/.env';
$env_local_file = $root_path . '/.env.local';

if (file_exists($env_file)) {
    echo "<p>✅ <strong>.env file exists</strong> (Size: " . filesize($env_file) . " bytes)</p>";
} else {
    echo "<p style='color: red;'>❌ <strong>.env file missing</strong></p>";
}

if (file_exists($env_local_file)) {
    echo "<p>✅ <strong>.env.local file exists</strong> (Size: " . filesize($env_local_file) . " bytes) - Overrides .env</p>";
} else {
    echo "<p>ℹ️ <strong>.env.local file not found</strong> (Optional)</p>";
}

// Recommendations
echo "<h2>💡 Recommendations</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px;'>";

if (!$connection_test) {
    echo "<h3>🚨 Critical Issues to Fix:</h3>";
    echo "<ol>";
    echo "<li><strong>Verify Database Host:</strong> Confirm that '{$actual_host}' is the correct database server address</li>";
    echo "<li><strong>Check Network Access:</strong> Ensure your server can reach the database host on port {$actual_port}</li>";
    echo "<li><strong>Firewall Rules:</strong> Verify firewall allows MySQL connections</li>";
    echo "<li><strong>Database Server Status:</strong> Confirm the database server is running</li>";
    echo "</ol>";
} else {
    echo "<h3>🔧 Configuration Issues to Fix:</h3>";
    echo "<ol>";
    echo "<li><strong>Verify Credentials:</strong> Double-check username and password</li>";
    echo "<li><strong>Database Existence:</strong> Ensure database '{$db}' exists</li>";
    echo "<li><strong>User Permissions:</strong> Verify user has access to the database</li>";
    echo "</ol>";
}

echo "<h3>🛠️ Quick Fixes:</h3>";
echo "<ol>";
echo "<li><strong>Create .env.local:</strong> For local testing, create a .env.local file with working local credentials</li>";
echo "<li><strong>Test with localhost:</strong> Try connecting to localhost MySQL if available</li>";
echo "<li><strong>Contact hosting provider:</strong> Verify database server details with your hosting provider</li>";
echo "</ol>";
echo "</div>";

// Example .env.local for development
echo "<h2>📝 Example .env.local for Development</h2>";
echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 8px;'>";
echo "# Create this file as .env.local for local development
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=
DB_DATABASE=wbhsms_database
DB_PORT=3306";
echo "</pre>";

echo "<p><em>This diagnostic completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>
