<?php
/**
 * Laboratory Management Module Test Script
 * 
 * This script tests the basic functionality of the laboratory management module
 * Run this after setting up the database tables and directory permissions
 */

// Include configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/db.php';

echo "<h1>Laboratory Management Module Test</h1>\n";
echo "<pre>\n";

// Test 1: Database Connectivity
echo "1. Testing Database Connection...\n";
try {
    if ($conn->ping()) {
        echo "   ✓ Database connection successful\n";
    } else {
        echo "   ✗ Database connection failed\n";
        exit();
    }
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
    exit();
}

// Test 2: Check Required Tables
echo "\n2. Checking Required Tables...\n";

$required_tables = ['lab_orders', 'lab_order_items', 'patients', 'employees'];
foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "   ✓ Table '$table' exists\n";
    } else {
        echo "   ✗ Table '$table' missing\n";
    }
}

// Test 3: Check Table Structure
echo "\n3. Checking Table Structure...\n";

// Check lab_order_items table structure
$result = $conn->query("DESCRIBE lab_order_items");
if ($result && $result->num_rows > 0) {
    echo "   ✓ lab_order_items table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "     - {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "   ✗ Cannot describe lab_order_items table\n";
}

// Test 4: Check Directory Permissions
echo "\n4. Checking Directory Permissions...\n";

$uploads_dir = $root_path . '/uploads/lab_results';
if (is_dir($uploads_dir)) {
    echo "   ✓ Uploads directory exists: $uploads_dir\n";
    
    if (is_writable($uploads_dir)) {
        echo "   ✓ Uploads directory is writable\n";
    } else {
        echo "   ✗ Uploads directory is not writable\n";
        echo "     Run: chmod 755 $uploads_dir\n";
    }
} else {
    echo "   ✗ Uploads directory does not exist: $uploads_dir\n";
    echo "     Directory should be created automatically\n";
}

// Test 5: Check .htaccess Protection
$htaccess_file = $uploads_dir . '/.htaccess';
if (file_exists($htaccess_file)) {
    echo "   ✓ .htaccess protection file exists\n";
    $content = file_get_contents($htaccess_file);
    if (strpos($content, 'Deny from all') !== false) {
        echo "   ✓ Directory access protection is configured\n";
    } else {
        echo "   ✗ Directory access protection may not be properly configured\n";
    }
} else {
    echo "   ✗ .htaccess protection file missing\n";
}

// Test 6: Sample Data Check
echo "\n5. Checking Sample Data...\n";

$patient_count = $conn->query("SELECT COUNT(*) as count FROM patients")->fetch_assoc()['count'];
echo "   • Patients in database: $patient_count\n";

$employee_count = $conn->query("SELECT COUNT(*) as count FROM employees WHERE role IN ('admin', 'laboratory_tech', 'doctor', 'nurse')")->fetch_assoc()['count'];
echo "   • Relevant employees in database: $employee_count\n";

$lab_order_count = $conn->query("SELECT COUNT(*) as count FROM lab_orders")->fetch_assoc()['count'];
echo "   • Lab orders in database: $lab_order_count\n";

if (isset($conn) && $conn->query("SHOW TABLES LIKE 'lab_order_items'")->num_rows > 0) {
    $lab_item_count = $conn->query("SELECT COUNT(*) as count FROM lab_order_items")->fetch_assoc()['count'];
    echo "   • Lab order items in database: $lab_item_count\n";
}

// Test 7: File Access Test
echo "\n6. Testing File Access...\n";

$test_files = [
    $root_path . '/pages/laboratory-management/lab_management.php',
    $root_path . '/pages/laboratory-management/create_lab_order.php',
    $root_path . '/pages/laboratory-management/upload_lab_result.php',
    $root_path . '/pages/laboratory-management/api/get_lab_order_details.php',
    $root_path . '/pages/laboratory-management/api/download_lab_result.php'
];

foreach ($test_files as $file) {
    if (file_exists($file)) {
        echo "   ✓ " . basename($file) . " exists\n";
    } else {
        echo "   ✗ " . basename($file) . " missing\n";
    }
}

// Test 8: PHP Configuration Check
echo "\n7. Checking PHP Configuration...\n";

$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');
$memory_limit = ini_get('memory_limit');

echo "   • upload_max_filesize: $upload_max\n";
echo "   • post_max_size: $post_max\n";
echo "   • memory_limit: $memory_limit\n";

// Convert to bytes for comparison
function convertToBytes($value) {
    $unit = strtolower(substr($value, -1));
    $value = (int) $value;
    switch ($unit) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    return $value;
}

$upload_bytes = convertToBytes($upload_max);
$post_bytes = convertToBytes($post_max);

if ($upload_bytes >= 10485760 && $post_bytes >= 10485760) { // 10MB
    echo "   ✓ PHP upload settings are sufficient for 10MB files\n";
} else {
    echo "   ⚠ PHP upload settings may be too low for 10MB files\n";
    echo "     Consider increasing upload_max_filesize and post_max_size\n";
}

// Test Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 50) . "\n";

if ($conn->ping() && is_dir($uploads_dir)) {
    echo "✓ Core functionality should work\n";
    echo "✓ Ready for laboratory management operations\n";
    
    if ($patient_count > 0 && $employee_count > 0) {
        echo "✓ Sample data available for testing\n";
    } else {
        echo "⚠ Limited sample data - add patients and employees for full testing\n";
    }
} else {
    echo "✗ Setup incomplete - please resolve the issues above\n";
}

echo "\nNext steps:\n";
echo "1. Run the SQL in database/lab_order_items_table.sql\n";
echo "2. Ensure proper directory permissions\n";
echo "3. Access the module at: /pages/laboratory-management/lab_management.php\n";
echo "4. Test with different user roles (admin, laboratory_tech, doctor, nurse)\n";

echo "</pre>\n";
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    h1 { color: #03045e; }
</style>