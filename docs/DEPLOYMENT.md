# Deployment Guide - Koronadal Health Management System

## Overview

This guide covers deploying the Koronadal Health Management System (KCHSMS) to various environments.

## Prerequisites

- PHP 8.3+ with extensions: mysqli, pdo_mysql, zip
- MySQL/MariaDB database
- Composer for dependency management
- Web server (Apache/Nginx)

## Environment Configuration

### 1. Environment Files

The system uses environment files for configuration:

- **Production**: Root `.env` file
- **Local Development**: `config/.env.local` file

### 2. Database Configuration

```bash
DB_HOST=your-database-host
DB_PORT=3306
DB_NAME=your-database-name
DB_USER=your-database-user
DB_PASS=your-database-password
```

### 3. Email Configuration (SMTP)

```bash
SMTP_HOST=smtp.gmail.com
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_PORT=587
```

## Coolify Deployment

### 1. Database Setup

1. Create MySQL database resource in Coolify
2. Configure external port access (for DBeaver management)
3. Import database schema from `database/wbhsms_cho.sql`

### 2. Application Deployment

1. **Source**: Connect to GitHub repository
2. **Build**: Uses Docker with provided Dockerfile
3. **Environment Variables**: Set in Coolify dashboard
4. **Domain**: Configure custom domain or use Coolify subdomain

### 3. Environment Variables for Coolify

```bash
DB_HOST=mysql-db          # Coolify service name
DB_PORT=3306
DB_NAME=default           # Or your database name
DB_USER=cho-admin
DB_PASS=your-secure-password
SMTP_HOST=smtp.gmail.com
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_PORT=587
APP_DEBUG=0
APP_ENV=production
```

## Docker Deployment

### Using the provided Dockerfile:

```bash
# Build the image
docker build -t kchsms .

# Run the container
docker run -d -p 80:80 \
  -e DB_HOST=your-db-host \
  -e DB_NAME=your-db-name \
  -e DB_USER=your-db-user \
  -e DB_PASS=your-db-pass \
  kchsms
```

## Traditional Server Deployment

### 1. File Upload

Upload all files except:
- `/tests/` directory
- `/docs/` directory (optional)
- `.env.local` files

### 2. Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Database Setup

1. Import `database/wbhsms_cho.sql`
2. Create database user with appropriate permissions
3. Configure environment variables

### 4. File Permissions

```bash
# Set proper ownership
chown -R www-data:www-data /path/to/project

# Set permissions
find /path/to/project -type d -exec chmod 755 {} \;
find /path/to/project -type f -exec chmod 644 {} \;
```

## Database Management

### Using DBeaver (Recommended)

1. **Connection Setup**:
   - Host: Your server IP
   - Port: Database external port
   - Database: default (or configured name)
   - Username/Password: From Coolify

2. **Benefits**:
   - Full database management capabilities
   - No web interface limitations
   - Advanced features and performance

### Schema Updates

When updating the database schema:

1. **Backup existing data**
2. **Run migration scripts** from `/database/` folder
3. **Test application functionality**

## Troubleshooting

### Common Issues

1. **File Path Errors**: Ensure all paths use `dirname(__DIR__, X)` pattern
2. **Database Connection**: Verify environment variables and network access
3. **Missing Dependencies**: Run `composer install`
4. **Permission Errors**: Check file/folder permissions

### Debug Mode

For troubleshooting, temporarily enable debug mode:

```bash
APP_DEBUG=1
```

**Remember to disable in production!**

## Security Considerations

### Production Security

1. **Environment Variables**: Never commit sensitive data to version control
2. **Test Files**: Block access to `/tests/` directory
3. **Database**: Use strong passwords and restricted user permissions
4. **HTTPS**: Always use SSL/TLS in production
5. **Debug Mode**: Always disabled in production (`APP_DEBUG=0`)

### File Exclusions

The `.gitignore` file excludes:
- Environment files with sensitive data
- Vendor dependencies (installed via Composer)
- Test files and temporary files

## Support

For deployment issues or questions:
1. Check the troubleshooting section above
2. Review error logs (Coolify logs or server logs)
3. Verify environment configuration
4. Test database connectivity using provided test utilities