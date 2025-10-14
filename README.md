# 🏥 Web-Based Healthcare Services Management System
## CHO Koronadal

A comprehensive healthcare management system for the City Health Office of Koronadal, designed for easy deployment with XAMPP.

## � Repository Structure

```
wbhsms-cho-koronadal/
├── index.php                    # Main homepage
├── api/                         # REST API endpoints and backend controllers
├── assets/                      # CSS, JS, images
├── config/                      # Database and environment configuration
├── includes/                    # Shared navigation and headers
├── pages/                       # Application pages (patient, management, queueing)
├── scripts/                     # Setup, maintenance, and utility scripts
│   ├── setup/                   # Installation and testing tools
│   ├── maintenance/             # Database maintenance scripts
│   └── cron/                    # Scheduled task scripts
├── tests/                       # Testing and debugging tools
├── docs/                        # Documentation and guides
├── utils/                       # Utility functions and templates
└── vendor/                      # Third-party libraries
```

## �🚀 Quick Setup

### Prerequisites
- [XAMPP](https://www.apachefriends.org/download.html) (includes PHP, MySQL, Apache)
- Web browser

### Installation Steps

1. **Download & Install XAMPP**
   - Download XAMPP from the official website
   - Install with default settings
   - Start Apache and MySQL services

2. **Clone/Download this Project**
   ```bash
   # Option 1: Clone with Git
   git clone https://github.com/daveynmdz/wbhsms-cho-koronadal.git
   
   # Option 2: Download ZIP and extract
   ```

3. **Place in XAMPP Directory**
   - Copy the project folder to: `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac)
   - Your path should be: `htdocs/wbhsms-cho-koronadal/`

4. **Setup Database**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Create a new database named: `wbhsms_cho`
   - Import the database file: `database/wbhsms_cho.sql`

5. **Configure Environment**
   ```bash
   # Copy the example environment file
   cp .env.example .env
   ```
   - Edit `.env` file if needed (default XAMPP settings should work)

6. **Test the Installation**
   - Visit: http://localhost/wbhsms-cho-koronadal/scripts/setup/testdb.php
   - You should see "Database Connection Successful!"
   - Visit: http://localhost/wbhsms-cho-koronadal/scripts/setup/setup_check.php
   - Verify all components are working properly

7. **Access the System**
   - Homepage: http://localhost/wbhsms-cho-koronadal/
   - Patient Login: http://localhost/wbhsms-cho-koronadal/pages/patient/auth/patient_login.php

## 📁 Project Structure

```
wbhsms-cho-koronadal/
├── 📄 index.php           # Main homepage
├── 📄 .env.example        # Environment configuration template
├── 📁 assets/             # CSS, JavaScript, images
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript files
│   └── images/           # Images and icons
├── 📁 config/             # Configuration files
│   ├── env.php           # Environment loader
│   ├── db.php            # Database connection
│   └── session/          # Session management
├── 📁 database/           # Database schema
│   └── wbhsms_cho.sql    # Main database file
├── 📁 includes/           # Shared components
│   ├── sidebar_admin.php  # Admin navigation
│   └── sidebar_patient.php # Patient navigation
├── 📁 pages/              # Application pages
│   ├── patient/          # Patient portal
│   │   ├── auth/         # Login/registration
│   │   ├── dashboard.php # Patient dashboard
│   │   └── profile/      # Profile management
│   └── management/       # Staff/admin portal
└── 📁 docs/              # Documentation
```

## 🔧 Configuration

### Default XAMPP Settings (.env file)
```bash
DB_HOST=localhost
DB_PORT=3306
DB_NAME=wbhsms_cho
DB_USER=root
DB_PASS=                   # Empty for XAMPP default
APP_DEBUG=1                # Enable for development
```

### Email Configuration (Optional)
For email features like password reset:
```bash
SMTP_HOST=smtp.gmail.com
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_PORT=587
```

## 🏥 System Features

- **Patient Portal**: Registration, appointments, medical records
- **Staff Portal**: Patient management, appointments, reports
- **Authentication**: Secure login with OTP verification
- **Dashboard**: Comprehensive overview of healthcare services
- **Responsive Design**: Works on desktop and mobile devices

## 🔍 Troubleshooting

### Common Issues

**Database Connection Failed?**
1. Make sure XAMPP MySQL is running
2. Verify database `wbhsms_cho` exists in phpMyAdmin
3. Check if database file was imported correctly
4. Test connection: http://localhost/wbhsms-cho-koronadal/scripts/setup/testdb.php

**Page Not Found?**
1. Verify project is in `htdocs/wbhsms-cho-koronadal/`
2. Make sure Apache is running in XAMPP
3. Check URL spelling

**PHP Errors?**
1. Enable error display in `.env`: `APP_DEBUG=1`
2. Check XAMPP error logs
3. Verify PHP extensions are enabled

## 🧪 Testing

- **Database Test**: `/scripts/setup/testdb.php` - Tests database connectivity
- **System Check**: Navigate through login and dashboard pages
- **Patient Registration**: Test the registration process
- **Queue System Tests**: Available in `/tests/` folder for comprehensive queue testing

## 📚 Documentation

### 📁 Available Documentation (`docs/` folder)

#### **System Setup & Configuration**
- **[SETUP_SUMMARY.md](docs/SETUP_SUMMARY.md)** - Complete system setup instructions
- **[ENV_CONFIGURATION_GUIDE.md](docs/ENV_CONFIGURATION_GUIDE.md)** - Environment configuration for local/production
- **[EMAIL_SETUP_GUIDE.md](docs/EMAIL_SETUP_GUIDE.md)** - Email system configuration

#### **System Architecture & Database**
- **[DATABASE_TABLE_ANALYSIS.md](docs/DATABASE_TABLE_ANALYSIS.md)** - Database structure and table relationships
- **[DASHBOARD_TEMPLATE_DOCS.md](docs/DASHBOARD_TEMPLATE_DOCS.md)** - Dashboard template documentation

#### **Queue Management System**
- **[QUEUE_SYSTEM_DOCUMENTATION.md](docs/QUEUE_SYSTEM_DOCUMENTATION.md)** - Complete queue system guide
- **[APPOINTMENT_QUEUEING_ENHANCEMENT.md](docs/APPOINTMENT_QUEUEING_ENHANCEMENT.md)** - Appointment-queue integration
- **[AUTOMATIC_STATUS_UPDATES.md](docs/AUTOMATIC_STATUS_UPDATES.md)** - Automated status update system
- **[Queueing/](docs/Queueing/)** - Station-specific workflow documentation:
  - `patient-flow_Version2.md` - Patient flow workflows
  - `station-checkin_Version2.md` - Check-in station procedures
  - `station-triage_Version2.md` - Triage station workflows
  - `station-consultation_Version2.md` - Consultation station procedures
  - `station-lab_Version2.md` - Laboratory station workflows
  - `station-pharmacy_Version3.md` - Pharmacy station procedures
  - `station-billing_Version2.md` - Billing station workflows
  - `station-document_Version2.md` - Document station procedures

#### **Staff Management**
- **[STAFF_ASSIGNMENT_GUIDE.md](docs/STAFF_ASSIGNMENT_GUIDE.md)** - Staff assignment system guide
- **[STAFF_ASSIGNMENT_LOGIN_FIX.md](docs/STAFF_ASSIGNMENT_LOGIN_FIX.md)** - Login system fixes and security

#### **Production Deployment & Fixes**
- **[AUTH_URL_FIX_SUMMARY.md](docs/AUTH_URL_FIX_SUMMARY.md)** - Authentication URL fixes for production
- **[EMPLOYEE_PRODUCTION_LOGOUT_FIX.md](docs/EMPLOYEE_PRODUCTION_LOGOUT_FIX.md)** - Employee logout fixes
- **[PATIENT_NAVIGATION_PRODUCTION_FIX.md](docs/PATIENT_NAVIGATION_PRODUCTION_FIX.md)** - Patient navigation fixes
- **[PATIENT_SESSION_HEADERS_FIX.md](docs/PATIENT_SESSION_HEADERS_FIX.md)** - Session header management
- **[ENHANCED_SESSION_HEADERS_FIX.md](docs/ENHANCED_SESSION_HEADERS_FIX.md)** - Advanced session handling
- **[MYSQLI_INTRANSACTION_FIX.md](docs/MYSQLI_INTRANSACTION_FIX.md)** - Database transaction fixes
- **[CENTRALIZED_FILES_SIDEBAR_FIX.md](docs/CENTRALIZED_FILES_SIDEBAR_FIX.md)** - Sidebar navigation fixes

## 📚 For Developers

### Advanced Setup (Production)
- See **[docs/ENV_CONFIGURATION_GUIDE.md](docs/ENV_CONFIGURATION_GUIDE.md)** for production deployment
- Use Composer for dependency management: `composer install`
- Configure proper environment variables for production

### File Structure
- **Essential Files**: All files in this repository are required for functionality
- **Core Components**: `index.php`, `config/`, `pages/patient/`, `assets/`
- **Database**: Import `database/wbhsms_cho.sql` for full functionality

## 🆘 Support

For issues or questions:
1. Check the troubleshooting section above
2. Test database connection using `/scripts/setup/testdb.php`
3. Review XAMPP logs for errors
4. Create an issue in the GitHub repository

## 📄 License

This project is developed for the City Health Office of Koronadal.

---

**City Health Office of Koronadal** - Improving healthcare through technology