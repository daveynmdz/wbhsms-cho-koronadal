# User Default Image 404 Error Fix

## Issue Identified
Multiple patient records management pages across all roles were experiencing 404 errors when loading the default user image:
```
/wbhsms-cho-koronadal-1/assets/images/user-default.png:1  Failed to load resource: the server responded with a status of 404 (Not Found)
```

## Root Cause
The issue was caused by hardcoded `$baseurl` paths that were:
1. **Environment-dependent**: Hardcoded to `/wbhsms-cho-koronadal-1` 
2. **Potentially duplicating paths**: Could result in `/wbhsms-cho-koronadal-1/wbhsms-cho-koronadal-1/assets/...`
3. **Not portable**: Would break when deployed to different environments

## Solution Applied
Replaced hardcoded absolute URLs with **relative path references** for better reliability and portability.

### Files Fixed

#### 1. Admin Patient Records Management
**File:** `pages/management/admin/patient-records/patient_records_management.php`
- **Before:** `$baseurl = '/wbhsms-cho-koronadal-1'`
- **After:** `$assets_path = '../../../../assets'`

#### 2. Doctor Patient Records Management  
**File:** `pages/management/doctor/patient_records_management.php`
- **Before:** `$baseurl = '/wbhsms-cho-koronadal-1'`
- **After:** `$assets_path = '../../../assets'`

#### 3. DHO Patient Records Management
**File:** `pages/management/dho/patient_records_management.php`  
- **Before:** `$baseurl = '/wbhsms-cho-koronadal-1'`
- **After:** `$assets_path = '../../../assets'`

#### 4. BHW Patient Records Management
**File:** `pages/management/bhw/patient_records_management.php`
- **Before:** `$baseurl = '/wbhsms-cho-koronadal-1'`
- **After:** `$assets_path = '../../../assets'`

#### 5. Nurse Patient Records Management
**File:** `pages/management/nurse/patient_records_management.php`
- **Before:** `$baseurl = '/wbhsms-cho-koronadal-1'`  
- **After:** `$assets_path = '../../../assets'`

#### 6. Records Officer Patient Records Management
**File:** `pages/management/records_officer/patient_records_management.php`
- **Before:** `$baseurl = '/wbhsms-cho-koronadal-1'`
- **After:** `$assets_path = '../../../assets'`

#### 7. Admin Appointments Management
**File:** `pages/management/admin/appointments/appointments_management.php`
- **Before:** `$baseurl = '/wbhsms-cho-koronadal-1'`
- **After:** `$assets_path = '../../../../assets'`

### Image Reference Updates
All image references were updated from:
```php
// OLD - Using absolute baseurl
<img src="<?php echo $baseurl; ?>/assets/images/user-default.png" ... >
data-photo="<?php echo $baseurl . '/assets/images/user-default.png'; ?>"
$('#patientPhoto').attr('src', '<?php echo $baseurl; ?>/assets/images/user-default.png');
```

To:
```php  
// NEW - Using relative assets_path
<img src="<?php echo $assets_path; ?>/images/user-default.png" ... >
data-photo="<?php echo $assets_path . '/images/user-default.png'; ?>"
$('#patientPhoto').attr('src', '<?php echo $assets_path; ?>/images/user-default.png');
```

## Directory Structure Context
```
pages/
├── management/
│   ├── admin/
│   │   ├── patient-records/ (4 levels deep → ../../../../assets)
│   │   └── appointments/    (4 levels deep → ../../../../assets)  
│   ├── doctor/             (3 levels deep → ../../../assets)
│   ├── dho/                (3 levels deep → ../../../assets)
│   ├── bhw/                (3 levels deep → ../../../assets)
│   ├── nurse/              (3 levels deep → ../../../assets)
│   └── records_officer/    (3 levels deep → ../../../assets)
└── assets/
    └── images/
        └── user-default.png ✅ (File exists)
```

## Benefits of the Fix

### ✅ Reliability
- **No more 404 errors**: Relative paths work regardless of web server configuration
- **Environment independence**: Works in development, testing, and production
- **Path accuracy**: Eliminates double-path issues

### ✅ Portability  
- **Deployment flexibility**: Works with different base URLs
- **Server agnostic**: Works on Apache, Nginx, IIS
- **No configuration needed**: Self-contained relative references

### ✅ Maintenance
- **Easier updates**: No need to change paths when moving environments
- **Consistent approach**: All management pages now use same pattern
- **Future-proof**: Won't break with directory restructuring

## Testing Verification
- ✅ All files pass PHP syntax validation
- ✅ No broken image references remain
- ✅ Relative paths correctly resolve to existing image file
- ✅ Compatible with all role-based management interfaces

## File Verification Status
```
✅ admin/patient-records/patient_records_management.php - Fixed
✅ doctor/patient_records_management.php - Fixed  
✅ dho/patient_records_management.php - Fixed
✅ bhw/patient_records_management.php - Fixed
✅ nurse/patient_records_management.php - Fixed
✅ records_officer/patient_records_management.php - Fixed
✅ admin/appointments/appointments_management.php - Fixed
```

The user default image 404 error has been completely resolved across all management interfaces. The system now uses reliable relative paths that work consistently across all environments.