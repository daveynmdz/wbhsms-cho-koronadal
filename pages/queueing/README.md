# CHO Koronadal Queue Management System

## Overview
The queue management system provides comprehensive patient flow control for the City Health Office of Koronadal (Main District) WBHSMS. This module handles patient check-in, multi-station queue management, real-time monitoring, and public displays with full CHO theme integration. The system supports PhilHealth-based routing and service-specific workflows across 7 station types.

## Station Directory

| Station ID | Station Name         | Station Type   | Service ID | Description                                    |
|------------|---------------------|---------------|------------|------------------------------------------------|
| 16         | Check-In Counter    | checkin       | 10         | Patient registration and PhilHealth verification |
| 1–3        | Triage 1–3          | triage        | 1          | Triage assessment and vital signs              |
| 5–11       | Consultation/Treatment | consultation | various    | Medical consult, dental, TB, vaccination, etc. |
| 13         | Laboratory          | lab           | 8          | Diagnostic testing and sample collection       |
| 14–15      | Dispensing 1–2      | pharmacy      | 1          | Medicine dispensing and prescription services  |
| 4          | Billing             | billing       | 9          | Payment processing and invoice generation      |
| 12         | Medical Documents   | document      | 9          | Certificates and medical documentation         |

## Core Files

### Station Interfaces (Role-Based Access)
- **`checkin_station.php`** - ✅ **Check-In Counter** - Patient registration, appointment verification, and queue entry
- **`triage_station.php`** - ✅ **Triage Station** - Vital signs collection and patient assessment  
- **`consultation_station.php`** - ✅ **Consultation Station** - Medical consultations and treatment routing
- **`lab_station.php`** - ✅ **Laboratory Station** - Test processing and results management
- **`pharmacy_station.php`** - ✅ **Pharmacy Station** - Prescription dispensing and medication management
- **`billing_station.php`** - ✅ **Billing Station** - Payment processing and invoice generation
- **`document_station.php`** - ✅ **Document Station** - Medical certificates and documentation

### Administrative Tools  
- **`dashboard.php`** - ✅ **Admin Queue Dashboard** - Central control with station toggles and statistics
- **`admin_monitor.php`** - ✅ **Master Monitor View** - Real-time monitoring of all stations
- **`logs.php`** - ✅ **Queue Logs & Reports** - Historical data and analytics with filtering
- **`staff_assignments.php`** - ✅ **Staff Assignment Management** - Assign employees to stations with schedules

### Patient Check-In System
- **`checkin.php`** - ✅ **Staff Check-In Interface** - QR scanning and appointment verification
- **`checkin_actions.php`** - ✅ **Check-In API Handlers** - Backend processing for check-in operations
- **`checkin_public.php`** - ✅ **Patient Self-Service** - Public kiosk interface for patient check-in

### Public Display System (Waiting Area Monitors)
- **`public_display_selector.php`** - ✅ **Display Launcher** - Admin interface to open displays on multiple monitors
- **`public_display_triage.php`** - ✅ **Triage Display** - Real-time queue status for triage waiting area
- **`public_display_consultation.php`** - ✅ **Consultation Display** - Current serving patients for consultation areas
- **`public_display_lab.php`** - ✅ **Laboratory Display** - Queue status for laboratory waiting area
- **`public_display_pharmacy.php`** - ✅ **Pharmacy Display** - Prescription queue for pharmacy waiting area
- **`public_display_billing.php`** - ✅ **Billing Display** - Payment queue for billing counter
- **`public_display_document.php`** - ✅ **Document Display** - Documentation requests queue

### System Utilities
- **`print_ticket.php`** - ✅ **Queue Ticket Printing** - Generate numbered queue tickets with QR codes
- **`get_patient_details.php`** - ✅ **Patient Data API** - Backend service for patient information retrieval
- **`patient_search.php`** - ✅ **Patient Search Interface** - Advanced patient lookup and filtering
- **`setup_stations.php`** - ✅ **Station Setup** - Initialize stations and staff assignments with admin controls
- **`queue_api.php`** - ✅ **REST API Endpoints** - Comprehensive REST API for queue operations and real-time updates

## Patient Flow Workflows

The system supports multiple patient flow patterns based on **PhilHealth membership** and **service type (service_id)**:

### 1. Normal Patient Flow (PhilHealth Members)
**Services**: Primary Care, Dental, TB, Vaccination, Family Planning (service_id: 1,2,3,4,6,7)
```
Check-In [16] → Triage [1-3] → Consultation [5-11] → Lab [13] OR Pharmacy [14-15] → End
```
*Billing station [4] is skipped as PhilHealth covers these services*

### 2. Non-PhilHealth Patient Flow  
**Services**: Primary Care, Dental, TB, Vaccination, Family Planning (service_id: 1,2,3,4,6,7)
```
Check-In [16] → Triage [1-3] → Consultation [5-11] → Billing [4] → Consultation [5-11] → Lab [13] OR Pharmacy [14-15] → End
```
*Payment required before continuing medical process*

