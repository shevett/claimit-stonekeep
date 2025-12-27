<?php

/**
 * ClaimIt Application Functions
 * 
 * REFACTORED STRUCTURE:
 * Functions are now organized in modular files for better maintainability.
 * 
 * Completed modules:
 * - core.php: Database, escaping, redirects, CSRF
 * - auth.php: Authentication and authorization
 * - users.php: User management
 * - communities.php: Community management
 * 
 * Placeholder modules (functions still in this file):
 * - items.php: Item management
 * - claims.php: Claims system
 * - images.php: Image handling
 * - cache.php: Caching functions
 * - utilities.php: Helper utilities
 */

// Load all modular function libraries
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/items.php';
require_once __DIR__ . '/claims.php';
require_once __DIR__ . '/images.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/communities.php';


/**
 * Escape HTML output to prevent XSS attacks
 */
if (!function_exists('escape')) {
function escape($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
}

/**
 * Get database connection
 * Returns a PDO instance or null on failure
 */
if (!function_exists('getDbConnection')) {
function getDbConnection()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}
}

/**
 * Test database connection
 * Returns array with status and message
 */
if (!function_exists('testDbConnection')) {
function testDbConnection()
{
    try {
        $pdo = getDbConnection();

        if ($pdo === null) {
            return [
                'success' => false,
                'message' => 'Failed to connect to database'
            ];
        }

        // Test query
        $stmt = $pdo->query('SELECT VERSION() as version, DATABASE() as db_name');
        $result = $stmt->fetch();

        return [
            'success' => true,
            'message' => 'Database connection successful',
            'details' => [
                'mysql_version' => $result['version'],
                'database' => $result['db_name'],
                'host' => DB_HOST,
                'charset' => DB_CHARSET
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Database test failed: ' . $e->getMessage()
        ];
    }
}
}

/**
 * Get user by ID from database
 * Returns user array or null if not found
 */
if (!function_exists('getUserById')) {
function getUserById($userId)
{
    try {
        $pdo = getDbConnection();
        if ($pdo === null) {
            return null;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        // Convert database fields to match existing format
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'picture' => $user['picture'],
            'verified_email' => (bool)$user['verified_email'],
            'locale' => $user['locale'],
            'last_login' => $user['last_login'],
            'created_at' => $user['created_at'],
            'display_name' => $user['display_name'],
            'zipcode' => $user['zipcode'],
            'show_gone_items' => (bool)$user['show_gone_items'],
            'email_notifications' => (bool)$user['email_notifications'],
            'new_listing_notifications' => (bool)$user['new_listing_notifications']
        ];
    } catch (Exception $e) {
        error_log("Error getting user by ID: " . $e->getMessage());
        return null;
    }
}
}

/**
 * Create a new user in database
 * Returns true on success, false on failure
 */
if (!function_exists('createUser')) {
function createUser($userData)
{
    try {
        $pdo = getDbConnection();
        if ($pdo === null) {
            return false;
        }

        $sql = "INSERT INTO users (
            id, email, name, picture, verified_email, locale,
            last_login, created_at, display_name, zipcode,
            show_gone_items, email_notifications, new_listing_notifications,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $userData['id'],
            $userData['email'] ?? null,
            $userData['name'] ?? null,
            $userData['picture'] ?? null,
            isset($userData['verified_email']) ? (int)$userData['verified_email'] : null,
            $userData['locale'] ?? null,
            $userData['last_login'] ?? date('Y-m-d H:i:s'),
            $userData['created_at'] ?? date('Y-m-d H:i:s'),
            $userData['display_name'] ?? null,
            $userData['zipcode'] ?? null,
            isset($userData['show_gone_items']) ? (int)$userData['show_gone_items'] : 1,
            isset($userData['email_notifications']) ? (int)$userData['email_notifications'] : 1,
            isset($userData['new_listing_notifications']) ? (int)$userData['new_listing_notifications'] : 1,
            date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log("Error creating user: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Update an existing user in database
 * Returns true on success, false on failure
 */
if (!function_exists('updateUser')) {
function updateUser($userId, $userData)
{
    try {
        $pdo = getDbConnection();
        if ($pdo === null) {
            return false;
        }

        $sql = "UPDATE users SET 
            email = ?, name = ?, picture = ?, verified_email = ?, locale = ?,
            last_login = ?, display_name = ?, zipcode = ?,
            show_gone_items = ?, email_notifications = ?, new_listing_notifications = ?,
            updated_at = ?
        WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $userData['email'] ?? null,
            $userData['name'] ?? null,
            $userData['picture'] ?? null,
            isset($userData['verified_email']) ? (int)$userData['verified_email'] : null,
            $userData['locale'] ?? null,
            $userData['last_login'] ?? date('Y-m-d H:i:s'),
            $userData['display_name'] ?? null,
            $userData['zipcode'] ?? null,
            isset($userData['show_gone_items']) ? (int)$userData['show_gone_items'] : 1,
            isset($userData['email_notifications']) ? (int)$userData['email_notifications'] : 1,
            isset($userData['new_listing_notifications']) ? (int)$userData['new_listing_notifications'] : 1,
            date('Y-m-d H:i:s'),
            $userId
        ]);
    } catch (Exception $e) {
        error_log("Error updating user: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Save or update user (upsert operation)
 * Returns true on success, false on failure
 */
if (!function_exists('saveUser')) {
function saveUser($userData)
{
    $existingUser = getUserById($userData['id']);

    if ($existingUser) {
        return updateUser($userData['id'], $userData);
    } else {
        return createUser($userData);
    }
}
}

/**
 * Redirect to a specific page
 */
if (!function_exists('redirect')) {
function redirect($page = 'home')
{
    // Determine the correct base URL based on the environment
    $isLocalhost = isset($_SERVER['HTTP_HOST']) && (
        $_SERVER['HTTP_HOST'] === 'localhost:8000' ||
        $_SERVER['HTTP_HOST'] === '127.0.0.1:8000' ||
        $_SERVER['HTTP_HOST'] === 'localhost:8080' ||
        $_SERVER['HTTP_HOST'] === '127.0.0.1:8080'
    );

    $baseUrl = $isLocalhost
        ? 'http://' . $_SERVER['HTTP_HOST'] . '/'
        : 'https://claimit.stonekeep.com/';

    // Discard any output buffer to allow redirect
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Location: ' . $baseUrl . '?page=' . urlencode($page));
    exit;
}
}

/**
 * Get Authentication service instance (lazy loading)
 */
if (!function_exists('getAuthService')) {
function getAuthService()
{
    static $authService = null;

    // Always attempt initialization if not already done
    // This ensures fresh initialization on each request
    if ($authService === null) {
        try {
            $authService = new AuthService();
        } catch (Exception $e) {
            error_log('Failed to initialize Auth service: ' . $e->getMessage());
            return null;
        }
    }

    return $authService;
}
}

/**
 * Get Email service instance (lazy loading)
 */
if (!function_exists('getEmailService')) {
function getEmailService()
{
    static $emailService = null;

    if ($emailService === null) {
        try {
            $awsService = getAwsService();
            if (!$awsService) {
                throw new Exception('AWS service not available');
            }
            $emailService = new \ClaimIt\EmailService($awsService);
        } catch (Exception $e) {
            error_log('Failed to initialize Email service: ' . $e->getMessage());
            return null;
        }
    }

    return $emailService;
}
}


/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
function isLoggedIn()
{
    $authService = getAuthService();
    return $authService ? $authService->isLoggedIn() : false;
}
}

/**
 * Get current authenticated user
 */
if (!function_exists('getCurrentUser')) {
function getCurrentUser()
{
    $authService = getAuthService();
    return $authService ? $authService->getCurrentUser() : null;
}
}

/**
 * Require authentication (redirect to login if not authenticated)
 */
if (!function_exists('requireAuth')) {
function requireAuth()
{
    $authService = getAuthService();
    if ($authService) {
        $authService->requireAuth();
    } else {
        redirect('login');
    }
}
}

/**
 * Check if current user owns an item
 */
if (!function_exists('currentUserOwnsItem')) {
function currentUserOwnsItem(string $trackingNumber): bool
{
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }

    // Get item from database and check user_id
    $item = getItemFromDb($trackingNumber);
    return $item && ($item['user_id'] === $user['id']);
}
}

/**
 * Generate CSRF token
 */
if (!function_exists('generateCSRFToken')) {
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
}

/**
 * Verify CSRF token
 */
if (!function_exists('verifyCSRFToken')) {
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
}

/**
 * Format date for display
 */
if (!function_exists('formatDate')) {
function formatDate($date, $format = 'F j, Y')
{
    return date($format, strtotime($date));
}
}

/**
 * Show flash message
 */
if (!function_exists('showFlashMessage')) {
function showFlashMessage()
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}
}

/**
 * Set flash message
 */
if (!function_exists('setFlashMessage')) {
function setFlashMessage($message, $type = 'info')
{
    $_SESSION['flash_message'] = [
        'text' => $message,
        'type' => $type
    ];
}
}

/**
 * Get AWS service instance (singleton with lazy loading)
 * Only initializes when actually needed to avoid performance impact
 *
 * @return ClaimIt\AwsService|null
 */
if (!function_exists('getAwsService')) {
function getAwsService()
{
    static $awsService = null;
    static $requestId = null;

    // Generate a unique request ID to ensure fresh initialization per request
    if ($requestId === null) {
        $requestId = uniqid('req_', true);
    }

    // Always attempt initialization if not already done
    // This ensures fresh initialization on each request
    if ($awsService === null) {
        try {
            // Use output buffering to suppress any AWS SDK warnings during initialization
            ob_start();
            $awsService = new ClaimIt\AwsService();
            ob_end_clean();

            error_log("AWS Service initialized successfully (Request: $requestId)");
        } catch (Exception $e) {
            error_log("AWS Service initialization failed (Request: $requestId): " . $e->getMessage());
            ob_end_clean(); // Clean up any output buffer
            return null;
        }
    }

    return $awsService;
}
}

/**
 * Check if AWS service is available without initializing it
 * Useful for conditional logic that doesn't need AWS
 *
 * @return bool
 */
if (!function_exists('isAwsServiceAvailable')) {
function isAwsServiceAvailable()
{
    static $available = null;

    if ($available === null) {
        try {
            $awsService = getAwsService();
            $available = ($awsService !== null);
        } catch (Exception $e) {
            $available = false;
        }
    }

    return $available;
}
}

/**
 * Simple in-memory cache for user settings to avoid repeated S3 calls
 * Cache expires after 5 minutes to balance performance vs data freshness
 */
if (!function_exists('getUserSettingsCache')) {
function getUserSettingsCache($userId, $key = null)
{
    static $cache = [];
    static $cacheTime = [];
    $cacheExpiry = 300; // 5 minutes

    $cacheKey = $userId . ($key ? '_' . $key : '');

    // Check if cache is still valid
    if (isset($cache[$cacheKey]) && isset($cacheTime[$cacheKey])) {
        if (time() - $cacheTime[$cacheKey] < $cacheExpiry) {
            return $cache[$cacheKey];
        } else {
            // Cache expired, remove it
            unset($cache[$cacheKey], $cacheTime[$cacheKey]);
        }
    }

    return null;
}
}

/**
 * Set user settings cache
 */
if (!function_exists('setUserSettingsCache')) {
function setUserSettingsCache($userId, $value, $key = null)
{
    static $cache = [];
    static $cacheTime = [];

    $cacheKey = $userId . ($key ? '_' . $key : '');
    $cache[$cacheKey] = $value;
    $cacheTime[$cacheKey] = time();
}
}

/**
 * Get all items efficiently with minimal S3 API calls
 * This function batches operations to avoid N+1 query problems
 *
 * @param bool $includeGoneItems Whether to include gone items
 * @return array Array of items
 */
if (!function_exists('getAllItemsEfficiently')) {
function getAllItemsEfficiently($includeGoneItems = false)
{
    static $itemsCache = null;
    static $cacheTime = null;
    static $cacheKey = null;

    // Check if cache should be cleared
    if (shouldClearItemsCache()) {
        $itemsCache = null;
        $cacheTime = null;
        $cacheKey = null;
        error_log('Items cache fully cleared');
    }

    // Create cache key based on parameters
    $currentCacheKey = md5($includeGoneItems ? 'with_gone' : 'without_gone');

    // Use longer cache for better performance (5 minutes)
    $cacheExpiry = 300; // 5 minutes cache for items

    // Check cache first (only if cache key matches)
    if ($itemsCache !== null && $cacheTime !== null && $cacheKey === $currentCacheKey && (time() - $cacheTime) < $cacheExpiry) {
        // Use cached data
    } else {
        try {
            $startTime = microtime(true);

            // Get items from database
            $dbItems = getAllItemsFromDb($includeGoneItems);
            debugLog("Loaded " . count($dbItems) . " items from database");

            // Get all claims from database in one query for efficiency
            $pdo = getDbConnection();
            $claimsStmt = $pdo->query("SELECT * FROM claims WHERE status = 'active' ORDER BY claimed_at ASC");
            $allClaims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Group claims by item
            $claimsByItem = [];
            foreach ($allClaims as $claim) {
                $trackingNumber = $claim['item_tracking_number'];
                if (!isset($claimsByItem[$trackingNumber])) {
                    $claimsByItem[$trackingNumber] = [];
                }
                $claimsByItem[$trackingNumber][] = $claim;
            }

            debugLog("Loaded claims for " . count($claimsByItem) . " items");

            // Process each item
            $items = [];
            $currentUser = getCurrentUser();

            foreach ($dbItems as $dbItem) {
                $trackingNumber = $dbItem['tracking_number'];

                // Get image key and URL
                $imageKey = $dbItem['image_file'];
                $imageUrl = null;
                if ($imageKey) {
                    $imageUrl = getCloudFrontUrl($imageKey);
                }

                // Pre-compute item states
                $isItemGone = (bool)$dbItem['gone'];
                $canEditItem = canUserEditItem($dbItem['user_id']);

                // Get claims for this item
                $activeClaims = $claimsByItem[$trackingNumber] ?? [];
                $primaryClaim = !empty($activeClaims) ? $activeClaims[0] : null;

                // User-specific claim data
                $isUserClaimed = false;
                $canUserClaim = false;
                if ($currentUser) {
                    foreach ($activeClaims as $claim) {
                        if ($claim['user_id'] === $currentUser['id']) {
                            $isUserClaimed = true;
                            break;
                        }
                    }
                    $canUserClaim = !$isItemGone && !$isUserClaimed;
                }

                $items[] = [
                    'tracking_number' => $trackingNumber,
                    'title' => $dbItem['title'],
                    'description' => $dbItem['description'],
                    'price' => $dbItem['price'],
                    'contact_email' => $dbItem['contact_email'],
                    'image_key' => $imageKey,
                    'image_url' => $imageUrl,
                    'image_width' => $dbItem['image_width'],
                    'image_height' => $dbItem['image_height'],
                    'posted_date' => $dbItem['submitted_at'],
                    'submitted_timestamp' => $dbItem['submitted_timestamp'],
                    'user_id' => $dbItem['user_id'],
                    'user_name' => $dbItem['user_name'],
                    'user_email' => $dbItem['user_email'],
                    'gone' => $dbItem['gone'],
                    'gone_at' => $dbItem['gone_at'],
                    'gone_by' => $dbItem['gone_by'],
                    'relisted_at' => $dbItem['relisted_at'],
                    'relisted_by' => $dbItem['relisted_by'],
                    'is_item_gone' => $isItemGone,
                    'can_edit_item' => $canEditItem,
                    'active_claims' => $activeClaims,
                    'primary_claim' => $primaryClaim,
                    'is_user_claimed' => $isUserClaimed,
                    'can_user_claim' => $canUserClaim
                ];
            }

            $endTime = microtime(true);
            $totalTime = round(($endTime - $startTime) * 1000, 2);
            debugLog("Performance: Loaded " . count($items) . " items from database in {$totalTime}ms");

            // Cache the results
            $itemsCache = $items;
            $cacheTime = time();
            $cacheKey = $currentCacheKey;
        } catch (Exception $e) {
            error_log('Error loading items efficiently: ' . $e->getMessage());
            return [];
        }
    }

    // Return all items
    return $itemsCache;
}
}

/**
 * Generate cached presigned URL for better performance
 * Uses caching to avoid repeated presigned URL generation
 *
 * @param string $imageKey S3 object key
 * @return string Presigned URL
 */
if (!function_exists('getCachedPresignedUrl')) {
function getCachedPresignedUrl($imageKey)
{
    static $urlCache = [];
    static $cacheTime = [];
    $cacheExpiry = 3600; // 1 hour cache for presigned URLs

    // Check if cache should be cleared
    if (shouldClearImageUrlCache()) {
        $urlCache = [];
        $cacheTime = [];
        error_log('Presigned URL cache fully cleared');
    }

    $cacheKey = $imageKey;

    // Check cache first
    if (isset($urlCache[$cacheKey]) && isset($cacheTime[$cacheKey])) {
        if (time() - $cacheTime[$cacheKey] < $cacheExpiry) {
            return $urlCache[$cacheKey];
        } else {
            // Cache expired, remove it
            unset($urlCache[$cacheKey], $cacheTime[$cacheKey]);
        }
    }

    try {
        $urlStartTime = microtime(true);
        $awsService = getAwsService();
        if (!$awsService) {
            return '';
        }

        // Generate presigned URL with longer expiration
        $url = $awsService->getPresignedUrl($imageKey, 3600); // 1 hour expiration
        $urlEndTime = microtime(true);
        $urlTime = round(($urlEndTime - $urlStartTime) * 1000, 2);
        debugLog("getCachedPresignedUrl for {$imageKey}: {$urlTime}ms");

        // Cache the URL
        $urlCache[$cacheKey] = $url;
        $cacheTime[$cacheKey] = time();

        return $url;
    } catch (Exception $e) {
        error_log('Error generating presigned URL: ' . $e->getMessage());
        return '';
    }
}
}

/**
 * Clear items cache when items are modified
 * This ensures fresh data after item updates
 */
if (!function_exists('clearItemsCache')) {
function clearItemsCache()
{
    // PHP static variables can't be directly cleared from outside the function
    // Instead, we'll use a global flag to force cache invalidation
    global $__itemsCacheCleared;
    $__itemsCacheCleared = true;

    error_log('Items cache invalidation flag set - next request will fetch fresh data');
}
}

/**
 * Check if items cache should be cleared
 * Called from within getAllItemsEfficiently
 */
if (!function_exists('shouldClearItemsCache')) {
function shouldClearItemsCache()
{
    global $__itemsCacheCleared;
    if (isset($__itemsCacheCleared) && $__itemsCacheCleared) {
        $__itemsCacheCleared = false; // Reset flag
        return true;
    }
    return false;
}
}

/**
 * Clear presigned URL cache when images are modified
 * This ensures fresh URLs after image updates
 */
if (!function_exists('clearImageUrlCache')) {
function clearImageUrlCache()
{
    // PHP static variables can't be directly cleared from outside the function
    // Instead, we'll use a global flag to force cache invalidation
    global $__imageUrlCacheCleared;
    $__imageUrlCacheCleared = true;

    error_log('Image URL cache invalidation flag set - next request will generate fresh URLs');
}
}

/**
 * Check if image URL cache should be cleared
 * Called from within getCachedPresignedUrl
 */
if (!function_exists('shouldClearImageUrlCache')) {
function shouldClearImageUrlCache()
{
    global $__imageUrlCacheCleared;
    if (isset($__imageUrlCacheCleared) && $__imageUrlCacheCleared) {
        $__imageUrlCacheCleared = false; // Reset flag
        return true;
    }
    return false;
}
}

/**
 * Clear all static caches for development/testing
 * This forces fresh initialization of all services
 */
if (!function_exists('clearAllCaches')) {
function clearAllCaches()
{
    // Clear OPcache if available
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    // Clear static variables by forcing re-initialization
    // This is a development helper function
    error_log('All caches cleared - next request will use fresh initialization');
}
}

/**
 * Simple performance monitoring - log page load times
 * This helps track performance improvements
 */
if (!function_exists('logPagePerformance')) {
function logPagePerformance($pageName)
{
    static $startTime = null;

    if ($startTime === null) {
        $startTime = microtime(true);
    } else {
        $loadTime = microtime(true) - $startTime;
        error_log("Performance: {$pageName} loaded in " . round($loadTime * 1000, 2) . "ms");
    }
}
}

/**
 * Debug logging function - only logs when DEBUG is set to 'yes'
 *
 * @param string $message The message to log
 */
if (!function_exists('debugLog')) {
function debugLog($message)
{
    if (defined('DEBUG') && DEBUG === 'yes') {
        error_log($message);
    }
}
}

/**
 * Get CloudFront URL for an image
 *
 * @param string $imageKey The S3 object key for the image
 * @return string CloudFront URL or presigned URL in development mode
 */
if (!function_exists('getCloudFrontUrl')) {
function getCloudFrontUrl($imageKey)
{
    // In development mode, always use presigned URLs to bypass CloudFront cache
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
        debugLog("Development mode: using presigned URL for: {$imageKey}");
        return getCachedPresignedUrl($imageKey);
    }

    if (!defined('CLOUDFRONT_DOMAIN') || empty(CLOUDFRONT_DOMAIN)) {
        // Fallback to presigned URL if CloudFront not configured
        debugLog("CloudFront not configured, using presigned URL for: {$imageKey}");
        return getCachedPresignedUrl($imageKey);
    }

    // CloudFront origin path is /images, so strip the images/ prefix from the key
    $cloudFrontPath = str_replace('images/', '', $imageKey);
    debugLog("Using CloudFront URL for: {$imageKey} (CloudFront path: {$cloudFrontPath})");
    return 'https://' . CLOUDFRONT_DOMAIN . '/' . $cloudFrontPath;
}
}

/**
 * Format file size in human readable format
 *
 * @param int $bytes File size in bytes
 * @return string Formatted size
 */
if (!function_exists('formatFileSize')) {
function formatFileSize($bytes)
{
    if ($bytes == 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor(log($bytes, 1024));

    return sprintf('%.1f %s', $bytes / pow(1024, $factor), $units[$factor]);
}
}

/**
 * Get file extension from filename or path
 *
 * @param string $filename
 * @return string File extension (lowercase)
 */
if (!function_exists('getFileExtension')) {
function getFileExtension($filename)
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}
}

/**
 * Get MIME type for file extension
 *
 * @param string $extension File extension
 * @return string MIME type
 */
if (!function_exists('getMimeType')) {
function getMimeType($extension)
{
    $mimeTypes = [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'csv' => 'text/csv'
    ];

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}
}

/**
 * Check if AWS credentials are configured
 *
 * @return bool True if credentials file exists
 */
if (!function_exists('hasAwsCredentials')) {
function hasAwsCredentials()
{
    return file_exists(__DIR__ . '/../config/aws-credentials.php');
}
}

/**
 * Validate S3 object key format
 *
 * @param string $key S3 object key
 * @return bool True if valid
 */
if (!function_exists('isValidS3Key')) {
function isValidS3Key($key)
{
    // Basic validation for S3 key names
    if (empty($key) || strlen($key) > 1024) {
        return false;
    }

    // Check for invalid characters
    if (preg_match('/[\x00-\x1F\x7F]/', $key)) {
        return false;
    }

    return true;
}
}



/**
 * Simple YAML parser for our specific format
 */
if (!function_exists('parseSimpleYaml')) {
function parseSimpleYaml($yamlContent)
{
    $data = [];
    $lines = explode("\n", $yamlContent);
    $i = 0;

    while ($i < count($lines)) {
        $line = trim($lines[$i]);

        // Skip comments and empty lines
        if (empty($line) || $line[0] === '#') {
            $i++;
            continue;
        }

        // Handle claims array
        if ($line === 'claims:') {
            $data['claims'] = [];
            $i++; // Move to next line

            // Read claims array
            while ($i < count($lines)) {
                $nextLine = trim($lines[$i]);

                // End of claims array - check for non-claim lines
                if (empty($nextLine) || (!preg_match('/^- /', $nextLine) && !preg_match('/^  - /', $nextLine))) {
                    break;
                }

                // Start of a new claim (with or without proper indentation)
                if (preg_match('/^- /', $nextLine) || preg_match('/^  - /', $nextLine)) {
                    $claim = [];

                    // Check if the first property is on the same line as the dash
                    if (preg_match('/^- (\w+): (.+)$/', $nextLine, $matches)) {
                        $propKey = $matches[1];
                        $propValue = trim($matches[2]);

                        // Handle multi-line values (starts with |)
                        if ($propValue === '|') {
                            $multilineValue = '';
                            $i++; // Move to next line

                            // Read subsequent indented lines
                            while ($i < count($lines)) {
                                $nextLine = $lines[$i];
                                if (empty(trim($nextLine))) {
                                    $i++;
                                    break; // End of multiline block
                                }
                                if (preg_match('/^  (.*)$/', $nextLine, $matches)) {
                                    if ($multilineValue !== '') {
                                        $multilineValue .= ' ';
                                    }
                                    $multilineValue .= $matches[1];
                                    $i++;
                                } else {
                                    break; // End of multiline block
                                }
                            }
                            $claim[$propKey] = $multilineValue;
                        } else {
                            // Remove quotes if present
                            if (strlen($propValue) >= 2 && (($propValue[0] === '"' && $propValue[-1] === '"') || ($propValue[0] === "'" && $propValue[-1] === "'"))) {
                                $propValue = substr($propValue, 1, -1);
                            }
                            $claim[$propKey] = $propValue;
                        }
                    }

                    $i++; // Move to next line

                    // Read remaining claim properties
                    while ($i < count($lines)) {
                        $claimLine = trim($lines[$i]);

                        // End of this claim - check for next claim or end of array
                        if (empty($claimLine) || preg_match('/^- /', $claimLine) || preg_match('/^  - /', $claimLine)) {
                            break;
                        }

                        // Skip lines that don't have proper indentation (not part of this claim)
                        if (!preg_match('/^  /', $lines[$i])) {
                            break;
                        }

                        // Parse claim property
                        if (strpos($claimLine, ':') !== false) {
                            list($propKey, $propValue) = explode(':', $claimLine, 2);
                            $propKey = trim($propKey);
                            $propValue = trim($propValue);

                            // Handle multi-line values (starts with |)
                            if ($propValue === '|') {
                                $multilineValue = '';
                                $i++; // Move to next line

                                // Read subsequent indented lines
                                while ($i < count($lines)) {
                                    $nextLine = $lines[$i];
                                    if (empty(trim($nextLine))) {
                                        $i++;
                                        break; // End of multiline block
                                    }
                                    if (preg_match('/^  (.*)$/', $nextLine, $matches)) {
                                        if ($multilineValue !== '') {
                                            $multilineValue .= ' ';
                                        }
                                        $multilineValue .= $matches[1];
                                        $i++;
                                    } else {
                                        break; // End of multiline block
                                    }
                                }
                                $claim[$propKey] = $multilineValue;
                                continue;
                            }

                            // Remove quotes if present
                            if (strlen($propValue) >= 2 && (($propValue[0] === '"' && $propValue[-1] === '"') || ($propValue[0] === "'" && $propValue[-1] === "'"))) {
                                $propValue = substr($propValue, 1, -1);
                            }

                            // Handle empty values - set to empty string instead of skipping
                            $claim[$propKey] = $propValue;
                        }
                        $i++;
                    }

                    if (!empty($claim)) {
                        $data['claims'][] = $claim;
                    }
                    continue;
                }
                $i++;
            }
            continue;
        }

        // Parse key: value pairs
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Handle multi-line values (starts with |)
            if ($value === '|') {
                $multilineValue = '';
                $i++; // Move to next line

                // Read subsequent indented lines
                while ($i < count($lines)) {
                    $nextLine = $lines[$i];
                    if (empty(trim($nextLine))) {
                        $i++;
                        break; // End of multiline block
                    }
                    if (preg_match('/^  (.*)$/', $nextLine, $matches)) {
                        if ($multilineValue !== '') {
                            $multilineValue .= ' ';
                        }
                        $multilineValue .= $matches[1];
                        $i++;
                    } else {
                        break; // End of multiline block
                    }
                }
                $data[$key] = $multilineValue;
                continue;
            }

            // Handle regular values
            // Remove quotes if present
            if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
                $value = substr($value, 1, -1);
            }

            $data[$key] = $value;
        }
        $i++;
    }

    // Initialize claims array if not present (for backward compatibility)
    if (!isset($data['claims'])) {
        $data['claims'] = [];
    }

    return $data;
}
}

/**
 * Generate Open Graph meta tags for social media previews
 *
 * @param string $page Current page
 * @param array $data Page-specific data (item, user, etc.)
 * @return string HTML meta tags
 */
if (!function_exists('generateOpenGraphTags')) {
function generateOpenGraphTags($page, $data = [])
{
    // Detect environment and set appropriate base URL
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

    if ($host === 'claimit.stonekeep.com') {
        $baseUrl = 'https://claimit.stonekeep.com/';
    } else {
        $baseUrl = $protocol . '://' . $host . '/';
    }

    $siteName = 'ClaimIt';
    $defaultDescription = 'Find and share items in your community';

    $metaTags = [];

    switch ($page) {
        case 'item':
            if (isset($data['item'])) {
                $item = $data['item'];
                $metaTags['title'] = $item['title'] ?? 'Item on ClaimIt';
                $metaTags['description'] = $item['description'] ?? $defaultDescription;
                $metaTags['type'] = 'website';
                $metaTags['url'] = $baseUrl . '?page=item&id=' . urlencode($item['tracking_number']);

                // Add image if available
                if (isset($item['image_key']) && $item['image_key']) {
                    $awsService = getAwsService();
                    if ($awsService) {
                        try {
                            $imageUrl = $awsService->getPresignedUrl($item['image_key'], 3600);
                            $metaTags['image'] = $imageUrl;
                            $metaTags['image_width'] = '1200';
                            $metaTags['image_height'] = '630';
                        } catch (Exception $e) {
                            // Image not available, skip
                        }
                    }
                }
            } else {
                // Fallback when item data is not available
                $metaTags['title'] = 'Item on ClaimIt';
                $metaTags['description'] = $defaultDescription;
                $metaTags['type'] = 'website';
                $metaTags['url'] = $baseUrl;
            }
            break;

        case 'user-listings':
            if (isset($data['userName']) && isset($data['items'])) {
                $userName = $data['userName'];
                $itemCount = count($data['items']);
                $metaTags['title'] = $userName . "'s Items on ClaimIt";
                $metaTags['description'] = "View " . $userName . "'s " . $itemCount . " item" . ($itemCount !== 1 ? 's' : '') . " on ClaimIt";
                $metaTags['type'] = 'website';
                $metaTags['url'] = $baseUrl . '?page=user-listings&id=' . urlencode($data['userId'] ?? '');
            }
            break;

        case 'items':
            $metaTags['title'] = 'Browse Items on ClaimIt';
            $metaTags['description'] = 'Find free items and great deals in your community';
            $metaTags['type'] = 'website';
            $metaTags['url'] = $baseUrl . '?page=items';
            break;

        default:
            $metaTags['title'] = $siteName;
            $metaTags['description'] = $defaultDescription;
            $metaTags['type'] = 'website';
            $metaTags['url'] = $baseUrl;
            break;
    }

    // Generate HTML meta tags
    $html = '';

    // Ensure required keys exist with fallbacks
    $title = $metaTags['title'] ?? $siteName;
    $description = $metaTags['description'] ?? $defaultDescription;
    $type = $metaTags['type'] ?? 'website';
    $url = $metaTags['url'] ?? $baseUrl;

    // Basic meta tags
    $html .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta property="og:type" content="' . htmlspecialchars($type) . '">' . "\n";
    $html .= '<meta property="og:url" content="' . htmlspecialchars($url) . '">' . "\n";
    $html .= '<meta property="og:site_name" content="' . htmlspecialchars($siteName) . '">' . "\n";

    // Image meta tags
    if (isset($metaTags['image'])) {
        $html .= '<meta property="og:image" content="' . htmlspecialchars($metaTags['image']) . '">' . "\n";
        $html .= '<meta property="og:image:width" content="' . htmlspecialchars($metaTags['image_width']) . '">' . "\n";
        $html .= '<meta property="og:image:height" content="' . htmlspecialchars($metaTags['image_height']) . '">' . "\n";
    }

    // Twitter Card meta tags
    $html .= '<meta name="twitter:card" content="' . (isset($metaTags['image']) ? 'summary_large_image' : 'summary') . '">' . "\n";
    $html .= '<meta name="twitter:title" content="' . htmlspecialchars($metaTags['title']) . '">' . "\n";
    $html .= '<meta name="twitter:description" content="' . htmlspecialchars($metaTags['description']) . '">' . "\n";
    if (isset($metaTags['image'])) {
        $html .= '<meta name="twitter:image" content="' . htmlspecialchars($metaTags['image']) . '">' . "\n";
    }

    return $html;
}
}

/**
 * Add current user to item's claims list
 */
if (!function_exists('addClaimToItem')) {
function addClaimToItem($trackingNumber)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in to claim items');
    }

    // Check if user can claim this item
    if (!canUserClaim($trackingNumber, $currentUser['id'])) {
        throw new Exception('You cannot claim this item');
    }

    // Get current item data from database
    $item = getItemFromDb($trackingNumber);
    if (!$item) {
        throw new Exception('Item not found');
    }
    $data = $item; // For compatibility with email code below

    // Create new claim
    $newClaim = [
        'user_id' => $currentUser['id'],
        'user_name' => $currentUser['name'],
        'user_email' => $currentUser['email'],
        'claimed_at' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];

    // Save claim to database
    if (!createClaimInDb($trackingNumber, $newClaim)) {
        throw new Exception('Failed to save claim to database');
    }

    // Send email notification to item owner (if different from claimer)
    try {
        if ($data['user_id'] !== $currentUser['id']) {
            $emailService = getEmailService();
            if ($emailService) {
                // Get item owner information
                $itemOwner = [
                    'id' => $data['user_id'],
                    'email' => $data['user_email'] ?? '',
                    'name' => $data['user_name'] ?? 'Unknown'
                ];

                // Prepare item data for email
                $itemForEmail = [
                    'tracking_number' => $trackingNumber,
                    'title' => $data['title'] ?? 'Untitled Item',
                    'description' => $data['description'] ?? '',
                    'type' => $data['type'] ?? 'Unknown',
                    'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
                    'image_key' => $data['image_key'] ?? null
                ];

                // Send notification
                $emailService->sendItemClaimedNotification($itemOwner, $itemForEmail, $currentUser);
            }
        }
    } catch (Exception $e) {
        // Log error but don't fail the claim process
        error_log("Failed to send email notification for claim on item $trackingNumber: " . $e->getMessage());
    }

    return $newClaim;
}
}

