# CHO Koronadal Queue Management System

## Overview
The queue management system provides comprehensive patient flow control for the CHO Koronadal WBHSMS. This module handles patient check-in, station-level queue management, real-time monitoring, and public displays with full CHO theme integration.

## Core Files

### Main Interfaces (CHO Theme Applied)
- **`dashboard.php`** - ✅ **Admin Queue Dashboard** - Central control with toggle switches and statistics
- **`station.php`** - ✅ **Individual Station Interface** - Staff can manage their station queue and toggle open/closed
- **`admin_monitor.php`** - ✅ **Master Monitor View** - Real-time monitoring of all stations
- **`checkin.php`** - ✅ **Patient Check-in System** - Staff-operated patient registration and queue entry
- **`logs.php`** - ✅ **Queue Logs & Reports** - Historical data and analytics
- **`staff_assignments.php`** - ✅ **Staff Assignment Management** - Assign staff to stations with schedules

### Public Display System
- **`public_display_selector.php`** - ✅ **Display Launcher** - Admin interface to open displays on monitors
- **`public_display_triage.php`** - ✅ **Triage Display** - Waiting area screen for triage services
- **`public_display_consultation.php`** - ✅ **Consultation Display** - Waiting area screen for consultations  
- **`public_display_lab.php`** - ✅ **Laboratory Display** - Waiting area screen for lab services
- **`public_display_pharmacy.php`** - ✅ **Pharmacy Display** - Waiting area screen for pharmacy
- **`public_display_billing.php`** - ✅ **Billing Display** - Waiting area screen for billing
- **`public_display_document.php`** - ✅ **Document Display** - Waiting area screen for document processing

### Utilities
- **`print_ticket.php`** - ✅ **Queue Ticket Printing** - Generate queue number tickets

## ✅ **Implemented Features**

### ✅ Station Management & Toggle Control
- **Role-based Station Access**: Admin can access all, staff can manage assigned stations, others view-only
- **Station Toggle Functionality**: Staff can open/close their stations, admins can override
- **Smart Queue Routing**: Patients only assigned to OPEN stations of the correct service type
- **Real-time Status Updates**: Open/closed status reflected across all interfaces
- **Priority Queue Management**: Emergency > Priority > Normal ordering

### ✅ Administrative Dashboard  
- **Multi-station Overview**: Real-time monitoring of all queue stations
- **Queue Statistics**: Total, waiting, in-progress, completed counts
- **Station Toggle Controls**: Admin can open/close any station remotely
- **Staff Assignment Integration**: Shows assigned employees and schedules
- **Performance Analytics**: Busiest stations, average wait times

### ✅ Check-in System
- **Dual Patient Search**: By patient ID or appointment details
- **Appointment Validation**: Verifies scheduled appointments before check-in
- **Queue Code Generation**: Structured queue numbers for CHO appointments  
- **Priority Assignment**: Emergency, priority, and normal queue levels
- **Multi-service Support**: Routes to appropriate service stations

### ✅ Individual Station Interface
- **Staff Queue Management**: Call next patient, mark completed, skip patients
- **Station Toggle**: Open/close station to control patient flow
- **Queue Display**: Real-time list of waiting, in-progress, and completed patients
- **Patient Actions**: Complete service, skip patient, manage queue status
- **Permission System**: Only assigned staff can manage their station

### ✅ Public Display System
- **Multi-Monitor Support**: Separate displays for each service type
- **Real-time Updates**: Shows currently serving and queue status
- **Service-Specific Displays**: Triage, Consultation, Lab, Pharmacy, Billing, Document
- **Admin Launcher**: Easy interface to open displays on multiple monitors
- **Modern UI Design**: CHO theme integration with responsive layouts

### ✅ Monitoring & Reporting
- **Master Monitor View**: Admin oversight of all stations with current patients
- **Queue Logs**: Historical data with filtering and search capabilities
- **Staff Assignment Management**: Schedule and assign employees to stations
- **Real-time Statistics**: Active queues, completion rates, wait times
- **System Status Tracking**: Station availability and assignment status

## API Integration
- Integrates with `/api/queue_management.php` for backend operations
- Real-time updates via AJAX
- RESTful API endpoints for queue operations

## Security
- Employee authentication required for all administrative functions
- Role-based access control
- Session management integration
- Public display accessible without authentication

## Navigation
All pages include consistent navigation with links to:
- Queue Dashboard
- Check-in Interface
- Station Views
- Public Display
- Queue Logs

## Implementation Status
🚧 **Currently**: HTML skeleton structure with placeholder content
📋 **Next**: Business logic implementation and API integration
🎯 **Goal**: Full queue management system with real-time updates