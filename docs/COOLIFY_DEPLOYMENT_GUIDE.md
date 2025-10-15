# 🚀 Production Deployment Guide for Coolify

## ⚠️ CRITICAL SECURITY NOTICE

**DO NOT DEPLOY THE .env FILES TO PRODUCTION!**

The local `.env` and `.env.local` files contain:
- `APP_DEBUG=1` (exposes OTP codes)
- Development database credentials
- SMTP credentials that should be secured

## 📋 Pre-Deployment Security Checklist

### 1. Remove .env Files from Production Build
Add to `.gitignore` or ensure Coolify excludes:
```
.env
.env.local
```

### 2. Set Environment Variables in Coolify

#### Required Production Environment Variables:
```bash
# ⚠️  CRITICAL SECURITY SETTINGS
APP_DEBUG=0
APP_ENV=production

# 📊 DATABASE CONFIGURATION
DB_HOST=your_production_db_host
DB_PORT=3306
DB_DATABASE=wbhsms_database
DB_USERNAME=your_production_db_user
DB_PASSWORD=your_secure_production_password

# 📧 EMAIL CONFIGURATION (Required for OTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_production_email@domain.com
SMTP_PASS=your_production_app_password
SMTP_FROM=noreply@chokoronadal.gov.ph
SMTP_FROM_NAME=CHO Koronadal Health System

# 🌐 SYSTEM CONFIGURATION
SYSTEM_URL=https://your-production-domain.com
CONTACT_PHONE=(083) 228-8042
CONTACT_EMAIL=info@chokoronadal.gov.ph
FACILITY_ADDRESS=Koronadal City, South Cotabato
```

## 🛡️ Security Verification Steps

### Step 1: Run Security Check
Before deployment, run:
```bash
php scripts/setup/production_security_check.php
```

### Step 2: Expected Production Output
For a secure production deployment:
```
✅ APP_DEBUG is properly disabled.
✅ SMTP appears to be configured.
✅ Database password is configured.
✅ Database connection successful.
✅ All security files have APP_DEBUG protection implemented
🟢 EXCELLENT: All security checks passed!
✅ Ready for production deployment.
```

## 🔧 Coolify Deployment Steps

### 1. Environment Variable Setup
In Coolify dashboard:
1. Go to your application settings
2. Navigate to "Environment Variables"
3. Add each variable from the list above
4. **Ensure APP_DEBUG=0 is set**

### 2. Build Configuration
Ensure your build excludes:
- `.env`
- `.env.local`
- `tests/`
- `docs/` (optional)

### 3. Database Setup
1. Create production database
2. Import `database/wbhsms_database.sql`
3. Configure production database credentials

### 4. SMTP Configuration
For Gmail:
1. Enable 2-factor authentication
2. Generate App Password
3. Use App Password in SMTP_PASS
4. Use production email address

## 🧪 Post-Deployment Testing

### 1. Registration Flow Test
1. Visit registration page
2. Fill out patient registration form
3. **Verify no debug messages appear**
4. Check OTP email delivery
5. Complete OTP verification
6. Confirm registration success

### 2. Security Verification
1. **No OTP codes should appear in UI**
2. **No "DEVELOPMENT MODE" messages**
3. Error messages should be generic
4. All forms should work properly

## 🚨 Critical Security Issues Fixed

### Before (Vulnerable):
- OTP codes displayed in UI regardless of environment
- Development messages always shown
- Debug mode always active

### After (Secure):
- OTP codes only shown if `APP_DEBUG=1`
- Development messages only if `APP_DEBUG=1`
- Production mode hides all debug information

## 🔍 Security Features Implemented

### ✅ Environment-Based Debug Control
```php
// Safe: Only shows in development
if (getenv('APP_DEBUG') === '1') {
    $_SESSION['dev_message'] = "DEVELOPMENT MODE: Your OTP is {$otp}";
}
```

### ✅ SQL Injection Prevention
All database queries use prepared statements:
```php
$stmt = $pdo->prepare("SELECT * FROM patients WHERE email = ?");
$stmt->execute([$email]);
```

### ✅ XSS Prevention
All output is sanitized:
```php
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
```

### ✅ CSRF Protection
All forms include CSRF tokens:
```php
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    // Reject request
}
```

### ✅ Password Security
Passwords are properly hashed:
```php
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
```

## 📁 File Security Status

| File | Status | Security Features |
|------|--------|------------------|
| `registration_otp.php` | ✅ Secure | APP_DEBUG protection, prepared statements, input sanitization |
| `register_patient.php` | ✅ Secure | Environment-aware debug, CSRF protection, SQL injection prevention |
| `resend_registration_otp.php` | ✅ Secure | APP_DEBUG protection, rate limiting, error handling |
| `patient_registration.php` | ✅ Secure | CSRF protection, input validation, XSS prevention |
| `registration_success.php` | ✅ Secure | Session cleanup, no sensitive data exposure |

## 🎯 Production Deployment Checklist

- [ ] Remove .env files from production deployment
- [ ] Set APP_DEBUG=0 in Coolify environment
- [ ] Configure production database credentials
- [ ] Set up production SMTP settings
- [ ] Test registration flow end-to-end
- [ ] Verify no debug messages appear
- [ ] Confirm OTP emails are delivered
- [ ] Check all form submissions work
- [ ] Monitor error logs for issues

## ⚡ Quick Commands

### Test Production Security
```bash
php scripts/setup/production_security_check.php
```

### Clear Local Development Data
```bash
# Clear sessions if needed
php -r "session_start(); session_destroy();"
```

## 🆘 Troubleshooting

### Issue: OTP codes showing in production
**Solution**: Verify `APP_DEBUG=0` in Coolify environment

### Issue: No OTP emails received
**Solution**: Check SMTP configuration in Coolify environment

### Issue: Database connection failed
**Solution**: Verify database credentials in Coolify environment

### Issue: Forms not submitting
**Solution**: Check error logs for CSRF or validation issues

---

**Status**: ✅ Ready for Production Deployment
**Last Updated**: October 14, 2025
**Security Level**: Production Grade