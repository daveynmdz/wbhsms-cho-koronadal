# Station Interface Specifications - CHO Koronadal

This document defines the standardized UI/UX patterns, grid layouts, and functional requirements for all queue management station interfaces in the CHO Koronadal WBHSMS.

---

## 🎨 Universal UI/UX Standards

### **MANDATORY Design Requirements**
- **Sidebar Navigation**: Always include role-appropriate sidebar (`sidebar_admin.php`, `sidebar_doctor.php`, etc.)
- **Layout Container**: Main content wrapped in `<section class="homepage">` 
- **Breadcrumb Navigation**: Top navigation path (e.g., Home / Queue Management / [Station])
- **Page Header Structure**:
  - Page Title: "[Station Name] Station"
  - Back Button: Returns to dashboard or previous page
  - "Queue Logs" Button: Opens audit logs for station review
- **CSS Framework**: **Custom CSS ONLY** - `sidebar.css`, `dashboard.css`, `edit.css`
- **NO Bootstrap**: External frameworks are strictly forbidden
- **Responsive Design**: Mobile/tablet adaptive with collapsible sidebars

### **Color Scheme & Styling**
- **Consistent Elements**: All tables, buttons, cards, status badges use unified styling
- **Hover Effects**: Interactive elements must have hover states
- **Status Indicators**: Color-coded status badges (OPEN/CLOSED, priority levels)
- **Spacing Rules**: Consistent padding and margins as defined in custom CSS

---

## 📐 Standard Grid Layout (div1–div7)

All station interfaces use a standardized 7-division grid system for consistency:

### **Grid Structure Overview**

| DIV | Purpose | Content Type | Update Frequency |
|-----|---------|--------------|------------------|
| **div1** | Station Information | Static/Semi-static | On status change |
| **div2** | Real-time Statistics | Dynamic counters | Auto-refresh (30s) |
| **div3** | Current Patient Details | Patient context | On patient call/change |
| **div4** | Employee Action Controls | Interactive buttons | Context-sensitive |
| **div5** | Active Queue Display | Scrollable patient list | Real-time updates |
| **div6** | Skipped Patient Queue | Recall functionality | On skip/recall actions |
| **div7** | Completed Patients Log | Historical data | On completion |

---

## 🏥 Station-Specific Implementations

### **Check-In Counter Station**

#### **Employee Interface Features**
- **QR Scanner Integration**: Live camera feed for appointment QR codes
- **Dual Search Methods**: 
  - Left Panel: QR Code scanning interface
  - Right Panel: Manual search (appointment_id, patient_id, name, barangay, date)
- **Results Table**: Appointment details with priority indicators and action buttons
- **Verification Modal**: Full appointment details with PhilHealth status check
- **Priority Classification**: PWD, Senior Citizen, Pregnant patient flagging

#### **Patient Self-Service Interface** (`checkin_public.php`)
- **Appointment Summary Card**: QR code display, appointment details, patient info
- **Progress Indicator**: Visual workflow showing current step in queue process
- **Real-time Updates**: Queue position, estimated wait time, status changes
- **Help Request**: Staff assistance modal for corrections or issues

---

### **Triage Station Interface**

#### **div1-div7 Content Specification**

| DIV | Content Details |
|-----|-----------------|
| **div1** | Station name (Triage 1-3), OPEN/CLOSED badge, assigned nurse, shift hours, current date |
| **div2** | Live counters: Waiting, In Progress, Completed Today, Skipped patients |
| **div3** | Current patient: Photo, ID, Name, DOB, Barangay, queue_code, priority, referral summary |
| **div4** | Actions: Enter Vitals, View Profile, View Referral, Push to Consultation, Call Next, Skip |
| **div5** | Waiting patients table: queue_code, priority level, ETA, Force Call action |
| **div6** | Skipped patients: queue_code, skip reason, Recall action button |
| **div7** | Completed patients: queue_code, forwarded station, completion timestamp |

#### **Specialized Functions**
- **Vitals Data Entry**: Integrated modal for BP, heart rate, temperature, weight, height
- **Patient Assessment**: Risk evaluation and consultation routing
- **Emergency Protocols**: Priority escalation for urgent cases
- **Database Integration**: Links to `vitals` table and `visits` records

---

### **Consultation Station Interface**

#### **Employee Actions (div4)**

| Action Button | Functionality | Routing Logic |
|---------------|---------------|---------------|
| Enter Consultation Notes | Opens `/pages/clinical-encounter-management/consultation.php` | Clinical documentation |
| Reroute to Lab Queue | Pushes patient to Laboratory station [13] | Diagnostic testing required |
| Reroute to Pharmacy Queue | Pushes patient to Pharmacy stations [14-15] | Medication dispensing |
| Reroute to Billing Queue | Pushes patient to Billing station [4] | Payment processing |
| Reroute to Document Queue | Pushes patient to Document station [12] | Certificate requests |
| Call Next Patient | Retrieves next queued patient | Standard queue progression |
| Skip Patient Queue | Moves to skipped queue (div6) | Patient unavailable/delayed |

#### **Patient Context Display (div3)**
- **Medical History Integration**: Previous visits and conditions
- **Referral Information**: Referring provider and reason details
- **Treatment Plan Display**: Current service requirements and next steps
- **Priority Indicators**: Visual flags for emergency, PWD, senior, pregnant status

---

