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
 * 6. Add authorized redirect URIs:
 *    - http://localhost:8000/auth/google/callback (for development)
 *    - https://claimit.stonekeep.com/auth/google/callback (for production)
 *    The application will automatically use the correct one based on the environment.
 * 7. Copy the Client ID and Client Secret below
 */

// Determine the correct redirect URI based on the environment
$isLocalhost = isset($_SERVER['HTTP_HOST']) && (
    $_SERVER['HTTP_HOST'] === 'localhost:8000' || 
    $_SERVER['HTTP_HOST'] === '127.0.0.1:8000'
);

$redirectUri = $isLocalhost 
    ? 'http://localhost:8000/auth/google/callback'
    : 'https://claimit.stonekeep.com/auth/google/callback';

return [
    'client_id' => 'YOUR_GOOGLE_CLIENT_ID_HERE',
    'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET_HERE',
    'redirect_uri' => $redirectUri,
    'scopes' => [
        'openid',
        'profile',
        'email'
    ]
]; 