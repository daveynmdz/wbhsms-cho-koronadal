# QR Code Enhancement for Appointment Confirmations

## 📋 Overview

This enhancement adds QR code generation and email integration to the appointment booking system. When patients book appointments, they receive a confirmation email with an embedded QR code that can be scanned at the check-in counter for fast service.

## ✨ Features Implemented

### 1. QR Code Generation
- **Library**: Custom PHP QR Code implementation (`/includes/phpqrcode.php`)
- **JSON Payload Format**: 
  ```json
  {
    "appointment_id": "APT-00000024",
    "patient_id": "35", 
    "referral_id": null
  }
  ```
- **File Storage**: `/assets/qr/appointments/QR-APT-00000024.png`
- **Error Handling**: Directory creation, write permissions, file validation

### 2. Enhanced Email System
- **Subject**: "Your Appointment Confirmation – City Health Office of Koronadal"
- **Embedded QR Code**: Displayed inline using `addEmbeddedImage()`
- **QR Attachment**: Also attached as file for saving
- **Fallback**: Manual check-in instructions if QR generation fails
- **Responsive Design**: Works in Gmail, Outlook, mobile clients

### 3. Integration Points
- **Appointment Booking**: Automatic QR generation after successful booking
- **Check-in System**: Compatible with existing QR scanner in `checkin.php`
- **Database**: Optional `qr_code_path` column for tracking
- **Queue Management**: Works with existing queue system

## 🗂️ Files Modified/Created

### New Files
```
/includes/phpqrcode.php                        # QR code generation library
/utils/appointment_qr_generator.php           # QR generation utility class
/pages/patient/appointment/test_qr_email.php  # Comprehensive test suite
/test_simple_qr.php                          # Quick QR generation test
/database/optional_qr_enhancement.sql        # Optional DB schema enhancement
```

### Modified Files
```
/pages/patient/appointment/submit_appointment.php
- Added QR generation after appointment creation
- Enhanced email function with QR embedding
- Updated response to include QR status
```

### Directory Structure
```
/assets/qr/appointments/          # QR code image storage
├── QR-APT-00000024.png          # Individual appointment QR codes
├── QR-APT-00000025.png
└── ...
```

## 🚀 How It Works

### 1. Appointment Booking Flow
```
Patient Books Appointment
    ↓
Appointment Created in Database
    ↓
QR Code Generated with JSON Payload
    ↓ 
QR Image Saved to /assets/qr/appointments/
    ↓
Email Sent with Embedded QR Code
    ↓
Patient Receives Confirmation
```

### 2. Check-in Flow
```
Patient Arrives at Facility
    ↓
Staff Opens Check-in System (HTTPS Required)
    ↓
QR Scanner Activated
    ↓
QR Code Scanned from Email
    ↓
JSON Parsed and Appointment Auto-filled
    ↓
Fast Check-in Completed
```

## 🧪 Testing Instructions

### 1. Quick Test
1. Access: `http://localhost:8080/wbhsms-cho-koronadal/test_simple_qr.php`
2. Verify QR code generation works
3. Check that image files are created

### 2. Full Integration Test
1. Access: `http://localhost:8080/wbhsms-cho-koronadal/pages/patient/appointment/test_qr_email.php`
2. Run all test suites
3. Verify all components are working

### 3. End-to-End Test
1. **Book Appointment**: Use normal booking flow
2. **Check Email**: Verify QR code in confirmation email
3. **Test Scanning**: Access check-in system via HTTPS
4. **Scan QR**: Use camera to scan QR from email
5. **Verify Auto-fill**: Confirm appointment data populates

## ⚙️ Configuration

### Email Configuration
Email settings are configured in `/config/env.php`:
```php
$_ENV['SMTP_HOST'] = 'smtp.gmail.com';
$_ENV['SMTP_USER'] = 'cityhealthofficeofkoronadal@gmail.com';
$_ENV['SMTP_PASS'] = 'your-app-password';
$_ENV['SMTP_PORT'] = 587;
```

### HTTPS Requirement
- **QR Scanning**: Requires HTTPS due to browser camera security policies
- **Access URLs**: 
  - `https://localhost/wbhsms-cho-koronadal/pages/queueing/checkin.php`
  - `https://192.168.1.3/wbhsms-cho-koronadal/pages/queueing/checkin.php`

### Optional Database Enhancement
```sql
ALTER TABLE appointments 
ADD COLUMN qr_code_path VARCHAR(255) NULL 
COMMENT 'Relative path to appointment QR code image file';
```

## 🔧 Maintenance

### QR File Cleanup
The system includes automatic cleanup functionality:
```php
$qr_generator = new AppointmentQRGenerator();
$result = $qr_generator->cleanupOldQRCodes(30); // Delete files older than 30 days
```

### Monitoring
- **QR Generation**: Check `/assets/qr/appointments/` directory size
- **Email Delivery**: Monitor SMTP logs for failures
- **Error Logs**: Check PHP error logs for QR generation issues

## 🎯 Benefits

### For Patients
- ✅ Fast check-in process
- ✅ Visual confirmation of appointment
- ✅ Reduced waiting time
- ✅ Modern, tech-savvy experience

### For Staff
- ✅ Automatic data entry
- ✅ Reduced manual errors
- ✅ Faster patient processing
- ✅ Better queue management

### For System
- ✅ Integration with existing check-in system
- ✅ Maintains current workflow
- ✅ Backward compatible (manual check-in still works)
- ✅ Scalable solution

## 🔍 Troubleshooting

### Common Issues

#### QR Code Not Generated
- **Check**: Directory permissions on `/assets/qr/appointments/`
- **Solution**: Ensure PHP can write to the directory
- **Command**: `chmod 755 /assets/qr/appointments/`

#### Email Not Sending
- **Check**: SMTP configuration in `/config/env.php`
- **Solution**: Verify Gmail app password or SMTP credentials
- **Test**: Use test script to verify email configuration

#### Camera Not Working in QR Scanner
- **Issue**: HTTP instead of HTTPS
- **Solution**: Access check-in system via HTTPS
- **URLs**: Use `https://localhost` or `https://192.168.1.3`

#### QR Scanner Not Reading Code
- **Check**: JSON format in generated QR
- **Solution**: Verify QR contains proper appointment JSON
- **Test**: Use any QR scanner app to read generated code

## 📞 Support

For issues with the QR code enhancement:

1. **Check Test Scripts**: Run comprehensive tests first
2. **Verify Configuration**: Ensure HTTPS and SMTP are configured
3. **Check Logs**: Review PHP error logs for specific issues
4. **File Permissions**: Verify write access to QR directory

## 🚀 Future Enhancements

### Potential Improvements
- **QR Code Expiration**: Add time-based QR validation
- **Digital Wallet Integration**: Apple Wallet/Google Pay passes
- **SMS QR Delivery**: Send QR codes via SMS as backup
- **Advanced Analytics**: Track QR usage and scan rates
- **Batch QR Generation**: Generate QR codes for multiple appointments

### API Extensions
- **QR Regeneration**: Allow patients to regenerate lost QR codes
- **QR Validation**: Real-time QR code verification endpoint
- **Mobile App Integration**: Native mobile app QR scanning