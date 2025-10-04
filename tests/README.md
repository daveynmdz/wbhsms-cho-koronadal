# Testing Utilities

This directory contains testing utilities for the CHO Koronadal Healthcare Management System.

## Available Tests

### Database Connection Test
- **File:** `testdb.php` - Advanced database connectivity test
- **Root Test:** `/testdb.php` - Simple XAMPP-friendly test
- **Setup Check:** `/setup_check.php` - Complete system validation

### Integration Tests
- **File:** `test_appointment_queueing_integration.php` - Enhanced appointment booking + queueing integration test
- **Features:** Tests time slot queue numbering, visit creation, appointment logging, 20-patient slot limits

## XAMPP Testing

For XAMPP users, use the simplified tests in the root directory:

1. **Setup Validation:** http://localhost/wbhsms-cho-koronadal/setup_check.php
2. **Database Test:** http://localhost/wbhsms-cho-koronadal/testdb.php
3. **Homepage:** http://localhost/wbhsms-cho-koronadal/

## Running Tests

### Via Browser (Recommended for XAMPP)
```
http://localhost/wbhsms-cho-koronadal/setup_check.php
http://localhost/wbhsms-cho-koronadal/testdb.php
```

### Via Command Line
```bash
# From project root
php setup_check.php
php testdb.php
```

## Security Note

⚠️ **Important:** Test files should be removed or blocked in production environments.