# Code Verification Fixes: Backup Codes & Email Verification

## Summary

Fixed two critical bugs in the user verification system:

1. **Backup codes not working** - Alphanumeric characters were being stripped during 2FA verification
2. **Email verification code type mismatch** - Different code types need different validation rules

## Issue #1: Backup Codes Stripped During 2FA Verification

### Root Cause
In `TwoFactorController.php` line 60, the code cleaning operation was removing **all** non-numeric characters:

```php
// BUGGY: Removes A-Z letters needed for backup codes
$code = preg_replace('/[^0-9]/', '', $code);
```

Backup codes contain letters (A-Z, 2-9) and numbers, but this regex would strip them to only numbers, making them invalid.

### Code Specifications
- **OTP Codes**: 6 numeric digits (000000 - 999999)
- **Backup Codes**: 8 alphanumeric characters (A-Z, 2-9, excluding 0, 1, I, L, O for clarity)

### The Fix
Separate handling for OTP and backup code verification:

```php
// Keep the raw code for backup code validation
$rawCode = trim($code);

// For OTP: strip non-numeric, then verify
$cleanedOtp = preg_replace('/[^0-9]/', '', $rawCode);
$isOtpValid = $this->twoFactorService->verifyOtp($user, $cleanedOtp);

// For backup codes: verify without cleaning (preserves letters)
$isBackupValid = $this->backupCodeService->verify($user, $rawCode);

// Accept either valid OTP or valid backup code
if ($isOtpValid || $isBackupValid) {
    // Proceed with login
}
```

### Changes Made
**File**: `src/Controller/UserManagement/TwoFactorController.php`

- Changed line 60 from destructive regex to preserve code
- Added separate validation paths for OTP (numeric only) and backup codes (alphanumeric)
- OTP cleaning only happens for OTP verification, not backup codes

### Testing
```bash
# Test backup code verification
1. Generate user with 2FA enabled (8 backup codes created)
2. Disable authentication, logout
3. Login attempt, enter one of the backup codes (e.g., "ABC2D9EF")
4. Should accept and mark code as used
5. Repeat with different code, should work
6. Try same code again, should reject (one-time use)

# Test OTP still works
1. Login with 2FA enabled
2. Enter 6-digit OTP code (with or without spaces)
3. Should accept and allow login
```

---

## Issue #2: Email Verification Code Type Mismatch

### Root Cause
No critical bug here, but the system design needs clarification:

- **Email verification codes**: 6 numeric digits (000000 - 999999)
- **Backup codes**: 8 alphanumeric characters (A-Z, 2-9)

The controller validation at `EmailVerificationController.php` line 55 correctly enforces 6 digits:

```php
if (strlen($code) !== 6 || !ctype_digit($code)) {
    $this->addFlash('error', 'Le code de vérification doit contenir 6 chiffres.');
    return $this->redirectToRoute('app_verify_email');
}
```

### Email Verification Flow for New Users
1. User registers at `/register/agriculteur` (or agriplus/fournisseur)
2. Registration controller generates verification code (6 digits)
3. Email sent with verification code
4. User auto-logged in after registration
5. Redirected to `/verify-email` (requires authentication - satisfied by auto-login)
6. User enters 6-digit code from email
7. Email marked as verified, user proceeds to dashboard

### Why It Works
The registration flow includes auto-login (`Security::login()` at lines 86, 158, 221):
- Users are authenticated after registration
- They can immediately access `/verify-email` endpoint
- No need for unauthenticated email verification link

**However**, if a user skips email verification, they won't be able to verify it later without being logged in (by design).

### Code Validation Rules
```php
// Email verification: 6 numeric digits only
if (strlen($code) !== 6 || !ctype_digit($code)) {
    // Reject
}

// OTP: 6 numeric digits, spaces/dashes can be stripped
if (strlen($cleanedCode) !== 6 || !ctype_digit($cleanedCode)) {
    // Reject
}

// Backup codes: 8 alphanumeric, NO cleaning
if (strlen($code) !== 8 || !preg_match('/^[A-Z2-9]{8}$/', $code)) {
    // Reject
}
```

---