/**
 * Remove a specific claim from an item (owner only)
 */
if (!function_exists('removeClaimFromItem')) {
function removeClaimFromItem($trackingNumber, $claimUserId)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }

    // Check if current user owns the item
    if (!currentUserOwnsItem($trackingNumber)) {
        throw new Exception('Only the item owner can remove claims');
    }

    // Find the claim in database
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $stmt = $pdo->prepare("
        UPDATE claims 
        SET status = 'removed', updated_at = ?
        WHERE item_tracking_number = ? AND user_id = ? AND status = 'active'
    ");

    $stmt->execute([date('Y-m-d H:i:s'), $trackingNumber, $claimUserId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Claim not found or already removed');
    }

    return true;
}
}

/**
 * Remove current user's own claim from an item
 */
if (!function_exists('removeMyClaim')) {
function removeMyClaim($trackingNumber)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }

    // Remove claim from database
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $stmt = $pdo->prepare("
        UPDATE claims 
        SET status = 'removed', updated_at = ?
        WHERE item_tracking_number = ? AND user_id = ? AND status = 'active'
    ");

    $stmt->execute([date('Y-m-d H:i:s'), $trackingNumber, $currentUser['id']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('You do not have an active claim on this item');
    }

    return true;
}
}

/**
 * Get all active claims for an item in chronological order
 */
