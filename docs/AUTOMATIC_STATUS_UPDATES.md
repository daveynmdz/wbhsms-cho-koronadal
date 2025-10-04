# Automatic Status Update System

## Overview

This system automatically updates the status of appointments and referrals based on business rules and time-based logic. The system ensures that:

1. **Appointments** with status 'confirmed' are automatically changed to 'cancelled' when the scheduled date/time has passed
2. **Referrals** with status 'active' are automatically changed to 'accepted' when they are used to book an appointment
3. **Referrals** with status 'active' are automatically changed to 'expired' if they are older than 30 days and haven't been used

## Components

### 1. AutomaticStatusUpdater Class
**File:** `utils/automatic_status_updater.php`

This is the main class that handles all automatic status updates:

- `updateExpiredAppointments()` - Updates appointments that have passed their scheduled time
- `updateUsedReferrals()` - Updates referrals that have been used for appointments  
- `updateExpiredReferrals()` - Updates referrals that have expired (30+ days old)
- `runAllUpdates()` - Executes all update methods

### 2. Integration in Appointments Page
**File:** `pages/patient/appointment/appointments.php`

The automatic status updater is called every time the appointments page loads, ensuring users always see current status information.

### 3. Test Script
**File:** `test_status_updates.php`

A comprehensive test script that:
- Shows current status before updates
- Runs the automatic updates
- Shows results after updates
- Provides debugging information

### 4. Cron Job Script
**File:** `cron_status_updater.php`

A scheduled task script for running updates automatically on the server.

## Business Rules

### Appointments
- **Trigger:** Scheduled date/time has passed
- **Action:** Status changes from 'confirmed' to 'cancelled'
- **Reason:** "Automatically cancelled - appointment time has passed"

### Referrals (Used)
- **Trigger:** Referral is linked to a confirmed or completed appointment
- **Action:** Status changes from 'active' to 'accepted'

### Referrals (Expired)
- **Trigger:** Referral is 30+ days old and hasn't been used for any appointment
- **Action:** Status changes from 'active' to 'expired'

## Usage

### Manual Testing
To test the automatic status updates manually:

```bash
# Navigate to your web root
cd c:\xampp\htdocs\wbhsms-cho-koronadal

# Run the test script in browser
http://localhost/wbhsms-cho-koronadal/test_status_updates.php

# Or run the updater directly
http://localhost/wbhsms-cho-koronadal/utils/automatic_status_updater.php
```

### Scheduled Updates (Windows Task Scheduler)
For automatic updates, set up a Windows scheduled task:

1. Open Task Scheduler
2. Create Basic Task
3. Set trigger (e.g., every hour, daily)
4. Set action: Start a program
5. Program: `C:\xampp\php\php.exe`
6. Arguments: `C:\xampp\htdocs\wbhsms-cho-koronadal\cron_status_updater.php`

### Real-time Updates
The system runs automatically when users visit the appointments page, ensuring they always see current status information.

## Configuration

### Referral Expiry Period
The default expiry period for referrals is 30 days. To change this:

1. Open `utils/automatic_status_updater.php`
2. Find the line: `$expiry_days = 30;`
3. Change the value to your desired number of days

### Logging
Status updates are logged to:
- PHP error log
- Custom log file: `logs/status_updater_cron.log` (for cron jobs)

## Monitoring

### Success Indicators
- Appointments with past dates show status 'cancelled'
- Referrals used for appointments show status 'accepted'
- Old unused referrals show status 'expired'

### Error Checking
- Check PHP error logs for any database errors
- Review the custom log file for cron job execution
- Use the test script to verify updates are working

## Database Impact

The system updates the following tables:
- `appointments` - Updates status and cancellation_reason
- `referrals` - Updates status and updated_at

All updates include proper timestamps and maintain data integrity.

## Troubleshooting

### Common Issues

1. **Updates not running**
   - Check database connection
   - Verify file permissions
   - Check PHP error logs

2. **Partial updates**
   - Review the test script output
   - Check for database lock issues
   - Verify business logic conditions

3. **Performance concerns**
   - Monitor database query performance
   - Consider running updates less frequently if needed
   - Add database indexes if queries are slow

### Database Queries for Manual Checking

```sql
-- Check appointments that should be auto-cancelled
SELECT * FROM appointments 
WHERE status = 'confirmed' 
AND CONCAT(scheduled_date, ' ', scheduled_time) < NOW();

-- Check referrals that should be auto-accepted
SELECT r.* FROM referrals r
INNER JOIN appointments a ON r.referral_id = a.referral_id
WHERE r.status = 'active' AND a.status IN ('confirmed', 'completed');

-- Check referrals that should be expired
SELECT * FROM referrals 
WHERE status = 'active' 
AND referral_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
AND referral_id NOT IN (
    SELECT DISTINCT referral_id FROM appointments 
    WHERE referral_id IS NOT NULL 
    AND status IN ('confirmed', 'completed')
);
```

## Security Considerations

- The system only updates records based on time and status logic
- No user input is processed, reducing security risks
- All database operations use prepared statements
- Logging helps with audit trails

## Future Enhancements

Potential improvements:
1. Email notifications for status changes
2. Configurable business rules via admin panel
3. More detailed audit logging
4. Status change history tracking
5. Integration with appointment reminder system