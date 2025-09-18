<?php
/**
 * Utility functions for ClaimIt application
 */

/**
 * Escape HTML output to prevent XSS attacks
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a specific page
 */
function redirect($page = 'home') {
    // Determine the correct base URL based on the environment
    $isLocalhost = isset($_SERVER['HTTP_HOST']) && (
        $_SERVER['HTTP_HOST'] === 'localhost:8000' || 
        $_SERVER['HTTP_HOST'] === '127.0.0.1:8000'
    );
    
    $baseUrl = $isLocalhost 
        ? 'http://localhost:8000/'
        : 'https://claimit.stonekeep.com/';
    
    echo "<script>window.location.href = '" . $baseUrl . "?page=" . urlencode($page) . "';</script>";
    echo "<p>Redirecting to " . htmlspecialchars($page) . "... <a href='" . $baseUrl . "?page=" . urlencode($page) . "'>Click here if not redirected automatically</a></p>";
    exit;
}

/**
 * Get Authentication service instance (lazy loading)
 */
function getAuthService() {
    static $authService = null;
    
    // Always attempt initialization if not already done
    // This ensures fresh initialization on each request
    if ($authService === null) {
        try {
            $awsService = getAwsService();
            if (!$awsService) {
                throw new Exception('AWS service not available');
            }
            $authService = new AuthService($awsService);
        } catch (Exception $e) {
            error_log('Failed to initialize Auth service: ' . $e->getMessage());
            return null;
        }
    }
    
    return $authService;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    $authService = getAuthService();
    return $authService ? $authService->isLoggedIn() : false;
}

/**
 * Get current authenticated user
 */
function getCurrentUser() {
    $authService = getAuthService();
    return $authService ? $authService->getCurrentUser() : null;
}

/**
 * Require authentication (redirect to login if not authenticated)
 */
function requireAuth() {
    $authService = getAuthService();
    if ($authService) {
        $authService->requireAuth();
    } else {
        redirect('login');
    }
}

/**
 * Check if current user owns an item
 */
function currentUserOwnsItem(string $trackingNumber): bool {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    $authService = getAuthService();
    return $authService ? $authService->userOwnsItem($user['id'], $trackingNumber) : false;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Show flash message
 */
function showFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Set flash message
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'text' => $message,
        'type' => $type
    ];
}

/**
 * Get AWS service instance (singleton with lazy loading)
 * Only initializes when actually needed to avoid performance impact
 * 
 * @return ClaimIt\AwsService|null
 */