### 3. Laboratory Test-Only Flow
**Service**: Laboratory Tests (service_id: 8)
- **PhilHealth**: `Check-In [16] → Triage [1-3] → Laboratory [13] → End`
- **Non-PhilHealth**: `Check-In [16] → Triage [1-3] → Billing [4] → Laboratory [13] → End`

### 4. Medical Document Request Flow
**Service**: Certificates and Documentation (service_id: 9)
```
Check-In [16] → Billing [4] → Medical Documents [12] → End
```

## ✅ **Implemented Features**

### ✅ PhilHealth-Based Queue Routing
- **Smart Service Routing**: Automatic flow determination based on PhilHealth status and service type
- **Payment Integration**: Non-PhilHealth patients routed through billing before treatment continuation
- **Service-Specific Flows**: Dedicated workflows for consultations, lab tests, and document requests
- **Priority Management**: Emergency, PWD, Senior Citizen, and Pregnant priority handling

### ✅ Multi-Station Management
- **7 Station Types**: Check-in, Triage (1-3), Consultation (5-11), Lab, Pharmacy (14-15), Billing, Documents
- **Role-Based Access**: Station-specific interfaces with appropriate employee role restrictions
- **Station Toggle Control**: Staff can open/close stations, admins can override all stations
- **Real-time Synchronization**: Queue status updates across all connected interfaces

### ✅ Advanced Check-In System
- **QR Code Scanning**: Fast appointment verification using generated QR codes
- **Dual Search Methods**: Patient ID search and appointment detail lookup
- **PhilHealth Verification**: Automatic membership status checking and flow routing
- **Priority Classification**: Automatic priority assignment based on patient flags (PWD, Senior, Pregnant)
- **Referral Integration**: Support for referral appointments with context preservation

### ✅ Station-Specific Workflows
- **Grid Layout System**: Standardized div1-div7 layout across all station interfaces
- **Action-Based Controls**: Station-appropriate actions (route to lab, dispense medication, etc.)
- **Patient Context Display**: Comprehensive patient information with referral summaries
- **Queue Management Tools**: Call next, skip patient, recall from skipped queue

### ✅ Public Display & Monitoring
- **Multi-Monitor Support**: Dedicated displays for each service type waiting area
- **Real-time Queue Updates**: Live patient calling and status updates
- **Administrative Oversight**: Master monitor view for system-wide queue status
- **Performance Analytics**: Station efficiency metrics and wait time tracking

## Technical Architecture

### API Integration
- **Backend Service**: `QueueManagementService` class handles all queue operations
- **REST Endpoints**: `/api/queue_management.php` and `/api/queueing/` controllers
- **Real-time Updates**: AJAX polling and WebSocket support for live queue status
- **Database Integration**: Full integration with `wbhsms_database.sql` schema

### UI/UX Standards
- **CSS Framework**: Custom CSS only (`sidebar.css`, `dashboard.css`, `edit.css`) - NO Bootstrap
- **Layout System**: Standardized div1-div7 grid layout across all station interfaces  
- **Responsive Design**: Mobile-friendly with collapsible sidebars and adaptive layouts
- **Consistent Styling**: Unified color scheme, button styles, and status badges

### Security & Access Control
- **Role-Based Permissions**: Station access restricted by employee roles and assignments
- **Session Management**: Separate employee and patient session handling
- **Facility Restriction**: All operations restricted to facility_id='1' (CHO Main District)
- **Audit Trail**: Comprehensive logging of all queue actions and patient movements

### Database Schema Integration
- **Queue Entries**: Links to `visits` table for clinical encounter tracking
- **Station Management**: Uses `stations` and `station_assignments` tables
- **Patient Priority**: Integration with `patient_flags` for priority classification
- **Appointment System**: Full integration with existing appointment booking system

## Development Guidelines

### File Structure Conventions
- **Station Files**: `{station_type}_station.php` pattern for consistency
- **Public Displays**: `public_display_{station_type}.php` for waiting area monitors
- **API Handlers**: `{feature}_actions.php` for backend processing
- **Utilities**: Shared services in `/utils/` directory

### Coding Standards
- **Database Access**: Use PDO with prepared statements for all database operations
- **Error Handling**: Comprehensive error logging and user-friendly error messages
- **Real-time Updates**: Implement auto-refresh mechanisms for queue status changes
- **Mobile Compatibility**: Ensure all interfaces work on tablet/mobile devices

## Implementation Status
✅ **Production Ready**: Full queue management system with comprehensive workflows
✅ **PhilHealth Integration**: Complete support for membership-based routing
✅ **Multi-Station Support**: All 7 station types implemented with role-based access
✅ **Real-time Monitoring**: Live queue updates and public display system
🔄 **Continuous Enhancement**: Ongoing optimization based on CHO operational needs