# Module 1: User Management & Profiles

## Symfony Web Application Development Requirements

### 📋 Executive Summary

This document outlines the complete feature set of **Module 1: User Management and Profiles** from the AgriLink JavaFX desktop application, serving as a comprehensive specification for developing an equivalent Symfony web application.

**Module Purpose:** Complete user authentication, authorization, profile management, and security system supporting multiple user roles and advanced security features.

---

## 🏗️ Architecture Overview

### Technology Stack (JavaFX Desktop)

- **Backend:** Java 17 + JDBC
- **Database:** MySQL 5.7/8.0
- **UI Framework:** JavaFX 17
- **Security:** BCrypt password hashing, JWT tokens
- **Email:** Jakarta Mail API
- **OAuth:** Google OAuth 2.0
- **Face Recognition:** face-api.js (JavaScript library)
- **SMS:** Twilio API
- **Build:** Maven

### Recommended Symfony Stack

- **Framework:** Symfony 6.4/7.x
- **Database:** Doctrine ORM + MySQL
- **Security:** Symfony Security Bundle + JWT (LexikJWTAuthenticationBundle)
- **Forms:** Symfony Forms
- **Validation:** Symfony Validator
- **Email:** Symfony Mailer
- **OAuth:** KnpUOAuth2ClientBundle
- **Password:** Symfony PasswordHasher (BCrypt/Argon2)
- **SMS:** Twilio SDK

---

## 👤 User Entity Model

### Core User Attributes

```php
// Entity: User
class User
{
    // Common fields (all users)
    private int $id;
    private string $nom;                    // Full name
    private ?\DateTime $dateNaissance;      // Date of birth
    private string $email;                  // Email (unique)
    private ?string $telephone;             // Phone number
    private string $password;               // BCrypt/Argon2 hashed
    private ?string $photoProfil;           // Avatar path/URL
    private ?string $gouvernant;            // Governorate (province)
    private ?string $ville;                 // City
    private ?string $codePostale;           // Postal code

    // Role & Status
    private string $role;                   // Enum: ADMIN, AGRICULTEUR, AGRIPLUS, FOURNISSEUR, USER
    private bool $isActive = true;          // Account active status
    private bool $emailVerified = false;    // Email verification status

    // Timestamps
    private \DateTime $createdAt;
    private \DateTime $updatedAt;
}
```

### Role-Specific Attributes

#### 1. ADMIN Role

```php
private ?string $adminNiveau;  // Enum: SUPER_ADMIN, ADMIN, MODERATOR
```

#### 2. AGRICULTEUR (Farmer) Role

```php
private ?int $agriculteurExpAnnee;  // Years of farming experience (0-60)
```

#### 3. AGRIPLUS (Premium Subscription) Role

```php
private ?string $agriplusAbonnement;        // Enum: BASIC, PREMIUM, ENTERPRISE
private ?\DateTime $agriplusDateExpiration; // Subscription expiration date
```

#### 4. FOURNISSEUR (Supplier) Role

**Two Sub-types:**

**Type 1: PERSONNE (Individual Supplier)**

```php
private ?string $fournisseurTypeFournisseur = 'PERSONNE';
private ?string $fournisseurCertifications;  // JSON array of certifications
private ?string $fournisseurCin;             // National ID number (Tunisia CIN)
```

**Type 2: SOCIETE (Company Supplier)**

```php
private ?string $fournisseurTypeFournisseur = 'SOCIETE';
private ?string $fournisseurCertifications;    // JSON array of certifications
private ?string $fournisseurRaisonSocial;      // Business name
private ?string $fournisseurNumRegistre;       // Registration number
private ?string $fournisseurFormeJuridique;    // Legal form (SARL, SA, etc.)
private ?float $fournisseurCapital;            // Company capital
```

### Authentication & Security Attributes

```php
// OAuth Integration
private ?string $oauthProvider;      // 'google' or null for local accounts
private ?string $oauthProviderId;    // OAuth subject ID from provider
private bool $passwordMigrated;      // Password migration flag

// Security & Rate Limiting
private int $failedLoginAttempts = 0;
private ?\DateTime $lockedUntil;     // Account lockout timestamp
private ?\DateTime $lastLogin;       // Last successful login

// Two-Factor Authentication
private bool $twoFactorEnabled = false;          // Email-based 2FA
private bool $intrusionCaptureEnabled = false;   // Photo capture on failed login

// Phone Verification
private bool $phoneVerified = false;
private ?string $otpCode;                        // Current OTP code
private ?\DateTime $otpExpiration;               // OTP expiry timestamp
private int $otpAttempts = 0;                    // Failed OTP attempts

// Face Recognition (Optional)
private ?string $faceDescriptor;     // JSON array of 128 floats from face-api.js
private ?\DateTime $faceEnrolledAt;  // Face enrollment timestamp
```

### Helper Methods (Business Logic)

```php
public function isAdmin(): bool;
public function isAgriculteur(): bool;
public function isAgriPlus(): bool;
public function isFournisseur(): bool;
public function isFournisseurPersonne(): bool;
public function isFournisseurSociete(): bool;
public function isSuperAdmin(): bool;
public function getRoleDescription(): string;     // Human-readable role
public function getNomComplet(): string;          // Full name or business name
public function isOAuthAccount(): bool;
public function isLockedOut(): bool;
public function hasFaceEnrolled(): bool;
```

---

## 🔐 Authentication Features

