# Upload Path Configuration - Quick Reference Card

## For Your Development Environment

Choose the configuration that matches your setup:

### Windows Users

**If using XAMPP:**
```env
UPLOADS_BASE_DIR=C:/xampp/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

**If using WAMP:**
```env
UPLOADS_BASE_DIR=C:/wamp64/www/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

**If using MAMP:**
```env
UPLOADS_BASE_DIR=C:/MAMP/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

---

### macOS Users

**If using XAMPP:**
```env
UPLOADS_BASE_DIR=/Applications/XAMPP/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

**If using MAMP:**
```env
UPLOADS_BASE_DIR=/Applications/MAMP/htdocs/agrilink/uploads
UPLOADS_BASE_URL=http://localhost:8888/agrilink/uploads
```

---

### Linux Users

**If using XAMPP:**
```env
UPLOADS_BASE_DIR=/opt/lampp/htdocs/agrilink/uploads
UPLOADS_BASE_URL=/agrilink/uploads
```

---

## Setup Instructions (5 Steps)

### Step 1: Create `.env.local`
```bash
touch .env.local
```

### Step 2: Add Your Configuration
Copy your environment's configuration from above and paste it into `.env.local`

### Step 3: Create Upload Directory
```bash
mkdir -p <UPLOADS_BASE_DIR>
```

### Step 4: Set Permissions (Unix/Linux/macOS)
```bash
chmod 755 <UPLOADS_BASE_DIR>
```

### Step 5: Clear Cache
```bash
php bin/console cache:clear
```

---

## Automated Setup (Recommended)

### macOS/Linux:
```bash
bash docs/upload-setup.sh
```

### Windows:
```cmd
docs/upload-setup.bat
```

---

## Troubleshooting Checklist

- [ ] `.env.local` file exists in project root
- [ ] `UPLOADS_BASE_DIR` path is correct for your setup
- [ ] Directory exists: `mkdir -p <UPLOADS_BASE_DIR>`
- [ ] Directory is writable (chmod 755 on Unix/Linux/macOS)
- [ ] Cache cleared: `php bin/console cache:clear`
- [ ] Virtual host points to `public/` folder
- [ ] Can login to application
- [ ] Can upload an image
- [ ] Image displays on page

---

## Need Help?

- **Quick start:** See `TEAM_SETUP.md`
- **Detailed guide:** See `UPLOAD_SETUP.md`
- **All configs:** See `.env.local.exemple`
- **Auto setup:** Run `docs/upload-setup.sh` (Unix/Linux/macOS) or `docs/upload-setup.bat` (Windows)

---

**Remember:** Never commit `.env.local` to git!
