<?php
/**
 * Database Connection Diagnostic Tool
 * Use this to test and debug database connection issues in production
 */

// Enable all error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

echo "<h1>Database Connection Diagnostic</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .error { color: red; } .success { color: green; } .info { color: blue; }</style>";

// Load environment configuration
require_once __DIR__ . '/../config/env.php';

echo "<h2>Environment Configuration</h2>";
echo "<div class='info'>";
echo "<strong>DB_HOST:</strong> " . (getenv('DB_HOST') ?: 'localhost') . "<br>";
echo "<strong>DB_PORT:</strong> " . (getenv('DB_PORT') ?: '3306') . "<br>";
echo "<strong>DB_DATABASE:</strong> " . (getenv('DB_DATABASE') ?: 'wbhsms_database') . "<br>";
echo "<strong>DB_USERNAME:</strong> " . (getenv('DB_USERNAME') ?: 'root') . "<br>";
echo "<strong>DB_PASSWORD:</strong> " . (empty(getenv('DB_PASSWORD')) ? 'Empty' : 'Set (length: ' . strlen(getenv('DB_PASSWORD')) . ')') . "<br>";
echo "</div>";

echo "<h2>Connection Tests</h2>";

// Test 1: PDO Connection
echo "<h3>1. PDO Connection Test</h3>";
if (isset($pdo) && $pdo !== null) {
    echo "<div class='success'>✅ PDO connection successful</div>";
    
    // Test a simple query
    try {
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "<div class='success'>✅ PDO query test successful: " . $result['test'] . "</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ PDO query test failed: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='error'>❌ PDO connection failed</div>";
    if (isset($db_connection_error)) {
        echo "<div class='error'>Error: " . $db_connection_error . "</div>";
    }
}

// Test 2: MySQLi Connection
echo "<h3>2. MySQLi Connection Test</h3>";
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_DATABASE') ?: 'wbhsms_database';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

try {
    echo "<div class='info'>Attempting connection to: $host:$port, database: $db, user: $user</div>";
    
    $test_conn = new mysqli($host, $user, $pass, $db, $port);
    
    if ($test_conn->connect_error) {
        throw new Exception('Connection failed: ' . $test_conn->connect_error);
    }
    
    echo "<div class='success'>✅ MySQLi connection successful</div>";
    
    // Test a simple query
    $result = $test_conn->query("SELECT 1 as test");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<div class='success'>✅ MySQLi query test successful: " . $row['test'] . "</div>";
    } else {
        echo "<div class='error'>❌ MySQLi query test failed: " . $test_conn->error . "</div>";
    }
    
    $test_conn->close();
    
} catch (Exception $e) {
    echo "<div class='error'>❌ MySQLi connection failed: " . $e->getMessage() . "</div>";
}

// Test 3: Network connectivity
echo "<h3>3. Network Connectivity Test</h3>";
$host_to_ping = getenv('DB_HOST') ?: 'localhost';
if ($host_to_ping !== 'localhost') {
    echo "<div class='info'>Testing network connectivity to: $host_to_ping</div>";
    
    // Try to open a socket connection
    $connection = @fsockopen($host_to_ping, $port, $errno, $errstr, 10);
    if ($connection) {
        echo "<div class='success'>✅ Network connectivity successful</div>";
        fclose($connection);
    } else {
        echo "<div class='error'>❌ Network connectivity failed: $errstr ($errno)</div>";
    }
} else {
    echo "<div class='info'>Skipping network test for localhost</div>";
}

// Test 4: Check .env file
echo "<h3>4. Environment File Check</h3>";
$env_file = __DIR__ . '/../.env';
$env_local_file = __DIR__ . '/../.env.local';

if (file_exists($env_file)) {
    echo "<div class='success'>✅ .env file exists</div>";
    $env_content = file_get_contents($env_file);
    $has_db_host = strpos($env_content, 'DB_HOST=') !== false;
    echo "<div class='" . ($has_db_host ? 'success' : 'error') . "'>" . 
         ($has_db_host ? '✅' : '❌') . " DB_HOST found in .env</div>";
} else {
    echo "<div class='error'>❌ .env file not found</div>";
}

if (file_exists($env_local_file)) {
    echo "<div class='info'>ℹ️ .env.local file exists (overrides .env)</div>";
} else {
    echo "<div class='info'>ℹ️ .env.local file not found (using .env only)</div>";
}

echo "<h2>Recommendations</h2>";
echo "<div class='info'>";
echo "<strong>If connections are failing:</strong><br>";
echo "1. Verify database server is running<br>";
echo "2. Check database credentials in .env file<br>";
echo "3. Ensure database exists and user has proper permissions<br>";
echo "4. Check network connectivity to database server<br>";
echo "5. Verify database server is accepting connections on the specified port<br>";
echo "</div>";

echo "<h2>Current Server Info</h2>";
echo "<div class='info'>";
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "<strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "<strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>";
echo "<strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "<br>";
echo "</div>";
?>