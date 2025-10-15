<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== PATH RESOLUTION DEBUG ===<br>";

echo "Current file: " . __FILE__ . "<br>";
echo "Current directory: " . __DIR__ . "<br>";

echo "<br>Testing different path methods:<br>";

// Method 1: Current approach
$root_path1 = dirname(dirname(dirname(__FILE__)));
echo "Method 1 (dirname x3): $root_path1<br>";
echo "Config path would be: " . $root_path1 . '/config/db.php<br>';
echo "Exists: " . (file_exists($root_path1 . '/config/db.php') ? 'YES' : 'NO') . "<br><br>";

// Method 2: Using __DIR__
$root_path2 = dirname(dirname(__DIR__));
echo "Method 2 (__DIR__ x2): $root_path2<br>";
echo "Config path would be: " . $root_path2 . '/config/db.php<br>';
echo "Exists: " . (file_exists($root_path2 . '/config/db.php') ? 'YES' : 'NO') . "<br><br>";

// Method 3: Absolute path
$root_path3 = 'C:/xampp/htdocs/wbhsms-cho-koronadal-1';
echo "Method 3 (absolute): $root_path3<br>";
echo "Config path would be: " . $root_path3 . '/config/db.php<br>';
echo "Exists: " . (file_exists($root_path3 . '/config/db.php') ? 'YES' : 'NO') . "<br><br>";

// Method 4: Realpath
$root_path4 = realpath(__DIR__ . '/../../');
echo "Method 4 (realpath): $root_path4<br>";
echo "Config path would be: " . $root_path4 . '/config/db.php<br>';
echo "Exists: " . (file_exists($root_path4 . '/config/db.php') ? 'YES' : 'NO') . "<br><br>";

// List actual directory structure
echo "=== DIRECTORY STRUCTURE ===<br>";
echo "Contents of current directory (__DIR__):<br>";
if (is_dir(__DIR__)) {
    foreach (scandir(__DIR__) as $item) {
        if ($item !== '.' && $item !== '..') {
            echo "- $item" . (is_dir(__DIR__ . '/' . $item) ? ' (DIR)' : ' (FILE)') . "<br>";
        }
    }
}

echo "<br>Contents of parent directory:<br>";
$parent_dir = dirname(__DIR__);
if (is_dir($parent_dir)) {
    foreach (scandir($parent_dir) as $item) {
        if ($item !== '.' && $item !== '..') {
            echo "- $item" . (is_dir($parent_dir . '/' . $item) ? ' (DIR)' : ' (FILE)') . "<br>";
        }
    }
}

echo "<br>Contents of grandparent directory:<br>";
$grandparent_dir = dirname(dirname(__DIR__));
if (is_dir($grandparent_dir)) {
    foreach (scandir($grandparent_dir) as $item) {
        if ($item !== '.' && $item !== '..') {
            echo "- $item" . (is_dir($grandparent_dir . '/' . $item) ? ' (DIR)' : ' (FILE)') . "<br>";
        }
    }
}

// Test the specific path that should work
$test_paths = [
    __DIR__ . '/../../config/db.php',
    dirname(__DIR__) . '/../config/db.php',
    dirname(dirname(__DIR__)) . '/config/db.php',
    'C:/xampp/htdocs/wbhsms-cho-koronadal-1/config/db.php'
];

echo "<br>=== TESTING SPECIFIC PATHS ===<br>";
foreach ($test_paths as $i => $path) {
    echo "Path " . ($i+1) . ": $path<br>";
    echo "Exists: " . (file_exists($path) ? 'YES' : 'NO') . "<br>";
    echo "Realpath: " . (realpath($path) ?: 'FAILED') . "<br><br>";
}
?>