if (!function_exists('getActiveClaims')) {
function getActiveClaims($trackingNumber)
{
    // Get claims from database - already uses the helper function
    return getClaimsForItem($trackingNumber);
}
}

/**
 * Get the primary (first) active claim for an item
 */
if (!function_exists('getPrimaryClaim')) {
function getPrimaryClaim($trackingNumber)
{
    $activeClaims = getActiveClaims($trackingNumber);
    return !empty($activeClaims) ? $activeClaims[0] : null;
}
}

/**
 * Check if a user has an active claim on an item
 */
if (!function_exists('isUserClaimed')) {
function isUserClaimed($trackingNumber, $userId)
{
    $activeClaims = getActiveClaims($trackingNumber);

    foreach ($activeClaims as $claim) {
        if ($claim['user_id'] === $userId) {
            return true;
        }
    }

    return false;
}
}

/**
 * Check if a user can claim an item
 */
if (!function_exists('canUserClaim')) {
function canUserClaim($trackingNumber, $userId)
{
    // User must be logged in
    if (!$userId) {
        return false;
    }

    // Check if user already has an active claim
    if (isUserClaimed($trackingNumber, $userId)) {
        return false;
    }

    // Check if user owns the item
    if (currentUserOwnsItem($trackingNumber)) {
        return false;
    }

    return true;
}
}

