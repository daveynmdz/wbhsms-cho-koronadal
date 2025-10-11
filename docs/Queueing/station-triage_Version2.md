# Triage Station Specification (CHO Main District)

This specification defines the workflow, UI, backend/API integration, and database mapping for the Triage station in the Web-Based Healthcare Services Management System (CHO Koronadal). It references your authoritative database schema (`wbhsms_database.sql`) and provides technical mappings for safe, targeted implementation.

---

## Patient UI (What the Patient Sees/Does)

### Notification of Turn
- **Public Display:** `public_display_triage.php` shows next called patient and assigned station.
- **Personal Display:** Patient queueing page (`pages/patient/queueing.php`) shows their `queue_code`, waiting station (TRIAGE), current status (`waiting`, `called`, `in_progress`, `skipped`, `done`), and a progress bar:
  - `CHECK-IN → TRIAGE → CONSULTATION/TREATMENT → LAB TEST / DISPENSING → END QUEUE`

### During / After Triage
- No dedicated triage UI for patients; all status/progress/next steps are surfaced in the queueing file.
- After triage, next-step instructions (e.g., “Proceed to Consultation when called”) are shown once forwarded by nurse.

### Interactions
- Patients may request staff assistance or corrections via the “Request staff assistance” modal available on the queueing page.
- Alerts/snackbars are shown for delays, skipped status, cancellations—queueing file reflects these statuses in real time.

---

## Employee UI (Triage Nurse/Staff)

### Main Screen
- **File:** `triage_station.php` (or station.php instance for triage) — global layout (sidebar, homepage section, breadcrumb, header).
- **Grid Layout:** 6 columns × 7 rows using `.parent` and `.div1`...`.div7`. Responsive for smaller screens.

### Div Content (Functional)
- **div1:** Station info — name (e.g., Triage 1), status badge (OPEN/CLOSED), assigned staff, service type, shift hours, date.
- **div2:** Stats grid — live cards for counts (Waiting, In Progress, Completed Today, Skipped).
- **div3:** Current patient — profile picture, Patient ID, Name, DOB, Barangay, `queue_code`, `priority_level`, `service_id`, referral summary.
- **div4:** Action controls — 
  - Enter Patient Vitals (opens consultation.php or modal; links to `vitals` table and `visits`)
  - View Patient Profile
  - View Referral (modal)
  - Push to Consultation (forwards `queue_code`)
  - Call Next Patient
  - Skip Patient (moves to skipped queue)
- **div5:** Live queued patients — scrollable table (queue_code, priority, ETA, Force Call Queue action).
- **div6:** Skipped queue — scrollable table, Recall Queue action.
- **div7:** Completed by this station — scrollable table (queue_code, next station).

### Vitals Data-Entry
- Numeric fields: systolic_bp, diastolic_bp, heart_rate, respiratory_rate, temperature, weight, height.
- Remarks field.
- Save: inserts/updates `vitals` table, links to `visits`.

### Controls & Logging
- Actions (Call/Skip/Recall/Force Call/Push Forward) update DB and log to `queue_logs`.
- Staff view action history (queue_logs, appointment_logs, patient_flags) for each patient.
- Priority handling: force-call/reassign, log reason in `queue_logs` and optionally `patient_flags`.

---

## Backend / Integration

### Confirmed DB Tables/Columns
- `queue_entries`, `queue_logs`, `vitals`, `visits`, `appointments`, `referrals`, `patient_flags`, `appointment_logs`.

### Suggested REST Endpoints

- **GET /api/triage/queue?station_id=triage1**
  - Returns: waiting list, skipped list, in_progress, counts.
  - Reads: `queue_entries`, `queue_logs`.

- **POST /api/triage/call**
  - Input: `{ queue_entry_id, station_id, performed_by }`
  - Action: set `queue_entries.status='in_progress'`, set `time_called`, log `queue_logs`.

- **POST /api/triage/skip**
  - Input: `{ queue_entry_id, reason, performed_by }`
  - Action: set `queue_entries.status='skipped'`, log `queue_logs`.

- **POST /api/triage/recall**
  - Input: `{ queue_entry_id, performed_by }`
  - Action: set `queue_entries.status='waiting'`, update order/timestamps, log `queue_logs`.

- **POST /api/triage/vitals**
  - Input: `{ visit_id, patient_id, vitals: { systolic_bp,...,remarks }, performed_by }`
  - Action: Insert/Update `vitals`, update `visits` with `vitals_id`, log `queue_logs` and `appointment_logs`/`visit_logs`.

- **POST /api/triage/forward**
  - Input: `{ queue_entry_id, next_station_id, performed_by }`
  - Action: create new `queue_entries` for next station, mark current `status='completed'`, log `queue_logs`.

- **POST /api/triage/force_call**
  - Input: `{ queue_entry_id, performed_by, reason }`
  - Action: reorder waiting queue, log `queue_logs` with override.

### DB Table/Column Mapping

- **Read:** `queue_entries`, `referrals`, `appointments`, `patients`, `visits`
- **Write/Update:**
  - `queue_entries.status, time_called, time_started, time_completed, assigned_station_id`
  - `vitals`: insert/update all fields, return `vitals_id`
  - `visits`: link `vitals_id`, update time fields, `visit_status`
  - `queue_logs`: every change (action, performed_by, reason, timestamps)
  - Optionally `patient_flags` for exceptions
  - `appointment_logs`/`visit_logs` for lifecycle changes

### Audit & Traceability
- Every triage action creates a `queue_logs` record (action, performed_by, timestamps, reasons).
- Logs linked to `employee_sessions/employees`.
- Vitals writes also create short entry in `visit_logs`/`appointment_logs`.

### Session/Role Validations
- Validate employee session is active and role permission (nurse/triage).
- Ensure station_id matches employee assignment/shift.
- CSRF tokens on forms; performed_by from session.

### Real-Time Updates
- WebSocket/SSE endpoint publishes `queue_entries` and `queue_logs` events.
- UI clients subscribe for updates (public display, triage UI, patient queueing UI).

### Error Handling & Fallback
- DB transactions for atomic actions (forward updates, vitals).
- If DB write fails: return HTTP 5xx, UI shows snackbar and actionable steps.
- If WebSocket fails: fallback to polling.
- Vitals save: rollback on failure; do not change queue status unless vitals are saved.

---

## Business & Implementation Notes

- **Queue Ordering/Priority:** Respect 1 normal : 2 priority ratio when selecting next patient.
- **Force Call:** Treat as override, log who/why in `queue_logs`, optionally create `patient_flags`.
- **Concurrency:** Use SELECT ... FOR UPDATE or counter approach to avoid race conditions.
- **Completed List:** div7 queries `queue_entries` completed by this station.
- **Data Source:** Confirm field names in `wbhsms_database.sql` and adapt if needed.

---

## Developer Implementation Checklist

- Confirm table/column names in `wbhsms_database.sql`.
- Implement endpoints and unit test (happy/failure paths).
- Add WebSocket channel `triage:station:<station_id>` for state publishing.
- Build triage UI grid and wire actions to API endpoints.
- Ensure all actions log to `queue_logs` with performed_by.
- Add UI snackbars for success/error and real-time updates.
- Enforce role/session checks server-side for station actions.
