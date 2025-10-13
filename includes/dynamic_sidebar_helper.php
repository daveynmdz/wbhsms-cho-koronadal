<?php
/**
 * Dynamic Sidebar Inclusion Helper
 * This helper function can be used by centralized files to include the correct sidebar
 * based on the user's role instead of hardcoding admin sidebar.
 */

/**
 * Include the appropriate sidebar based on user role
 * @param string $activePage - The page identifier for sidebar highlighting
 * @param string $root_path - Path to the root directory
 */
function includeDynamicSidebar($activePage, $root_path) {
    // Get user role from session
    $role = strtolower($_SESSION['role'] ?? 'admin');
    
    // Map roles to their sidebar files
    $sidebar_file = $root_path . '/includes/sidebar_' . $role . '.php';
    
    // Set the active page for sidebar highlighting
    global $activePage;
    $GLOBALS['activePage'] = $activePage;
    
    // Check if the role-specific sidebar exists
    if (file_exists($sidebar_file)) {
        include $sidebar_file;
    } else {
        // Fallback to admin sidebar if role-specific sidebar doesn't exist
        // This shouldn't happen for valid roles, but provides safety
        include $root_path . '/includes/sidebar_admin.php';
    }
}

/**
 * Get back URL based on user role
 * Useful for topbar back buttons in centralized files
 */
function getRoleDashboardUrl($role = null) {
    if ($role === null) {
        $role = strtolower($_SESSION['role'] ?? 'admin');
    }
    
    return "../management/{$role}/dashboard.php";
}
?>