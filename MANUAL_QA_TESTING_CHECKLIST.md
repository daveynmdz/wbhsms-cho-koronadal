# Manual QA Testing Checklist - Queue System Integrity

**Generated:** <?php echo date('Y-m-d H:i:s'); ?>
**Version:** Final Audit v1.0

## 🔍 Pre-Testing Setup

### phpMyAdmin Database Verification
1. **Open phpMyAdmin** and navigate to your WBHSMS database
2. **Verify Core Tables Exist:**
   - [ /] `queue_entries` table exists with `station_id` column
   - [ /] `queue_logs` table exists with proper structure
   - [ /] `assignment_schedules` table exists (no `employee_station_assignments`)
   - [ /] `stations` table is properly configured

3. **Check Index Optimization:**
   ```sql
   -- Run these commands to verify indexes exist:
   SHOW INDEX FROM queue_entries WHERE Column_name = 'station_id';
   SHOW INDEX FROM queue_logs WHERE Column_name = 'queue_entry_id';
   SHOW INDEX FROM assignment_schedules WHERE Column_name = 'employee_id';
   ```
   - [ /] station_id index exists on queue_entries
   - [ /] queue_entry_id index exists on queue_logs
   - [ /] employee_id index exists on assignment_schedules

### Test Data Preparation  
4. **Check/Create Test Data for Today:**
   - [ ] **First, check existing data:**
     ```sql
     -- Verify you have test data for today
     SELECT COUNT(*) FROM queue_entries WHERE DATE(created_at) = CURDATE();
     SELECT COUNT(*) FROM assignment_schedules WHERE is_active = 1;
     ```
   - [ ] At least 3 queue entries exist for today
   - [ ] At least 2 active station assignments exist

5. **Verify Test Data Quality:**
   ```sql
   -- Check variety of queue statuses
   SELECT status, COUNT(*) as count 
   FROM queue_entries 
   WHERE DATE(created_at) = CURDATE() 
   GROUP BY status;
   ```
   - [ ] Multiple queue statuses present (waiting, in_progress, done, etc.)
   - [ ] Queue entries have valid station_id assignments
   - [ ] Audit logs exist for all queue entries

---

## 🏥 Browser Testing - Station Management

### Station Assignment Interface
6. **Access Station Management** (as Admin/DHO):
   - [ ] Navigate to `/pages/management/queueing/manage_queue.php`
   - [ ] Page loads without errors
   - [ ] Station assignments display correctly
   - [ ] Employee names show in assignment dropdowns

7. **Test Station Assignment:**
   - [ ] Select employee from dropdown
   - [ ] Assign to available station
   - [ ] Success message appears
   - [ ] Assignment reflects immediately in interface
   - [ ] **Verify in phpMyAdmin:** New record in `assignment_schedules` table

### Station Operation Interface  
8. **Access Station Dashboard** (as assigned employee):
   - [ ] Navigate to `/pages/queueing/station.php`
   - [ ] Correct station name displays in header
   - [ ] Queue list shows patients waiting
   - [ ] Statistics card shows accurate counts
   - [ ] "Call Next Patient" button is functional

---

## 📋 Queue Operations Testing

### Queue Entry Creation
9. **Create New Queue Entry:**
   - [ ] Book appointment through system
   - [ ] Queue entry auto-created with station_id populated
   - [ ] **Verify in phpMyAdmin:** 
     ```sql
     SELECT qe.*, ql.* FROM queue_entries qe 
     LEFT JOIN queue_logs ql ON qe.queue_entry_id = ql.queue_entry_id 
     WHERE qe.queue_entry_id = [LAST_CREATED_ID];
     ```
   - [ ] Queue entry has valid station_id
   - [ ] Corresponding log entry exists in queue_logs
   - [ ] Log action = 'created', new_status = 'waiting'

### Queue Status Transitions
10. **Test "Call Next Patient":**
   - [ ] Click "Call Next Patient" button
   - [ ] Patient status changes from 'waiting' to 'in_progress'
   - [ ] **Verify in phpMyAdmin:**
     ```sql
     SELECT * FROM queue_logs WHERE queue_entry_id = [PATIENT_ID] ORDER BY created_at DESC LIMIT 2;
     ```
   - [ ] New log entry created with action = 'status_changed'
   - [ ] old_status = 'waiting', new_status = 'in_progress'

11. **Test "Complete Patient":**
    - [ ] Click "Complete" on in-progress patient
    - [ ] Status changes to 'done'
    - [ ] Completion time recorded
    - [ ] **Verify in phpMyAdmin:** New log entry with status_changed to 'done'

12. **Test "Skip Patient":**
    - [ ] Click "Skip" on waiting patient  
    - [ ] Status changes to 'skipped'
    - [ ] **Verify in phpMyAdmin:** Log entry shows 'waiting' → 'skipped'

13. **Test "No Show":**
    - [ ] Mark patient as "No Show"
    - [ ] Status changes to 'no_show'
    - [ ] **Verify in phpMyAdmin:** Log entry shows transition to 'no_show'

