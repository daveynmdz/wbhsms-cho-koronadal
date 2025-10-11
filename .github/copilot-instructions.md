# Web-Based Healthcare Services Management System (CHO Koronadal)

## Project Architecture

This is a **dual-session PHP healthcare management system** with separate patient and employee portals. Built for XAMPP deployment with MySQL backend and role-based access control.

### Core Directory Structure
```
├── config/
│   ├── db.php              # Dual PDO/MySQLi connections 
│   ├── env.php             # XAMPP-optimized environment loader
│   └── session/            # Separate patient/employee sessions
├── pages/
│   ├── patient/            # Patient portal (separate session namespace)
│   └── management/         # Employee portal with role-based dashboards
│       └── admin/          # Admin-specific pages
│           ├── dashboard.php
│           ├── patient-records/
│           │   └── patient_records_management.php
│           ├── referrals/
│           │   └── referrals_management.php
│           ├── appointments/
│           │   └── appointments_management.php
│           ├── user-management/
│           │   ├── employee_list.php
│           │   ├── add_employee.php
│           │   └── edit_employee.php
│           └── staff-management/
│               └── staff_assignments.php
├── api/                    # REST endpoints + OOP controllers
├── utils/                  # Service classes (QueueManagementService)
├── includes/               # Role-based sidebars + topbar components
└── scripts/setup/          # XAMPP validation tools
```

## Database & Configuration Patterns

### Database Connection Strategy
- **PDO preferred** for new code: `global $pdo` from `config/db.php`
- **MySQLi legacy support**: `global $conn` for existing code
- **Environment**: XAMPP defaults in `config/env.php` (root user, empty password)
- **Path Resolution**: Use `$root_path = dirname(dirname(__DIR__))` pattern

### Session Management
- **Dual sessions**: `EMPLOYEE_SESSID` vs `PATIENT_SESSID` 
- **Include pattern**: `require_once $root_path . '/config/session/employee_session.php'`
- **Functions**: `is_employee_logged_in()`, `get_employee_session($key)`, `clear_employee_session()`

## Key Architectural Components

### Queue Management System
- **Service class**: `utils/QueueManagementService` handles queue lifecycle
- **API patterns**: REST endpoints in `api/queue_management.php` + OOP controllers in `api/queueing/`
- **Database tables**: Use `stations` + `station_assignments` (NOT `staff_assignments`)
- **Integration**: Queue entries auto-created on appointment booking

#### Multi-Station Queue Workflows
```
Station          Function                              Special Rules
─────────────────────────────────────────────────────────────────────
Triage          Vital signs, pre-consultation         Standard flow
Consultation    Doctor consultations, dental          Standard flow  
Laboratory      Sample collection, result upload      Time-sensitive requeue rules
Pharmacy        Prescription verification             Standard flow
Billing         Payment computation, receipts         Standard flow
Document        Certificates, medico-legal           Standard flow
```

#### Laboratory Queue Special Rules
- **Collection phase**: Technician calls patient for specimen collection
- **Waiting state**: Patient enters "Waiting for Lab Results" with logged wait time
- **Requeue rules**: 
  - Before 4:00 PM: Can requeue to referring doctor
  - After 4:00 PM: Must complete visit (no requeue)
- **Interface**: Use `station_lab.php` for lab-specific workflows

#### Manual Override System
- **Authorized roles**: Doctors, Nurses, Admins can skip/recall/reassign patients
- **Audit trail**: All overrides logged in `queue_logs` (timestamp, action, employee_id)
- **Access control**: Only assigned employees (via `assignment_schedules`) or Admins control queues

#### Queue-Visit Relationship
- **Enforcement**: Every queue record MUST link to valid `visit_id` - no standalone entries
- **Traceability**: Ensures clinical encounter connection to administrative operations

### Role-Based UI System
- **Sidebars**: Role-specific includes (`sidebar_admin.php`, `sidebar_doctor.php`, etc.)
- **Topbar**: Reusable component with `renderTopbar()` function
- **Dashboard routing**: `/pages/management/{role}/dashboard.php` pattern
- **Path variables**: `$activePage` for navigation state

#### Role Permission Matrix
```
Role                    Permissions                              Scope
────────────────────────────────────────────────────────────────────────
Admin                  Full system access                       System-wide
Doctor                 Patient care, queue management           Clinical operations
Nurse                  Patient care, triage, queue overrides    Clinical support
DHO                    Referrals, view records (read-only)      District-wide
BHW                    Referrals, view records (read-only)      Barangay-assigned
Laboratory Tech        Lab workflows, result upload             Laboratory station
Pharmacist             Prescription dispensing                   Pharmacy station
Cashier                Billing, payment processing              Financial operations
Records Officer        Documentation, medical certificates       Administrative
```

### Station Assignment Architecture
- **Correct tables**: `stations` (definitions) + `station_assignments` (daily assignments)
- **Station types**: `'checkin','triage','billing','consultation','lab','pharmacy','document'`
- **Service integration**: Staff assigned to stations linked to specific services

## Testing & Debugging Workflows

### XAMPP Validation Commands
```bash
# System validation (run after setup)
http://localhost/project/scripts/setup/setup_check.php

# Database connectivity test  
http://localhost/project/scripts/setup/testdb.php

# Session debugging (for redirect loop issues)
http://localhost/project/scripts/setup/setup_debug.php
```

