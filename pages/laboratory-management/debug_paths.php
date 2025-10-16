<?php
// Debug file to check path resolution
echo "<h2>Laboratory Management Path Debug</h2>\n";
echo "<pre>\n";

echo "Current file: " . __FILE__ . "\n";
echo "Current directory: " . __DIR__ . "\n";

// Test different path calculations
echo "\nPath calculations:\n";
echo "dirname(__DIR__): " . dirname(__DIR__) . "\n";
echo "dirname(dirname(__DIR__)): " . dirname(dirname(__DIR__)) . "\n"; 
echo "dirname(dirname(dirname(__DIR__))): " . dirname(dirname(dirname(__DIR__))) . "\n";

// Check what should be the root path
$root_path = dirname(dirname(__DIR__));
echo "\nUsing root_path = dirname(dirname(__DIR__)): " . $root_path . "\n";

// Check if config files exist
$config_session = $root_path . '/config/session/employee_session.php';
$config_db = $root_path . '/config/db.php';

echo "\nChecking config files:\n";
echo "Session config: " . $config_session . "\n";
echo "File exists: " . (file_exists($config_session) ? 'YES' : 'NO') . "\n";

echo "DB config: " . $config_db . "\n"; 
echo "File exists: " . (file_exists($config_db) ? 'YES' : 'NO') . "\n";

// Check server environment
echo "\nServer environment:\n";
echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'Unknown') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "\n";

echo "</pre>\n";
?>