/**
 * Get user's position in the waitlist for an item
 */
if (!function_exists('getUserClaimPosition')) {
function getUserClaimPosition($trackingNumber, $userId)
{
    $activeClaims = getActiveClaims($trackingNumber);

    foreach ($activeClaims as $index => $claim) {
        if ($claim['user_id'] === $userId) {
            return $index + 1; // Position is 1-based
        }
    }

    return null; // User not in waitlist
}
}

/**
 * Convert data array back to YAML format
 */
if (!function_exists('convertToYaml')) {
function convertToYaml($data)
{
    $yaml = '';

    foreach ($data as $key => $value) {
        if ($key === 'claims') {
            // Handle claims array specially
            $yaml .= "claims:\n";
            if (is_array($value)) {
                foreach ($value as $claim) {
                    $yaml .= "  - user_id: " . $claim['user_id'] . "\n";
                    $yaml .= "    user_name: " . $claim['user_name'] . "\n";
                    $yaml .= "    user_email: " . $claim['user_email'] . "\n";
                    $yaml .= "    claimed_at: " . $claim['claimed_at'] . "\n";
                    $yaml .= "    status: " . $claim['status'] . "\n";
                    if (isset($claim['removed_at'])) {
                        $yaml .= "    removed_at: " . $claim['removed_at'] . "\n";
                    }
                    if (isset($claim['removed_by'])) {
                        $yaml .= "    removed_by: " . $claim['removed_by'] . "\n";
                    }
                }
            }
        } else {
            // Handle regular key-value pairs
            if (is_string($value) && (strpos($value, "\n") !== false || strpos($value, ':') !== false)) {
                // Multi-line or complex value
                $yaml .= $key . ": |\n";
                $lines = explode("\n", $value);
                foreach ($lines as $line) {
                    $yaml .= "  " . $line . "\n";
                }
            } else {
                $yaml .= $key . ": " . $value . "\n";
            }
        }
    }

    return $yaml;
}
}

