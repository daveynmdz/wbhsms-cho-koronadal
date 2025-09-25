# Tests

This folder contains test files and utilities for the Koronadal Health Management System.

## Test Files

- `testdb.php` - Database connection testing utility
- `test_paths.php` - Path resolution testing script
- `test_db_connection.php` - Production database connection test (if exists)

## Running Tests

### Database Connection Test
```bash
# For local testing
php tests/testdb.php

# For path resolution testing  
php tests/test_paths.php
```

### Web-based Tests
Access via browser:
- Local: `http://localhost/wbhsms-cho-koronadal/tests/testdb.php`
- Production: `https://your-domain.com/tests/testdb.php`

## Security Note

⚠️ **Important**: Test files should not be accessible in production environments. Make sure your web server configuration blocks access to the `/tests/` directory in production.