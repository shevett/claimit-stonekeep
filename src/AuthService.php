<?php

namespace ClaimIt;

/**
 * Authentication service for Google OAuth integration
 */
class AuthService
{
    private $googleClient;
    private $config;

    public function __construct()
    {

        // Load Google OAuth configuration
        $configPath = __DIR__ . '/../config/google-oauth.php';
        if (!file_exists($configPath)) {
            throw new \Exception('Google OAuth configuration not found. Please copy google-oauth.example.php to google-oauth.php and configure it.');
        }

        $this->config = require $configPath;

        // Initialize Google Client
        $this->googleClient = new \Google_Client();
        $this->googleClient->setClientId($this->config['client_id']);
        $this->googleClient->setClientSecret($this->config['client_secret']);
        $this->googleClient->setRedirectUri($this->config['redirect_uri']);
        $this->googleClient->setScopes($this->config['scopes']);
        $this->googleClient->setAccessType('offline');
        $this->googleClient->setPrompt('select_account consent');
    }

    /**
     * Get Google OAuth authorization URL
     * @param string $state Opaque value round-tripped by Google, used here to
     *                      carry the originating tenant prefix (empty string
     *                      for apex/self-hosted logins) across the redirect.
     */
    public function getAuthUrl(string $state = ''): string
    {
        if ($state !== '') {
            $this->googleClient->setState($state);
        }
        return $this->googleClient->createAuthUrl();
    }

    /**
     * Exchange a Google OAuth authorization code for a verified, normalized
     * user profile. Does NOT touch the database or session - callers decide
     * where/when to complete login (see completeLogin()), since on a
     * multitenant setup this callback always runs on the apex domain
     * (Google's redirect_uri must be fixed), which may not be the correct
     * tenant database to save the user into.
     */
    public function exchangeCodeForProfile(string $code): array
    {
        try {
            // Exchange authorization code for access token
            $token = $this->googleClient->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new \Exception('OAuth error: ' . $token['error']);
            }

            $this->googleClient->setAccessToken($token);

            // Get user info from Google
            $oauth2 = new \Google_Service_Oauth2($this->googleClient);
            $userInfo = $oauth2->userinfo->get();

            // Ensure we get a properly sized picture from Google
            $pictureUrl = $userInfo->picture;
            if ($pictureUrl && !str_contains($pictureUrl, '=s')) {
                // Add size parameter to Google profile pictures for better quality
                $pictureUrl = $pictureUrl . '=s96-c';
            }

            // Create user profile
            return [
                'id' => $userInfo->id,
                'email' => $userInfo->email,
                'name' => $userInfo->name,
                'picture' => $pictureUrl,
                'verified_email' => $userInfo->verifiedEmail,
                'locale' => $userInfo->locale,
                'last_login' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            throw new \Exception('Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Complete login for an already-verified Google profile: merge with any
     * existing stored preferences, save to the database, and start the
     * session. Uses whichever database getDbConnection() currently points
     * at - callers must ensure that's the correct (tenant) database before
     * calling this.
     * @param array $googleProfile Profile from exchangeCodeForProfile(), or
     *                             decoded from a verified OAuth handoff token
     */
    public function completeLogin(array $googleProfile): array
    {
        $user = $googleProfile;

        // Load existing user profile if it exists
        $existingUser = getUserById($user['id']);
        if ($existingUser) {
            // Update last login and merge any new info
            $user['created_at'] = $existingUser['created_at'];
            // Preserve user preferences from database
            $user['display_name'] = $existingUser['display_name'] ?? null;
            $user['zipcode'] = $existingUser['zipcode'] ?? null;
            $user['show_gone_items'] = $existingUser['show_gone_items'] ?? true;
            $user['email_notifications'] = $existingUser['email_notifications'] ?? true;
            $user['new_listing_notifications'] = $existingUser['new_listing_notifications'] ?? true;
        }

        // Save user profile to database
        saveUser($user);

        // Store user in session
        $this->loginUser($user);

        return $user;
    }

    /**
     * Handle OAuth callback and authenticate user (apex/self-hosted path -
     * exchanges the code and completes login in one step, against whichever
     * database is currently connected).
     */
    public function handleCallback(string $code): array
    {
        return $this->completeLogin($this->exchangeCodeForProfile($code));
    }

    /**
     * Log in a user (store in session)
     */
    public function loginUser(array $user): void
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user'] = $user;
        $_SESSION['authenticated'] = true;
    }

    /**
     * Log out the current user
     */
    public function logout(): void
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['user']);
        unset($_SESSION['authenticated']);
        session_destroy();
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): ?array
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION['user'] ?? null;
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    /**
     * Require authentication (redirect to login if not authenticated)
     */
    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            redirect('login');
        }
    }
}