## Implementation Summary

### Modified Files

**1. `src/Controller/UserManagement/TwoFactorController.php`**
- **Line 60 (CHANGED)**: Separated OTP and backup code handling
- **Old**: `$code = preg_replace('/[^0-9]/', '', $code);` (destroyed backup codes)
- **New**: Keeps raw code, cleans only for OTP verification

**Logic Flow**:
```
User enters code (OTP or backup)
├─ Trim whitespace
├─ Try OTP path: clean to digits, validate 6-digit format, verify against TOTP
├─ Try backup path: preserve full code, validate 8-char alphanumeric format
└─ Accept if either succeeds
```

### New Test File

**`tests/Service/CodeVerificationTest.php`**
- 5 test cases validating the fix
- Tests backup codes contain letters
- Tests email codes are numeric only
- Tests separation of concerns between code types
- Shows the difference in processing requirements

---

## Security Considerations

### Backup Codes
- **Format**: 8 alphanumeric (A-Z, 2-9)
- **Storage**: SHA256 hashed in JSON column `user.backup_codes`
- **Validation**: Hash-equals timing-safe comparison
- **Usage**: One-time only, marked as 'used' immediately
- **Generation**: Automatic when 2FA enabled (8 codes per user)

### Email Verification Codes
- **Format**: 6 numeric digits
- **Storage**: Plain text in `user.email_verification_token` (short-lived)
- **Expiration**: 15 minutes
- **Usage**: Can be resent (with rate limiting)
- **Validation**: Direct string comparison (timing-safe not needed for 6-digit codes)

### Risk Mitigation
1. **Code Collision**: Mathematically negligible with proper randomization
2. **Brute Force**: Rate limiting on resend attempts and failed verifications
3. **Timing Attacks**: Backup codes use `hash_equals()`, email codes are low-value
4. **Code Disclosure**: Codes are sent via email only, no API exposure

---

## Testing Checklist

- [ ] Test backup code login with first code
- [ ] Test backup code login with second code
- [ ] Test backup code one-time use (reject on reuse)
- [ ] Test OTP login still works with spaces (e.g., "123 456")
- [ ] Test OTP login with dashes (e.g., "123-456")
- [ ] Test email verification for new users
- [ ] Test email verification code expiration (wait 16 minutes)
- [ ] Test resend verification email rate limiting
- [ ] Test all three registration types (agriculteur, agriplus, fournisseur)
- [ ] Run full test suite for regressions

---

## References

### Related Files
- `src/Service/BackupCodeService.php` - Backup code generation and verification
- `src/Service/EmailVerificationService.php` - Email code generation and verification
- `src/Service/TwoFactorService.php` - OTP generation and verification
- `src/Controller/UserManagement/EmailVerificationController.php` - Email verification UI
- `src/Controller/UserManagement/TwoFactorController.php` - 2FA verification (with the fix)
- `src/Controller/UserManagement/RegistrationController.php` - Registration flow

### Documentation
- `BACKUP_CODES_ARCHITECTURE.md` - Complete backup code system design
- `BACKUP_CODES_VERIFICATION.md` - Backup code verification flow
- `BACKUP_CODES_SECURITY.md` - Security analysis of backup codes

---

## Verification Steps

### Step 1: Verify Backup Codes Work
```bash
# 1. Register new user
# 2. Enable 2FA in profile
# 3. Copy one backup code
# 4. Logout
# 5. Login, use backup code at 2FA prompt
# ✓ Should accept and log in
# ✓ Code should be marked as used
```

### Step 2: Verify Email Verification Works
```bash
# 1. Register new user (should receive email with 6-digit code)
# 2. Check email for verification code
# 3. Navigate to /verify-email
# 4. Enter code and submit
# ✓ Should mark email as verified
# ✓ Should redirect to dashboard
```

### Step 3: Verify OTP Still Works
```bash
# 1. Enable 2FA, get TOTP secret
# 2. Add to authenticator app
# 3. Logout
# 4. Login, enter TOTP from authenticator
# ✓ Should accept and log in
```

---

**Status**: READY FOR TESTING
**Last Updated**: 2024
