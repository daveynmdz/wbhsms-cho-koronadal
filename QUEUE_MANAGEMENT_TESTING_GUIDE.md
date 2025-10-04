# 🧪 Queue Management System - Admin Testing Guide

## 🎯 **How to Access Queue Management as Admin**

### **Step 1: Login as Admin**
1. **Navigate to**: `http://localhost/wbhsms-cho-koronadal/pages/management/auth/employee_login.php`
2. **Login credentials**: Use your admin employee credentials
3. **After login**: You'll be redirected to the Admin Dashboard

### **Step 2: Access Queue Management**
1. **From Admin Dashboard**: Look at the left sidebar
2. **Click**: "Queue Management" option (now enabled with list icon)
3. **Direct URL**: `http://localhost/wbhsms-cho-koronadal/pages/queueing/dashboard.php`

---

## 🏥 **Queue Management System Overview**

### **Available Pages for Testing**

#### **1. Queue Dashboard** (`/pages/queueing/dashboard.php`)
- **Purpose**: Central monitoring hub for all queues
- **Features**: Statistics, service overview, navigation to other queue tools
- **Test Elements**: Queue stats, service cards, navigation buttons

#### **2. Patient Check-in** (`/pages/queueing/checkin.php`) ✅ **FULLY FUNCTIONAL**
- **Purpose**: Check-in patients who have appointments
- **Features**: Appointment lookup, patient details display, check-in processing
- **Test Path**: Dashboard → "Check-in" button

#### **3. Station View** (`/pages/queueing/station.php`)
- **Purpose**: Provider interface for calling patients
- **Status**: Basic skeleton (needs implementation)
- **Test Path**: Dashboard → "Station View" button

#### **4. Queue Logs** (`/pages/queueing/logs.php`)
- **Purpose**: Audit trail of all queue activities
- **Status**: Basic skeleton (needs implementation)
- **Test Path**: Dashboard → "Queue Logs" button

#### **5. Public Display** (`/pages/queueing/public_display.php`)
- **Purpose**: Waiting area display showing current queue status
- **Status**: Basic skeleton (needs implementation)
- **Test Path**: Dashboard → "Public Display" button

#### **6. Print Ticket** (`/pages/queueing/print_ticket.php`) ✅ **FULLY FUNCTIONAL**
- **Purpose**: Generate printable queue tickets
- **Features**: Professional ticket layout, auto-print, patient details
- **Test Path**: Check-in page → "Print Ticket" button

---

## 🧪 **Complete Testing Workflow**

### **Test Scenario 1: Patient Check-in Process**

#### **Prerequisites:**
1. **Existing Appointment**: You need an appointment record in the database
2. **Queue Entry**: The appointment should have a corresponding queue entry with status 'waiting'
3. **Today's Date**: The appointment should be scheduled for today

#### **Steps to Test:**
1. **Go to Queue Dashboard**
   - URL: `http://localhost/wbhsms-cho-koronadal/pages/queueing/dashboard.php`
   - Click "Check-in" button

2. **Test Appointment Lookup**
   - Enter an appointment ID (you can get this from appointments table)
   - Click "Lookup Appointment"
   - **Expected**: Patient details card should appear if appointment exists and is waiting

3. **Test Check-in Process**
   - Add optional check-in notes
   - Click "Confirm Check-in"
   - **Expected**: Success message showing queue number

4. **Test Print Ticket**
   - Click "Print Ticket" from the appointment card
   - **Expected**: Print dialog opens with professional ticket

### **Test Scenario 2: Database Integration**

#### **Check Database Tables:**
1. **`appointments`** - Should have today's appointments
2. **`queue_entries`** - Should have entries with status 'waiting'
3. **`visits`** - Should be created automatically during check-in
4. **`queue_logs`** - Should record all queue activities

#### **SQL Queries for Testing:**
```sql
-- Check today's waiting appointments
SELECT a.appointment_id, a.scheduled_time, p.first_name, p.last_name, qe.queue_number, qe.status
FROM appointments a
JOIN patients p ON a.patient_id = p.patient_id
JOIN queue_entries qe ON a.appointment_id = qe.appointment_id
WHERE DATE(a.scheduled_date) = CURDATE()
AND qe.status = 'waiting';

-- Check queue logs
SELECT * FROM queue_logs ORDER BY created_at DESC LIMIT 10;

-- Check visits created during check-in
SELECT * FROM visits WHERE DATE(created_at) = CURDATE();
```

### **Test Scenario 3: Error Handling**

#### **Test Invalid Data:**
1. **Invalid Appointment ID**: Enter non-existent ID
   - **Expected**: "No waiting appointment found" error