/**
 * Get all items posted by a specific user (optimized version)
 * Uses the same efficient pattern as getAllItemsEfficiently
 */
if (!function_exists('getUserItemsEfficiently')) {
function getUserItemsEfficiently($userId, $includeGoneItems = false)
{
    static $userItemsCache = [];
    static $cacheTime = [];
    static $cacheKey = null;

    // Create cache key based on parameters
    $currentCacheKey = md5($userId . '_' . ($includeGoneItems ? 'with_gone' : 'without_gone'));

    // Use longer cache for better performance (5 minutes)
    $cacheExpiry = 300; // 5 minutes cache for user items

    // Check cache first (only if cache key matches)
    if (isset($userItemsCache[$currentCacheKey]) && isset($cacheTime[$currentCacheKey]) && (time() - $cacheTime[$currentCacheKey]) < $cacheExpiry) {
        return $userItemsCache[$currentCacheKey];
    }

    try {
        $startTime = microtime(true);

        // Get user's items from database
        $dbItems = getUserItemsFromDb($userId, $includeGoneItems);
        debugLog("Loaded " . count($dbItems) . " items for user {$userId} from database");

        // Get all claims for these items
        $trackingNumbers = array_column($dbItems, 'tracking_number');
        if (!empty($trackingNumbers)) {
            $pdo = getDbConnection();
            $placeholders = implode(',', array_fill(0, count($trackingNumbers), '?'));
            $stmt = $pdo->prepare("SELECT * FROM claims WHERE item_tracking_number IN ($placeholders) AND status = 'active' ORDER BY claimed_at ASC");
            $stmt->execute($trackingNumbers);
            $allClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $allClaims = [];
        }

        // Group claims by item
        $claimsByItem = [];
        foreach ($allClaims as $claim) {
            $trackingNumber = $claim['item_tracking_number'];
            if (!isset($claimsByItem[$trackingNumber])) {
                $claimsByItem[$trackingNumber] = [];
            }
            $claimsByItem[$trackingNumber][] = $claim;
        }

        // Process each item
        $items = [];
        $currentUser = getCurrentUser();

        foreach ($dbItems as $dbItem) {
            $trackingNumber = $dbItem['tracking_number'];

            // Get image key and URL
            $imageKey = $dbItem['image_file'];
            $imageUrl = null;
            if ($imageKey) {
                $imageUrl = getCloudFrontUrl($imageKey);
            }

            // Pre-compute item states
            $isItemGone = (bool)$dbItem['gone'];
            $canEditItem = canUserEditItem($dbItem['user_id']);

            // Get claims for this item
            $activeClaims = $claimsByItem[$trackingNumber] ?? [];
            $primaryClaim = !empty($activeClaims) ? $activeClaims[0] : null;

            // User-specific claim data
            $isUserClaimed = false;
            $canUserClaim = false;
            if ($currentUser) {
                foreach ($activeClaims as $claim) {
                    if ($claim['user_id'] === $currentUser['id']) {
                        $isUserClaimed = true;
                        break;
                    }
                }
                $canUserClaim = !$isItemGone && !$isUserClaimed;
            }

            $items[] = [
                'tracking_number' => $trackingNumber,
                'title' => $dbItem['title'],
                'description' => $dbItem['description'],
                'price' => $dbItem['price'],
                'contact_email' => $dbItem['contact_email'],
                'image_key' => $imageKey,
                'image_url' => $imageUrl,
                'image_width' => $dbItem['image_width'],
                'image_height' => $dbItem['image_height'],
                'posted_date' => $dbItem['submitted_at'],
                'submitted_timestamp' => $dbItem['submitted_timestamp'],
                'user_id' => $dbItem['user_id'],
                'user_name' => $dbItem['user_name'],
                'user_email' => $dbItem['user_email'],
                'gone' => $dbItem['gone'],
                'gone_at' => $dbItem['gone_at'],
                'gone_by' => $dbItem['gone_by'],
                'relisted_at' => $dbItem['relisted_at'],
                'relisted_by' => $dbItem['relisted_by'],
                'is_item_gone' => $isItemGone,
                'can_edit_item' => $canEditItem,
                'active_claims' => $activeClaims,
                'primary_claim' => $primaryClaim,
                'is_user_claimed' => $isUserClaimed,
                'can_user_claim' => $canUserClaim
            ];
        }

        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2);
        debugLog("Performance: Loaded user items from database in {$totalTime}ms");

        // Cache the results
        $userItemsCache[$currentCacheKey] = $items;
        $cacheTime[$currentCacheKey] = time();

        return $items;
    } catch (Exception $e) {
        error_log('Error loading user items efficiently: ' . $e->getMessage());
        return [];
    }
}
}

/**
 * Get all items that a user has claimed or is on the waiting list for (optimized version)
 * Uses the same efficient pattern as getAllItemsEfficiently
 */
if (!function_exists('getItemsClaimedByUserOptimized')) {
function getItemsClaimedByUserOptimized($userId)
{
    static $claimedItemsCache = [];
    static $cacheTime = [];

    // Create cache key
    $currentCacheKey = md5($userId . '_claimed');

    // Use longer cache (5 minutes)
    $cacheExpiry = 300;

    // Check cache first
    if (isset($claimedItemsCache[$currentCacheKey]) && isset($cacheTime[$currentCacheKey]) && (time() - $cacheTime[$currentCacheKey]) < $cacheExpiry) {
        return $claimedItemsCache[$currentCacheKey];
    }

    try {
        $startTime = microtime(true);

        // Get claimed items from database
        $dbItems = getClaimedItemsByUser($userId);
        debugLog("Loaded " . count($dbItems) . " claimed items for user {$userId} from database");

        // Get all claims for these items  to determine position
        $trackingNumbers = array_column($dbItems, 'tracking_number');
        if (!empty($trackingNumbers)) {
            $pdo = getDbConnection();
            $placeholders = implode(',', array_fill(0, count($trackingNumbers), '?'));
            $stmt = $pdo->prepare("SELECT * FROM claims WHERE item_tracking_number IN ($placeholders) AND status = 'active' ORDER BY claimed_at ASC");
            $stmt->execute($trackingNumbers);
            $allClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $allClaims = [];
        }

        // Group claims by item
        $claimsByItem = [];
        foreach ($allClaims as $claim) {
            $trackingNumber = $claim['item_tracking_number'];
            if (!isset($claimsByItem[$trackingNumber])) {
                $claimsByItem[$trackingNumber] = [];
            }
            $claimsByItem[$trackingNumber][] = $claim;
        }

        // Process each item
        $claimedItems = [];

        foreach ($dbItems as $dbItem) {
            $trackingNumber = $dbItem['tracking_number'];

            // Get image key and URL
            $imageKey = $dbItem['image_file'];
            $imageUrl = null;
            if ($imageKey) {
                $imageUrl = getCloudFrontUrl($imageKey);
            }

            // Find user's claim and position
            $activeClaims = $claimsByItem[$trackingNumber] ?? [];
            $userClaim = null;
            $claimPosition = 0;

            foreach ($activeClaims as $index => $claim) {
                if ($claim['user_id'] === $userId) {
                    $userClaim = $claim;
                    $claimPosition = $index + 1;
                    break;
                }
            }

            $claimedItems[] = [
                'tracking_number' => $trackingNumber,
                'title' => $dbItem['title'],
                'description' => $dbItem['description'],
                'price' => $dbItem['price'],
                'contact_email' => $dbItem['contact_email'],
                'image_key' => $imageKey,
                'image_url' => $imageUrl,
                'image_width' => $dbItem['image_width'],
                'image_height' => $dbItem['image_height'],
                'posted_date' => $dbItem['submitted_at'],
                'user_id' => $dbItem['user_id'],
                'user_name' => $dbItem['user_name'],
                'user_email' => $dbItem['user_email'],
                'claim' => $userClaim,
                'claim_position' => $claimPosition,
                'is_primary_claim' => $claimPosition === 1,
                'total_claims' => count($activeClaims)
            ];
        }

        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2);
        debugLog("Performance: Loaded claimed items from database in {$totalTime}ms");

        // Cache the results
        $claimedItemsCache[$currentCacheKey] = $claimedItems;
        $cacheTime[$currentCacheKey] = time();

        return $claimedItems;
    } catch (Exception $e) {
        error_log('Error loading claimed items: ' . $e->getMessage());
        return [];
    }
}
}

/**
 * Get all items that a user has claimed or is on the waiting list for
 */
