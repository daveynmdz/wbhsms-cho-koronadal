# API Directory

## Overview
This directory contains REST API endpoints and backend controllers for the CHO Koronadal WBHSMS.

## Structure

### REST Endpoints
- **`queue_management.php`** - Legacy queue management REST API endpoint
  - Handles GET/POST/PUT requests for queue operations
  - Provides direct API access for frontend applications

### Backend Controllers
- **`queueing/`** - Queue management backend classes
  - `QueueController.php` - Main queue management controller class
  - `QueueActions.php` - Procedural action endpoints for queue operations
  - Object-oriented approach for business logic separation

## Usage Patterns

### Direct API Access
```
GET  /api/queue_management.php?action=queue_list&service_id=1
POST /api/queue_management.php (with JSON payload)
PUT  /api/queue_management.php (for status updates)
```

### Action-Based Endpoints
```
POST /api/queueing/QueueActions.php
Content-Type: application/json
{
  "action": "checkInPatient",
  "queue_entry_id": 123,
  "employee_id": 456
}
```

### Controller Class Usage
```php
require_once __DIR__ . '/queueing/QueueController.php';
$queueController = new QueueController();
$result = $queueController->createQueueEntry($patient_id, $appointment_id, $service_id);
```

## Database Access
- All API endpoints and controllers use the project's standard database connection
- Requires `config/db.php` which provides both PDO and MySQLi connections
- PDO is preferred for new development (prepared statements, better security)

## Authentication
- Most endpoints require employee session authentication
- Public display endpoints may be accessible without authentication
- Role-based access control applied based on user permissions

## Error Handling
- Standardized JSON error responses
- Appropriate HTTP status codes
- Debug information available in development mode