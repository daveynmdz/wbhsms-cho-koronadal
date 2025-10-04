# Management Models

## Overview
This directory contains data access layer models for the management section of the CHO Koronadal WBHSMS.

## Models

### QueueModel.php
Handles all database operations for queue management including:

#### Core Functions
- **`createQueueEntry()`** - Create new queue entries with auto-generated queue numbers
- **`updateQueueStatus()`** - Update queue status with comprehensive logging
- **`getActiveQueueByService()`** - Retrieve active queues with priority ordering
- **`reassignQueue()`** - Transfer patients between service queues
- **`reinstateQueue()`** - Reinstate previously skipped/cancelled patients

#### Features
- **Transaction Management** - All operations use database transactions
- **Audit Logging** - All changes logged to `queue_logs` table
- **Priority Handling** - Emergency > Priority > Normal queue ordering
- **Status Validation** - Validates allowed status transitions
- **Time Tracking** - Automatic waiting and turnaround time calculation
- **Estimated Wait Times** - Calculates estimated wait based on queue position

#### Database Tables
- **`queue_entries`** - Main queue data with status, timing, and patient info
- **`queue_logs`** - Audit trail of all queue actions and status changes

#### Usage Example
```php
require_once 'models/QueueModel.php';

$queueModel = new QueueModel();

// Create new queue entry
$result = $queueModel->createQueueEntry(
    $appointment_id, $patient_id, $service_id, 
    'consultation', 'normal'
);

// Update status
$result = $queueModel->updateQueueStatus(
    $queue_entry_id, 'in_progress', $employee_id, 'Patient called'
);

// Get active queue
$queue = $queueModel->getActiveQueueByService($service_id, 'consultation');
```

## Standards
- All models use PDO for database access
- Comprehensive error handling with exceptions
- Transaction-based operations for data integrity
- Consistent return format with success/error status
- Detailed logging for audit trails