### **Laboratory Station Interface**

#### **Specialized Workflow Controls**
- **Sample Collection Mode**: Patient check-in for specimen collection
- **Result Entry Interface**: Test result input and validation forms  
- **Quality Control**: Result verification and approval workflow
- **Routing Decisions**:
  - Return to Doctor: With completed results for follow-up consultation
  - Complete Visit: If no further consultation required
  - Route to Pharmacy: If medication prescribed based on test results

#### **Laboratory-Specific Features**
- **Test Requisition Display**: Ordered tests and collection requirements
- **Sample Tracking**: Specimen collection status and processing timeline
- **Result Integration**: Direct entry to patient records and clinical system
- **Time Management**: Collection phase vs. waiting for results workflow

---

### **Pharmacy Station Interface**

#### **Prescription Management System**
- **Prescription Verification**: Validation of doctor orders and patient eligibility
- **Inventory Integration**: Stock checking and dispensing calculations
- **Patient Counseling**: Medication education and instruction delivery
- **Dispensing Controls**:
  - Verify prescription authenticity and dosage
  - Check drug interactions and allergies
  - Calculate dispensing quantities and refills
  - Generate dispensing labels and instructions

#### **Quality & Safety Features**
- **Drug Interaction Checking**: Automated safety validation
- **Allergy Alerts**: Patient-specific contraindication warnings
- **Dosage Validation**: Age and weight-appropriate dosing verification
- **Inventory Tracking**: Real-time stock levels and reorder notifications

---

### **Billing Station Interface**

#### **Financial Processing Controls**
- **Invoice Creation**: Comprehensive billing for services and medications
- **Payment Processing**: Multiple payment methods and receipt generation
- **PhilHealth Integration**: Benefit verification and claim processing
- **Routing After Payment**:
  - Return to Consultation: Continue treatment after payment
  - Route to Laboratory: Proceed to paid diagnostic tests
  - Route to Document: Process paid certificate requests
  - Complete Visit: If no further services required

#### **Payment Verification System**
- **Service Fee Calculation**: Automated pricing based on service types
- **Discount Application**: Senior citizen, PWD, and other applicable discounts
- **Receipt Generation**: Official CHO receipts with proper documentation
- **Audit Trail**: Complete financial transaction logging

---

### **Document Station Interface**

#### **Certificate Processing Workflow**
- **Document Type Selection**: Medical certificates, clearances, fitness evaluations
- **Authorization Verification**: Doctor approval and signature requirements
- **Template Management**: Standardized certificate formats and content
- **Digital Signatures**: Electronic approval and authentication system

#### **Quality Control Features**
- **Approval Workflow**: Multi-level authorization for different certificate types
- **Template Validation**: Ensure all required fields and information completed
- **Digital Archive**: Electronic storage and retrieval of issued documents
- **Patient Notification**: Completion alerts and document pickup instructions

---

## 🔧 Technical Implementation Guidelines

### **Database Integration Patterns**
```php
// Standard queue entry retrieval
$current_patient = $queue_service->getCurrentPatient($station_id, $employee_id);

// Station status management
$station_status = $queue_service->getStationStatus($station_id);

// Patient routing between stations
$queue_service->routePatient($queue_code, $from_station, $to_station, $notes);
```

### **AJAX Update Patterns**
- **Auto-refresh Intervals**: div2 (30 seconds), div5 (15 seconds), div7 (60 seconds)
- **Real-time Actions**: Patient calling, status updates, queue modifications
- **Error Handling**: User-friendly error messages with retry options
- **Loading States**: Visual indicators during processing operations

### **Responsive Behavior**
- **Mobile Layout**: Sidebar collapses to hamburger menu
- **Tablet Optimization**: Grid layout adapts to smaller screens
- **Touch Interface**: Large buttons and touch-friendly controls
- **Accessibility**: Screen reader compatibility and keyboard navigation

### **Security & Access Control**
```php
// Role-based station access
if (!hasStationAccess($_SESSION['employee_role'], $station_type)) {
    redirectToUnauthorized();
}

// Station assignment validation
if (!isAssignedToStation($_SESSION['employee_id'], $station_id)) {
    showReadOnlyMode(); // View-only access
}
```

---

## 📋 Development Checklist

### **For Each Station Interface:**

#### **Required Elements:**
- [ ] Proper sidebar integration with role-based menu
- [ ] Breadcrumb navigation with correct path
- [ ] Page header with title, back button, and queue logs link
- [ ] Complete div1-div7 grid implementation
- [ ] Role-based action button visibility
- [ ] Real-time data refresh mechanisms
- [ ] Mobile-responsive layout adaptation
- [ ] Custom CSS styling (no Bootstrap)

#### **Functional Requirements:**
- [ ] Current patient display with full context
- [ ] Queue management actions (call, skip, recall)
- [ ] Station-appropriate routing buttons
- [ ] Real-time statistics and counters
- [ ] Error handling and user feedback
- [ ] Database integration with proper logging
- [ ] Session management and security validation

#### **Testing Criteria:**
- [ ] All buttons function correctly
- [ ] Real-time updates work as expected
- [ ] Mobile/tablet layout displays properly
- [ ] Database operations complete successfully
- [ ] Security restrictions properly enforced
- [ ] Audit logging captures all actions

This standardized specification ensures consistency across all station interfaces while providing the flexibility needed for station-specific workflows and requirements.