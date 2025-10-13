# Staff Assignment System - Simplified

This document explains the new simplified staff assignment system for queue management.

## Key Changes Made

### 1. **Simplified Assignment Types**
- **Only this Day**: Assigns an employee to a station for the selected date only
- **Permanent Assignment**: Assigns an employee to a station daily until manually changed (creates assignments for 365 days)

### 2. **Easy Assignment Management**
- **Assign Button**: For unassigned stations - opens assignment modal
- **Reassign Button**: For assigned stations - allows changing the employee
- **Remove Button**: For assigned stations - removes the assignment with options

### 3. **Remove Assignment Options**
- **Only this Day**: Removes assignment for the selected date only
- **Permanent Removal**: Removes the employee from all future assignments for that station

## How to Use

### Setup (First Time Only)
1. Run the setup script: `php utils/setup_station_management.php`
2. This creates the necessary database tables and sample stations

### Daily Operations

#### Assigning Staff
1. Click the **Assign** button (blue user-plus icon) for any unassigned station
2. Select the employee from the dropdown (filtered by role)
3. Choose assignment type:
   - **Only this Day** for temporary coverage
   - **Permanent Assignment** for regular daily assignments
4. Set shift times if needed
5. Click **Assign**

#### Changing Assignments
1. Click the **Reassign** button (yellow exchange icon) for assigned stations
2. Select the new employee
3. Click **Reassign**

#### Removing Assignments
1. Click the **Remove** button (red user-minus icon) for assigned stations
2. Choose removal type:
   - **Only this Day** to remove just today's assignment
   - **Permanent Removal** to remove from all future dates
3. Click **Remove Assignment**

## Database Structure

### Tables Created
- `stations`: Stores station information (consultation rooms, lab, pharmacy, etc.)
- `station_assignments`: Stores employee-to-station assignments with dates

### Assignment Types
- `single_day`: One-time assignment for specific date
- `permanent`: Recurring daily assignment

## Benefits of the New System

1. **Simpler Interface**: Only two clear assignment options
2. **No Complex Date Ranges**: Permanent assignments handle recurring needs automatically  
3. **Easy Management**: Clear buttons for assign/reassign/remove operations
4. **Role-Based Filtering**: Only appropriate roles shown for each station type
5. **Flexible Removal**: Remove assignments for single day or permanently

## Station Type Mapping

| Station Type | Allowed Roles |
|--------------|---------------|
| Triage | Nurse |
| Consultation | Doctor |
| Check-in | Records Officer, Nurse |
| Laboratory | Laboratory Tech |
| Pharmacy | Pharmacist |
| Billing | Cashier |
| Document | Records Officer |
| Vaccination | Doctor, Nurse |
| Family Planning | Doctor, Nurse |
| Dental | Doctor |
| TB DOTS | Doctor |
| Animal Bite | Doctor |