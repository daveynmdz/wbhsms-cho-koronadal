# Enhanced Appointment Booking & Queueing Integration

## Summary of Changes Made

This document outlines the improvements made to the appointment booking and queueing system to address the requirements for time slot-based queue numbering, proper visit management, and comprehensive appointment logging.

## ✅ **Changes Implemented**

### 1. **Time Slot-Based Queue Numbering**
- **File Modified:** `utils/queue_management_service.php`
- **Change:** Updated `generateQueueNumber()` method to generate queue numbers per time slot instead of per day
- **Logic:** Queue numbers are now sequential within each date + time + facility combination
- **Limit:** Maximum 20 patients per time slot to prevent overcrowding
- **Behavior:** Throws exception when time slot is full

### 2. **Proper Visit Record Creation**
- **File Modified:** `utils/queue_management_service.php`
- **Change:** Updated `createQueueEntry()` method to create visit records
- **Process:**
  1. Creates new row in `visits` table with patient_id, facility_id, appointment_id
  2. Sets visit_date = appointment.scheduled_date
  3. Sets visit_status = 'ongoing'
  4. Links queue_entries to the new visit_id

### 3. **Comprehensive Appointment Logging**
- **New File:** `utils/appointment_logger.php` - Utility class for appointment action logging
- **File Modified:** `pages/patient/appointment/submit_appointment.php`
- **Changes:**
  - All appointment creations now logged to `appointment_logs` with action = 'created'
  - Logs include scheduled date/time, creator information, IP address, user agent
  - Prepared for future rescheduling and update logging

### 4. **Enhanced Error Handling**
- **Graceful Slot Full Handling:** Appointments can still be created even if queue is full
- **Better Error Messages:** More descriptive error messages for queue failures
- **Transaction Safety:** All operations use database transactions for consistency

### 5. **Improved Response Data**
- **Additional Fields:** Queue responses now include visit_id for reference
- **Better Status Messages:** More informative success/error messages
- **Queue Information:** Enhanced queue details in API responses

## 📋 **Database Schema Impact**

### Tables Updated:
- **`visits`** - Now properly populated with appointment-linked visits
- **`queue_entries`** - Now links to proper visit_id instead of using appointment_id as visit_id
- **`appointment_logs`** - Now consistently populated for all appointment actions
- **`queue_logs`** - Continues to log all queue actions (unchanged)

### New Relationships:
```
appointments (1) → (1) visits
visits (1) → (1) queue_entries
appointments (1) → (n) appointment_logs
queue_entries (1) → (n) queue_logs
```

## 🔧 **Technical Features**

### Time Slot Management:
- Queue numbers reset for each unique date + time + facility combination
- 20 patient limit per time slot prevents overcrowding
- Queue numbers remain fixed (no renumbering after cancellations)

### Visit Lifecycle:
- Visit created automatically when queue entry is made
- Visit status starts as 'ongoing'
- Visit linked to both appointment and queue entry

### Audit Trail:
- Complete appointment lifecycle logged in `appointment_logs`
- All queue actions logged in `queue_logs`
- IP address and user agent tracking for security

## 📄 **Files Created/Modified**

### New Files:
- `utils/appointment_logger.php` - Appointment logging utility class
- `tests/test_appointment_queueing_integration.php` - Integration test script

### Modified Files:
- `utils/queue_management_service.php` - Enhanced with time slot logic and visit creation
- `pages/patient/appointment/submit_appointment.php` - Added appointment logging
- `tests/README.md` - Updated with new test information

## 🧪 **Testing**

### Test Script Available:
- **Location:** `tests/test_appointment_queueing_integration.php`
- **Tests:**
  1. Time slot queue number generation
  2. Visit record creation
  3. Appointment logging functionality
  4. 20-patient slot limit enforcement
  5. Automatic cleanup

### Manual Testing:
1. Book appointments for same time slot
2. Verify queue numbers are sequential within slot
3. Check that visit records are created
4. Confirm appointment_logs entries
5. Test slot full scenario (21st patient)

## ⚠️ **Breaking Changes**

### Queue Number Generation:
- **Before:** Sequential per queue_type per day
- **After:** Sequential per date + time + facility combination
- **Impact:** Queue numbers will restart at 1 for each time slot

### Visit Creation:
- **Before:** queue_entries.visit_id = appointment_id (invalid reference)
- **After:** Proper visit records created and referenced
- **Impact:** Better data integrity and reporting capabilities

## 🔄 **Future Enhancements Ready**

The system is now prepared for:
- **Appointment Rescheduling:** Use `AppointmentLogger->logAppointmentReschedule()`
- **Appointment Updates:** Use `AppointmentLogger->logAppointmentUpdate()`
- **Appointment Completion:** Use `AppointmentLogger->logAppointmentCompletion()`
- **Queue Transfer Between Services:** Enhanced queue management supports this
- **Visit Time Tracking:** time_in/time_out can be properly managed

## 📊 **Benefits Achieved**

1. **Better Queue Management:** Time slot-based numbering prevents overcrowding
2. **Improved Data Integrity:** Proper visit records and foreign key relationships
3. **Complete Audit Trail:** All appointment and queue actions logged
4. **Enhanced User Experience:** Better error messages and queue information
5. **Scalable Architecture:** Ready for future appointment management features

The enhanced system now provides a robust foundation for appointment booking with proper queueing, visit management, and comprehensive logging capabilities.