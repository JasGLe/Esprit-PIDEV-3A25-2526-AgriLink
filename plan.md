`# Intrusion Capture Security Feature

## Overview
When user toggles `intrusion_capture_enabled` ON:
- Track failed login attempts using existing `failedLoginAttempts` field
- On 3rd failed attempt: capture camera photo (if permitted)
- Send intrusion alert email with embedded image
- Reset counter on successful login

Uses existing User fields: `failedLoginAttempts`, `lastLogin`, `intrusionCaptureEnabled`

## Implementation Plan

### 1. Frontend - Security Settings Toggle
- [ ] Add intrusion_capture_enabled toggle to security settings page
- [ ] Update form to persist toggle state

### 2. Login Controller Updates
- [ ] Detect failed login attempts
- [ ] Trigger camera capture on 3rd failed attempt
- [ ] Reset counter on success
- [ ] Update lastLogin on success

### 3. Camera Capture Service (JavaScript)
- [ ] Create camera capture utility with WebRTC
- [ ] Check camera permission status
- [ ] Capture frame on demand
- [ ] Convert to base64

### 4. API Endpoint
- [ ] POST `/api/security/capture-intrusion` 
- [ ] Receive base64 image from frontend
- [ ] Queue email with attachment
- [ ] Send intrusion alert email

### 5. Email Service & Template
- [ ] Update email service to support inline images
- [ ] Create intrusion_alert.html.twig template
- [ ] Include device info (IP, user agent)

### 6. Testing
- [ ] Test failed login tracking
- [ ] Test camera capture workflow
- [ ] Test email sending with image
