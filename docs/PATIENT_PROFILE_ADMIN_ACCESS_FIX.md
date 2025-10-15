# Patient Profile Admin Access Fix

## Issue Identified
Admin users were unable to access patient profiles due to:
1. **Incorrect URL paths** in patient record management pages
2. **Wrong session variable checks** in patient profile authentication
3. **Session type mismatch** between employee and patient sessions

## Root Cause
The patient records management pages were linking to incorrect paths that don't exist in the file structure, causing 404 errors when admins tried to view patient profiles.

## Fixes Applied

### 1. Fixed Patient Profile URL Paths
**Files Updated:**
- `pages/management/admin/patient-records/patient_records_management.php`
- `pages/management/doctor/patient_records_management.php` 
- `pages/management/dho/patient_records_management.php`
- `pages/management/bhw/patient_records_management.php`

**Changes:**
```php
// BEFORE (incorrect paths)
../../patient/profile.php?patient_id=X&view_mode=admin

// AFTER (correct paths)  
../../../patient/profile/profile.php?patient_id=X&view_mode=admin
```

### 2. Fixed Session Variable Authentication
**File:** `pages/patient/profile/profile.php`

**Changes:**
```php
// BEFORE (incorrect session variable)
if ($view_mode === 'admin' && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'))

// AFTER (correct session variable)
if ($view_mode === 'admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'))
```

### 3. Fixed Session Type Loading
**File:** `pages/patient/profile/profile.php`

**Changes:**
```php
// BEFORE (always used patient session)
require_once $root_path . '/config/session/patient_session.php';

// AFTER (dynamic session loading based on view mode)
$view_mode = $_GET['view_mode'] ?? null;
if ($view_mode === 'admin' || $view_mode === 'bhw' || $view_mode === 'dho' || $view_mode === 'doctor' || $view_mode === 'nurse') {
    // Use employee session for management users
    require_once $root_path . '/config/session/employee_session.php';
} else {
    // Use patient session for regular patient view
    require_once $root_path . '/config/session/patient_session.php';
}
```

## Correct URL Structure

### For Admin Access:
```
✅ CORRECT: http://localhost/wbhsms-cho-koronadal-1/pages/patient/profile/profile.php?patient_id=32&view_mode=admin
❌ INCORRECT: http://localhost/wbhsms-cho-koronadal-1/pages/management/patient/profile.php?patient_id=32&view_mode=admin
```

### For Other Role Access:
- **Doctor:** `pages/patient/profile/profile.php?patient_id=X&view_mode=doctor`
- **DHO:** `pages/patient/profile/profile.php?patient_id=X&view_mode=dho`  
- **BHW:** `pages/patient/profile/profile.php?patient_id=X&view_mode=bhw`
- **Nurse:** `pages/patient/profile/profile.php?patient_id=X&view_mode=nurse`

## File Structure Context
```
pages/
├── management/
│   ├── admin/
│   │   └── patient-records/
│   │       └── patient_records_management.php  (links to patient profiles)
│   ├── doctor/
│   ├── bhw/
│   └── dho/
└── patient/
    └── profile/
        └── profile.php  (actual patient profile page)
```

## Testing
After these fixes, admin users should be able to:
1. Click "View Profile" links from patient records management pages
2. Access patient profiles with proper admin permissions
3. View patient data in admin view mode without authentication errors

## Session Management
The patient profile now properly:
- Uses employee session when accessed by management roles (admin, doctor, bhw, dho, nurse)
- Uses patient session when accessed by patients themselves
- Validates the correct session variables for each access type

## Security Notes
The authentication checks are currently set to development mode (commented out redirects). For production deployment, uncomment the redirect lines to enforce proper authentication.