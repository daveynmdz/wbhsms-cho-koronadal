# Centralized Files - Dynamic Sidebar Solution

## Problem
When files are moved from role-specific directories (e.g., `pages/management/admin/referrals/`) to centralized locations (e.g., `pages/referrals/`), they lose the context of which role is accessing them. This causes issues with:

1. **Sidebar Display**: Hardcoded admin sidebar shows for all users
2. **Breadcrumb Navigation**: Links point to wrong dashboard
3. **User Experience**: Users see incorrect navigation for their role

## Solution
Use the dynamic sidebar helper to automatically include the correct sidebar based on user role.

### Dynamic Sidebar Helper
Location: `includes/dynamic_sidebar_helper.php`

#### Usage in Centralized Files:
```php
<?php
// Include dynamic sidebar helper
require_once $root_path . '/includes/dynamic_sidebar_helper.php';

// Include the correct sidebar based on user role
includeDynamicSidebar('page_name', $root_path);
?>
```

#### Breadcrumb Navigation:
```php
<!-- Dynamic dashboard link -->
<a href="<?php echo getRoleDashboardUrl(); ?>"><i class="fas fa-home"></i> Dashboard</a>
```

## Implementation Example
**Before (Hardcoded Admin):**
```php
<?php
$activePage = 'referrals';
include $root_path . '/includes/sidebar_admin.php';
?>

<!-- Breadcrumb -->
<a href="../dashboard.php">Dashboard</a>
```

**After (Dynamic Role-Based):**
```php
<?php
require_once $root_path . '/includes/dynamic_sidebar_helper.php';
includeDynamicSidebar('referrals', $root_path);
?>

<!-- Breadcrumb -->
<a href="<?php echo getRoleDashboardUrl(); ?>">Dashboard</a>
```

## Files Fixed
1. **Referrals Management** (`pages/referrals/referrals_management.php`)
   - Dynamic sidebar inclusion
   - Role-based breadcrumb navigation

## Benefits
1. **Correct Sidebar**: Each role sees their appropriate navigation
2. **Proper Breadcrumbs**: Dashboard links go to correct role dashboard
3. **Consistent UX**: Users always see familiar navigation
4. **Future-Proof**: Easy to apply to new centralized files

## Future Centralization
When moving files to centralized locations:
1. Replace hardcoded sidebar includes with `includeDynamicSidebar()`
2. Update breadcrumb links to use `getRoleDashboardUrl()`
3. Test with different roles to ensure correct navigation
4. Verify role-based access control is still in place

This approach maintains the benefits of centralization while preserving role-specific user experience.