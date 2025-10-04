# Email Configuration Setup Guide

## Overview
The appointment booking system now includes automatic email confirmation functionality. When patients book appointments, they will receive a professionally formatted email with:

- Complete appointment details
- Appointment reference number
- Important reminders (arrive early, bring ID, etc.)
- Contact information for changes/cancellations
- Facility-specific information

## Quick Setup Instructions

### 1. Copy Environment File
```bash
copy .env.example .env
```

### 2. Configure Email Settings in .env
Open the `.env` file and update these settings:

```env
# Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-cho-email@gmail.com
SMTP_PASSWORD=your-app-password-here
FROM_EMAIL=noreply@chokoronadal.gov.ph
FROM_NAME=CHO Koronadal Health System
```

### 3. Gmail Setup (Recommended)

#### Step 1: Enable 2-Factor Authentication
1. Go to your Google Account settings: https://myaccount.google.com/
2. Click on "Security" in the left menu
3. Under "Signing in to Google", click "2-Step Verification"
4. Follow the setup process

#### Step 2: Generate App Password
1. After enabling 2FA, go back to Security settings
2. Click "App passwords" under "Signing in to Google"
3. Select "Mail" for app type and "Other" for device
4. Enter "CHO Koronadal Health System" as the device name
5. Copy the generated 16-character password
6. Use this password in your `.env` file as `SMTP_PASSWORD`

#### Step 3: Update Configuration
```env
SMTP_USERNAME=your-gmail-address@gmail.com
SMTP_PASSWORD=abcd-efgh-ijkl-mnop  # The 16-character app password
```

### 4. Alternative Email Providers

#### For Outlook/Hotmail:
```env
SMTP_HOST=smtp-mail.outlook.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
```

#### For Yahoo Mail:
```env
SMTP_HOST=smtp.mail.yahoo.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
```

#### For Custom SMTP:
```env
SMTP_HOST=your-smtp-server.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-username
SMTP_PASSWORD=your-password
```

### 5. Test Configuration

#### Enable Debug Mode (Optional)
Add this to your `.env` file to see detailed email logs:
```env
DEBUG_EMAIL=true
```

#### Test Email Sending
1. Book a test appointment
2. Check the browser console for any email-related errors
3. Check your email logs if debug mode is enabled
4. Verify the patient receives the confirmation email

## Email Features

### Professional Template
- Mobile-responsive design
- CHO Koronadal branding
- Clear appointment details
- Important reminders section
- Contact information

### Smart Status Handling
- Shows email success/failure in booking confirmation
- Graceful degradation if email fails
- Detailed error messages for troubleshooting

### Security Features
- Uses secure SMTP with TLS encryption
- Proper error handling and logging
- No sensitive data exposure in error messages

## Troubleshooting

### Common Issues

#### "SMTP connection failed"
- Check your internet connection
- Verify SMTP host and port settings
- Ensure your email provider allows SMTP access

#### "Authentication failed"
- Double-check username and password
- For Gmail, ensure you're using an app password, not your regular password
- Verify 2-factor authentication is enabled (for Gmail)

#### "Email rejected by server"
- Check if the recipient email address is valid
- Verify your FROM_EMAIL domain is properly configured
- Some servers reject emails from localhost - consider using a real domain

#### "Permission denied"
- Ensure your email account has SMTP access enabled
- Check if your hosting provider blocks outgoing SMTP connections

### Log Files
Email errors are logged to your PHP error log. Check:
- XAMPP: `xampp/php/logs/php_error_log`
- Apache logs: `xampp/apache/logs/error.log`

### Debug Mode
Enable debug mode in your `.env` file:
```env
DEBUG_EMAIL=true
```

This will show detailed SMTP communication in your logs.

## Configuration Examples

### Production Setup (Gmail)
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=appointments@chokoronadal.gov.ph
SMTP_PASSWORD=your-app-password
FROM_EMAIL=noreply@chokoronadal.gov.ph
FROM_NAME=CHO Koronadal Health System
CONTACT_PHONE=(083) 228-8042
CONTACT_EMAIL=info@chokoronadal.gov.ph
DEBUG_EMAIL=false
```

### Development Setup
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-test-email@gmail.com
SMTP_PASSWORD=your-app-password
FROM_EMAIL=test@chokoronadal.local
FROM_NAME=CHO Koronadal Health System (Test)
DEBUG_EMAIL=true
```

## Security Best Practices

1. **Never commit `.env` file to version control**
2. **Use app passwords, not regular passwords**
3. **Enable 2-factor authentication**
4. **Regularly rotate app passwords**
5. **Use dedicated email account for system notifications**
6. **Monitor email sending logs for unusual activity**

## Support

If you encounter issues:
1. Check the troubleshooting section above
2. Enable debug mode and check logs
3. Verify your email provider's SMTP settings
4. Test with a simple email client first
5. Contact your system administrator for assistance