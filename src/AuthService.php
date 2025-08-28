<?php

/**
 * Authentication service for Google OAuth integration
 */
class AuthService
{
    private $googleClient;
    private $awsService;
    private $config;

    public function __construct($awsService)
    {
        $this->awsService = $awsService;
        
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
     */
    public function getAuthUrl(): string
    {
        return $this->googleClient->createAuthUrl();
    }

    /**
     * Handle OAuth callback and authenticate user
     */
    public function handleCallback(string $code): array
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
            $user = [
                'id' => $userInfo->id,
                'email' => $userInfo->email,
                'name' => $userInfo->name,
                'picture' => $pictureUrl,
                'verified_email' => $userInfo->verifiedEmail,
                'locale' => $userInfo->locale,
                'last_login' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ];



            // Load existing user profile if it exists
            $existingUser = $this->loadUserProfile($user['id']);
            if ($existingUser) {
                // Update last login and merge any new info
                $user['created_at'] = $existingUser['created_at'];
                $user = array_merge($existingUser, $user);
            }

            // Save user profile to S3
            $this->saveUserProfile($user);

            // Store user in session
            $this->loginUser($user);

            return $user;

        } catch (\Exception $e) {
            throw new \Exception('Authentication failed: ' . $e->getMessage());
        }
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

    /**
     * Save user profile to S3
     */
    private function saveUserProfile(array $user): void
    {
        $userKey = 'users/' . $user['id'] . '.json';
        $userData = json_encode($user, JSON_PRETTY_PRINT);
        
        $this->awsService->putObject($userKey, $userData, 'application/json');
    }

    /**
     * Load user profile from S3
     */
    private function loadUserProfile(string $userId): ?array
    {
        try {
            $userKey = 'users/' . $userId . '.json';
            $objectData = $this->awsService->getObject($userKey);
            $userData = $objectData['content']; // Extract content from the result array
            return json_decode($userData, true);
        } catch (\Exception $e) {
            return null; // User doesn't exist yet
        }
    }

    /**
     * Get user's posted items
     */
    public function getUserItems(string $userId): array
    {
        $items = [];
        
        try {
            $result = $this->awsService->listObjects();
            $objects = $result['objects'] ?? [];
            
            foreach ($objects as $object) {
                $key = $object['key'];
                
                // Skip user profiles and non-YAML files
                if (strpos($key, 'users/') === 0 || !str_ends_with($key, '.yaml')) {
                    continue;
                }
                
                // Get YAML content
                $objectData = $this->awsService->getObject($key);
                $yamlContent = $objectData['content']; // Extract content from the result array
                $data = parseSimpleYaml($yamlContent);
                
                // Check if this item belongs to the user
                if (isset($data['user_id']) && $data['user_id'] === $userId) {
                    $trackingNumber = str_replace('.yaml', '', $key);
                    
                    // Check for image
                    $imageKey = null;
                    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    foreach ($imageExtensions as $ext) {
                        $possibleImageKey = $trackingNumber . '.' . $ext;
                        if ($this->awsService->objectExists($possibleImageKey)) {
                            $imageKey = $possibleImageKey;
                            break;
                        }
                    }
                    
                    $items[] = [
                        'tracking_number' => $trackingNumber,
                        'title' => $data['title'],
                        'description' => $data['description'] ?? '',
                        'price' => (float)($data['price'] ?? 0),
                        'contact_email' => $data['contact_email'] ?? '',
                        'submitted_at' => $data['submitted_at'] ?? '',
                        'image_key' => $imageKey,
                        'user_id' => $data['user_id']
                    ];
                }
            }
            
            // Sort by submission date (newest first)
            usort($items, function($a, $b) {
                return strcmp($b['submitted_at'], $a['submitted_at']);
            });
            
        } catch (\Exception $e) {
            error_log('Error loading user items: ' . $e->getMessage());
        }
        
        return $items;
    }

    /**
     * Check if user owns an item
     */
    public function userOwnsItem(string $userId, string $trackingNumber): bool
    {
        try {
            $yamlKey = $trackingNumber . '.yaml';
            $objectData = $this->awsService->getObject($yamlKey);
            $yamlContent = $objectData['content']; // Extract content from the result array
            $data = parseSimpleYaml($yamlContent);
            
            return isset($data['user_id']) && $data['user_id'] === $userId;
        } catch (\Exception $e) {
            return false;
        }
    }
} 