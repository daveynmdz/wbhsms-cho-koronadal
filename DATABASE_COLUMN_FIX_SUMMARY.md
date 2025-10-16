# Database Column Fix - New Consultation System

## 🐛 **Issue Identified**
**Error:** `Unknown column 'a.appointment_type' in 'field list'`

The new consultation system was trying to access `appointment_type` column which doesn't exist in the appointments table.

## 🔍 **Root Cause Analysis**

### **Appointments Table Structure (Actual):**
```sql
CREATE TABLE `appointments` (
  `appointment_id` int UNSIGNED NOT NULL,
  `patient_id` int UNSIGNED NOT NULL,
  `facility_id` int UNSIGNED NOT NULL,
  `referral_id` int UNSIGNED DEFAULT NULL,
  `service_id` int UNSIGNED DEFAULT NULL,        -- This exists
  `scheduled_date` date NOT NULL,
  `scheduled_time` time NOT NULL,
  `status` enum('confirmed','completed','cancelled','checked_in'),
  -- No 'appointment_type' column
  -- No 'chief_complaint' column
)
```

### **Services Table Relationship:**
The appointments table uses `service_id` to link to a `services` table, which contains the service names/types.

## ✅ **Fixes Applied**

### **1. Updated Patient Search Query**
**Before:**
```sql
SELECT a.appointment_type, a.chief_complaint as appointment_complaint
FROM appointments a
```

**After:**
```sql
SELECT COALESCE(s.service_name, 'General Consultation') as service_name
FROM appointments a
LEFT JOIN services s ON a.service_id = s.service_id
```

### **2. Updated Patient Details Query**
**Before:**
```sql
SELECT a.appointment_type, a.chief_complaint as appointment_complaint
FROM appointments a
```

**After:**
```sql
SELECT COALESCE(s.service_name, 'General Consultation') as service_name
FROM appointments a
LEFT JOIN services s ON a.service_id = s.service_id
```

### **3. Updated JavaScript References**
**Before:**
```javascript
<small>${patient.appointment_type || 'General'}</small>
// Pre-fill from appointment_complaint
```

**After:**
```javascript
<small>${patient.service_name || 'General'}</small>
// Removed appointment_complaint reference
```

## 🎯 **Changes Made**

### **Files Modified:**
- `pages/clinical-encounter-management/new_consultation.php`

### **Specific Changes:**
1. **Added JOIN with services table** to get proper service names
2. **Replaced `appointment_type` with `service_name`** from services table
3. **Removed `chief_complaint`** reference from appointments table
4. **Updated JavaScript** to use correct field names
5. **Added COALESCE** to provide fallback for missing service names

## 📊 **Database Query Optimization**

### **New Query Structure:**
```sql
SELECT DISTINCT
    v.visit_id,
    p.first_name, p.last_name, p.middle_name,
    a.scheduled_date, a.scheduled_time,
    COALESCE(s.service_name, 'General Consultation') as service_name,
    c.consultation_status,
    vt.blood_pressure -- vitals check
FROM visits v
INNER JOIN patients p ON v.patient_id = p.patient_id
INNER JOIN appointments a ON v.appointment_id = a.appointment_id
LEFT JOIN services s ON a.service_id = s.service_id  -- NEW JOIN
LEFT JOIN consultations c ON v.visit_id = c.visit_id
LEFT JOIN vitals vt ON v.visit_id = vt.visit_id
WHERE a.status IN ('checked_in', 'in_progress')
AND v.visit_status IN ('checked_in', 'active', 'in_progress')
```

## ✅ **Expected Results**

After this fix, the new consultation system should:

1. **✅ Load without SQL errors**
2. **✅ Display correct service names** (Primary Care, Dental, etc.) instead of "appointment_type"
3. **✅ Show proper appointment information** in the search results
4. **✅ Allow patient selection and consultation form loading**

## 🧪 **Testing Verification**

To verify the fix works:

1. **Navigate to:** `Clinical Encounters → New Consultation`
2. **Search for patients** - should load without errors
3. **Check service names** - should show actual service names from services table
4. **Select a patient** - consultation form should populate correctly

## 🔧 **Database Compatibility**

The fix maintains full compatibility with:
- ✅ Existing appointments table structure
- ✅ Services table relationships  
- ✅ Visits and consultations integration
- ✅ All existing appointment management features

---

**Status:** ✅ **FIXED**  
**Date:** October 16, 2025  
**Impact:** New consultation system now works with actual database schema