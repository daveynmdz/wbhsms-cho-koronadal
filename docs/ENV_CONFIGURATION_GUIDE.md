# Environment Configuration Setup

## Dual Environment Support

This project supports both **local development** and **production deployment** with different database configurations:

### Files Structure:
- **`.env`** → Production settings (committed to repository)
- **`.env.local`** → Local development settings (ignored by git)

### Database Configuration:

#### Production (`.env`):
```env
DB_HOST=agcw0oc048kwgss0co0c8kcs
DB_USERNAME=mysql
DB_PASSWORD=kVZrJ1rdWCg6hM70rFHT950tx2BZmcYgkh0zsKBVw6mKaiRxYuO0C9ZZDEewtwMM
```

#### Local Development (`.env.local`):
```env
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=
```

### How It Works:

1. **Environment Loading Order**:
   - First loads `.env` (production settings)
   - Then loads `.env.local` (overrides for local development)

2. **Deployment**:
   - Production: Uses `.env` settings (cloud database)
   - Local: Uses `.env.local` settings (XAMPP localhost)

3. **Git Handling**:
   - `.env` is committed (production settings)
   - `.env.local` is ignored by git (local-only)

### For Developers:

#### Setup Local Environment:
1. Ensure XAMPP is running
2. Database `wbhsms_database` exists in MySQL
3. `.env.local` file exists with localhost settings
4. Access via `http://localhost:8080/wbhsms-cho-koronadal-1/`

#### Before Deployment:
- Only commit changes to `.env` if production settings need updates
- Never commit `.env.local` (it's gitignored)
- Production will automatically use `.env` settings

This setup ensures seamless development locally while maintaining production compatibility.