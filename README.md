# Koronadal Health Management System (KCHSMS)

A comprehensive web-based healthcare services management system for the City Health Office of Koronadal.

## 🏥 Features

- **Patient Management**: Registration, profiles, medical history tracking
- **Appointment System**: Scheduling and management for various healthcare services  
- **Referral System**: Intelligent referral routing and tracking
- **Multi-Role Support**: Admin, Doctor, Nurse, Pharmacist, BHW, DHO, and more
- **Real-time Notifications**: Email notifications for appointments and referrals
- **Responsive Design**: Works on desktop and mobile devices

## 🚀 Quick Start

### Prerequisites

- PHP 8.3+ with extensions: mysqli, pdo_mysql, zip
- MySQL/MariaDB database
- Composer
- Web server (Apache/Nginx)

### Local Development

1. **Clone the repository**
   ```bash
   git clone https://github.com/daveynmdz/wbhsms-cho-koronadal.git
   cd wbhsms-cho-koronadal
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp config/.env.example config/.env.local
   # Edit config/.env.local with your database settings
   ```

4. **Setup database**
   ```bash
   # Import database/wbhsms_cho.sql into your MySQL database
   ```

5. **Start development server**
   ```bash
   php -S localhost:8000
   ```

## 📁 Project Structure

```
wbhsms-cho-koronadal/
├── 📁 assets/          # CSS, JS, images
├── 📁 config/          # Configuration files
├── 📁 database/        # SQL schemas and migrations
├── 📁 docs/           # Documentation
├── 📁 includes/       # Shared PHP includes
├── 📁 pages/          # Application pages
│   ├── 📁 management/  # Admin/staff interfaces
│   └── 📁 patient/     # Patient interfaces
├── 📁 tests/          # Test utilities
├── 📁 vendor/         # Composer dependencies
└── 📄 index.php       # Application entry point
```

## 🐳 Deployment

### Coolify (Recommended)

See [Deployment Guide](docs/DEPLOYMENT.md) for detailed Coolify deployment instructions.

### Docker

```bash
docker build -t kchsms .
docker run -d -p 80:80 kchsms
```

### Traditional Server

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for complete deployment instructions.

## 🔧 Configuration

### Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `DB_HOST` | Database host | `localhost` |
| `DB_NAME` | Database name | `wbhsms_cho` |
| `DB_USER` | Database user | `cho-admin` |
| `DB_PASS` | Database password | `secure-password` |
| `SMTP_HOST` | Email server | `smtp.gmail.com` |
| `APP_DEBUG` | Debug mode | `0` (production) |

### Database Management

- **Development**: Use phpMyAdmin or similar web interface
- **Production**: Use DBeaver for advanced database management (see deployment guide)

## 🧪 Testing

```bash
# Database connection test
php tests/testdb.php

# Path resolution test  
php tests/test_paths.php
```

## 📚 Documentation

- [Deployment Guide](docs/DEPLOYMENT.md) - Complete deployment instructions
- [Path Fixes Summary](docs/PATH_FIXES_SUMMARY.md) - Path resolution documentation
- [Database Updates](docs/database_update_instructions.html) - Schema update instructions

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is developed for the City Health Office of Koronadal.

## 🆘 Support

For issues, questions, or deployment help:
1. Check the [documentation](docs/)
2. Review [troubleshooting guide](docs/DEPLOYMENT.md#troubleshooting)
3. Create an issue in the GitHub repository

---

**City Health Office of Koronadal** - Improving healthcare through technology
