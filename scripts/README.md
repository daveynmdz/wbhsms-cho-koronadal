# Scripts Directory

This directory contains utility scripts organized by purpose:

## Folders

### `/setup`
Scripts for initial system setup and validation:
- `setup_check.php` - Validates XAMPP setup and system requirements
- `setup_debug.php` - Debug utility for session and configuration issues
- `testdb.php` - Simple database connection test for XAMPP

### `/maintenance`
Scripts for system maintenance and updates:
- `fix_appointment_statuses.php` - Fixes NULL appointment statuses in database
- `update_dashboard_template.php` - Standardizes dashboard files across roles

### `/cron`
Scripts designed to run as scheduled tasks:
- `status_updater.php` - Automatically updates expired appointments and referrals

## Usage

### Setup Scripts
Run these during initial installation or troubleshooting:
```
http://localhost/wbhsms-cho-koronadal/scripts/setup/setup_check.php
http://localhost/wbhsms-cho-koronadal/scripts/setup/testdb.php
```

### Maintenance Scripts
Run these as needed for system maintenance:
```
http://localhost/wbhsms-cho-koronadal/scripts/maintenance/fix_appointment_statuses.php
```

### Cron Scripts
Set up as scheduled tasks on your server. For XAMPP/Windows:
- Use Windows Task Scheduler
- Point to: `C:\xampp\php\php.exe C:\xampp\htdocs\wbhsms-cho-koronadal\scripts\cron\status_updater.php`

## Security Note
These scripts contain sensitive operations. In production:
- Move these outside the web-accessible directory
- Add authentication checks
- Use command-line execution only for maintenance scripts