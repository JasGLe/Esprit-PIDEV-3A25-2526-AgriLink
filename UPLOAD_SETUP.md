# Upload Configuration Guide

This guide helps all team members configure image uploads for their local development environment (MAMP, WAMP, XAMPP).

## Overview

The application uses environment variables to configure upload paths. Each developer needs to set these variables in their `.env.local` file based on their local setup.

## Configuration Steps

### 1. Create `.env.local` file

If you don't have a `.env.local` file in the project root, create one:

```bash
touch .env.local
```

### 2. Add Upload Configuration

Copy the configuration for your environment from `.env.local.exemple` and paste it into your `.env.local` file.

---

## Environment-Specific Configurations

### XAMPP on Windows

**Configuration for `.env.local`:**

```env
UPLOADS_BASE_DIR=C:/xampp/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

**Steps:**
1. Ensure the upload directory exists: `C:/xampp/htdocs/agrilink/uploads`
2. Create it if it doesn't exist
3. Make sure the directory is writable by the web server

**Verify:**
- Virtual host should point to: `C:/xampp/htdocs/agrilink/public`
- URL should be: `http://localhost/agrilink/`

---

### XAMPP on macOS

**Configuration for `.env.local`:**

```env
UPLOADS_BASE_DIR=/Applications/XAMPP/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

**Steps:**
1. Ensure the upload directory exists: `/Applications/XAMPP/htdocs/agrilink/uploads`
2. Create it if it doesn't exist
3. Make sure the directory is writable by the web server

**Verify:**
- Virtual host should point to: `/Applications/XAMPP/htdocs/agrilink/public`
- URL should be: `http://localhost/agrilink/`

---

### XAMPP on Linux

**Configuration for `.env.local`:**

```env
UPLOADS_BASE_DIR=/opt/lampp/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

**Steps:**
1. Ensure the upload directory exists: `/opt/lampp/htdocs/agrilink/uploads`
2. Create it if it doesn't exist
3. Make sure the directory is writable by the web server

**Verify:**
- Virtual host should point to: `/opt/lampp/htdocs/agrilink/public`
- URL should be: `http://localhost/agrilink/`

---

### WAMP on Windows

**Configuration for `.env.local`:**

```env
UPLOADS_BASE_DIR=C:/wamp64/www/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

**Steps:**
1. Ensure the upload directory exists: `C:/wamp64/www/agrilink/uploads`
2. Create it if it doesn't exist
3. Make sure the directory is writable by the web server

**Verify:**
- Virtual host should point to: `C:/wamp64/www/agrilink/public`
- URL should be: `http://localhost/agrilink/`

---

### MAMP on macOS

**Configuration for `.env.local`:**

```env
UPLOADS_BASE_DIR=/Applications/MAMP/htdocs/agrilink/uploads
UPLOADS_BASE_URL=http://localhost:8888/agrilink/uploads
```

**Steps:**
1. Ensure the upload directory exists: `/Applications/MAMP/htdocs/agrilink/uploads`
2. Create it if it doesn't exist
3. Make sure the directory is writable by the web server

**Verify:**
- Virtual host should point to: `/Applications/MAMP/htdocs/agrilink/public`
- MAMP usually runs on port 8888 by default
- URL should be: `http://localhost:8888/agrilink/`

---

### MAMP on Windows

**Configuration for `.env.local`:**

```env
UPLOADS_BASE_DIR=C:/MAMP/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

**Steps:**
1. Ensure the upload directory exists: `C:/MAMP/htdocs/agrilink/uploads`
2. Create it if it doesn't exist
3. Make sure the directory is writable by the web server

**Verify:**
- Virtual host should point to: `C:/MAMP/htdocs/agrilink/public`
- URL should be: `http://localhost/agrilink/`

---

## Common Issues

### Issue: "Permission denied" when uploading

**Solution:**
Make sure the uploads directory is writable by the web server user.

**For Unix/Linux/macOS:**
```bash
chmod 755 /path/to/agrilink/uploads
chmod 755 /path/to/agrilink/uploads/*
```

**For Windows:**
Right-click the folder → Properties → Security → Edit permissions

---

### Issue: Uploads directory not found

**Solution:**
Create the directory manually if it doesn't exist:

**Unix/Linux/macOS:**
```bash
mkdir -p /path/to/agrilink/uploads
```

**Windows:**
Use File Explorer to create the folder or use:
```cmd
mkdir C:\path\to\agrilink\uploads
```

---

### Issue: Images not displaying after upload

**Solution:**
Check that:
1. The `UPLOADS_BASE_URL` is correctly set for your environment
2. The web server can serve files from the uploads directory
3. The virtual host is correctly configured

---

## Cache Clear (Important!)

After updating `.env.local`, clear the Symfony cache:

```bash
php bin/console cache:clear
```

---

## Virtual Host Configuration Example

### Apache (XAMPP/WAMP/MAMP)

Add this to your virtual hosts configuration (usually in `httpd-vhosts.conf`):

```apache
<VirtualHost *:80>
    ServerName localhost
    ServerAlias agrilink.local
    DocumentRoot "C:/xampp/htdocs/agrilink/public"
    
    <Directory "C:/xampp/htdocs/agrilink/public">
        AllowOverride All
        Require all granted
    </Directory>
    
    # Allow uploads directory
    <Directory "C:/xampp/htdocs/agrilink/uploads">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Note:** Adjust the paths and port number according to your setup.

---

## Testing Uploads

1. Login to the application
2. Navigate to the upload feature (e.g., marketplace, announcements, etc.)
3. Upload an image
4. Verify the image appears and is accessible

---

## Additional Notes

- Each developer should have their own `.env.local` file (not committed to git)
- Never commit sensitive information to `.env`
- The `.env` file contains default values; `.env.local` overrides them
- For production, use proper `.env.prod` or secrets management

---

## Support

If you encounter issues:
1. Check that your environment variables are correctly set in `.env.local`
2. Clear the Symfony cache: `php bin/console cache:clear`
3. Ensure the uploads directory exists and is writable
4. Verify your virtual host configuration
5. Check the application logs: `tail -f var/log/dev.log`

---

**Last Updated:** April 7, 2026
