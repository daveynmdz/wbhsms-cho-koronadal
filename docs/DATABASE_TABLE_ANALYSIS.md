# Database Table Analysis - Staff Assignment System

## ✅ **CORRECT TABLES TO USE:**

### 1. **`stations` Table** 
- **Purpose**: Defines physical stations in the health facility
- **Structure**: 
  - `station_id` (Primary Key)
  - `station_name` (e.g., "Check-In Counter", "Triage Station 1")
  - `station_type` (enum: 'checkin','triage','billing','consultation','lab','pharmacy','document')
  - `station_number` (for multiple stations of same type)
  - `service_id` (links to services table)
  - `is_active` (enables/disables station)

### 2. **`station_assignments` Table**
- **Purpose**: Assigns employees to specific stations by date
- **Structure**:
  - `assignment_id` (Primary Key)
  - `station_id` (Foreign Key to stations table)
  - `employee_id` (Foreign Key to employees table)
  - `assigned_date` (date of assignment)
  - `shift_start` & `shift_end` (work hours)
  - `status` (active/inactive)
  - `assigned_by` (who made the assignment)

## ❌ **REDUNDANT TABLE (Do Not Use):**

### `staff_assignments` Table
- **Issue**: Duplicates functionality but uses different approach
- **Problems**: 
  - Uses `station_type` + `station_number` instead of `station_id`
  - No relationship to actual stations table
  - Different station types than stations table
  - Creates confusion and data inconsistency

## ✅ **RECOMMENDATION:**

**Use Only:** `stations` + `station_assignments` tables

**Reasons:**
1. ✅ Proper relational design with foreign keys
2. ✅ Already has existing data (Oct 6-20 assignments)  
3. ✅ Follows database normalization principles
4. ✅ Consistent with rest of system architecture

## 🛠 **Code Fixed To Use:**
- `stations` table for station definitions
- `station_assignments` table for employee assignments
- Proper JOIN operations between tables
- Correct station types: 'checkin','triage','billing','consultation','lab','pharmacy','document'

## 📊 **Station Types & Roles:**
- **checkin** → records_officer, nurse
- **triage** → nurse  
- **consultation** → doctor
- **lab** → laboratory_tech
- **pharmacy** → pharmacist
- **billing** → cashier
- **document** → records_officer