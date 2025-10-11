# Laboratory Station Specification

---

## UI/UX Styling & Layout Instructions

**MANDATORY:**
- **Sidebar Navigation:** Always include the correct sidebar for laboratory staff.
- **Layout:** Main content must be wrapped in `<section class="homepage">` using your custom page layout.
- **Breadcrumb Navigation:** Top of the page with navigation path (e.g., Home / Queue Management / Laboratory).
- **Page Header:**  
  - Page Title: "Laboratory Station"
  - Back Button: Returns to dashboard or previous page
  - "Queue Logs" Button: Opens `queue_logs` for audit review
  - Both buttons must be styled per custom CSS.
- **Styling:**  
  Use only custom CSS: `sidebar.css`, `dashboard.css`, `edit.css`. NO Bootstrap or external frameworks.
- **Consistency:**  
  All tables, buttons, cards, and status badges must use a consistent color scheme, spacing, hover effects, and responsive rules as defined in your CSS assets.
- **Responsive:**  
  Layout must adapt to tablet/mobile. Sidebar collapses, topbar/breadcrumb stacks.

---

## UI Grid Layout (div1–div7)

| DIV      | Purpose/Content                                                                                                                                                                                                                         |
|----------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **div1** | **Station Info:** Station name (e.g., "Laboratory 1"), status badge (OPEN/CLOSED), assigned staff, service type, shift hours, current date.                                                     |
| **div2** | **Stats Grid:** Cards showing real-time counts (Waiting, In Progress, Completed Today, Skipped). Must auto-update.                                                                             |
| **div3** | **Current Patient Details:** Profile photo, Patient ID, Name, DOB, Barangay, `queue_code`, priority_level, service_id, referral summary (if any), lab order summary.                            |
| **div4** | **Actions for Current Patient:** See Employee Actions below (buttons only, no modals/forms).                                                                                                   |
| **div5** | **Live Queued Patients:** Scrollable table of waiting patients—`queue_code`, priority, ETA, action "Force Call Queue" (override next call).                                                    |
| **div6** | **Skipped Queue:** Table of skipped patients with "Recall Queue" action (returns patient to active queue).                                                                                     |
| **div7** | **Completed Patients:** List of patients completed by this station—`queue_code`, next station assigned, timestamp, result status.                                                              |

---

## Employee Actions (div4 – Actions for Current Patient)

| Action Button                  | Description & Logic                                                                                                     |
|------------------------------- |----------------------------------------------------------------------------------------------------------------------- |
| Process Lab Order              | Redirects to `/pages/lab-test/process_lab_test.php` for specimen collection/result upload.                              |
| Reroute to Consultation Queue  | Pushes patient to original consultation station. If not found (`service_id='8'`), show error message.                   |
| Reroute to Pharmacy Queue      | Pushes patient to Pharmacy station, empties div3.                                                                      |
| End Patient Queue              | Marks visit/appointment as completed, updates all relevant tables and logs.                                            |
| Call Next Patient to Serve     | Calls next patient in queue, fills div3.                                                                               |
| Skip Patient Queue             | Moves patient to skipped queue (div6), empties div3.                                                                   |
| Recall Patient Queue           | Calls skipped patient back into queue; fills div3.                                                                     |

**No modals or forms required—actions are button-driven.**

---

## Backend/API Integration

### API Endpoints & Payloads

- `POST /api/lab/call_next`
  - Payload: `{ station_id, performed_by }`
  - Action: Advance queue; updates `queue_entries`, logs action.

- `POST /api/lab/skip`
  - Payload: `{ queue_entry_id, performed_by, reason }`
  - Action: Set status to `skipped`; logs to `queue_logs`.

- `POST /api/lab/recall`
  - Payload: `{ queue_entry_id, performed_by }`
  - Action: Return patient to queue; logs to `queue_logs`.

- `POST /api/lab/reroute`
  - Payload: `{ queue_entry_id, target_station_id, performed_by }`
  - Action: Create new queue entry for target station, mark current as completed; logs to `queue_logs`.

- `POST /api/lab/process_order`
  - Frontend only: Redirect to process lab order page.

- `POST /api/lab/end_queue`
  - Payload: `{ queue_entry_id, performed_by }`
  - Marks visit/appointment as completed.

### Database Tables/Columns

- `queue_entries`: Update `status`, `assigned_station_id`, `time_started`, `time_completed`, `visit_id`, `appointment_id`
- `queue_logs`: Log every action (`action`, `performed_by`, `previous_status`, `new_status`, `reason`, `timestamp`)
- `visits`, `appointment_logs`: Mark visit as completed

### Audit/Logging Requirements

- All actions must log to `queue_logs` with employee, timestamp, status before/after, reason.
- "Queue Logs" button must open audit modal/page for the current queue entry.

---

## Patient Flow Context

- **PhilHealth Members:** `[Check-In → Triage → Lab → End]`
- **Non-PhilHealth:** `[Check-In → Triage → Billing → Lab → End]`
- Reroutes to consultation are only allowable if patient originally came from consultation station.

---

## Additional Development Notes

- **Styling:** Only custom CSS: `sidebar.css`, `dashboard.css`, `edit.css`. No Bootstrap.
- **Layout:** Main content in `<section class="homepage">`, sidebar included, responsive for mobile.
- Always show breadcrumb, page header, back button, and queue logs button.

---

## Example Markup Structure

```html
<section class="homepage">
  <!-- Breadcrumb Navigation -->
  <nav class="breadcrumb">
    Home / Queue Management / Laboratory
  </nav>
  <!-- Page Header -->
  <header class="page-header">
    <h1>Laboratory Station</h1>
    <button class="btn-back">Back</button>
    <button class="btn-queue-logs">Queue Logs</button>
  </header>
  <!-- Grid Layout: div1-div7 -->
  <div class="station-grid">
    <div class="div1"> <!-- Station Info --></div>
    <div class="div2"> <!-- Stats Grid --></div>
    <div class="div3"> <!-- Current Patient Details --></div>
    <div class="div4"> <!-- Action Buttons --></div>
    <div class="div5"> <!-- Live Queued Patients --></div>
    <div class="div6"> <!-- Skipped Queue --></div>
    <div class="div7"> <!-- Completed Patients --></div>
  </div>
</section>
```