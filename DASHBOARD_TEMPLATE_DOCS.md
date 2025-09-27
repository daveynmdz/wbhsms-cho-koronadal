# Dashboard Standardization Utility

This utility script helps standardize dashboard files across the CHO Koronadal Health Management System by applying consistent patterns for absolute paths, session handling, and proper includes.

## Features

- Adds standardized PHP headers with proper path handling
- Implements consistent session management and security checks
- Ensures proper role-based access control
- Adds debug logging for troubleshooting
- Creates backups of original files before modification
- Updates CSS and sidebar includes to use absolute paths

## Usage

### From Command Line

```bash
php update_dashboard_template.php path/to/dashboard.php role_name [backup=1|0]
```

Example:
```bash
php update_dashboard_template.php pages/management/nurse/dashboard.php nurse
```

### From PHP Code

```php
require_once 'update_dashboard_template.php';

// Parameters: file path, role name, create backup (optional, defaults to true)
standardize_dashboard('pages/management/doctor/dashboard.php', 'doctor', true);
```

## What Gets Standardized

1. **Path Handling**: Uses `dirname(__DIR__)` for absolute paths
2. **Session Security**:
   - Proper session validation
   - Role-based access control
   - Redirect handling to prevent loops
3. **Error Logging**:
   - Session information
   - Database connection status
   - Access attempts
4. **Cache Control Headers**
5. **Sidebar Includes**: Updated to use absolute paths
6. **CSS References**: Updated to use absolute paths

## Example Output

When run successfully:
```
Backup created: pages/management/nurse/dashboard.php.bak.20240726-123045
Successfully updated pages/management/nurse/dashboard.php with standardized template
```

## Standardized Dashboard Files

The following dashboard files have been standardized:

1. Admin Dashboard
2. DHO (District Health Officer) Dashboard
3. Records Officer Dashboard
4. Laboratory Tech Dashboard
5. Cashier Dashboard
6. BHW (Barangay Health Worker) Dashboard

To standardize additional dashboard files, use this utility.