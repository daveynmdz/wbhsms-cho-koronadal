<?php
/**
 * Station Path Helper
 * This file provides the correct root path for all station files
 */

// Get the correct root path for the application
function getApplicationRootPath() {
    // Method 1: Try relative path resolution
    $current_dir = __DIR__;
    $possible_paths = [
        $current_dir . '/../../',
        dirname(dirname($current_dir)),
        realpath($current_dir . '/../../'),
        'C:/xampp/htdocs/wbhsms-cho-koronadal-1',
        str_replace('\\', '/', dirname(dirname($current_dir)))
    ];
    
    foreach ($possible_paths as $path) {
        if ($path && file_exists($path . '/config/db.php')) {
            return rtrim(str_replace('\\', '/', $path), '/');
        }
    }
    
    // Fallback: throw error if no valid path found
    throw new Exception('Could not determine application root path. Config file not found.');
}

// Export the root path
try {
    $root_path = getApplicationRootPath();
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>