### 1. Local Authentication (Email + Password)

#### Login Flow

**Endpoint:** `POST /api/auth/login`

**Request:**

```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "rememberMe": true
}
```

**Response (Success):**

```json
{
  "status": "success",
  "user": {
    "id": 1,
    "nom": "John Doe",
    "email": "user@example.com",
    "role": "AGRICULTEUR",
    "photoProfil": "/uploads/profiles/user_1.jpg"
  },
  "accessToken": "eyJhbGciOiJIUzI1NiIs...",
  "refreshToken": "eyJhbGciOiJIUzI1NiIs..."
}
```

**Response (2FA Required):**

```json
{
  "status": "2fa_required",
  "userId": 1,
  "message": "Code de vérification envoyé à votre email"
}
```

**Response (Error - Account Locked):**

```json
{
  "status": "error",
  "errorType": "ACCOUNT_LOCKED",
  "message": "Compte verrouillé. Réessayez plus tard."
}
```

#### Authentication Logic

1. **Validate Email:** Find user by email
2. **Check Account Status:**
   - Account active (`isActive`)
   - Not locked out (`lockedUntil` < now)
   - Email verified (`emailVerified`)
3. **Verify Password:**
   - Use Symfony PasswordHasher
   - Support transparent BCrypt migration if needed
4. **Handle Failed Attempts:**
   - Increment `failedLoginAttempts`
   - Send warning email at 3 attempts
   - Apply 3-second delay after 3 attempts (anti-brute-force)
   - Lock account after 5 attempts (configurable)
   - Send intrusion alert email on lock
5. **Generate Tokens:**
   - Access token (JWT, 15-minute expiry)
   - Refresh token (JWT, 7-day expiry)
6. **Store Refresh Token:**
   - Save hashed refresh token in `UserSession` table
   - Track device/IP for security audit
7. **Check 2FA:**
   - If `twoFactorEnabled`, generate OTP and return 2FA flow
8. **Update Last Login:** Set `lastLogin` timestamp

#### Password Hashing

- **Algorithm:** BCrypt (cost 12) or Argon2id
- **Transparent Migration:** Support migrating legacy plain-text passwords on first login
- **Password Reset:** Secure token-based reset via email

### 2. OAuth Authentication (Google)

#### OAuth Login Flow

**Endpoint:** `GET /auth/google`

1. Redirect to Google authorization URL
2. User authorizes on Google
3. Google redirects to: `GET /auth/google/callback?code=...`
4. Exchange authorization code for tokens
5. Retrieve user info from Google
6. **Account Lookup Logic:**
   - **Case 1:** OAuth account exists → Login
   - **Case 2:** Local account exists with same email → Link OAuth and login
   - **Case 3:** New user → Show role selection screen
7. Generate JWT tokens and complete login

**Google OAuth Configuration:**

```yaml
# config/packages/knpu_oauth2_client.yaml
knpu_oauth2_client:
  clients:
    google:
      type: google
      client_id: "%env(GOOGLE_CLIENT_ID)%"
      client_secret: "%env(GOOGLE_CLIENT_SECRET)%"
      redirect_route: auth_google_callback
      redirect_params: {}
```

**Scopes Required:**

- `openid`
- `email`
- `profile`

**OAuth User Info Retrieved:**

- Provider ID (Google subject ID)
- Email
- Full name
- Profile picture URL

#### OAuth Role Selection

For new OAuth users, present role selection:

- AGRICULTEUR
- FOURNISSEUR (then choose PERSONNE or SOCIETE)
- AGRIPLUS
- USER (default)

Complete registration with selected role and OAuth data.

### 3. Two-Factor Authentication (2FA)

#### 2FA Step-Up Flow

**Triggered After:** Successful password authentication if `twoFactorEnabled = true`

1. **Generate OTP:** 6-digit random code
2. **Send Email:** Use Symfony Mailer to send code
3. **Store OTP:** In-memory or cache (Redis) with 10-minute expiry
4. **Return 2FA Required Response**

**Endpoint:** `POST /api/auth/2fa/verify`

**Request:**

```json
{
  "userId": 1,
  "code": "123456",
  "rememberMe": true
}
```

**Validation:**

- Check OTP not expired
- Max 3 attempts
- On success, generate tokens and complete login
- On failure, decrement remaining attempts

**Endpoint:** `POST /api/auth/2fa/resend`

**Cooldown:** 60 seconds between resend requests

### 4. Face Recognition Authentication (Optional)

#### Technology: face-api.js

**JavaScript Library:** https://github.com/justadudewhohacks/face-api.js

**Models Required:**

- `tiny_face_detector`
- `face_landmark_68`
- `face_recognition`

#### Face Enrollment

**Endpoint:** `POST /api/auth/face/enroll`

1. Capture face via webcam (JavaScript)
2. Extract 128-dimensional face descriptor using face-api.js
3. Send descriptor array to backend
4. Store as JSON in `faceDescriptor` field
5. Set `faceEnrolledAt` timestamp

**Request:**

```json
{
  "userId": 1,
  "descriptor": [0.123, -0.456, 0.789, ...]  // 128 floats
}
```

#### Face Login

**Endpoint:** `POST /api/auth/face/login`

1. Capture face via webcam
2. Extract face descriptor
3. Send to backend with email
4. Backend compares with stored descriptor using Euclidean distance
5. Threshold: < 0.6 = match
6. Complete login if match

### 5. Phone Verification (SMS)

