<?php
/**
 * Google OAuth Configuration
 * 
 * To get these credentials:
 * 1. Go to https://console.developers.google.com/
 * 2. Create a new project or select existing one
 * 3. Enable Google+ API
 * 4. Go to Credentials > Create Credentials > OAuth 2.0 Client IDs
 * 5. Set application type to "Web application"
 * 6. Add authorized redirect URI: http://localhost:8000/auth/google/callback
 *    (and your production domain when deploying)
 * 7. Copy the Client ID and Client Secret below
 */

return [
    'client_id' => 'YOUR_GOOGLE_CLIENT_ID_HERE',
    'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET_HERE',
    'redirect_uri' => 'http://localhost:8000/auth/google/callback',
    'scopes' => [
        'openid',
        'profile',
        'email'
    ]
]; 