if (!function_exists('getItemsClaimedByUser')) {
function getItemsClaimedByUser($userId)
{
    $awsService = getAwsService();
    if (!$awsService) {
        return [];
    }

    $claimedItems = [];

    try {
        $result = $awsService->listObjects();
        $objects = $result['objects'] ?? [];

        foreach ($objects as $object) {
            // Only process YAML files
            if (!str_ends_with($object['key'], '.yaml')) {
                continue;
            }

            try {
                $trackingNumber = basename($object['key'], '.yaml');

                // Get YAML content
                $yamlObject = $awsService->getObject($object['key']);
                $yamlContent = $yamlObject['content'];

                // Parse YAML content
                $data = parseSimpleYaml($yamlContent);
                if (!$data || !isset($data['description']) || !isset($data['price']) || !isset($data['contact_email'])) {
                    continue;
                }

                // Check if user has claimed this item
                $activeClaims = getActiveClaims($trackingNumber);
                $userClaim = null;
                $claimPosition = null;

                foreach ($activeClaims as $index => $claim) {
                    if ($claim['user_id'] === $userId) {
                        $userClaim = $claim;
                        $claimPosition = $index + 1; // 1-based position
                        break;
                    }
                }

                if ($userClaim) {
                    // Check if corresponding image exists
                    $imageKey = null;
                    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    foreach ($imageExtensions as $ext) {
                        $possibleImageKey = 'images/' . $trackingNumber . '.' . $ext;
                        foreach ($objects as $imgObj) {
                            if ($imgObj['key'] === $possibleImageKey) {
                                $imageKey = $possibleImageKey; // Full S3 path with images/ prefix
                                break 2;
                            }
                        }
                    }

                    $title = $data['title'] ?? $data['description'] ?? 'Untitled';
                    $description = $data['description'];

                    $claimedItems[] = [
                        'tracking_number' => $trackingNumber,
                        'title' => $title,
                        'description' => $description,
                        'price' => $data['price'],
                        'contact_email' => $data['contact_email'],
                        'image_key' => $imageKey,
                        'posted_date' => $data['submitted_at'] ?? 'Unknown',
                        'yaml_key' => $object['key'],
                        'user_id' => $data['user_id'] ?? 'legacy_user',
                        'user_name' => $data['user_name'] ?? 'Legacy User',
                        'user_email' => $data['user_email'] ?? $data['contact_email'] ?? '',
                        'claim' => $userClaim,
                        'claim_position' => $claimPosition,
                        'is_primary_claim' => $claimPosition === 1,
                        'total_claims' => count($activeClaims)
                    ];
                }
            } catch (Exception $e) {
                // Skip invalid YAML files
                continue;
            }
        }

        // Sort items by claim date (newest first)
        usort($claimedItems, function ($a, $b) {
            $dateA = strtotime($a['claim']['claimed_at']);
            $dateB = strtotime($b['claim']['claimed_at']);
            return $dateB - $dateA;
        });
    } catch (Exception $e) {
        // Return empty array on error
        return [];
    }

    return $claimedItems;
}
}

/**
 * Get ordinal suffix for numbers (1st, 2nd, 3rd, etc.)
 */
if (!function_exists('getOrdinalSuffix')) {
function getOrdinalSuffix($number)
{
    if ($number >= 11 && $number <= 13) {
        return 'th';
    }

    switch ($number % 10) {
        case 1:
            return 'st';
        case 2:
            return 'nd';
        case 3:
            return 'rd';
        default:
            return 'th';
    }
}
}

/**
 * Truncate text to a specified length with ellipsis
 *
 * @param string $text The text to truncate
 * @param int $length The maximum length
 * @return string The truncated text
 */
if (!function_exists('truncateText')) {
function truncateText($text, $length = 100)
{
    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length) . '...';
}
}

/**
 * Get user display name from S3 user settings file
 *
 * @param string $userId The Google user ID
 * @param string $defaultName The default name to use if no custom name is set
 * @return string The display name to use
 */
if (!function_exists('getUserDisplayName')) {
function getUserDisplayName($userId, $defaultName = '')
{
    try {
        $user = getUserById($userId);

        if (!$user) {
            return $defaultName;
        }

        // Return custom display name if set, otherwise default
        if (isset($user['display_name']) && !empty($user['display_name'])) {
            return $user['display_name'];
        }

        return $defaultName;
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Error getting user display name for user $userId: " . $e->getMessage());
        return $defaultName;
    }
}
}

/**
 * Get user's zip code from settings
 *
 * @param string $userId The Google user ID
 * @return string The user's zip code or empty string if not set
 */
if (!function_exists('getUserZipcode')) {
function getUserZipcode($userId)
{
    try {
        $user = getUserById($userId);

        if (!$user) {
            return '';
        }

        // Return zipcode if set, otherwise empty string
        if (isset($user['zipcode']) && !empty($user['zipcode'])) {
            return $user['zipcode'];
        }

        return '';
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Error getting user zipcode for user $userId: " . $e->getMessage());
        return '';
    }
}
}

/**
 * Get a single item by tracking number
 *
 * @param string $trackingNumber The item tracking number
 * @return array|null The item data or null if not found
 */
if (!function_exists('getItem')) {
function getItem($trackingNumber)
{
    // Simple wrapper around getItemFromDb for backward compatibility
    return getItemFromDb($trackingNumber);
}
}

/**
 * Resize and compress image to keep it under specified size limit
 *
 * @param string $sourcePath Path to the source image file
 * @param string $targetPath Path where the resized image should be saved
 * @param int $maxSizeBytes Maximum file size in bytes (default: 500KB)
 * @param int $maxWidth Maximum width in pixels (default: 1200)
 * @param int $maxHeight Maximum height in pixels (default: 1200)
 * @param int $quality JPEG quality (default: 85)
 * @return bool True on success, false on failure
 */
if (!function_exists('resizeImageToFitSize')) {
function resizeImageToFitSize($sourcePath, $targetPath, $maxSizeBytes = 512000, $maxWidth = 1200, $maxHeight = 1200, $quality = 85)
{
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        error_log('GD extension not available for image resizing');
        return false;
    }

    // Get image info
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        error_log('Invalid image file: ' . $sourcePath);
        return false;
    }

    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];

    // Create image resource based on type
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            error_log('Unsupported image type: ' . $mimeType);
            return false;
    }

    if (!$sourceImage) {
        error_log('Failed to create image resource from: ' . $sourcePath);
        return false;
    }

    // Calculate new dimensions while maintaining aspect ratio
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = (int)($originalWidth * $ratio);
    $newHeight = (int)($originalHeight * $ratio);

    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    if (!$newImage) {
        imagedestroy($sourceImage);
        error_log('Failed to create new image resource');
        return false;
    }

    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefill($newImage, 0, 0, $transparent);
    }

    // Resize the image
    if (!imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight)) {
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        error_log('Failed to resize image');
        return false;
    }

    // Try different quality levels to get under size limit
    $currentQuality = $quality;
    $attempts = 0;
    $maxAttempts = 10;

    do {
        // Save the image
        $success = false;
        if ($mimeType === 'image/jpeg') {
            $success = imagejpeg($newImage, $targetPath, $currentQuality);
        } elseif ($mimeType === 'image/png') {
            // For PNG, we can't control quality directly, so we'll use a different approach
            $success = imagepng($newImage, $targetPath, 9); // PNG compression level 0-9
        } elseif ($mimeType === 'image/gif') {
            $success = imagegif($newImage, $targetPath);
        }

        if (!$success) {
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            error_log('Failed to save resized image');
            return false;
        }

        // Check file size
        $fileSize = filesize($targetPath);

        if ($fileSize <= $maxSizeBytes) {
            // Success! File is under the size limit
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            return true;
        }

        // File is still too large, reduce quality and try again
        $currentQuality -= 10;
        $attempts++;
    } while ($currentQuality > 10 && $attempts < $maxAttempts);

    // If we still can't get under the size limit, try reducing dimensions
    if ($fileSize > $maxSizeBytes && $attempts >= $maxAttempts) {
        imagedestroy($sourceImage);
        imagedestroy($newImage);

        // Try with smaller dimensions
        $newMaxWidth = (int)($maxWidth * 0.8);
        $newMaxHeight = (int)($maxHeight * 0.8);

        if ($newMaxWidth > 200 && $newMaxHeight > 200) {
            return resizeImageToFitSize($sourcePath, $targetPath, $maxSizeBytes, $newMaxWidth, $newMaxHeight, $quality);
        }
    }

    imagedestroy($sourceImage);
    imagedestroy($newImage);

    // If we get here, we couldn't get under the size limit
    error_log('Could not resize image to fit within size limit');
    return false;
}
}

/**
 * Check if the current user is an administrator
 */
if (!function_exists('isAdmin')) {
function isAdmin()
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }

    $userId = $currentUser['id'] ?? null;

    // First check if user is the master admin from config
    if ($userId === ADMIN_USER_ID) {
        return true;
    }

    // Then check if user has admin flag in database
    if (isset($currentUser['is_admin']) && $currentUser['is_admin']) {
        return true;
    }

    return false;
}
}

/**
 * Check if the current user can edit/delete an item (either owner or admin)
 */
if (!function_exists('canUserEditItem')) {
function canUserEditItem($itemUserId)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }

    // User can edit if they own the item OR if they are an admin
    return ($currentUser['id'] === $itemUserId) || isAdmin();
}
}

/**
 * Rotate an image 90 degrees clockwise using GD library
 *
 * @param string $imageContent The binary image content
 * @param string $contentType The MIME type of the image
 * @return string|false The rotated image content or false on failure
 */
if (!function_exists('rotateImage90Degrees')) {
function rotateImage90Degrees($imageContent, $contentType)
{
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        error_log('GD extension is not loaded');
        return false;
    }

    // Create image resource from content
    $sourceImage = imagecreatefromstring($imageContent);
    if ($sourceImage === false) {
        error_log('Failed to create image from string');
        return false;
    }

    // Get original dimensions
    $originalWidth = imagesx($sourceImage);
    $originalHeight = imagesy($sourceImage);

    // Rotate the image 90 degrees clockwise
    $rotatedImage = imagerotate($sourceImage, -90, 0);
    if ($rotatedImage === false) {
        error_log('Failed to rotate image');
        imagedestroy($sourceImage);
        return false;
    }

    // Clean up the original image
    imagedestroy($sourceImage);

    // Capture the rotated image as a string
    ob_start();

    // Determine output format based on content type
    $success = false;
    switch (strtolower($contentType)) {
        case 'image/jpeg':
        case 'image/jpg':
            $success = imagejpeg($rotatedImage, null, 90); // 90% quality
            break;
        case 'image/png':
            $success = imagepng($rotatedImage, null, 6); // Compression level 6
            break;
        case 'image/gif':
            $success = imagegif($rotatedImage);
            break;
        case 'image/webp':
            if (function_exists('imagewebp')) {
                $success = imagewebp($rotatedImage, null, 90);
            }
            break;
        default:
            // Default to JPEG if format is unknown
            $success = imagejpeg($rotatedImage, null, 90);
            break;
    }

    if (!$success) {
        error_log('Failed to output rotated image');
        imagedestroy($rotatedImage);
        ob_end_clean();
        return false;
    }

    $rotatedContent = ob_get_contents();
    ob_end_clean();

    // Clean up the rotated image
    imagedestroy($rotatedImage);

    return $rotatedContent;
}
}