#### SMS Provider: Twilio

**Endpoint:** `POST /api/auth/phone/send-otp`

**Request:**

```json
{
  "userId": 1,
  "telephone": "+21612345678"
}
```

**Process:**

1. Generate 6-digit OTP
2. Send SMS via Twilio
3. Store OTP with 10-minute expiry
4. Max 5 resend attempts per hour

**Endpoint:** `POST /api/auth/phone/verify-otp`

**Request:**

```json
{
  "userId": 1,
  "code": "123456"
}
```

**On Success:** Set `phoneVerified = true`

### 6. Session Management

#### JWT Token System

**Access Token:**

- Type: JWT
- Expiry: 15 minutes
- Contains: userId, email, role, permissions
- Used for API requests

**Refresh Token:**

- Type: JWT
- Expiry: 7 days (configurable)
- Stored hashed in `UserSession` table
- Used to obtain new access token

**Endpoint:** `POST /api/auth/refresh`

**Request:**

```json
{
  "refreshToken": "eyJhbGciOiJIUzI1NiIs..."
}
```

**Response:**

```json
{
  "accessToken": "eyJhbGciOiJIUzI1NiIs...",
  "refreshToken": "eyJhbGciOiJIUzI1NiIs..." // Same or new
}
```

**UserSession Table Schema:**

```sql
CREATE TABLE UserSession (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    refresh_token_hash VARCHAR(255) NOT NULL,
    device_info VARCHAR(255),
    ip_address VARCHAR(45),
    expires_at DATETIME NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
    INDEX idx_token_hash (refresh_token_hash),
    INDEX idx_expiry (expires_at)
);
```

#### Session Features

1. **Auto-Login:** Check stored tokens on page load
2. **Token Refresh:** Automatically refresh access token before expiry
3. **Remember Me:** Extend refresh token expiry to 30 days
4. **Revoke All Sessions:** Invalidate all refresh tokens for user
5. **Activity Tracking:** Update last activity timestamp
6. **Inactivity Timeout:** Auto-logout after 30 minutes (configurable)

### 7. Logout

**Endpoint:** `POST /api/auth/logout`

**Actions:**

1. Revoke current refresh token in database
2. Clear client-side tokens
3. Log security event
4. Redirect to login page

---

## 🔒 Security Features

### 1. Account Lockout & Rate Limiting

**Configuration (auth.yaml):**

```yaml
security:
  authentication:
    max_login_attempts: 5
    lockout_duration_minutes: 15
    warning_threshold: 3
    brute_force_delay_seconds: 3
```

**Lockout Logic:**

1. Track failed login attempts in `User.failedLoginAttempts`
2. Send warning email at attempt 3
3. Apply 3-second delay after attempt 3
4. Lock account for 15 minutes after attempt 5
5. Send intrusion alert email on lock
6. Optional: Capture photo via webcam if `intrusionCaptureEnabled`

### 2. Email Verification

**Registration Flow:**

1. User registers with email
2. Generate 6-digit verification code
3. Send email with code
4. Store code with 24-hour expiry
5. User enters code to verify

**Database Table:**

```sql
CREATE TABLE EmailVerification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
);
```

**Endpoints:**

- `POST /api/auth/verify-email` - Verify code
- `POST /api/auth/resend-verification` - Resend code

### 3. Password Reset

**Flow:**

1. User requests password reset
2. Generate secure token (32 bytes, hex)
3. Send email with reset link
4. Token expires after 1 hour
5. User clicks link, enters new password
6. Validate token and update password
7. Invalidate all existing sessions

**Endpoints:**

- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`

### 4. Security Event Logging

**SecurityEvent Table Schema:**

```sql
CREATE TABLE SecurityEvent (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    event_type VARCHAR(50) NOT NULL,  -- LOGIN_SUCCESS, LOGIN_FAILED, 2FA_FAILED, etc.
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE SET NULL,
    INDEX idx_user_event (user_id, event_type),
    INDEX idx_created (created_at)
);
```

**Event Types:**

- `LOGIN_SUCCESS`
- `LOGIN_FAILED`
- `ACCOUNT_LOCKED`
- `SUSPICIOUS_ACTIVITY`
- `2FA_OTP_SENT`
- `2FA_SUCCESS`
- `2FA_FAILED`
- `PASSWORD_CHANGED`
- `EMAIL_VERIFIED`
- `OAUTH_LOGIN`
- `OAUTH_REGISTER`
- `SESSION_REVOKED`
- `LOGOUT`

### 5. Intrusion Detection

**Optional Feature:** Capture webcam photo on failed login

**Implementation:**

1. User enables `intrusionCaptureEnabled` in profile
2. On failed login attempt, trigger webcam capture (JavaScript)
3. Upload photo to server
4. Include photo URL in intrusion alert email
5. Store photo reference in security log

---

## 📝 User Registration

### Registration Types

#### 1. Agriculteur (Farmer) Registration

**Endpoint:** `POST /api/auth/register/agriculteur`

**Form Fields:**

- Nom (Full name) - Required
- Email - Required, unique
- Password - Required (see validation rules)
- Confirm Password - Required
- Téléphone - Optional
- Date de Naissance - Optional
- Gouvernant (Governorate) - Dropdown
- Ville - Text
- Code Postale - Text
- Années d'expérience - Spinner (0-60)

**Validation:**

- Email format
- Email uniqueness
- Password strength
- Age >= 18 years (if birthdate provided)

#### 2. Fournisseur (Supplier) Registration

**Endpoint:** `POST /api/auth/register/fournisseur`

**Step 1: Choose Type**

- Personne (Individual)
- Société (Company)

**Step 2: Common Fields**

- Nom (or Raison Sociale for company)
- Email
- Password
- Téléphone
- Gouvernant, Ville, Code Postale

**Step 3: Type-Specific Fields**

**Personne:**

- CIN (National ID) - Required, unique, 8 digits

**Société:**

- Raison Sociale (Business Name) - Required
- Numéro de Registre (Registration Number) - Required, unique
- Forme Juridique (Legal Form) - Dropdown: SARL, SA, SUARL, SNC
- Capital - Number

**Step 4: Certifications (Optional)**

- Add multiple certifications
- Each certification has:
  - Nom (Certification name)
  - Organisme (Issuing organization)
  - Date d'obtention
  - Date d'expiration (optional)
  - Description (optional)
  - File upload (PDF, JPG, PNG)

#### 3. AgriPlus (Premium) Registration

**Endpoint:** `POST /api/auth/register/agriplus`

**Form Fields:**

- Common registration fields
- Subscription plan selection:
  - BASIC - €9.99/month
  - PREMIUM - €19.99/month
  - ENTERPRISE - €49.99/month
- Payment integration (Stripe/PayPal)

**On Success:**

- Set `agriplusAbonnement`
- Set `agriplusDateExpiration` (1 month from now)
- Send welcome email with subscription details

### Post-Registration Flow

1. Insert user record with `emailVerified = false`
2. Generate email verification code
3. Send verification email
4. Redirect to email verification page
5. Optionally: Send SMS verification if phone provided

---

## 👥 User CRUD Operations

### 1. List Users (Admin Only)

**Endpoint:** `GET /api/users`

**Query Parameters:**

- `role` - Filter by role
- `active` - Filter by active status
- `search` - Search by name/email
- `page` - Pagination
- `limit` - Results per page

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "nom": "John Doe",
      "email": "john@example.com",
      "role": "AGRICULTEUR",
      "isActive": true,
      "emailVerified": true,
      "createdAt": "2024-01-15T10:30:00Z"
    }
  ],
  "total": 150,
  "page": 1,
  "limit": 20
}
```

### 2. Get User by ID

**Endpoint:** `GET /api/users/{id}`

**Response:** Full user object with role-specific fields

### 3. Create User (Admin Only)

**Endpoint:** `POST /api/users`

**Request:** User object with role and role-specific fields

### 4. Update User

**Endpoint:** `PUT /api/users/{id}`

**Allowed Fields:**

- Common fields (name, email, phone, location)
- Role-specific fields
- Admin can update any field
- Users can only update their own profile

### 5. Delete User (Admin Only)

**Endpoint:** `DELETE /api/users/{id}`

**Soft Delete:** Set `isActive = false` instead of physical deletion

### 6. Activate/Suspend User

**Endpoints:**

- `POST /api/users/{id}/activate`
- `POST /api/users/{id}/suspend`

### 7. User Statistics

**Endpoint:** `GET /api/users/stats`

**Response:**

```json
{
  "total": 1500,
  "active": 1350,
  "byRole": {
    "AGRICULTEUR": 800,
    "FOURNISSEUR": 450,
    "AGRIPLUS": 200,
    "ADMIN": 5,
    "USER": 45
  },
  "newThisMonth": 120,
  "verifiedEmail": 1400
}
```

---

## 🎨 Profile Management

### 1. View Profile

**Endpoint:** `GET /api/profile`

**Response:** Current user's full profile

### 2. Update Profile

**Endpoint:** `PUT /api/profile`

**Form Sections:**

#### Personal Information

- Nom
- Date de Naissance
- Téléphone

#### Location

- Gouvernant (Dropdown: 24 Tunisia governorates)
- Ville
- Code Postale

#### Email Change

- New Email
- Requires re-verification

#### Role-Specific Fields

**Agriculteur:**

- Années d'expérience (Spinner: 0-60)

**Fournisseur:**

- Certifications management (CRUD)
- If Personne: CIN (read-only after registration)
- If Société: Business info (partially editable)

**AgriPlus:**

- Subscription info (read-only)
- Upgrade/downgrade subscription
- Renewal settings

### 3. Change Password

**Endpoint:** `POST /api/profile/change-password`

**Request:**

```json
{
  "currentPassword": "OldPass123!",
  "newPassword": "NewPass456!",
  "confirmPassword": "NewPass456!"
}
```

**Validation:**

- Current password must be correct
- New password must meet requirements
- Passwords must match

**On Success:**

- Update password hash
- Revoke all sessions except current
- Send email notification

### 4. Upload Avatar

**Endpoint:** `POST /api/profile/avatar`

**Request:** Multipart form with image file

**Validation:**

- File types: PNG, JPG, JPEG, GIF, WEBP
- Max size: 5 MB
- Recommended dimensions: 400x400px

**Process:**

1. Validate file
2. Generate unique filename: `user_{id}_{timestamp}.{ext}`
3. Resize to 400x400 (maintain aspect ratio)
4. Save to: `public/uploads/profiles/`
5. Update `photoProfil` field
6. Delete old avatar file

**Response:**

```json
{
  "photoUrl": "/uploads/profiles/user_1_1234567890.jpg"
}
```

### 5. Delete Avatar

