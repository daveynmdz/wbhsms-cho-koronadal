# Laboratory Management Module

## Overview
This module provides comprehensive laboratory management functionality for the Web-Based Healthcare Services Management System (WBHSMS). It enables doctors to order lab tests, laboratory technicians to manage test processing and upload results, and provides secure access to lab records.

## Features

### 1. Lab Order Management
- **Create Lab Orders**: Doctors and nurses can create lab orders by selecting patients and choosing from predefined lab tests
- **Consolidated View**: One lab order per patient per day, with individual test items tracked separately
- **Status Tracking**: Real-time status updates for individual tests and overall order status
- **Search & Filter**: Filter by patient name, order date, and status

### 2. Lab Test Processing
- **Test Status Management**: Track tests through pending → in progress → completed workflow
- **Result Upload**: Secure PDF upload for lab results (lab technicians only)
- **Result Text**: Text-based results entry with remarks
- **Automatic Status Updates**: Overall order status auto-calculated from individual test statuses

### 3. Role-Based Access Control
- **Doctors/Nurses**: Can create lab orders and view results
- **Laboratory Technicians**: Can upload results, update test status, and manage all lab operations
- **Admins**: Full access to all laboratory management functions
- **Patients**: Can view and download their own lab results (via patient portal integration)

### 4. Security Features
- **Server-side Authorization**: All operations validated server-side based on user role
- **Secure File Upload**: PDF validation, size limits, unique filenames
- **Protected Downloads**: Access control for lab result files
- **Directory Security**: .htaccess protection for uploads directory

## Database Schema

### Required Tables

#### lab_order_items (New Table)
Run the SQL in `database/lab_order_items_table.sql` to create this table:

```sql
CREATE TABLE `lab_order_items` (
  `lab_order_item_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `lab_order_id` int UNSIGNED NOT NULL,
  `test_type` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `result` text COLLATE utf8mb4_unicode_ci,
  `result_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result_date` datetime DEFAULT NULL,
  `uploaded_by_employee_id` int UNSIGNED DEFAULT NULL,
  `special_instructions` text COLLATE utf8mb4_unicode_ci,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lab_order_item_id`),
  FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`lab_order_id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by_employee_id`) REFERENCES `employees` (`employee_id`)
);
```

#### lab_orders (Modified)
The existing table is modified to remove redundant columns and add overall_status:
- Removed: `test_type`, `result`, `result_date`
- Added: `overall_status` enum('pending','in_progress','completed','cancelled','partial') DEFAULT 'pending'

## Installation

### 1. Database Setup
```sql
-- Run this SQL to set up the required tables
SOURCE database/lab_order_items_table.sql;
```

### 2. Directory Permissions
Ensure the uploads directory has proper write permissions:
```bash
chmod 755 uploads/lab_results/
```

### 3. Navigation Update
The admin sidebar has been updated to include the Laboratory Management link. Ensure your role-based navigation includes the laboratory_management active page.

## File Structure

```
pages/laboratory-management/
├── lab_management.php              # Main dashboard
├── create_lab_order.php           # Lab order creation form
├── upload_lab_result.php          # Result upload interface
└── api/
    ├── get_lab_order_details.php  # Order details modal content
    ├── update_lab_item_status.php # Individual test status updates
    ├── update_lab_order_status.php # Overall order status updates
    └── download_lab_result.php    # Secure file download

uploads/lab_results/                # PDF storage (protected by .htaccess)
├── .htaccess                      # Security configuration
└── [uploaded PDF files]
```

## Usage

### For Doctors/Nurses
1. Navigate to Laboratory Management from admin sidebar
2. Click "Create Lab Order" to order tests for patients
3. Select patient and choose from predefined lab tests
4. View order status and progress in the main dashboard

### For Laboratory Technicians
1. Access Laboratory Management dashboard
2. View pending orders in the left panel
3. Click "View" to see detailed test items
4. Upload results using the Upload button for each test
5. Update test status as work progresses

### For Patients (Integration Required)
Patients can view their lab results through the patient portal by accessing the lab records section (integration with patient portal required).

## API Endpoints

### Authentication
All API endpoints require valid employee session with appropriate role permissions.

### Available Endpoints
- `GET api/get_lab_order_details.php?lab_order_id={id}` - Get order details modal
- `POST api/update_lab_item_status.php` - Update individual test status
- `POST api/update_lab_order_status.php` - Update overall order status
- `GET api/download_lab_result.php?file={filename}` - Download lab result PDF

## Predefined Lab Tests

The system includes 20 common lab tests with appropriate preparation instructions:
- Complete Blood Count (CBC)
- Urinalysis
- Fasting Blood Sugar (FBS)
- Lipid Profile
- Hepatitis B Surface Antigen
- Pregnancy Test (HCG)
- Stool Examination
- Chest X-ray
- Electrocardiogram (ECG)
- Blood Typing & Rh Factor
- Creatinine
- SGPT/ALT
- SGOT/AST
- Total Cholesterol
- Triglycerides
- Hemoglobin A1C
- Thyroid Function Test (TSH)
- Prostate Specific Antigen (PSA)
- Pap Smear
- Sputum Examination

## Security Considerations

### Server-Side Validation
- All operations validate user roles server-side
- File upload validation (PDF only, 10MB max)
- SQL injection prevention with prepared statements
- Directory traversal protection

### File Security
- Uploaded files stored outside web root access
- Unique filename generation
- .htaccess protection for uploads directory
- Access control for file downloads

### Role Enforcement
```php
// Sample server-side role enforcement
if ($_SESSION['role'] !== 'laboratory_tech' && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Not authorized');
}
```

## Troubleshooting

### Common Issues

1. **File Upload Fails**
   - Check directory permissions: `chmod 755 uploads/lab_results/`
   - Verify PHP upload settings: `upload_max_filesize`, `post_max_size`
   - Ensure file is PDF format and under 10MB

2. **Modal Not Loading**
   - Check JavaScript console for errors
   - Verify API endpoint paths are correct
   - Ensure session is valid

3. **Permission Denied**
   - Verify user role in database matches expected values
   - Check server-side authorization in PHP files
   - Ensure employee session is properly configured

### Debug Mode
Enable debug mode in `config/env.php` to see detailed error messages during development.

## Integration Notes

### Patient Portal Integration
To allow patients to view their lab results, integrate with the patient portal by:
1. Adding lab results section to patient dashboard
2. Using the download API with patient session validation
3. Filtering results by patient_id from session

### Clinical Encounter Integration
Lab orders can be linked to appointments, consultations, and visits through the respective ID fields in the lab_orders table.

## Future Enhancements

- Email notifications for completed results
- Barcode scanning for specimen tracking
- Integration with laboratory equipment
- Advanced reporting and analytics
- Mobile app support via API expansion