/**
 * Mark an item as gone
 *
 * @param string $trackingNumber The item tracking number
 * @return bool True on success, false on failure
 */
if (!function_exists('markItemAsGone')) {
function markItemAsGone($trackingNumber)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }

    // Check if user owns this item
    if (!currentUserOwnsItem($trackingNumber)) {
        throw new Exception('You can only mark your own items as gone');
    }

    // Update item in database
    $updates = [
        'gone' => 1,
        'gone_at' => date('Y-m-d H:i:s'),
        'gone_by' => $currentUser['id']
    ];

    if (!updateItemInDb($trackingNumber, $updates)) {
        throw new Exception('Failed to update item in database');
    }

    return true;
}
}

/**
 * Re-list an item (mark as not gone)
 *
 * @param string $trackingNumber The item tracking number
 * @return bool True on success, false on failure
 */
if (!function_exists('relistItem')) {
function relistItem($trackingNumber)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }

    // Check if user owns this item
    if (!currentUserOwnsItem($trackingNumber)) {
        throw new Exception('You can only re-list your own items');
    }

    // Update item in database
    $updates = [
        'gone' => 0,
        'relisted_at' => date('Y-m-d H:i:s'),
        'relisted_by' => $currentUser['id']
    ];

    if (!updateItemInDb($trackingNumber, $updates)) {
        throw new Exception('Failed to update item in database');
    }

    return true;
}
}

/**
 * Check if an item is marked as gone
 *
 * @param array $itemData The item data array
 * @return bool True if item is gone, false otherwise
 */
if (!function_exists('isItemGone')) {
function isItemGone($itemData)
{
    // Handle both database boolean format and legacy YAML 'yes'/'no' format
    return isset($itemData['gone']) && ($itemData['gone'] === true || $itemData['gone'] === 1 || $itemData['gone'] === 'yes');
}
}

/**
 * Get user setting for email notifications
 *
 * @param string $userId The user ID
 * @return bool True if user wants email notifications, false otherwise
 */
if (!function_exists('getUserEmailNotifications')) {
function getUserEmailNotifications($userId)
{
    // Check cache first
    $cached = getUserSettingsCache($userId, 'email_notifications');
    if ($cached !== null) {
        return $cached;
    }

    try {
        $user = getUserById($userId);

        if (!$user) {
            $result = false; // Default to no email notifications
            setUserSettingsCache($userId, $result, 'email_notifications');
            return $result;
        }

        // Return setting from database
        $result = $user['email_notifications'] ?? false;
        setUserSettingsCache($userId, $result, 'email_notifications');
        return $result;
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Error getting user email notifications setting for user $userId: " . $e->getMessage());
        $result = false; // Default to no email notifications
        setUserSettingsCache($userId, $result, 'email_notifications');
        return $result;
    }
}
}

/**
 * Get user's new listing notification preference
 */
if (!function_exists('getUserNewListingNotifications')) {
function getUserNewListingNotifications($userId)
{
    // Check cache first
    $cached = getUserSettingsCache($userId, 'new_listing_notifications');
    if ($cached !== null) {
        return $cached;
    }

    try {
        $user = getUserById($userId);

        if (!$user) {
            $result = false; // Default to no new listing notifications
            setUserSettingsCache($userId, $result, 'new_listing_notifications');
            return $result;
        }

        // Return setting from database
        $result = $user['new_listing_notifications'] ?? false;
        setUserSettingsCache($userId, $result, 'new_listing_notifications');
        return $result;
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Error getting user new listing notifications setting for user $userId: " . $e->getMessage());
        $result = false; // Default to no new listing notifications
        setUserSettingsCache($userId, $result, 'new_listing_notifications');
        return $result;
    }
}
}

/**
 * Get user setting for showing gone items
 *
 * @param string $userId The user ID
 * @return bool True if user wants to show gone items, false otherwise
 */
if (!function_exists('getUserShowGoneItems')) {
function getUserShowGoneItems($userId)
{
    // Check cache first
    $cached = getUserSettingsCache($userId, 'show_gone_items');
    if ($cached !== null) {
        return $cached;
    }

    try {
        $user = getUserById($userId);

        if (!$user) {
            $result = false; // Default to not showing gone items
            setUserSettingsCache($userId, $result, 'show_gone_items');
            return $result;
        }

        // Return setting from database
        $result = $user['show_gone_items'] ?? false;
        setUserSettingsCache($userId, $result, 'show_gone_items');
        return $result;
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Error getting user show gone items setting for user $userId: " . $e->getMessage());
        $result = false; // Default to not showing gone items
        setUserSettingsCache($userId, $result, 'show_gone_items');
        return $result;
    }
}
}

/**
 * Save user setting for showing gone items
 *
 * @param string $userId The user ID
 * @param bool $showGoneItems Whether to show gone items
 * @return bool True on success, false on failure
 */