14. **Test "Reinstate Patient":**
    - [ ] Reinstate a skipped/no-show patient
    - [ ] Status returns to 'waiting'
    - [ ] **Verify in phpMyAdmin:** Log entry shows 'reinstated' action

---

## 📊 Audit Integrity Verification

### Run Audit Scripts
14. **Execute Integrity Validator:**
    - [ ] Access `/test_queue_audit_integrity.php` in browser
    - [ ] Report shows "All Queue Entries Have Audit Logs" ✅
    - [ ] No missing logs reported
    - [ ] All entries have valid station_id

15. **Check Database Indexes:**
    - [ ] Access `/analyze_database_indexes.php` in browser
    - [ ] All critical indexes show as ✅ properly indexed
    - [ ] No red warnings about missing indexes

16. **Review Performance Report:**
    - [ ] Access `/performance_optimization_report.php` in browser
    - [ ] Review optimization recommendations
    - [ ] Execute suggested SQL commands if needed

---

## 🔧 Error Handling Testing

### Edge Case Scenarios
17. **Test Error Conditions:**
    - [ ] Try to call next patient when queue is empty
    - [ ] Appropriate "No patients waiting" message displays
    - [ ] Try to complete patient who is not in_progress
    - [ ] Error handling prevents invalid status transitions

18. **Test Station Assignment Conflicts:**
    - [ ] Try to assign same employee to multiple stations (same time)
    - [ ] System prevents overlapping assignments
    - [ ] Error message explains the conflict

---

## 🚀 Performance Validation

### Response Time Testing
19. **Measure Page Load Times:**
    - [ ] Station dashboard loads in < 2 seconds
    - [ ] Queue operations complete in < 1 second
    - [ ] Page refresh updates data correctly
    - [ ] No browser console errors

20. **Database Query Performance:**
    ```sql
    -- Test query performance in phpMyAdmin
    EXPLAIN SELECT * FROM queue_entries WHERE station_id = 1 AND DATE(created_at) = CURDATE();
    ```
    - [ ] Query uses index (key column shows index name)
    - [ ] Rows examined should be minimal
    - [ ] No "Using filesort" or "Using temporary" in Extra column

---

## 📋 Final Verification Checklist

### System Integration 
21. **Complete Workflow Test:**
    - [ ] Employee logs in → sees assigned station
    - [ ] Patient books appointment → queue entry created automatically
    - [ ] Patient arrives → check-in process works
    - [ ] Employee processes patient through all statuses
    - [ ] All status changes logged properly
    - [ ] Statistics update in real-time

### Data Consistency
22. **Cross-Reference Data:**
    ```sql
    -- Verify no orphaned records
    SELECT COUNT(*) FROM queue_entries WHERE station_id NOT IN (SELECT station_id FROM stations);
    SELECT COUNT(*) FROM queue_logs WHERE queue_entry_id NOT IN (SELECT queue_entry_id FROM queue_entries);
    ```
    - [ ] No orphaned queue entries (count should be 0)
    - [ ] No orphaned log entries (count should be 0)

### Migration Verification
23. **Check Migration Success:**
    ```sql
    -- Verify all entries have station_id populated
    SELECT COUNT(*) FROM queue_entries WHERE station_id IS NULL;
    ```
    - [ ] Count should be 0 (all entries have station_id)
    - [ ] If > 0, run migration script: `/migrate_queue_station_id.php`

---

## ✅ Sign-Off Criteria

**PASS CRITERIA - All items must be checked:**
- [ ] All queue operations create proper audit logs
- [ ] No database errors during testing
- [ ] All status transitions work correctly
- [ ] Station assignments function properly
- [ ] Performance is acceptable (< 2 second page loads)
- [ ] No orphaned or missing data
- [ ] Error handling prevents invalid operations
- [ ] Real-time updates work correctly

**TESTING COMPLETED BY:** _________________ **DATE:** _________________

**NOTES/ISSUES FOUND:**
```
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

**RECOMMENDATION:** 
- [ ] ✅ APPROVED - System ready for production use
- [ ] ❌ REQUIRES FIXES - Issues must be resolved before deployment

---

## 🚨 Troubleshooting Common Issues

### If Tests Fail:

**Missing Audit Logs:**
1. Check if `logQueueAction()` method is being called
2. Verify database connection in queue operations
3. Run: `SELECT * FROM queue_logs ORDER BY created_at DESC LIMIT 10;`

**Station Assignment Errors:**
1. Verify `assignment_schedules` table exists
2. Check for old `employee_station_assignments` references
3. Ensure date ranges don't overlap

**Performance Issues:**
1. Execute index creation commands from performance report
2. Check database server resources
3. Verify query optimization suggestions implemented

**Missing station_id Values:**
1. Run migration script: `/migrate_queue_station_id.php`
2. Verify stations table has active records
3. Check service-to-station mappings

---
*CHO Koronadal WBHSMS - Queue System Final Audit Checklist v1.0*