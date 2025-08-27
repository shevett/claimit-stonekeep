# Google OAuth Setup for ClaimIt

This guide will help you set up Google OAuth authentication for your ClaimIt marketplace application.

## Prerequisites

- ClaimIt application already running locally
- Google account for creating OAuth credentials
- AWS S3 bucket configured and working

## Step 1: Create Google Cloud Project

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Click "Select a Project" → "New Project"
3. Enter project name: "ClaimIt Marketplace" (or your preferred name)
4. Click "Create"

## Step 2: Enable Google+ API

1. In your Google Cloud Console, navigate to "APIs & Services" → "Library"
2. Search for "Google+ API"
3. Click on "Google+ API" and click "Enable"
4. Alternatively, search for "People API" and enable it (recommended for newer projects)

## Step 3: Create OAuth 2.0 Credentials

1. Go to "APIs & Services" → "Credentials"
2. Click "Create Credentials" → "OAuth 2.0 Client IDs"
3. If prompted, configure the OAuth consent screen first:
   - Choose "External" user type
   - Fill in the required fields:
     - App name: "ClaimIt Marketplace"
     - User support email: your email
     - Developer contact email: your email
   - Add your domain if you have one
   - Click "Save and Continue"
4. For Application type, select "Web application"
5. Set the name: "ClaimIt Web Client"
6. Add Authorized redirect URIs:
   - For development: `http://localhost:8000/auth/google/callback`
   - For production: `https://yourdomain.com/auth/google/callback`
7. Click "Create"
8. **Important**: Copy the Client ID and Client Secret - you'll need these next

## Step 4: Configure ClaimIt Application

1. In your ClaimIt project directory, copy the OAuth configuration template:
   ```bash
   cp config/google-oauth.example.php config/google-oauth.php
   ```

2. Edit `config/google-oauth.php` and replace the placeholder values:
   ```php
   <?php
   return [
       'client_id' => 'YOUR_ACTUAL_GOOGLE_CLIENT_ID_HERE',
       'client_secret' => 'YOUR_ACTUAL_GOOGLE_CLIENT_SECRET_HERE', 
       'redirect_uri' => 'http://localhost:8000/auth/google/callback',
       'scopes' => [
           'openid',
           'profile', 
           'email'
       ]
   ];
   ```

3. Update the redirect URI for production when you deploy:
   ```php
   'redirect_uri' => 'https://yourdomain.com/auth/google/callback',
   ```

## Step 5: Test the Authentication

1. Start your PHP development server:
   ```bash
   php -S localhost:8000 router.php
   ```

2. Open your browser and go to `http://localhost:8000`

3. Click "Login" in the navigation

4. Click "Continue with Google"

5. You should be redirected to Google's login page

6. After successful authentication, you should be redirected back to your dashboard

## Step 6: Verify User Features

After logging in, verify these features work:

- ✅ User avatar and name appear in navigation
- ✅ "My Posts" dashboard shows user-specific items
- ✅ "Make a new posting" creates items associated with your user
- ✅ Delete buttons only appear on your own items
- ✅ Other users' items cannot be deleted (security)
- ✅ Logout functionality works

## Step 7: Production Deployment

When deploying to production:

1. **Update OAuth Configuration**:
   - Add your production domain to Google OAuth redirect URIs
   - Update `config/google-oauth.php` with production redirect URI

2. **Security Considerations**:
   - Ensure `config/google-oauth.php` is not publicly accessible
   - Add `config/google-oauth.php` to your `.gitignore` if not already
   - Use HTTPS for production (required by Google)

3. **Apache Configuration**:
   - Update your `.htaccess` to handle the auth routes:
     ```apache
     RewriteRule ^auth/google/callback$ index.php?page=auth&action=callback [L,QSA]
     RewriteRule ^auth/google$ index.php?page=auth&action=google [L]
     RewriteRule ^auth/logout$ index.php?page=auth&action=logout [L]
     ```

## Troubleshooting

### Error: "OAuth configuration not found"
- Ensure `config/google-oauth.php` exists and has correct file permissions
- Check that the file has valid PHP syntax

### Error: "redirect_uri_mismatch" 
- The redirect URI in your Google OAuth settings must exactly match what's in your config
- Ensure there are no trailing slashes or typos

### Error: "Authentication service unavailable"
- Check that AWS credentials are properly configured
- Verify S3 bucket permissions allow creating user profiles

### Users can't delete items they posted
- Ensure the YAML structure includes `user_id` field
- Check that the user authentication is working properly

### Authentication loop or session issues
- Clear your browser cookies for localhost
- Check PHP session configuration
- Ensure session files are writable

## Support

If you encounter issues:

1. Check the browser developer console for JavaScript errors
2. Check your PHP error logs for server-side issues
3. Verify your AWS S3 permissions include user profile storage
4. Ensure all file permissions are correct

## Features Enabled by Authentication

With Google OAuth configured, users can now:

- **Post Items**: Only authenticated users can post new items
- **Manage Items**: Users see a dashboard with their posted items
- **Secure Deletion**: Users can only delete their own items
- **User Profiles**: User information is stored securely in S3
- **Session Management**: Persistent login sessions across browser visits
- **Contact Integration**: Email addresses are automatically filled from Google profiles

The application gracefully handles both authenticated and anonymous users - anonymous users can browse items but cannot post or manage content. 