if (!function_exists('saveUserShowGoneItems')) {
function saveUserShowGoneItems($userId, $showGoneItems)
{
    try {
        $user = getUserById($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        // Update the setting in database
        $user['show_gone_items'] = $showGoneItems;

        return updateUser($userId, $user);
    } catch (Exception $e) {
        error_log("Error saving user show gone items setting for user $userId: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Get all images for a specific item
 * Returns array of image keys sorted by index (primary first, then -1, -2, etc.)
 *
 * @param string $trackingNumber The tracking number of the item
 * @return array Array of image keys (with full S3 path including 'images/' prefix) or empty array
 */
if (!function_exists('getItemImages')) {
function getItemImages($trackingNumber)
{
    try {
        $awsService = getAwsService();
        if (!$awsService) {
            return [];
        }

        // Get all objects in the images/ directory
        $result = $awsService->listObjects('images/', 1000);
        $objects = $result['objects'] ?? [];

        $images = [];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        // Find all images matching this tracking number
        foreach ($objects as $object) {
            $key = $object['key'];

            // Check each extension
            foreach ($imageExtensions as $ext) {
                // Match primary image: images/TRACKINGNUM.ext
                if ($key === "images/{$trackingNumber}.{$ext}") {
                    $images[] = [
                        'key' => $key, // Store full S3 path with images/ prefix
                        'index' => null, // Primary image has no index
                    ];
                    break;
                }

                // Match additional images: images/TRACKINGNUM-N.ext
                $pattern = "/^images\/" . preg_quote($trackingNumber, '/') . "-(\d+)\.{$ext}$/";
                if (preg_match($pattern, $key, $matches)) {
                    $images[] = [
                        'key' => $key, // Store full S3 path with images/ prefix
                        'index' => (int)$matches[1],
                    ];
                    break;
                }
            }
        }

        // Sort: primary first (null index), then by index number
        usort($images, function ($a, $b) {
            if ($a['index'] === null) {
                return -1;
            }
            if ($b['index'] === null) {
                return 1;
            }
            return $a['index'] - $b['index'];
        });

        // Return just the keys (with full S3 path)
        return array_map(function ($img) {
            return $img['key'];
        }, $images);
    } catch (Exception $e) {
        error_log("Error getting images for item {$trackingNumber}: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Extract image index from image key
 * Returns null for primary image, integer for additional images
 *
 * @param string $imageKey The image key (with or without 'images/' prefix)
 * @return int|null The image index or null for primary
 */
if (!function_exists('getImageIndex')) {
function getImageIndex($imageKey)
{
    // Remove 'images/' prefix if present
    $imageKey = str_replace('images/', '', $imageKey);

    // Pattern: TRACKINGNUM-INDEX.ext where tracking number is YmdHis or YmdHis-xxxx
    // Format examples:
    //   New format with random suffix:
    //     20251115171627-5241.jpg -> null (primary, -5241 is the random suffix)
    //     20251115171627-5241-1.jpg -> 1 (additional image)
    //     20251115171627-5241-12.jpg -> 12 (additional image)
    //   Old format without random suffix:
    //     20251115171627.jpg -> null (primary)
    //     20251115171627-1.jpg -> 1 (additional image)
    //     20251115171627-12.jpg -> 12 (additional image)

    // First, check if it's a new format primary image: YmdHis-xxxx.ext (exactly 4 hex chars after dash)
    if (preg_match('/^\d{14}-[a-f0-9]{4}\.[^.]+$/i', $imageKey)) {
        return null; // Primary image with random suffix
    }

    // Next, try to match new format additional image: YmdHis-xxxx-INDEX.ext
    if (preg_match('/-[a-f0-9]{4}-(\d+)\.[^.]+$/i', $imageKey, $matches)) {
        return (int)$matches[1];
    }

    // Then, try to match old format additional image: YmdHis-INDEX.ext
    // Only match if it's after exactly 14 digits (the YmdHis part)
    if (preg_match('/^\d{14}-(\d+)\.[^.]+$/', $imageKey, $matches)) {
        return (int)$matches[1];
    }

    return null; // Primary image (old format without suffix)
}
}

/**
 * Delete a specific image from S3
 * Prevents deleting the primary/last image
 *
 * @param string $trackingNumber The tracking number
 * @param int|null $imageIndex The image index (null for primary)
 * @return bool Success or failure
 * @throws Exception If trying to delete primary or last image
 */
if (!function_exists('deleteImageFromS3')) {
function deleteImageFromS3($trackingNumber, $imageIndex)
{
    $awsService = getAwsService();
    if (!$awsService) {
        throw new Exception('AWS service not available');
    }

    // Get all images for this item
    $images = getItemImages($trackingNumber);

    if (count($images) <= 1) {
        throw new Exception('Cannot delete the last image. Items must have at least one image.');
    }

    // Prevent deleting primary image
    if ($imageIndex === null || $imageIndex === 0) {
        throw new Exception('Cannot delete the primary image. Add a new image first, then delete this one.');
    }

    // Find the image to delete
    $imageToDelete = null;
    foreach ($images as $imageKey) {
        if (getImageIndex($imageKey) === $imageIndex) {
            $imageToDelete = $imageKey;
            break;
        }
    }

    if (!$imageToDelete) {
        throw new Exception('Image not found');
    }

    // Delete from S3
    $fullKey = 'images/' . $imageToDelete;
    $awsService->deleteObject($fullKey);

    // Invalidate CloudFront cache
    try {
        $awsService->createInvalidation([$imageToDelete]);
    } catch (Exception $e) {
        error_log("CloudFront invalidation failed for {$imageToDelete}: " . $e->getMessage());
    }

    return true;
}
}

/**
 * Get the next available image index for an item
 *
 * @param string $trackingNumber The tracking number
 * @return int The next available index (1, 2, 3, etc.)
 */
if (!function_exists('getNextImageIndex')) {
function getNextImageIndex($trackingNumber)
{
    $images = getItemImages($trackingNumber);

    if (empty($images)) {
        return 1;
    }

    $maxIndex = 0;
    foreach ($images as $imageKey) {
        $index = getImageIndex($imageKey);
        if ($index !== null && $index > $maxIndex) {
            $maxIndex = $index;
        }
    }

    return $maxIndex + 1;
}
}

// ============================================================================
// DATABASE HELPER FUNCTIONS FOR ITEMS AND CLAIMS
// ============================================================================

/**
 * Get all items from database with optional filtering
 *
 * @param bool $includeGone Whether to include items marked as gone
 * @return array Array of items with all fields
 */
if (!function_exists('getAllItemsFromDb')) {
function getAllItemsFromDb($includeGone = false)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $sql = "SELECT * FROM items";
        if (!$includeGone) {
            $sql .= " WHERE gone = 0";
        }
        $sql .= " ORDER BY submitted_timestamp DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting items from database: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get items by user ID from database
 *
 * @param string $userId The user ID
 * @param bool $includeGone Whether to include items marked as gone
 * @return array Array of items
 */
if (!function_exists('getUserItemsFromDb')) {
function getUserItemsFromDb($userId, $includeGone = false)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $sql = "SELECT * FROM items WHERE user_id = ?";
        if (!$includeGone) {
            $sql .= " AND gone = 0";
        }
        $sql .= " ORDER BY submitted_timestamp DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting user items from database: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get all users from database
 * @return array Array of all users
 */
if (!function_exists('getAllUsers')) {
function getAllUsers()
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting all users: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get stats for user's items (always includes all items, even gone ones)
 * This provides accurate counts for the dashboard regardless of display preferences
 *
 * @param string $userId The user's ID
 * @return array Array with keys: total, free, for_sale, gone, with_claims
 */
if (!function_exists('getUserItemStats')) {
function getUserItemStats($userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [
            'total' => 0,
            'free' => 0,
            'for_sale' => 0,
            'gone' => 0,
            'with_claims' => 0
        ];
    }

    try {
        // Get all stats in a single query for efficiency
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN price = 0 THEN 1 ELSE 0 END) as free,
                SUM(CASE WHEN price > 0 THEN 1 ELSE 0 END) as for_sale,
                SUM(CASE WHEN gone = 1 THEN 1 ELSE 0 END) as gone,
                COUNT(DISTINCT CASE 
                    WHEN c.id IS NOT NULL THEN i.tracking_number 
                    ELSE NULL 
                END) as with_claims
            FROM items i
            LEFT JOIN claims c ON i.tracking_number = c.item_tracking_number 
                AND c.status = 'active'
            WHERE i.user_id = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int)($result['total'] ?? 0),
            'free' => (int)($result['free'] ?? 0),
            'for_sale' => (int)($result['for_sale'] ?? 0),
            'gone' => (int)($result['gone'] ?? 0),
            'with_claims' => (int)($result['with_claims'] ?? 0)
        ];
    } catch (Exception $e) {
        error_log("Error getting user item stats: " . $e->getMessage());
        return [
            'total' => 0,
            'free' => 0,
            'for_sale' => 0,
            'gone' => 0,
            'with_claims' => 0
        ];
    }
}
}

/**
 * Get single item by tracking number from database
 *
 * @param string $trackingNumber The item tracking number
 * @return array|null Item data or null if not found
 */
if (!function_exists('getItemFromDb')) {
function getItemFromDb($trackingNumber)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM items WHERE tracking_number = ?");
        $stmt->execute([$trackingNumber]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    } catch (Exception $e) {
        error_log("Error getting item from database: " . $e->getMessage());
        return null;
    }
}
}

/**
 * Create new item in database
 *
 * @param array $itemData Item data
 * @return bool Success status
 */
if (!function_exists('createItemInDb')) {
function createItemInDb($itemData)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO items (
                tracking_number, title, description, price, contact_email,
                image_file, image_width, image_height,
                user_id, user_name, user_email,
                submitted_at, submitted_timestamp,
                gone, gone_at, gone_by, relisted_at, relisted_by,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $itemData['tracking_number'],
            $itemData['title'],
            $itemData['description'],
            $itemData['price'] ?? 0,
            $itemData['contact_email'],
            $itemData['image_file'] ?? null,
            $itemData['image_width'] ?? null,
            $itemData['image_height'] ?? null,
            $itemData['user_id'],
            $itemData['user_name'],
            $itemData['user_email'],
            $itemData['submitted_at'],
            $itemData['submitted_timestamp'],
            isset($itemData['gone']) ? (int)$itemData['gone'] : 0,
            $itemData['gone_at'] ?? null,
            $itemData['gone_by'] ?? null,
            $itemData['relisted_at'] ?? null,
            $itemData['relisted_by'] ?? null,
            $now,
            $now
        ]);
    } catch (Exception $e) {
        error_log("Error creating item in database: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Update item in database
 *
 * @param string $trackingNumber The item tracking number
 * @param array $updates Fields to update
 * @return bool Success status
 */
if (!function_exists('updateItemInDb')) {
function updateItemInDb($trackingNumber, $updates)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $updates['updated_at'] = date('Y-m-d H:i:s');

        $fields = [];
        $values = [];
        foreach ($updates as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $trackingNumber;

        $sql = "UPDATE items SET " . implode(', ', $fields) . " WHERE tracking_number = ?";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute($values);
    } catch (Exception $e) {
        error_log("Error updating item in database: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Get claims for an item from database
 *
 * @param string $trackingNumber The item tracking number
 * @return array Array of claims
 */
if (!function_exists('getClaimsForItem')) {
function getClaimsForItem($trackingNumber)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM claims 
            WHERE item_tracking_number = ? 
            ORDER BY claimed_at ASC
        ");
        $stmt->execute([$trackingNumber]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting claims from database: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get items claimed by a user from database
 *
 * @param string $userId The user ID
 * @return array Array of items with claim info
 */
if (!function_exists('getClaimedItemsByUser')) {
function getClaimedItemsByUser($userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT i.*, c.claimed_at, c.status as claim_status, c.id as claim_id
            FROM items i
            INNER JOIN claims c ON i.tracking_number = c.item_tracking_number
            WHERE c.user_id = ?
            ORDER BY c.claimed_at DESC
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting claimed items from database: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Create claim in database
 *
 * @param string $trackingNumber The item tracking number
 * @param array $claimData Claim data
 * @return bool Success status
 */
if (!function_exists('createClaimInDb')) {
function createClaimInDb($trackingNumber, $claimData)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO claims (
                item_tracking_number, user_id, user_name, user_email,
                claimed_at, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $trackingNumber,
            $claimData['user_id'],
            $claimData['user_name'],
            $claimData['user_email'],
            $claimData['claimed_at'] ?? $now,
            $claimData['status'] ?? 'active',
            $now,
            $now
        ]);
    } catch (Exception $e) {
        error_log("Error creating claim in database: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Check if user has claimed an item
 *
 * @param string $trackingNumber The item tracking number
 * @param string $userId The user ID
 * @return bool True if user has claimed this item
 */
if (!function_exists('hasUserClaimedItem')) {
function hasUserClaimedItem($trackingNumber, $userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM claims 
            WHERE item_tracking_number = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$trackingNumber, $userId]);

        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Error checking claim in database: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Get all communities
 * @return array Array of all communities
 */
if (!function_exists('getAllCommunities')) {
function getAllCommunities()
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT * FROM communities ORDER BY short_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting all communities: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get a community by ID
 * @param int $id Community ID
 * @return array|null Community data or null if not found
 */
if (!function_exists('getCommunityById')) {
function getCommunityById($id)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM communities WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Error getting community by ID: " . $e->getMessage());
        return null;
    }
}
}

/**
 * Create a new community
 * @param array $data Community data (short_name, full_name, description, owner_id)
 * @return int|false The new community ID or false on failure
 */
if (!function_exists('createCommunity')) {
function createCommunity($data)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $sql = "INSERT INTO communities (short_name, full_name, description, owner_id, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['short_name'],
            $data['full_name'],
            $data['description'] ?? null,
            $data['owner_id']
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating community: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Update a community
 * @param int $id Community ID
 * @param array $data Community data to update
 * @return bool True on success, false on failure
 */
if (!function_exists('updateCommunity')) {
function updateCommunity($id, $data)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $sql = "UPDATE communities 
                SET short_name = ?, full_name = ?, description = ?, updated_at = NOW() 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['short_name'],
            $data['full_name'],
            $data['description'] ?? null,
            $id
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating community: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Delete a community
 * @param int $id Community ID
 * @return bool True on success, false on failure
 */
if (!function_exists('deleteCommunity')) {
function deleteCommunity($id)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM communities WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    } catch (Exception $e) {
        error_log("Error deleting community: " . $e->getMessage());
        return false;
    }
}
}