**Endpoint:** `DELETE /api/profile/avatar`

**Actions:**

1. Delete file from storage
2. Set `photoProfil = null`

### 6. Security Settings

**Endpoint:** `PUT /api/profile/security`

**Settings:**

```json
{
  "twoFactorEnabled": true,
  "intrusionCaptureEnabled": true
}
```

### 7. Manage Certifications (Fournisseur Only)

**Endpoints:**

- `GET /api/profile/certifications` - List all
- `POST /api/profile/certifications` - Add new
- `PUT /api/profile/certifications/{id}` - Update
- `DELETE /api/profile/certifications/{id}` - Remove

**Certification Object:**

```json
{
  "id": "uuid-v4",
  "nom": "ISO 9001",
  "organisme": "ISO",
  "dateObtention": "2023-01-15",
  "dateExpiration": "2026-01-15",
  "description": "Quality management certification",
  "fichierPath": "/uploads/certifications/cert_12345.pdf"
}
```

**File Upload:**

- Accepted formats: PDF, JPG, PNG
- Max size: 10 MB
- Storage: `public/uploads/certifications/`

---

## ✅ Validation Rules

### Email Validation

- Format: RFC 5322 compliant
- Uniqueness: Check against existing users
- No disposable email providers

### Password Requirements

```yaml
password:
  min_length: 8
  max_length: 128
  require_uppercase: true
  require_lowercase: true
  require_digit: true
  require_special_char: true
  special_chars: "!@#$%^&*()_+-=[]{}|;:,.<>?"
  forbidden_patterns:
    - "password"
    - "12345"
    - "qwerty"
```

**Password Strength Meter:**

- Weak (< 40 points): Red
- Medium (40-70 points): Orange
- Strong (> 70 points): Green

**Scoring:**

- Length: +4 per character
- Uppercase: +10
- Lowercase: +10
- Digits: +10
- Special characters: +15
- Mixed case: +5
- No common patterns: +10

### Phone Validation

- Format: International (E.164)
- Example: `+21612345678` (Tunisia)
- Regex: `^\+?[1-9]\d{1,14}$`

### CIN Validation (Tunisia National ID)

- Length: 8 digits
- Uniqueness: Check against existing fournisseurs

### Date of Birth Validation

- Minimum age: 18 years
- Maximum age: 120 years

### Postal Code Validation

- Format: 4 digits (Tunisia)
- Range: 1000-9999

---

## 🗄️ Database Schema

### User Table (Primary)