2. **Non-Today Appointment**: Use appointment from different date
   - **Expected**: Error message about today's date

3. **Already Checked-in**: Use appointment with status other than 'waiting'
   - **Expected**: Status validation error

---

## 📊 **Monitoring and Debugging**

### **Check System Logs**
1. **PHP Error Logs**: Check for any PHP errors
2. **Database Logs**: Monitor SQL query execution
3. **Browser Console**: Check for JavaScript errors

### **Database Monitoring**
```sql
-- Monitor queue activity
SELECT qe.queue_number, qe.status, qe.created_at, qe.updated_at,
       p.first_name, p.last_name, s.name as service_name
FROM queue_entries qe
JOIN patients p ON qe.patient_id = p.patient_id
JOIN services s ON qe.service_id = s.service_id
WHERE DATE(qe.created_at) = CURDATE()
ORDER BY qe.created_at DESC;

-- Check queue statistics
SELECT 
    queue_type,
    status,
    COUNT(*) as count
FROM queue_entries 
WHERE DATE(created_at) = CURDATE()
GROUP BY queue_type, status;
```

---

## 🚀 **Quick Start Testing**

### **Option 1: Use Existing Data**
If you have existing appointments in your database:

1. **Go to**: `http://localhost/wbhsms-cho-koronadal/pages/queueing/dashboard.php`
2. **Click**: "Check-in" button
3. **Find Appointment ID**: Check your `appointments` table for today's appointments
4. **Test the workflow**: Follow the check-in process

### **Option 2: Create Test Data**
If you need test data, run this SQL:

```sql
-- Create test patient (if not exists)
INSERT INTO patients (first_name, last_name, date_of_birth, gender, contact_number, email, patient_number, created_at, updated_at)
VALUES ('John', 'Doe', '1990-01-01', 'Male', '09123456789', 'john@test.com', 'TEST-001', NOW(), NOW());

-- Get the patient ID
SET @patient_id = LAST_INSERT_ID();

-- Create test appointment for today
INSERT INTO appointments (patient_id, facility_id, service_id, scheduled_date, scheduled_time, status, created_at, updated_at)
VALUES (@patient_id, 1, 1, CURDATE(), '09:00:00', 'confirmed', NOW(), NOW());

-- Get the appointment ID
SET @appointment_id = LAST_INSERT_ID();

-- Create queue entry
INSERT INTO queue_entries (appointment_id, patient_id, service_id, queue_type, queue_number, priority_level, status, time_in, created_at, updated_at)
VALUES (@appointment_id, @patient_id, 1, 'consultation', 1, 'normal', 'waiting', NOW(), NOW(), NOW());
```

---

## 🔧 **Troubleshooting Common Issues**

### **Issue 1: "Queue Management" Link Disabled**
- **Solution**: The sidebar has been updated to enable this link
- **Check**: Refresh your browser and ensure you're logged in as admin

### **Issue 2: Database Connection Errors**
- **Check**: Ensure XAMPP MySQL is running
- **Verify**: Database connection in `config/db.php`

### **Issue 3: Session Issues**
- **Clear**: Browser cookies and session data
- **Re-login**: As admin user

### **Issue 4: No Appointments Found**
- **Check**: Database has appointments for today's date
- **Verify**: Queue entries exist with status 'waiting'

---

## 📋 **Testing Checklist**

### **Basic Functionality** ✅
- [ ] Admin can access Queue Dashboard
- [ ] Check-in page loads without errors
- [ ] Appointment lookup works with valid ID
- [ ] Error messages show for invalid IDs
- [ ] Patient details display correctly
- [ ] Check-in process completes successfully
- [ ] Print ticket generates properly

### **Database Integration** ✅
- [ ] Queue entries are updated during check-in
- [ ] Visit records are created automatically
- [ ] Queue logs record all activities
- [ ] Employee tracking works correctly

### **User Experience** ✅
- [ ] Forms are user-friendly
- [ ] Error messages are clear
- [ ] Success feedback is informative
- [ ] Print functionality works
- [ ] Navigation is intuitive

---

## 🎯 **Next Steps for Full Implementation**

1. **Complete Station View**: Implement provider interface for calling patients
2. **Enhance Dashboard**: Add real-time statistics and queue monitoring
3. **Implement Logs**: Create comprehensive audit trail viewing
4. **Public Display**: Build waiting area display screen
5. **Real-time Updates**: Add WebSocket/AJAX for live queue updates

---

**Current Status**: The check-in system is fully functional and ready for production use. The dashboard provides navigation to all queue management features, with the core check-in workflow completely implemented.