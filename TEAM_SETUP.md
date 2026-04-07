# Team Setup Quick Start - Upload Configuration

This document provides a quick reference for setting up uploads for different local development environments.

## Supported Environments

- ✅ XAMPP (Windows, macOS, Linux)
- ✅ WAMP (Windows)
- ✅ MAMP (Windows, macOS)

## Quick Start

### Option 1: Using Automatic Setup Script

**macOS/Linux:**
```bash
bash docs/upload-setup.sh
```

**Windows (PowerShell):**
```powershell
cmd /c docs/upload-setup.bat
```

**Windows (Command Prompt):**
```cmd
docs/upload-setup.bat
```

### Option 2: Manual Configuration

1. Copy configuration from `.env.local.exemple`:
   ```bash
   # View the example file
   cat .env.local.exemple
   ```

2. Create or edit `.env.local`:
   ```bash
   touch .env.local
   ```

3. Add the configuration for your environment

4. Clear cache:
   ```bash
   php bin/console cache:clear
   ```

## Environment Configurations

### XAMPP Windows
```env
UPLOADS_BASE_DIR=C:/xampp/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

### XAMPP macOS
```env
UPLOADS_BASE_DIR=/Applications/XAMPP/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

### XAMPP Linux
```env
UPLOADS_BASE_DIR=/opt/lampp/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

### WAMP Windows
```env
UPLOADS_BASE_DIR=C:/wamp64/www/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

### MAMP macOS
```env
UPLOADS_BASE_DIR=/Applications/MAMP/htdocs/agrilink/uploads
UPLOADS_BASE_URL=http://localhost:8888/agrilink/uploads
```

### MAMP Windows
```env
UPLOADS_BASE_DIR=C:/MAMP/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

## Post-Configuration Steps

### 1. Create Upload Directory
```bash
# Unix/Linux/macOS
mkdir -p <UPLOADS_BASE_DIR>

# Windows (Command Prompt)
mkdir "<UPLOADS_BASE_DIR>"
```

### 2. Set Permissions (Unix/Linux/macOS)
```bash
chmod 755 <UPLOADS_BASE_DIR>
```

### 3. Clear Cache
```bash
php bin/console cache:clear
```

### 4. Test Upload
1. Login to the application
2. Upload an image
3. Verify it displays correctly

## Troubleshooting

**Images not uploading?**
- Check `.env.local` configuration
- Ensure directory is writable: `chmod 755 <UPLOADS_BASE_DIR>`
- Clear cache: `php bin/console cache:clear`

**Images not displaying?**
- Verify `UPLOADS_BASE_URL` is correct for your setup
- Check if virtual host is configured correctly
- Inspect browser console for 404 errors

**"Permission denied" errors?**
- Adjust directory permissions (see Step 2 above)
- On Windows, right-click folder → Properties → Security

## Full Documentation

For detailed configuration instructions, see [UPLOAD_SETUP.md](./UPLOAD_SETUP.md)

## Key Files

- **UPLOAD_SETUP.md** - Comprehensive configuration guide
- **.env.local.exemple** - Example environment variables
- **docs/upload-setup.sh** - Automated setup script (Unix/Linux/macOS)
- **docs/upload-setup.bat** - Automated setup script (Windows)

---

**Note:** Never commit `.env.local` to git. It contains local configuration specific to each developer's machine.