```sql
CREATE TABLE User (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT,

    -- Common Fields
    nom VARCHAR(100) NOT NULL,
    date_naissance DATE,
    email VARCHAR(150) NOT NULL UNIQUE,
    telephone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    photo_profil VARCHAR(255),
    gouvernant VARCHAR(50),
    ville VARCHAR(100),
    code_postale VARCHAR(10),

    -- Role & Status
    role ENUM('ADMIN', 'AGRICULTEUR', 'AGRIPLUS', 'FOURNISSEUR', 'USER') NOT NULL DEFAULT 'USER',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    email_verified BOOLEAN NOT NULL DEFAULT FALSE,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Admin Fields
    admin_niveau ENUM('SUPER_ADMIN', 'ADMIN', 'MODERATOR'),

    -- Agriculteur Fields
    agriculteur_exp_annee INT,

    -- AgriPlus Fields
    agriplus_abonnement ENUM('BASIC', 'PREMIUM', 'ENTERPRISE'),
    agriplus_date_expiration DATE,

    -- Fournisseur Fields
    fournisseur_type_fournisseur ENUM('PERSONNE', 'SOCIETE'),
    fournisseur_certifications TEXT,  -- JSON
    fournisseur_cin VARCHAR(8),
    fournisseur_raison_social VARCHAR(200),
    fournisseur_num_registre VARCHAR(50),
    fournisseur_forme_juridique VARCHAR(50),
    fournisseur_capital DECIMAL(15,2),

    -- OAuth Fields
    oauth_provider VARCHAR(50),
    oauth_provider_id VARCHAR(255),
    password_migrated BOOLEAN DEFAULT FALSE,

    -- Security Fields
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME,
    last_login DATETIME,

    -- 2FA & Security
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    intrusion_capture_enabled BOOLEAN DEFAULT FALSE,

    -- Phone Verification
    phone_verified BOOLEAN DEFAULT FALSE,
    otp_code VARCHAR(6),
    otp_expiration DATETIME,
    otp_attempts INT DEFAULT 0,

    -- Face Recognition
    face_descriptor TEXT,  -- JSON array of 128 floats
    face_enrolled_at DATETIME,

    -- Indexes
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_oauth (oauth_provider, oauth_provider_id),
    INDEX idx_active (is_active),
    INDEX idx_fournisseur_cin (fournisseur_cin),
    INDEX idx_fournisseur_registre (fournisseur_num_registre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Supporting Tables

#### UserSession

```sql
CREATE TABLE UserSession (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    refresh_token_hash VARCHAR(255) NOT NULL,
    device_info VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at DATETIME NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_token_hash (refresh_token_hash),
    INDEX idx_expiry (expires_at, revoked)
);
```

#### EmailVerification

```sql
CREATE TABLE EmailVerification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_code (code)
);
```

#### PasswordReset

```sql
CREATE TABLE PasswordReset (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expiry (expires_at)
);
```

#### SecurityEvent

```sql
CREATE TABLE SecurityEvent (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    event_type VARCHAR(50) NOT NULL,
    event_details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE SET NULL,
    INDEX idx_user_event (user_id, event_type),
    INDEX idx_type (event_type),
    INDEX idx_created (created_at)
);
```

---

## 📱 User Interface Views

### JavaFX Views (Desktop App Reference)

Total: 20 FXML views in module1

#### Authentication Views

1. **welcome.fxml** - Landing page with login/register choice
2. **login.fxml** - Email/password login + OAuth buttons
3. **face-login.fxml** - Face recognition login
4. **verify-2fa.fxml** - Two-factor code entry
5. **verify-email.fxml** - Email verification code entry
6. **verify-sms.fxml** - SMS OTP verification
7. **forgot-password.fxml** - Password reset request

#### Registration Views

8. **register-choice.fxml** - Choose user type (Agriculteur/Fournisseur/AgriPlus)
9. **register-agriculteur.fxml** - Farmer registration form
10. **register-fournisseur.fxml** - Supplier registration form (multi-step)
11. **register-agriplus.fxml** - Premium subscription registration
12. **oauth-role-selection.fxml** - Role selection for OAuth new users

#### Dashboard Views

13. **dashboard-admin.fxml** - Admin control panel
14. **dashboard-agriculteur.fxml** - Farmer dashboard
15. **dashboard-fournisseur.fxml** - Supplier dashboard
16. **dashboard-agriplus.fxml** - Premium user dashboard

#### Profile & Settings

17. **profile.fxml** - User profile management (650+ lines)
18. **certifications-view.fxml** - Certification management (Fournisseur)
19. **face-enroll.fxml** - Face enrollment interface
20. **add-user.fxml** - Admin: Create new user

### Symfony Twig Templates Needed

#### Layouts

- `base.html.twig` - Main layout
- `auth_layout.html.twig` - Authentication pages layout
- `dashboard_layout.html.twig` - Authenticated user layout

#### Authentication

- `auth/login.html.twig`
- `auth/register_choice.html.twig`
- `auth/register_agriculteur.html.twig`
- `auth/register_fournisseur.html.twig`
- `auth/register_agriplus.html.twig`
- `auth/oauth_role_selection.html.twig`
- `auth/verify_email.html.twig`
- `auth/verify_2fa.html.twig`
- `auth/forgot_password.html.twig`
- `auth/reset_password.html.twig`

#### Dashboard

- `dashboard/admin.html.twig`
- `dashboard/agriculteur.html.twig`
- `dashboard/fournisseur.html.twig`
- `dashboard/agriplus.html.twig`

#### Profile

- `profile/view.html.twig`
- `profile/edit.html.twig`
- `profile/certifications.html.twig`
- `profile/security.html.twig`

#### Admin

- `admin/users/list.html.twig`
- `admin/users/create.html.twig`
- `admin/users/edit.html.twig`
- `admin/security_logs.html.twig`

---

## 🔌 Email Services

### Email Templates Required

#### 1. Welcome Email

**Trigger:** User registration
**Subject:** Bienvenue sur AgriLink
**Content:** Welcome message, verification code

#### 2. Email Verification

**Subject:** Vérifiez votre adresse email
**Content:** 6-digit verification code, expiry time

#### 3. Two-Factor Authentication Code

**Subject:** Votre code de vérification AgriLink
**Content:** 6-digit 2FA code, expiry time

#### 4. Password Reset

**Subject:** Réinitialisation de votre mot de passe
**Content:** Reset link with token

#### 5. Suspicious Activity Warning

**Trigger:** 3 failed login attempts
**Subject:** Activité suspecte détectée sur votre compte
**Content:** Warning, timestamp, location, instructions

#### 6. Account Locked Alert

**Trigger:** Account locked due to failed attempts
**Subject:** Votre compte a été verrouillé
**Content:** Lock reason, duration, photo (if captured), instructions

#### 7. Password Changed Confirmation

**Trigger:** Password successfully changed
**Subject:** Votre mot de passe a été modifié
**Content:** Confirmation, timestamp, reset option if not user

#### 8. OAuth Account Linked

**Trigger:** OAuth provider linked to existing account
**Subject:** Compte Google lié à votre compte AgriLink
**Content:** Confirmation, security reminder

#### 9. Session Revoked

**Trigger:** All sessions revoked
**Subject:** Toutes vos sessions ont été révoquées
**Content:** Security action confirmation

### Email Configuration

**Symfony Mailer Configuration:**

```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'

# .env
MAILER_DSN=smtp://smtp.example.com:587
MAILER_FROM_EMAIL=noreply@agrilink.tn
MAILER_FROM_NAME=AgriLink
```

---

## 🛡️ Security Configuration (Symfony)

### security.yaml

```yaml
security:
  # Password hashing
  password_hashers:
    App\Entity\User:
      algorithm: auto
      cost: 12 # BCrypt cost factor

  # User provider
  providers:
    app_user_provider:
      entity:
        class: App\Entity\User
        property: email

  # Firewalls
  firewalls:
    dev:
      pattern: ^/(_(profiler|wdt)|css|images|js)/
      security: false

    api:
      pattern: ^/api
      stateless: true
      jwt: ~

    main:
      lazy: true
      provider: app_user_provider
      form_login:
        login_path: login
        check_path: login
        enable_csrf: true
      logout:
        path: logout
        target: login
      remember_me:
        secret: "%kernel.secret%"
        lifetime: 2592000 # 30 days

  # Access control
  access_control:
    - { path: ^/api/auth/(login|register), roles: PUBLIC_ACCESS }
    - { path: ^/api/auth, roles: IS_AUTHENTICATED_FULLY }
    - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
    - { path: ^/admin, roles: ROLE_ADMIN }
    - { path: ^/profile, roles: IS_AUTHENTICATED_FULLY }

  # Role hierarchy
  role_hierarchy:
    ROLE_ADMIN: ROLE_USER
    ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]
    ROLE_AGRIPLUS: ROLE_USER
    ROLE_AGRICULTEUR: ROLE_USER
    ROLE_FOURNISSEUR: ROLE_USER