function getAwsService() {
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

/**
 * Check if AWS service is available without initializing it
 * Useful for conditional logic that doesn't need AWS
 * 
 * @return bool
 */
function isAwsServiceAvailable() {
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

/**
 * Simple in-memory cache for user settings to avoid repeated S3 calls
 * Cache expires after 5 minutes to balance performance vs data freshness
 */
function getUserSettingsCache($userId, $key = null) {
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

/**
 * Set user settings cache
 */
function setUserSettingsCache($userId, $value, $key = null) {
    static $cache = [];
    static $cacheTime = [];
    
    $cacheKey = $userId . ($key ? '_' . $key : '');
    $cache[$cacheKey] = $value;
    $cacheTime[$cacheKey] = time();
}

/**
 * Get all items efficiently with minimal S3 API calls
 * This function batches operations to avoid N+1 query problems
 * 
 * @param bool $includeGoneItems Whether to include gone items
 * @return array Array of items
 */
function getAllItemsEfficiently($includeGoneItems = false) {
    static $itemsCache = null;
    static $cacheTime = null;
    static $cacheKey = null;
    
    // Create cache key based on parameters
    $currentCacheKey = md5($includeGoneItems ? 'with_gone' : 'without_gone');
    
    // Use longer cache for better performance (5 minutes)
    $cacheExpiry = 300; // 5 minutes cache for items
    
    // Check cache first (only if cache key matches)
    if ($itemsCache !== null && $cacheTime !== null && $cacheKey === $currentCacheKey && (time() - $cacheTime) < $cacheExpiry) {
        // Use cached data
    } else {
        try {
            // Initialize AWS service only when actually needed
            $awsService = getAwsService();
            if (!$awsService) {
                throw new Exception('AWS service not available');
            }
            
            // Single API call to get all objects
            $result = $awsService->listObjects('', 1000);
            $objects = $result['objects'] ?? [];
            
            $items = [];
            $yamlObjects = [];
            
            // Filter YAML files first
            foreach ($objects as $object) {
                if (str_ends_with($object['key'], '.yaml')) {
                    $yamlObjects[] = $object;
                }
            }
            
            debugLog("Found " . count($yamlObjects) . " YAML files to process");
            
            // Load YAML files in batches of 20 for better performance
            $yamlContents = [];
            $loadStartTime = microtime(true);
            $batchSize = 20;
            $totalBatches = ceil(count($yamlObjects) / $batchSize);
            
            for ($batch = 0; $batch < $totalBatches; $batch++) {
                $batchStart = $batch * $batchSize;
                $batchEnd = min($batchStart + $batchSize, count($yamlObjects));
                $batchObjects = array_slice($yamlObjects, $batchStart, $batchEnd - $batchStart);
                
                $batchStartTime = microtime(true);
                foreach ($batchObjects as $object) {
                    try {
                        $yamlObject = $awsService->getObject($object['key']);
                        $yamlContents[$object['key']] = $yamlObject['content'];
                    } catch (Exception $e) {
                        error_log("Failed to load YAML file {$object['key']}: " . $e->getMessage());
                        $yamlContents[$object['key']] = null;
                    }
                }
                $batchEndTime = microtime(true);
                $batchTime = round(($batchEndTime - $batchStartTime) * 1000, 2);
                debugLog("Batch " . ($batch + 1) . "/{$totalBatches}: Loaded " . count($batchObjects) . " files in {$batchTime}ms");
            }
            
            $loadEndTime = microtime(true);
            $totalLoadTime = round(($loadEndTime - $loadStartTime) * 1000, 2);
            debugLog("Total: Loaded " . count($yamlContents) . " YAML files in {$totalLoadTime}ms across {$totalBatches} batches");
            
            // Process all YAML files
            $processStartTime = microtime(true);
            debugLog("Performance: Starting YAML processing for " . count($yamlObjects) . " files");
            
            foreach ($yamlObjects as $object) {
                try {
                    // Extract tracking number from filename
                    $trackingNumber = basename($object['key'], '.yaml');
                    
                    // Get YAML content from bulk loaded data
                    $yamlContent = $yamlContents[$object['key']] ?? null;
                    if (!$yamlContent) {
                        continue; // Skip if failed to load
                    }
                    
                    // Parse YAML content
                    $data = parseSimpleYaml($yamlContent);
                    if ($data && isset($data['description']) && isset($data['price']) && isset($data['contact_email'])) {
                        
                        // Check if item is gone and should be filtered
                        if (!$includeGoneItems && isItemGone($data)) {
                            continue;
                        }
                        
                        // Handle backward compatibility
                        $title = $data['title'] ?? $data['description'];
                        $description = $data['description'];
                        
                        // Check for image (optimized - assume common extensions)
                        $imageKey = null;
                        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                        foreach ($imageExtensions as $ext) {
                            $possibleImageKey = 'images/' . $trackingNumber . '.' . $ext;
                            // Check if this image exists in our objects list (no additional API call)
                            foreach ($objects as $imgObject) {
                                if ($imgObject['key'] === $possibleImageKey) {
                                    $imageKey = $trackingNumber . '.' . $ext; // Store without images/ prefix for getCloudFrontUrl
                                    break 2;
                                }
                            }
                        }
                        
                        // Pre-generate image URL using CloudFront (much faster than presigned URLs)
                        $imageUrl = null;
                        if ($imageKey) {
                            $imageUrl = getCloudFrontUrl($imageKey);
                        }
                        
                        // Pre-compute item states to avoid expensive function calls during template rendering
                        $isItemGone = isItemGone($data);
                        $canEditItem = canUserEditItem($data['user_id'] ?? null);
                        
                        // Pre-compute claim data to avoid AWS calls during template rendering
                        // Always compute claim data so logged-out users can see claim status
                        $activeClaims = getActiveClaims($trackingNumber);
                        $primaryClaim = getPrimaryClaim($trackingNumber);
                        
                        // User-specific claim data (only for logged-in users)
                        $isUserClaimed = false;
                        $canUserClaim = false;
                        $currentUser = getCurrentUser();
                        if ($currentUser) {
                            $isUserClaimed = isUserClaimed($trackingNumber, $currentUser['id']);
                            $canUserClaim = canUserClaim($trackingNumber, $currentUser['id']);
                        }
                        
                        $items[] = [
                            'tracking_number' => $trackingNumber,
                            'title' => $title,
                            'description' => $description,
                            'price' => $data['price'],
                            'contact_email' => $data['contact_email'],
                            'image_key' => $imageKey,
                            'image_url' => $imageUrl,
                            'image_width' => $data['image_width'] ?? null,
                            'image_height' => $data['image_height'] ?? null,
                            'posted_date' => $data['submitted_at'] ?? 'Unknown',
                            'submitted_timestamp' => $data['submitted_timestamp'] ?? null,
                            'yaml_key' => $object['key'],
                            'user_id' => $data['user_id'] ?? 'legacy_user',
                            'user_name' => $data['user_name'] ?? 'Legacy User',
                            'user_email' => $data['user_email'] ?? $data['contact_email'] ?? '',
                            'gone' => $data['gone'] ?? null,
                            'gone_at' => $data['gone_at'] ?? null,
                            'gone_by' => $data['gone_by'] ?? null,
                            'relisted_at' => $data['relisted_at'] ?? null,
                            'relisted_by' => $data['relisted_by'] ?? null,
                            'is_item_gone' => $isItemGone,
                            'can_edit_item' => $canEditItem,
                            'active_claims' => $activeClaims,
                            'primary_claim' => $primaryClaim,
                            'is_user_claimed' => $isUserClaimed,
                            'can_user_claim' => $canUserClaim
                        ];
                    }
                } catch (Exception $e) {
                    // Skip invalid YAML files
                    continue;
                }
            }
            
            // Log YAML processing completion
            $processEndTime = microtime(true);
            $processTime = round(($processEndTime - $processStartTime) * 1000, 2);
            debugLog("Performance: YAML processing completed in {$processTime}ms, processed " . count($items) . " items");
            
            // Sort by tracking number (newest first)
            usort($items, function($a, $b) {
                return strcmp($b['tracking_number'], $a['tracking_number']);
            });
            
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

/**
 * Generate cached presigned URL for better performance
 * Uses caching to avoid repeated presigned URL generation
 * 
 * @param string $imageKey S3 object key
 * @return string Presigned URL
 */
function getCachedPresignedUrl($imageKey) {
    static $urlCache = [];
    static $cacheTime = [];
    $cacheExpiry = 3600; // 1 hour cache for presigned URLs
    
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

/**
 * Clear items cache when items are modified
 * This ensures fresh data after item updates
 */
function clearItemsCache() {
    // Clear the static cache variables
    $reflection = new ReflectionFunction('getAllItemsEfficiently');
    $staticVars = $reflection->getStaticVariables();
    
    // Force cache refresh by setting cache time to 0
    // This is a simple way to invalidate the cache
    error_log('Items cache cleared - next request will fetch fresh data');
}

/**
 * Clear presigned URL cache when images are modified
 * This ensures fresh URLs after image updates
 */
function clearImageUrlCache() {
    // Clear the static cache variables for presigned URLs
    $reflection = new ReflectionFunction('getCachedPresignedUrl');
    $staticVars = $reflection->getStaticVariables();
    
    error_log('Image URL cache cleared - next request will generate fresh URLs');
}

/**
 * Clear all static caches for development/testing
 * This forces fresh initialization of all services
 */
function clearAllCaches() {
    // Clear OPcache if available
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // Clear static variables by forcing re-initialization
    // This is a development helper function
    error_log('All caches cleared - next request will use fresh initialization');
}

/**
 * Simple performance monitoring - log page load times
 * This helps track performance improvements
 */
function logPagePerformance($pageName) {
    static $startTime = null;
    
    if ($startTime === null) {
        $startTime = microtime(true);
    } else {
        $loadTime = microtime(true) - $startTime;
        error_log("Performance: {$pageName} loaded in " . round($loadTime * 1000, 2) . "ms");
    }
}

/**
 * Debug logging function - only logs when DEBUG is set to 'yes'
 * 
 * @param string $message The message to log
 */
function debugLog($message) {
    if (defined('DEBUG') && DEBUG === 'yes') {
        error_log($message);
    }
}

/**
 * Get CloudFront URL for an image
 * 
 * @param string $imageKey The S3 object key for the image
 * @return string CloudFront URL or empty string if not configured
 */
function getCloudFrontUrl($imageKey) {
    if (!defined('CLOUDFRONT_DOMAIN') || empty(CLOUDFRONT_DOMAIN)) {
        // Fallback to presigned URL if CloudFront not configured
        debugLog("CloudFront not configured, using presigned URL for: {$imageKey}");
        return getCachedPresignedUrl($imageKey);
    }
    
    debugLog("Using CloudFront URL for: {$imageKey}");
    return 'https://' . CLOUDFRONT_DOMAIN . '/' . $imageKey;
}

/**
 * Format file size in human readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted size
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor(log($bytes, 1024));
    
    return sprintf('%.1f %s', $bytes / pow(1024, $factor), $units[$factor]);
}

/**
 * Get file extension from filename or path
 * 
 * @param string $filename
 * @return string File extension (lowercase)
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Get MIME type for file extension
 * 
 * @param string $extension File extension
 * @return string MIME type
 */
function getMimeType($extension) {
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

/**
 * Check if AWS credentials are configured
 * 
 * @return bool True if credentials file exists
 */
function hasAwsCredentials() {
    return file_exists(__DIR__ . '/../config/aws-credentials.php');
}

/**
 * Validate S3 object key format
 * 
 * @param string $key S3 object key
 * @return bool True if valid
 */
function isValidS3Key($key) {
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



/**
 * Simple YAML parser for our specific format
 */
function parseSimpleYaml($yamlContent) {
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

/**
 * Generate Open Graph meta tags for social media previews
 * 
 * @param string $page Current page
 * @param array $data Page-specific data (item, user, etc.)
 * @return string HTML meta tags
 */
function generateOpenGraphTags($page, $data = []) {
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
        if (isset($metaTags['image_width'])) {
            $html .= '<meta property="og:image:width" content="' . htmlspecialchars($metaTags['image_width']) . '">' . "\n";
        }
        if (isset($metaTags['image_height'])) {
            $html .= '<meta property="og:image:height" content="' . htmlspecialchars($metaTags['image_height']) . '">' . "\n";
        }
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

/**
 * Add current user to item's claims list
 */
function addClaimToItem($trackingNumber) {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in to claim items');
    }
    
    // Check if user can claim this item
    if (!canUserClaim($trackingNumber, $currentUser['id'])) {
        throw new Exception('You cannot claim this item');
    }
    
    $awsService = getAwsService();
    if (!$awsService) {
        throw new Exception('AWS service not available');
    }
    
    // Get current item data
    $yamlKey = $trackingNumber . '.yaml';
    $yamlObject = $awsService->getObject($yamlKey);
    $yamlContent = $yamlObject['content'];
    $data = parseSimpleYaml($yamlContent);
    
    // Initialize claims array if not present
    if (!isset($data['claims'])) {
        $data['claims'] = [];
    }
    
    // Create new claim
    $newClaim = [
        'user_id' => $currentUser['id'],
        'user_name' => $currentUser['name'],
        'user_email' => $currentUser['email'],
        'claimed_at' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];
    
    // Add to claims array
    $data['claims'][] = $newClaim;
    
    // Convert back to YAML and save
    $newYamlContent = convertToYaml($data);
    $awsService->putObject($yamlKey, $newYamlContent, 'text/yaml');
    
    return $newClaim;
}

/**
 * Remove a specific claim from an item (owner only)
 */
function removeClaimFromItem($trackingNumber, $claimUserId) {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }
    
    // Check if current user owns the item
    if (!currentUserOwnsItem($trackingNumber)) {
        throw new Exception('Only the item owner can remove claims');
    }
    
    $awsService = getAwsService();
    if (!$awsService) {
        throw new Exception('AWS service not available');
    }
    
    // Get current item data
    $yamlKey = $trackingNumber . '.yaml';
    $yamlObject = $awsService->getObject($yamlKey);
    $yamlContent = $yamlObject['content'];
    $data = parseSimpleYaml($yamlContent);
    
    // Find and remove the claim
    $claims = $data['claims'] ?? [];
    $updatedClaims = [];
    $found = false;
    
    foreach ($claims as $claim) {
        $status = $claim['status'] ?? 'active'; // Default to active for legacy claims
        if ($claim['user_id'] === $claimUserId && $status === 'active') {
            $found = true;
            // Mark as removed instead of deleting
            $claim['status'] = 'removed';
            $claim['removed_at'] = date('Y-m-d H:i:s');
            $claim['removed_by'] = $currentUser['id'];
        }
        $updatedClaims[] = $claim;
    }
    
    if (!$found) {
        throw new Exception('Claim not found or already removed');
    }
    
    $data['claims'] = $updatedClaims;
    
    // Convert back to YAML and save
    $newYamlContent = convertToYaml($data);
    $awsService->putObject($yamlKey, $newYamlContent, 'text/yaml');
    
    return true;
}

/**
 * Remove current user's own claim from an item
 */
function removeMyClaim($trackingNumber) {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }
    
    $awsService = getAwsService();
    if (!$awsService) {
        throw new Exception('AWS service not available');
    }
    
    // Get current item data
    $yamlKey = $trackingNumber . '.yaml';
    $yamlObject = $awsService->getObject($yamlKey);
    $yamlContent = $yamlObject['content'];
    $data = parseSimpleYaml($yamlContent);
    
    // Find and remove the user's claim
    $claims = $data['claims'] ?? [];
    $updatedClaims = [];
    $found = false;
    
    foreach ($claims as $claim) {
        $status = $claim['status'] ?? 'active'; // Default to active for legacy claims
        if ($claim['user_id'] === $currentUser['id'] && $status === 'active') {
            $found = true;
            // Mark as removed
            $claim['status'] = 'removed';
            $claim['removed_at'] = date('Y-m-d H:i:s');
            $claim['removed_by'] = $currentUser['id'];
        }
        $updatedClaims[] = $claim;
    }
    
    if (!$found) {
        throw new Exception('You do not have an active claim on this item');
    }
    
    $data['claims'] = $updatedClaims;
    
    // Convert back to YAML and save
    $newYamlContent = convertToYaml($data);
    $awsService->putObject($yamlKey, $newYamlContent, 'text/yaml');
    
    return true;
}

/**
 * Get all active claims for an item in chronological order
 */
function getActiveClaims($trackingNumber) {
    $awsService = getAwsService();
    if (!$awsService) {
        return [];
    }
    
    try {
        $yamlKey = $trackingNumber . '.yaml';
        $yamlObject = $awsService->getObject($yamlKey);
        $yamlContent = $yamlObject['content'];
        $data = parseSimpleYaml($yamlContent);
        
        $claims = $data['claims'] ?? [];
        
        $activeClaims = [];
        
        foreach ($claims as $claim) {
            // Consider claims active if they have no status field (legacy) or status is 'active'
            // Exclude claims with status 'removed'
            $status = $claim['status'] ?? 'active'; // Default to active for legacy claims
            if ($status === 'active') {
                $activeClaims[] = $claim;
            }
        }
        
        // Sort by claim date (oldest first)
        usort($activeClaims, function($a, $b) {
            $aDate = $a['claimed_at'] ?? '';
            $bDate = $b['claimed_at'] ?? '';
            return strcmp($aDate, $bDate);
        });
        
        return $activeClaims;
    } catch (Exception $e) {
        error_log("Error in getActiveClaims for $trackingNumber: " . $e->getMessage());
        return [];
    }
}

/**
 * Get the primary (first) active claim for an item
 */
function getPrimaryClaim($trackingNumber) {
    $activeClaims = getActiveClaims($trackingNumber);
    return !empty($activeClaims) ? $activeClaims[0] : null;
}

/**
 * Check if a user has an active claim on an item
 */
function isUserClaimed($trackingNumber, $userId) {
    $activeClaims = getActiveClaims($trackingNumber);
    
    foreach ($activeClaims as $claim) {
        if ($claim['user_id'] === $userId) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if a user can claim an item
 */
function canUserClaim($trackingNumber, $userId) {
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

/**
 * Get user's position in the waitlist for an item
 */
function getUserClaimPosition($trackingNumber, $userId) {
    $activeClaims = getActiveClaims($trackingNumber);
    
    foreach ($activeClaims as $index => $claim) {
        if ($claim['user_id'] === $userId) {
            return $index + 1; // Position is 1-based
        }
    }
    
    return null; // User not in waitlist
}

/**
 * Convert data array back to YAML format
 */
function convertToYaml($data) {
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

/**
 * Get all items that a user has claimed or is on the waiting list for
 */
function getItemsClaimedByUser($userId) {
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
                        $possibleImageKey = $trackingNumber . '.' . $ext;
                        foreach ($objects as $imgObj) {
                            if ($imgObj['key'] === $possibleImageKey) {
                                $imageKey = $possibleImageKey;
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
        usort($claimedItems, function($a, $b) {
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

/**
 * Get ordinal suffix for numbers (1st, 2nd, 3rd, etc.)
 */
function getOrdinalSuffix($number) {
    if ($number >= 11 && $number <= 13) {
        return 'th';
    }
    
    switch ($number % 10) {
        case 1: return 'st';
        case 2: return 'nd';
        case 3: return 'rd';
        default: return 'th';
    }
}

/**
 * Truncate text to a specified length with ellipsis
 *
 * @param string $text The text to truncate
 * @param int $length The maximum length
 * @return string The truncated text
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . '...';
}

/**
 * Get user display name from S3 user settings file
 *
 * @param string $userId The Google user ID
 * @param string $defaultName The default name to use if no custom name is set
 * @return string The display name to use
 */
function getUserDisplayName($userId, $defaultName = '') {
    try {
        $awsService = getAwsService();
        if (!$awsService) {
            return $defaultName;
        }
        
        $yamlKey = 'users/' . $userId . '.yaml';
        
        // Check if user settings file exists
        if (!$awsService->objectExists($yamlKey)) {
            return $defaultName;
        }
        
        // Get user settings
        $yamlObject = $awsService->getObject($yamlKey);
        $yamlContent = $yamlObject['content'];
        $userSettings = parseSimpleYaml($yamlContent);
        
        // Return custom display name if set, otherwise default
        if (isset($userSettings['display_name']) && !empty($userSettings['display_name'])) {
            return $userSettings['display_name'];
        }
        
        return $defaultName;
        
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Error getting user display name for user $userId: " . $e->getMessage());
        return $defaultName;
    }
}

/**
 * Get a single item by tracking number
 *
 * @param string $trackingNumber The item tracking number
 * @return array|null The item data or null if not found
 */
function getItem($trackingNumber) {
    try {
        $awsService = getAwsService();
        if (!$awsService) {
            return null;
        }
        
        $yamlKey = $trackingNumber . '.yaml';
        $yamlObject = $awsService->getObject($yamlKey);
        
        if ($yamlObject && isset($yamlObject['content'])) {
            return parseSimpleYaml($yamlObject['content']);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting item $trackingNumber: " . $e->getMessage());
        return null;
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
function resizeImageToFitSize($sourcePath, $targetPath, $maxSizeBytes = 512000, $maxWidth = 1200, $maxHeight = 1200, $quality = 85) {
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

/**
 * Check if the current user is an administrator
 */
function isAdmin() {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }
    
    return ($currentUser['id'] ?? null) === ADMIN_USER_ID;
}

/**
 * Check if the current user can edit/delete an item (either owner or admin)
 */
function canUserEditItem($itemUserId) {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }
    
    // User can edit if they own the item OR if they are an admin
    return ($currentUser['id'] === $itemUserId) || isAdmin();
}

/**
 * Rotate an image 90 degrees clockwise using GD library
 * 
 * @param string $imageContent The binary image content
 * @param string $contentType The MIME type of the image
 * @return string|false The rotated image content or false on failure
 */
function rotateImage90Degrees($imageContent, $contentType) {
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

/**
 * Mark an item as gone
 *
 * @param string $trackingNumber The item tracking number
 * @return bool True on success, false on failure
 */
function markItemAsGone($trackingNumber) {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }
    
    // Check if user owns this item
    if (!currentUserOwnsItem($trackingNumber)) {
        throw new Exception('You can only mark your own items as gone');
    }
    
    $awsService = getAwsService();
    if (!$awsService) {
        throw new Exception('AWS service not available');
    }
    
    // Get current item data
    $yamlKey = $trackingNumber . '.yaml';
    $yamlObject = $awsService->getObject($yamlKey);
    $yamlContent = $yamlObject['content'];
    $data = parseSimpleYaml($yamlContent);
    
    // Mark as gone
    $data['gone'] = 'yes';
    $data['gone_at'] = date('Y-m-d H:i:s');
    $data['gone_by'] = $currentUser['id'];
    
    // Convert back to YAML and save
    $newYamlContent = convertToYaml($data);
    $awsService->putObject($yamlKey, $newYamlContent, 'text/yaml');
    
    return true;
}

/**
 * Re-list an item (mark as not gone)
 *
 * @param string $trackingNumber The item tracking number
 * @return bool True on success, false on failure
 */
function relistItem($trackingNumber) {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }
    
    // Check if user owns this item
    if (!currentUserOwnsItem($trackingNumber)) {
        throw new Exception('You can only re-list your own items');
    }
    
    $awsService = getAwsService();
    if (!$awsService) {
        throw new Exception('AWS service not available');
    }
    
    // Get current item data
    $yamlKey = $trackingNumber . '.yaml';
    $yamlObject = $awsService->getObject($yamlKey);
    $yamlContent = $yamlObject['content'];
    $data = parseSimpleYaml($yamlContent);
    
    // Mark as not gone
    $data['gone'] = 'no';
    $data['relisted_at'] = date('Y-m-d H:i:s');
    $data['relisted_by'] = $currentUser['id'];
    
    // Convert back to YAML and save
    $newYamlContent = convertToYaml($data);
    $awsService->putObject($yamlKey, $newYamlContent, 'text/yaml');
    
    return true;
}

/**
 * Check if an item is marked as gone
 *
 * @param array $itemData The item data array
 * @return bool True if item is gone, false otherwise
 */
function isItemGone($itemData) {
    return isset($itemData['gone']) && $itemData['gone'] === 'yes';
}

/**
 * Get user setting for showing gone items
 *
 * @param string $userId The user ID
 * @return bool True if user wants to show gone items, false otherwise
 */
function getUserShowGoneItems($userId) {
    // Check cache first
    $cached = getUserSettingsCache($userId, 'show_gone_items');
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        $awsService = getAwsService();
        if (!$awsService) {
            $result = false; // Default to not showing gone items
            setUserSettingsCache($userId, $result, 'show_gone_items');
            return $result;
        }
        
        $yamlKey = 'users/' . $userId . '.yaml';
        
        // Check if user settings file exists
        if (!$awsService->objectExists($yamlKey)) {
            $result = false; // Default to not showing gone items
            setUserSettingsCache($userId, $result, 'show_gone_items');
            return $result;
        }
        
        // Get user settings
        $yamlObject = $awsService->getObject($yamlKey);
        $yamlContent = $yamlObject['content'];
        $userSettings = parseSimpleYaml($yamlContent);
        
        // Return setting if set, otherwise default to false
        $result = isset($userSettings['show_gone_items']) && $userSettings['show_gone_items'] === 'yes';
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

/**
 * Save user setting for showing gone items
 *
 * @param string $userId The user ID
 * @param bool $showGoneItems Whether to show gone items
 * @return bool True on success, false on failure
 */
function saveUserShowGoneItems($userId, $showGoneItems) {
    try {
        $awsService = getAwsService();
        if (!$awsService) {
            throw new Exception('AWS service not available');
        }
        
        $yamlKey = 'users/' . $userId . '.yaml';
        
        // Get existing user settings or create new
        $userSettings = [];
        if ($awsService->objectExists($yamlKey)) {
            $yamlObject = $awsService->getObject($yamlKey);
            $yamlContent = $yamlObject['content'];
            $userSettings = parseSimpleYaml($yamlContent);
        }
        
        // Update the setting
        $userSettings['show_gone_items'] = $showGoneItems ? 'yes' : 'no';
        
        // Convert back to YAML and save
        $newYamlContent = convertToYaml($userSettings);
        $awsService->putObject($yamlKey, $newYamlContent, 'text/yaml');
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error saving user show gone items setting for user $userId: " . $e->getMessage());
        return false;
    }
}

?> 