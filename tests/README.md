# Testing Utilities

This directory contains testing utilities for the CHO Koronadal Healthcare Management System.

## Available Tests

### Database Connection Tests
- **File:** `testdb.php` - Advanced database connectivity test with local/remote options
- **File:** `simple_database_check.php` - Basic database connection verification

### Queue System Tests
- **File:** `test_complete_patient_flow.php` - End-to-end patient journey testing (Check-in → Triage → Consultation → Pharmacy → Billing)
- **File:** `test_station_interfaces.php` - Verify all station interfaces work with QueueManagementService
- **File:** `test_critical_fixes.php` - Test core QueueManagementService methods after MySQLi/PDO fixes
- **File:** `test_routing_fix.php` - Test the fixed routePatientToStation method
- **File:** `working_queue_simulation.php` - Working queue simulation bypassing known issues
- **File:** `simple_queue_test.php` - Simple queue testing with AJAX interface
- **File:** `queue_test.php` - Complete queue test with data setup
- **File:** `debug_queue.php` - Debug queue system components
- **File:** `debug_queue_status.php` - Check latest queue entries and statuses
- **File:** `debug_simulation.php` - Raw response debugging for queue simulation
- **File:** `test_queue_setup.php` - Basic queue tables and data verification

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