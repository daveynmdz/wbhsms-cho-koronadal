# Check-In Station Specification (CHO Main District)

This file defines the workflow, UI, backend/API integration, and database mapping for the Check-In station in the Web-Based Healthcare Services Management System (CHO Koronadal). All business rules and minimal DB changes are included for safe, targeted implementation.

---

## Patient UI (What the Patient Sees/Does)

### How to Start
- Patient goes to the Check-in Station (reception/kiosk) and informs staff about their appointment.
- Patient UI: `sidebar_patient.php → CHO Queueing` opens `pages/patient/queueing.php`.

### Main Page Layout
- **Top**: Breadcrumb, header, and action buttons:
  - Back to `/pages/patient/dashboard.php`
  - `Book Appointment` (`/pages/patient/appointment/book_appointment.php`) — hidden if already booked.
- **Card A**: Appointment Summary (when present)
  - Appointment details: `Appointment ID`, `scheduled_date`, `scheduled_time`, `service_id`
  - Patient details: `Patient ID`, `Name`, `Barangay`
  - Priority status: derived from patient flags (`isPWD`, `isSenior`, pregnancy)
  - Referral details: `Referral ID`, `referral_reason`, `referred_by`
  - QR code: rendered from `appointments.qr_code_path` (longblob)
- **Card B**: If no appointment: message, button, and referral reminder.

### After Check-In / Feedback
- On acceptance, patient receives:
  - `queue_code` (format: `HHX-L-####`), assigned station (e.g. Triage), queue status
  - Progress bar across stations, estimated wait time
  - Snackbar notifications on events (e.g. accepted, cancelled)
- If cancelled (e.g. mismatch): clear cancellation reason and next steps
- Correction/help: “Request staff assistance” modal, correction request link (handled by staff)
- Notifications: Real-time updates (websocket/polling), error snackbars

---

## Employee UI (Check-In Staff Flow)

- **Main File**: `checkin.php` for role `checkin`
- **Instructions Card**: Validation steps for appointments/check-in
- **Two-Panel Input Card**
  - Left: QR Scanner (scan `appointments.qr_code_path`)
  - Right: Search/Filters (`appointment_id`, `patient_id`, `last_name`, `first_name`, `barangay`, `scheduled_date`)
- **Results Table**: `Appointment ID`, `scheduled_date`, `patient_id`, `last_name`, `first_name`, `barangay`, `priority_status`, Actions
- **View Appointment Modal**
  - Full details: appointment, referral, patient summary, QR preview
  - Actions: Accept Booking/Check-in, Flag Patient
  - Confirmation Modal: verify accuracy, mark priority (pregnant, PWD, senior)
  - Outcomes: 
    - Accepted as Priority/Normal → queue entry for triage
    - Flagging/cancellation → open flag modal, record reason, flag in `patient_flags`
- **Queue Management/Station View**
  - View queue order/status via `station.php`
  - Queue ratio policy (1 normal : 2 priority) enforced by queueing algorithm
- **Quick Actions**: Print slip, Call next, Skip, Reinstate, Cancel
- **Logging**: All actions logged (`queue_logs`, `appointment_logs`, `visits`, `patient_flags`)

---

## Backend & Integration Mapping (DB & API)

### API Endpoints
1. `POST /api/checkin/scan`  
   - Accept scanned QR/appointment ID, return appointment/patient/referral summary

2. `POST /api/checkin/accept`  
   - Accept appointment, check-in patient, create/update queue/visit/logs

3. `POST /api/checkin/flag`  
   - Create `patient_flags` for flagged/cancelled cases

4. `POST /api/queue/move`  
   - Push patient to next station, update status

5. `GET /api/queue/status`  
   - Real-time aggregates by station/type

6. `GET /api/patient/{id}`  
   - Retrieve patient, flags, last visits

### DB Tables/Columns
- **Read**:  
  - `appointments(appointment_id, patient_id, scheduled_date, scheduled_time, status, qr_code_path, referral_id, service_id)`  
- **Write/Update**:  
  - `appointments.status` → `'checked_in'`  
  - `visits` → create/update (`visit_id, patient_id, appointment_id, visit_date, time_in, visit_status`)  
  - `queue_entries` → create (`visit_id, appointment_id, patient_id, service_id, queue_type, queue_code, priority_level, status`)  
  - `queue_logs` → log queue changes  
  - `patient_flags` → create as needed  
  - `appointment_logs` → log appointment state changes

### Audit & Security
- Every action writes to `queue_logs`/`appointment_logs` with `performed_by`/timestamp
- Employee session and `role_permissions` validated before action

### Real-Time Updates
- Websocket/SSE endpoint publishes `queue_entries` changes for live UI updates

### Error Handling & Fallback
- DB transactions for atomic writes
- Fallbacks for QR scan/search failures
- Temp visit record + flag for unregistered/cancelled cases

---

## Minimal DB Changes (Safe ALTERs/CREATEs)

### Add Indexes (Recommended)

```sql
ALTER TABLE `queue_entries`
  ADD KEY `idx_queue_code` (`queue_code`),
  ADD KEY `idx_queue_number` (`queue_number`),
  ADD KEY `idx_priority_status_created` (`priority_level`,`status`,`created_at`);
```

### Add Helper Table (Optional)

```sql
CREATE TABLE IF NOT EXISTS `queue_counters` (
  `counter_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `counter_date` date NOT NULL,
  `counter_hour` varchar(8) NOT NULL,
  `patient_type` enum('normal','priority','emergency') NOT NULL,
  `current_value` int(10) UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY `uniq_hour_type` (`counter_date`,`counter_hour`,`patient_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Implementation Notes

- QR blob storage (`appointments.qr_code_path`) is DB-based and indexed
- Appointment status includes `'checked_in'` and appointment logs for lifecycle
- FKs and cascade rules are present for audit/cleanup
- Use transactions and/or `queue_counters` for unique queue code generation
- Enforce `role_permissions` server-side for API actions

---

## Summary

- UI/UX, backend, and DB requirements for Check-In station are fully mapped
- Minimal, safe DB changes included
- Ready for developer implementation and audit
