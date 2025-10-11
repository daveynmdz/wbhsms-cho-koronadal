# Consultation Station Specification

---

## UI/UX Styling & Layout Instructions

**MANDATORY:**
- **Sidebar Navigation:** Always include the correct sidebar for the logged-in role (e.g., doctor, nurse).
- **Layout:** Main content must be wrapped in `<section class="homepage">`, using your custom page layout.
- **Breadcrumb Navigation:** Top of the page with navigation path (e.g., Home / Queue Management / Consultation).
- **Page Header:**  
  - Page Title: "Consultation Station"
  - Back Button: Returns to dashboard or previous page  
  - "Queue Logs" Button: Opens queue_logs for audit review  
  - Both buttons must be styled per custom CSS.
- **Styling:**  
  Use only custom CSS: `sidebar.css`, `dashboard.css`, `edit.css`. NO Bootstrap or external frameworks.
- **Consistency:**  
  All tables, buttons, cards, and status badges must use a consistent color scheme, spacing, hover effects, and responsive rules as defined in your CSS assets.
- **Responsive:**  
  Layout must adapt to tablet/mobile. Sidebar collapses, topbar/breadcrumb stacks.

---

## UI Grid Layout (div1–div7)

| DIV      | Purpose/Content                                                                                                                                              |
|----------|-------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **div1** | **Station Info:** Station name (e.g., "Consultation 1"), status badge (OPEN/CLOSED), assigned staff, service type, shift hours, current date.               |
| **div2** | **Stats Grid:** Cards showing real-time counts (Waiting, In Progress, Completed Today, Skipped). Must auto-update.                                          |
| **div3** | **Current Patient Details:** Profile photo, Patient ID, Name, DOB, Barangay, `queue_code`, priority_level, service_id, referral summary (if any).           |
| **div4** | **Actions for Current Patient:** See Employee Actions below (buttons only, no modals/forms).                                                                |
| **div5** | **Live Queued Patients:** Scrollable table of waiting patients—`queue_code`, priority, ETA, action "Force Call Queue" (override next call).                 |
| **div6** | **Skipped Queue:** Table of skipped patients with "Recall Queue" action (returns patient to active queue).                                                  |
| **div7** | **Completed Patients:** List of patients completed by this station—`queue_code`, next station assigned, timestamp.                                          |

---

## Employee Actions (div4 – Actions for Current Patient)

| Action Button                | Description & Logic                                                                                   |
|------------------------------|------------------------------------------------------------------------------------------------------|
| Enter Consultation Notes     | Redirects to `/pages/clinical-encounter-management/consultation.php` for note entry.                 |
| Reroute to Lab Queue         | Pushes patient (`queue_code`) to Laboratory station, empties div3.                                   |
| Reroute to Pharmacy Queue    | Pushes patient to Pharmacy station, empties div3.                                                    |
| Reroute to Billing Queue     | Pushes patient to Billing station, empties div3.                                                     |
| Reroute to Document Queue    | Pushes patient to Document station, empties div3.                                                    |
| Call Next Patient to Serve   | Calls next patient in queue, fills div3.                                                             |
| Skip Patient Queue           | Moves patient to skipped queue (div6), empties div3.                                                 |
| Recall Patient Queue         | Calls skipped patient back into queue; fills div3.                                                   |

**No modals or forms required—actions are button-driven.**

---

## Backend/API Integration

### API Endpoints & Payloads

- `POST /api/consultation/call_next`
  - Payload: `{ station_id, performed_by }`
  - Action: Advance queue; updates `queue_entries`, logs action.

- `POST /api/consultation/skip`
  - Payload: `{ queue_entry_id, performed_by, reason }`
  - Action: Set status to `skipped`; logs to `queue_logs`.

- `POST /api/consultation/recall`
  - Payload: `{ queue_entry_id, performed_by }`
  - Action: Return patient to queue; logs to `queue_logs`.

- `POST /api/consultation/reroute`
  - Payload: `{ queue_entry_id, target_station_id, performed_by }`
  - Action: Create new queue entry for target station, mark current as completed; logs to `queue_logs`.

- `POST /api/consultation/enter_notes`
  - Frontend only: Redirect to consultation notes entry page.

### Database Tables/Columns

- `queue_entries`: Updates to `status`, `assigned_station_id`, `time_started`, `time_completed`, `visit_id`, `appointment_id`
- `queue_logs`: Log every action (`action`, `performed_by`, `previous_status`, `new_status`, `reason`, `timestamp`)
- `visits`: Update links if rerouted
- `appointment_logs`: Log reroutes, consultations

### Audit/Logging Requirements

- **Every action** must be logged in `queue_logs` with full details (employee, timestamp, status before/after, reason if applicable).
- **Queue Logs button** must open a modal or page showing all relevant logs for selected queue entry.

---

## Patient Flow Context

- **PhilHealth Members:** `[Check-In → Triage → Consultation → Lab or Pharmacy → End]`
- **Non-PhilHealth:** `[Check-In → Triage → Consultation → Billing → Consultation → Lab or Pharmacy → End]`
- Consultation station is the clinical decision point; reroutes determine the next queue destination.

---

## Additional Development Notes

- **Styling:** Use only custom CSS: `sidebar.css`, `dashboard.css`, `edit.css`. Never use Bootstrap or external frameworks.
- **Layout:** Always wrap main content in `<section class="homepage">`, sidebar included, responsive for mobile.
- **Breadcrumb and headers** must be present for navigation clarity.
- **Back button** should clearly return to previous page or dashboard.
- **Queue Logs button** is mandatory for audit review.

---

## Example Markup Structure

```html
<section class="homepage">
  <!-- Breadcrumb Navigation -->
  <nav class="breadcrumb">
    Home / Queue Management / Station View (Consultation)
  </nav>
  <!-- Page Header -->
  <header class="page-header">
    <h1>Consultation Station</h1>
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