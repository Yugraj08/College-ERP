# Hostinger Production Deployment Guide for MKR College ERP (PHP + MySQL)

This guide provides end-to-end instructions for deploying the **MKR College ERP System** on Hostinger Web Hosting (Shared, Cloud, or VPS) using LiteSpeed / Apache, PHP 8.2+, and MySQL.

---

## 1. Prerequisites in Hostinger hPanel

1. **PHP Version**:
   - Navigate to **hPanel -> Advanced -> PHP Configuration**.
   - Select **PHP 8.2** or **PHP 8.3**.
   - Ensure the following PHP extensions are checked and enabled:
     - `pdo_mysql` (Database PDO driver)
     - `mbstring` (Multi-byte string handling)
     - `json` (JSON parsing)
     - `openssl` (Cryptographic tokens & HTTPS)
     - `curl` (External API requests & payment webhooks)
     - `fileinfo` (MIME-type detection for document uploads)
     - `gd` or `imagick` (Image processing for student photos)

2. **PHP Settings Recommendations**:
   - `upload_max_filesize = 10M`
   - `post_max_size = 12M`
   - `memory_limit = 256M`
   - `max_execution_time = 60`

---

## 2. Database Creation & Migration

1. In hPanel, go to **Databases -> MySQL Databases**.
2. Create a new database:
   - **Database Name**: e.g. `u123456789_mkr_erp`
   - **Username**: e.g. `u123456789_erpuser`
   - **Password**: Generate a strong 20+ character password and save it securely.
3. Open **phpMyAdmin** for this database.
4. Go to the **Import** tab:
   - **Step 1**: Choose `database/schema.sql` and click **Go** to create all 16 InnoDB tables.
   - **Step 2**: Choose `database/seed.sql` and click **Go** to insert default academic sessions, departments, courses, and the initial Super Admin account.
5. Verify that tables are created with `utf8mb4_unicode_ci` collation.

---

## 3. Recommended Production File Structure

Hostinger serves public web requests from the `/public_html/` folder. To ensure internal application code (`src/`, `views/`, `database/`, `.env`) is physically unexposed to direct browser access, deploy using one of the following two architectures:

### Recommended Option A: Isolated Core Architecture (Highest Security)
Deploy backend core files one level above `public_html`:

```
/home/u123456789/
├── erp_core/                                  <-- PRIVATE (Outside public_html)
│   ├── src/
│   ├── views/
│   ├── database/
│   ├── vendor/
│   ├── composer.json
│   └── .env                                   <-- Real credentials here
│
└── public_html/                               <-- PUBLIC (Web Root)
    ├── index.php                              <-- Points to ../erp_core/public/index.php
    ├── .htaccess
    ├── css/
    ├── js/
    └── assets/
```

### Option B: Document Root Mapping (Standard hPanel Setup)
If uploading the repository directly into `public_html`:
1. Upload the entire project to `/home/u123456789/public_html/`.
2. In hPanel, go to **Websites -> Manage -> Domain -> Document Root** and set the Document Root to `/public_html/public`.
3. If your hosting tier does not allow changing Document Root, the provided root `.htaccess` will automatically rewrite all requests into `public/` while strictly blocking direct access to `src/`, `views/`, `database/`, and `.env`.

---

## 4. Production Environment Configuration (`.env`)

Create a `.env` file in the project core root (next to `composer.json`):

```env
# Production Environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mkrcollege.com
APP_SECRET=replace_with_a_secure_random_64_character_hex_string

# Hostinger MySQL Credentials
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u123456789_mkr_erp
DB_USER=u123456789_erpuser
DB_PASS=YourStrongHostingerDbPasswordHere
DB_CHARSET=utf8mb4

# Institutional Identity
COLLEGE_NAME="Matashri Kaushalya Ramdev Dr. Ganesh Ray Degree College"
COLLEGE_SHORT_NAME="MKR College"
COLLEGE_AFFILIATION="B.R.A. Bihar University (BRABU), Muzaffarpur"
COLLEGE_LOCATION="Punaura Dham, Sitamarhi, Bihar"
COLLEGE_HELPLINE="+91-7597052444"
COLLEGE_EMAIL="mkrcollege@gmail.com"

# Session Security
SESSION_LIFETIME=86400
SESSION_SECURE=true
SESSION_HTTPONLY=true
SESSION_SAMESITE=Lax
```

---

## 5. Web Server Configuration (`.htaccess`)

Ensure `public/.htaccess` is present in the public web root:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Prevent directory browsing
    Options -Indexes

    # Route all requests to index.php if not an existing file/directory
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L,QSA]
</IfModule>

# Forbid access to sensitive file extensions
<FilesMatch "(^\.env|\.sql|\.json|\.lock|\.xml|\.md)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# LiteSpeed & Apache Asset Caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/webp "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>
```

---

## 6. Default Administrative Credentials

After importing `database/seed.sql`, use the following initial credentials to log in:

- **Portal URL**: `https://yourdomain.com/login`
- **Role Tab**: Select **Super Admin**
- **Email**: `mkrcollege@gmail.com`
- **Default Password**: `Mkr@arvind98`

> [!IMPORTANT]
> Immediately upon initial sign-in, navigate to the Administrator Settings panel and change the default password.

---

## 7. Post-Deployment Verification Checklist

1. **SSL Certificate**: Ensure Hostinger Free SSL is active (HTTPS redirects automatically).
2. **Homepage**: Visit `https://yourdomain.com/` and confirm college emblem, notices ticker, and portal action cards render correctly.
3. **Login Form**: Verify split-screen login page, 7-second image cycle, and tab switcher.
4. **Admission Form**: Submit a test application to confirm CBCS subject dropdown cascading and MySQL record insertion.
5. **Database Integrity**: Verify that `users`, `students`, and `audit_logs` receive corresponding records.
