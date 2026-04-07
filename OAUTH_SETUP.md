# OAuth Setup Guide for AgriLink

This document explains how to set up Google and Facebook OAuth authentication for AgriLink.

## Features

- Sign in with Google
- Sign in with Facebook
- Automatic user creation/linking based on OAuth provider ID and email
- Profile image upload/management in admin panel
- User deletion and profile management

## Prerequisites

- Google Cloud Project
- Facebook Developer App
- Environment variables configured in `.env`

## Google OAuth Setup

### 1. Create Google OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Navigate to **APIs & Services** > **Credentials**
4. Click **Create Credentials** > **OAuth client ID**
5. Select **Web application**
6. Add authorized JavaScript origins: `http://localhost` (and production domain)
7. Add authorized redirect URIs: `http://localhost/oauth/google/callback` (and production domain)
8. Copy **Client ID** and **Client Secret**

### 2. Configure .env

Update `.env` with your Google credentials:

```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost/oauth/google/callback
```

For production, update the redirect URI:

```env
GOOGLE_REDIRECT_URI=https://your-domain.com/oauth/google/callback
```

## Facebook OAuth Setup

### 1. Create Facebook App

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Go to **My Apps** > **Create App**
3. Choose **Consumer** as app type
4. Fill in app details
5. Add **Facebook Login** product

### 2. Configure Facebook Login

1. Go to app settings > **Facebook Login** > **Settings**
2. Add redirect URIs under **Valid OAuth Redirect URIs**:
   - `http://localhost/oauth/facebook/callback` (development)
   - `https://your-domain.com/oauth/facebook/callback` (production)

### 3. Get App Credentials

1. Go to **Settings** > **Basic**
2. Copy **App ID** and **App Secret**

### 4. Configure .env

Update `.env` with your Facebook credentials:

```env
FACEBOOK_APP_ID=your-app-id
FACEBOOK_APP_SECRET=your-app-secret
FACEBOOK_REDIRECT_URI=http://localhost/oauth/facebook/callback
```

For production:

```env
FACEBOOK_REDIRECT_URI=https://your-domain.com/oauth/facebook/callback
```

## How It Works

### Login Flow

1. User clicks "Google" or "Facebook" button on login page
2. User is redirected to provider's OAuth dialog
3. User grants permissions
4. Provider redirects to callback URL with authorization code
5. Backend exchanges code for access token
6. Backend retrieves user info from provider
7. System checks if user exists by OAuth provider ID
8. If not found, checks by email and links accounts
9. If neither, creates new user
10. User is authenticated and redirected to dashboard

### Database Fields

User entity includes OAuth fields:

- `oauthProvider`: 'google' or 'facebook'
- `oauthProviderId`: Unique ID from OAuth provider
- `emailVerified`: Set to true for OAuth users (providers verify email)

### Security Considerations

- Passwords are empty for OAuth-only users
- OAuth provider ID is the primary identifier (prevents email-based account hijacking)
- Email verification is skipped for OAuth users (OAuth providers verify email)
- All OAuth flows use HTTPS in production

## Testing

### Local Testing

1. Ensure `.env` credentials are set
2. Navigate to login page
3. Click "Google" or "Facebook" button
4. Complete OAuth flow in provider's dialog
5. Verify redirect back to dashboard

### Common Issues

**"Invalid Redirect URI"**
- Ensure exact match between configured redirect URI and actual callback URL
- Check for trailing slashes
- Verify domain matches (localhost vs 127.0.0.1)

**"Invalid Client ID"**
- Verify credentials are correctly copied from provider console
- Check for extra spaces or quotes

**User Not Creating**
- Check browser console for JavaScript errors
- Check Symfony logs: `var/log/dev.log`
- Verify user entity fields (email, nom) are filled

**Account Linking Not Working**
- If user exists by email, existing account is linked automatically
- To create separate account, use different email

## File Structure

- `src/Controller/UserManagement/OAuthController.php` - OAuth callback handlers
- `src/Service/OAuthService.php` - OAuth token exchange and user data retrieval
- `templates/user_management/security/login.html.twig` - Login page with OAuth buttons
- `config/services.yaml` - OAuth service configuration with environment variables

## Next Steps

1. Set up Google and Facebook credentials
2. Add credentials to `.env` file
3. Update redirect URIs if deploying to production
4. Test OAuth flows locally
5. Deploy to production with HTTPS

## Support

For issues or questions:
- Check Symfony logs: `var/log/dev.log`
- Review OAuth provider documentation
- Verify all environment variables are set correctly