### Debugging Session Issues
- **Redirect loops**: Often caused by session path restrictions - use `$cookiePath = '/'`
- **Clear sessions**: Use `scripts/setup/setup_debug.php` "Clear Session" button
- **Debug file**: `tests/debug_session.php` shows session state + superglobals

## Development Conventions

### File Organization Patterns
- **Authentication**: Always include session config first: `require_once $root_path . '/config/session/employee_session.php'`
- **Database access**: Include `config/db.php` for dual connection support
- **Path resolution**: Use `$root_path = dirname(dirname(__DIR__))` from deeply nested files

### Error Handling & Environment
- **Debug mode**: Controlled by `$_ENV['APP_DEBUG']` in `config/env.php`
- **XAMPP-friendly**: Default settings work out-of-box with XAMPP installation
- **Production ready**: Debug mode can be disabled for production deployment

### API Development Patterns
- **Dual approach**: REST endpoints (`api/queue_management.php`) + OOP controllers (`api/queueing/QueueController.php`)
- **Authentication**: Employee session required for most operations
- **JSON responses**: Standardized error/success response format
- **Database**: Use PDO with prepared statements for new development

#### API Authentication Strategy
- **Internal APIs**: Session-based (`$_SESSION`) with role validation
- **Public endpoints**: Appointment booking, verification (QR/ID-based)
- **Audit logging**: All actions tracked in `queue_logs`/`appointment_logs`
- **Future enhancement**: JWT tokens for external integrations

### UI Component Integration & Standardized Patterns

#### **CRITICAL: UI Pattern Guidelines (MANDATORY)**
- **Viewing/List Pages**: Use **SIDEBAR ONLY** - no topbar (except mobile topbar for responsive design)
  - Pattern: `patient_records_management.php`, `referrals_management.php`, `employee_list.php`
  - Include: `sidebar_admin.php` or appropriate role sidebar
  - Layout: `<div class="homepage">` with sidebar navigation
  - Actions: Modal popups for quick actions (status updates, filters)
  - **IMPORTANT**: Keep mobile topbar elements in sidebar.css for responsive mobile navigation

- **Create/Edit/Form Pages**: Use **TOPBAR ONLY** - no sidebar  
  - Pattern: `create_referrals.php`, `add_employee.php`, `edit_employee.php`
  - Include: `topbar.php` with back button functionality
  - Layout: `<section class="homepage">` with topbar navigation
  - CSS: `topbar.css`, `profile-edit.css`, `edit.css`

#### **CSS Framework Standards (NO BOOTSTRAP)**
- **FORBIDDEN**: Bootstrap, external CSS frameworks
- **Required**: Use existing custom CSS only
  - `sidebar.css` - for sidebar-based pages
  - `topbar.css` - for topbar-based pages  
  - `dashboard.css` - for dashboard layouts
  - `profile-edit.css` - for form layouts
  - `edit.css` - for edit-specific styling

#### **Alert/Notification Standards (NO JAVASCRIPT ALERTS)**
- **FORBIDDEN**: `alert()`, `confirm()`, localhost popups
- **Required**: Use custom alert divs with proper styling
```php
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> Message content
    <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
</div>
```
- Auto-dismiss after 5-8 seconds with fade animation
- Use existing modal system for confirmations

#### **Component Integration**
- **Sidebar inclusion**: Pass `$activePage`, include appropriate role sidebar using `<?php include 'path/to/sidebar_admin.php'; ?>`
- **Path Resolution**: Sidebar uses script depth detection for robust path calculation - no need for manual relative paths
- **Active Page Values**: Use correct values for sidebar highlighting:
  - `'dashboard'` - Dashboard page
  - `'patient_records'` - Patient Records Management
  - `'referrals'` - Referrals Management  
  - `'appointments'` - Appointments Management
  - `'user_management'` - Employee Management
  - `'staff_assignments'` - Staff Assignment
- **Topbar usage**: `renderTopbar(['title' => 'Page Title', 'back_url' => '...', 'user_type' => 'employee'])`
- **Back button**: Always provide clear navigation path in topbar pages
- **Mobile Support**: Never remove mobile topbar elements from sidebar.css - required for responsive navigation

## Deployment & Production Patterns

### Supported Environments
```
Environment          Status    Use Case
──────────────────────────────────────────────────
XAMPP (Local)        ✅        Development/testing
LAN (Intranet)       ✅        CHO internal server
Hostinger VPS        ✅        Target production
AWS/DigitalOcean     ✅        Cloud deployment
Docker               ⚙️        Future containerization
```

### Production Deployment (Hostinger VPS)
- **Stack**: LAMP (Apache, PHP 7.4+, MySQL, Linux)
- **Security**: HTTPS enforcement, protected `/config/` and `/utils/` directories
- **Database**: Deploy with `wbhsms_database.sql`, implement backup policy
- **Maintenance**: Daily backups, log rotation, optional monitoring

### Security Considerations
- **Directory protection**: Use `.htaccess` for sensitive folders
- **Session security**: Separate employee/patient session namespaces
- **Audit compliance**: All critical actions logged with employee attribution
- **Future JWT**: Planned for mobile apps and external integrations

This system prioritizes **XAMPP compatibility**, **role-based security**, and **dual-session architecture** for healthcare workflow management.