```

---

## 📊 Admin Dashboard Features

### User Management

1. **User List Table**
   - Columns: ID, Photo, Name, Email, Role, Status, Created, Actions
   - Sorting by any column
   - Filtering: Role, Status, Email verified
   - Search: Name, Email
   - Pagination: 20 users per page
   - Export: Excel, PDF

2. **User Actions (Inline)**
   - Edit (modal or redirect)
   - Activate/Suspend (toggle)
   - Delete (confirmation)
   - View details (modal)
   - Impersonate (Super Admin only)

3. **Create User**
   - Form with all fields
   - Role selection determines visible fields
   - Password auto-generation option
   - Send welcome email checkbox

4. **Bulk Actions**
   - Select multiple users
   - Bulk activate/suspend
   - Bulk delete
   - Bulk export

### Statistics Dashboard

**Widgets:**

1. Total Users
2. Active Users
3. Users by Role (pie chart)
4. New Users This Month
5. Email Verification Rate
6. Recent Registrations (table)
7. Recent Logins (table)
8. Failed Login Attempts (chart)
9. Locked Accounts (alert list)

### Security Logs

**Table:** SecurityEvent

**Columns:**

- Timestamp
- User
- Event Type
- Details
- IP Address
- User Agent

**Filters:**

- Date range
- Event type
- User
- IP address

**Export:** CSV, Excel

---

## 🚀 API Endpoints Summary

### Authentication

| Method | Endpoint                         | Description               |
| ------ | -------------------------------- | ------------------------- |
| POST   | `/api/auth/register/agriculteur` | Register farmer           |
| POST   | `/api/auth/register/fournisseur` | Register supplier         |
| POST   | `/api/auth/register/agriplus`    | Register premium user     |
| POST   | `/api/auth/login`                | Login with email/password |
| POST   | `/api/auth/logout`               | Logout current session    |
| POST   | `/api/auth/refresh`              | Refresh access token      |
| GET    | `/api/auth/google`               | Initiate Google OAuth     |
| GET    | `/api/auth/google/callback`      | OAuth callback            |
| POST   | `/api/auth/2fa/verify`           | Verify 2FA code           |
| POST   | `/api/auth/2fa/resend`           | Resend 2FA code           |
| POST   | `/api/auth/face/enroll`          | Enroll face               |
| POST   | `/api/auth/face/login`           | Login with face           |
| POST   | `/api/auth/verify-email`         | Verify email code         |
| POST   | `/api/auth/resend-verification`  | Resend verification       |
| POST   | `/api/auth/forgot-password`      | Request password reset    |
| POST   | `/api/auth/reset-password`       | Reset password with token |
| POST   | `/api/auth/phone/send-otp`       | Send phone OTP            |
| POST   | `/api/auth/phone/verify-otp`     | Verify phone OTP          |

### Profile

| Method | Endpoint                           | Description              |
| ------ | ---------------------------------- | ------------------------ |
| GET    | `/api/profile`                     | Get current user profile |
| PUT    | `/api/profile`                     | Update profile           |
| POST   | `/api/profile/avatar`              | Upload avatar            |
| DELETE | `/api/profile/avatar`              | Delete avatar            |
| POST   | `/api/profile/change-password`     | Change password          |
| PUT    | `/api/profile/security`            | Update security settings |
| GET    | `/api/profile/certifications`      | List certifications      |
| POST   | `/api/profile/certifications`      | Add certification        |
| PUT    | `/api/profile/certifications/{id}` | Update certification     |
| DELETE | `/api/profile/certifications/{id}` | Delete certification     |

### Users (Admin)

| Method | Endpoint                          | Description         |
| ------ | --------------------------------- | ------------------- |
| GET    | `/api/users`                      | List all users      |
| GET    | `/api/users/{id}`                 | Get user by ID      |
| POST   | `/api/users`                      | Create user         |
| PUT    | `/api/users/{id}`                 | Update user         |
| DELETE | `/api/users/{id}`                 | Delete user         |
| POST   | `/api/users/{id}/activate`        | Activate user       |
| POST   | `/api/users/{id}/suspend`         | Suspend user        |
| GET    | `/api/users/stats`                | User statistics     |
| POST   | `/api/users/{id}/revoke-sessions` | Revoke all sessions |

### Security Logs (Admin)

| Method | Endpoint                  | Description          |
| ------ | ------------------------- | -------------------- |
| GET    | `/api/security-logs`      | List security events |
| GET    | `/api/security-logs/{id}` | Get event details    |

---

## 🧪 Testing Requirements

### Unit Tests

- User entity validation
- Password hashing/verification
- Token generation/validation
- Email sending (mock)
- OAuth flow (mock)

### Integration Tests

- Registration flow
- Login flow
- Password reset flow
- Profile update
- User CRUD operations

### Security Tests

- SQL injection protection
- XSS protection
- CSRF protection
- Rate limiting
- JWT token security
- Password brute-force protection

### E2E Tests (Symfony Panther)

- Complete registration flow
- Login and logout
- Profile update with avatar
- Password change
- 2FA flow

---

## 📦 Dependencies (Symfony)

### Core Packages

```bash
composer require symfony/security-bundle
composer require symfony/form
composer require symfony/validator
composer require symfony/mailer
composer require doctrine/orm
composer require doctrine/doctrine-bundle
composer require doctrine/doctrine-migrations-bundle
```

### Authentication & Security

```bash
composer require lexik/jwt-authentication-bundle
composer require knpuniversity/oauth2-client-bundle
composer require symfony/password-hasher
```

### Additional Libraries

```bash
composer require twilio/sdk  # SMS verification
composer require phpoffice/phpspreadsheet  # Excel export
composer require dompdf/dompdf  # PDF export
composer require symfony/uid  # UUID generation
```

### Frontend Assets

```bash
npm install face-api.js  # Face recognition
npm install axios  # HTTP client
npm install webcam-easy  # Webcam capture
```

---

## 🎯 Implementation Priority

### Phase 1: Core Authentication (Week 1-2)

- [x] User entity and database schema
- [x] Registration (Agriculteur, Fournisseur)
- [x] Login/logout
- [x] Email verification
- [x] Password reset

### Phase 2: Advanced Auth (Week 3)

- [x] OAuth (Google) integration
- [x] Two-factor authentication
- [x] Remember me functionality
- [x] Session management

### Phase 3: Profile & Security (Week 4)

- [x] Profile management
- [x] Avatar upload
- [x] Password change
- [x] Security settings
- [x] Rate limiting & lockout

### Phase 4: Admin Features (Week 5)

- [x] Admin dashboard
- [x] User CRUD
- [x] Security logs
- [x] Statistics

### Phase 5: Advanced Features (Week 6)

- [x] Face recognition (optional)
- [x] Phone verification
- [x] Intrusion detection
- [x] Certification management

---

## 📖 Documentation & Resources

### JavaFX Desktop App Reference Files

**Key Files to Review:**

- `/src/main/java/tn/agrilink/module1/models/User.java` (468 lines)
- `/src/main/java/tn/agrilink/module1/services/AuthService.java` (439 lines)
- `/src/main/java/tn/agrilink/module1/services/UserService.java` (822 lines)
- `/src/main/java/tn/agrilink/module1/controllers/ProfileController.java` (650+ lines)
- `/src/main/java/tn/agrilink/module1/controllers/LoginController.java`
- `/src/main/java/tn/agrilink/module1/controllers/DashboardAdminController.java`

### Configuration Files

- `/src/main/resources/config.properties.example` - Database and auth config
- `/src/main/java/tn/agrilink/module1/utils/AuthConfig.java` - Authentication settings

### Database Migrations

Located in: `/src/main/java/tn/agrilink/module1/sql/`

- V001 - Initial user table
- V002 - OAuth fields
- V003 - 2FA & security
- V004 - Phone verification
- V005 - Face recognition
- V006 - UserSession table
- V007 - SecurityEvent table

---

## ✨ Key Differences Desktop vs Web

### Desktop App (JavaFX)

- **Session:** Local file storage with encryption
- **Face Auth:** WebView + face-api.js embedded
- **Notifications:** System tray notifications
- **File Storage:** Local htdocs directory
- **Navigation:** Scene switching

### Web App (Symfony)

- **Session:** HTTP cookies + JWT tokens
- **Face Auth:** Browser webcam API + face-api.js
- **Notifications:** Browser notifications + email
- **File Storage:** Server public directory
- **Navigation:** Standard HTTP routing

### Migration Considerations

1. **Token Storage:** Use secure HTTP-only cookies for refresh tokens
2. **File Uploads:** Implement proper file validation and storage
3. **Real-time Updates:** Consider WebSocket for notifications
4. **Mobile Support:** Responsive design for all views
5. **API Design:** RESTful API for potential mobile app

---

## 🎓 Learning Resources

### Symfony Documentation

- Security Component: https://symfony.com/doc/current/security.html
- Form Component: https://symfony.com/doc/current/forms.html
- Mailer: https://symfony.com/doc/current/mailer.html
- Doctrine ORM: https://www.doctrine-project.org/projects/orm.html

### Authentication Best Practices

- OWASP Authentication Cheat Sheet
- JWT Best Practices (RFC 8725)
- OAuth 2.0 Security Best Current Practice

### Face Recognition

- face-api.js: https://github.com/justadudewhohacks/face-api.js
- Face Recognition in Web Apps (MDN)

---

## 📝 Notes

1. **Security First:** All authentication endpoints must be thoroughly tested
2. **GDPR Compliance:** Implement data deletion and export features
3. **Accessibility:** Follow WCAG 2.1 guidelines for all forms
4. **Performance:** Optimize database queries with proper indexing
5. **Scalability:** Use Redis for session storage in production
6. **Monitoring:** Implement logging for all security events
7. **Documentation:** Keep API documentation up to date (use API Platform)

---

**Generated:** 2024-03-23  
**Version:** 1.0  
**Source:** AgriLink JavaFX Module 1 Analysis  
**Target:** Symfony 6.4+ Web Application
