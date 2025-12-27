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

