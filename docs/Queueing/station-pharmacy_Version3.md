# Pharmacy Station Specification

---

## UI/UX Styling & Layout Instructions

**MANDATORY:**
- **Sidebar Navigation:** Always include the correct sidebar for pharmacy staff.
- **Layout:** Main content must be wrapped in `<section class="homepage">` using your custom page layout.
- **Breadcrumb Navigation:** Top of the page with navigation path (e.g., Home / Queue Management / Pharmacy).
- **Page Header:**  
  - Page Title: "Pharmacy Station"
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

| DIV      | Purpose/Content                                                                                                                                               |
|----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **div1** | **Station Info:** Station name (e.g., "Dispensing 1"), status badge (OPEN/CLOSED), assigned staff, service type, shift hours, current date.                  |
| **div2** | **Stats Grid:** Cards showing real-time counts (Waiting, In Progress, Completed Today, Skipped). Must auto-update.                                           |
| **div3** | **Current Patient Details:** Profile photo, Patient ID, Name, DOB, Barangay, `queue_code`, priority_level, service_id, prescription summary (if any).        |
| **div4** | **Actions for Current Patient:** See Employee Actions below (buttons only, no modals/forms).                                                                 |
| **div5** | **Live Queued Patients:** Scrollable table of waiting patients—`queue_code`, priority, ETA, action "Force Call Queue" (override next call).                  |
| **div6** | **Skipped Queue:** Table of skipped patients with "Recall Queue" action (returns patient to active queue).                                                   |
| **div7** | **Completed Patients:** List of patients completed by this station—`queue_code`, next station assigned, timestamp, dispensed summary.                        |

---

## Employee Actions (div4 – Actions for Current Patient)

| Action Button                  | Description & Logic                                                                                                     |
|------------------------------- |------------------------------------------------------------------------------------------------------------------------|
| End Patient Queue              | Marks visit/appointment as completed, updates tables and logs.                                                          |
| Call Next Patient to Serve     | Calls next patient in queue, fills div3.                                                                               |
| Skip Patient Queue             | Moves patient to skipped queue (div6), empties div3.                                                                   |
| Recall Patient Queue           | Calls skipped patient back into queue; fills div3.                                                                     |

**No modals or forms required—actions are button-driven.**

---

## Backend/API Integration

### API Endpoints & Payloads

- `POST /api/pharmacy/call_next`
  - Payload: `{ station_id, performed_by }`
  - Advance queue; updates `queue_entries`, logs action.

- `POST /api/pharmacy/skip`
  - Payload: `{ queue_entry_id, performed_by, reason }`
  - Set status to `skipped`; logs to `queue_logs`.

- `POST /api/pharmacy/recall`
  - Payload: `{ queue_entry_id, performed_by }`
  - Return patient to queue; logs to `queue_logs`.

- `POST /api/pharmacy/end_queue`
  - Payload: `{ queue_entry_id, performed_by }`
  - Completes visit, updates status.

### Database Tables/Columns

- `queue_entries`: Update `status`, `assigned_station_id`, `time_completed`, `visit_id`, `appointment_id`
- `queue_logs`: Log every action
- `visits`, `appointment_logs`: Mark visit as completed

### Audit/Logging Requirements

- Log every button action with employee, timestamp, previous/new status, reason if applicable.
- Queue Logs button must open audit log for current queue entry.

---

## Patient Flow Context

- **PhilHealth:** `[Check-In → Triage → Consultation → Pharmacy → End]`
- **Non-PhilHealth:** `[Check-In → Triage → Consultation → Billing → Consultation → Pharmacy → End]`

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
    Home / Queue Management / Pharmacy
  </nav>
  <!-- Page Header -->
  <header class="page-header">
    <h1>Pharmacy Station</h1>
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