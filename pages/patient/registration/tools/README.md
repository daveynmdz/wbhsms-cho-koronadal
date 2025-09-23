# Registration Tools

This folder contains diagnostic and testing tools for the CHO Koronadal registration system.

## 🔧 Available Tools

### 1. `system_check.php`
**Comprehensive System Health Check**
- Database connectivity and table structure validation
- Environment variable verification
- File permissions and SMTP connectivity tests
- Password validation and age calculation tests
- Security configuration checks
- Overall system health summary

**Usage:** Navigate to `http://localhost/wbhsms-cho-koronadal/pages/registration/tools/system_check.php`

### 2. `debug_registration.php`
**Quick Registration Debug**
- Database connection test
- Environment variables display
- Barangay loading test
- SMTP connection test
- Recent mail error logs

**Usage:** Navigate to `http://localhost/wbhsms-cho-koronadal/pages/registration/tools/debug_registration.php`

### 3. `test_registration_no_email.php`
**Direct Registration Test (No Email)**
- Test patient registration without OTP email verification
- Useful for testing database insertion logic
- Bypasses email requirements for development

**Usage:** Navigate to `http://localhost/wbhsms-cho-koronadal/pages/registration/tools/test_registration_no_email.php`

### 4. `test_registration.php`
**Database Structure and Registration System Overview**
- View patients table structure
- Check available barangays
- Display recent registrations
- Quick links to registration and login forms
- Form field requirements summary

**Usage:** Navigate to `http://localhost/wbhsms-cho-koronadal/pages/registration/tools/test_registration.php`

### 5. `mail_error.log`
**Email Error Logging**
- Contains SMTP authentication errors and email sending failures
- Automatically populated by registration system when email errors occur
- Useful for troubleshooting email delivery issues

**Location:** `mail_error.log` in this tools directory

## 📝 When to Use These Tools

### During Development:
- Use `system_check.php` to verify all components are working
- Use `debug_registration.php` for quick troubleshooting
- Use `test_registration_no_email.php` to test database logic
- Use `test_registration.php` to view system overview and structure
- Check `mail_error.log` for email delivery issues

### Before Deployment:
- Run `system_check.php` to ensure production readiness
- Verify all tests pass before going live

### Troubleshooting Issues:
- Check `debug_registration.php` for immediate diagnostics
- Review system health with `system_check.php`
- Check `mail_error.log` for email authentication problems
- Test specific components individually

## 🔒 Security Note

These tools are for **development and testing only**. They should not be accessible in production environments as they may expose sensitive configuration information.

## 📁 File Structure

```
tools/
├── README.md                        # This file
├── system_check.php                 # Comprehensive health check
├── debug_registration.php           # Quick debug tool
├── test_registration_no_email.php   # Direct registration test
├── test_registration.php            # Database overview tool
└── mail_error.log                   # Email error logging
```

## 🔗 Related Files

Main registration system files (in parent directory):
- `../patient_registration.php` - Main registration form
- `../register_patient.php` - Backend processing
- `../registration_otp.php` - OTP verification
- `../registration_success.php` - Success page
- `../resend_registration_otp.php` - Resend OTP functionality

---
*Created for CHO Koronadal Web-Based Health Management System*