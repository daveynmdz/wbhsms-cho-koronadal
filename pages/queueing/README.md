# Queueing Module

## Overview
The queueing module provides comprehensive queue management functionality for the CHO Koronadal WBHSMS. This module handles patient flow, queue monitoring, and service station management.

## Files

### Core Pages
- **`checkin.php`** - Patient check-in and triage verification interface
- **`station.php`** - Station-specific view for healthcare providers (parameterized by service_id/station_id)
- **`public_display.php`** - Public waiting area display showing "now serving" information
- **`dashboard.php`** - Administrative dashboard for multi-service queue monitoring
- **`logs.php`** - Queue logs and historical data for analysis and reporting

### JavaScript
- **`../../assets/js/queueing.js`** - Client-side queue management functionality

## Features (Planned)

### Check-in System
- Patient verification and appointment lookup
- Triage process integration
- Queue number assignment
- Priority level management

### Station Management
- Service-specific queue displays
- Real-time status updates
- Patient calling system
- Service completion tracking

### Public Display
- "Now serving" information
- Queue status overview
- Waiting time estimates
- Multi-service display support

### Administrative Dashboard
- Multi-service queue overview
- Real-time statistics
- Queue flow monitoring
- Staff performance metrics

### Logging & Reporting
- Comprehensive queue history
- Wait time analytics
- Service efficiency reports
- Data export functionality

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