<?php

/**
 * Image handling and AWS S3/CloudFront functions
 */

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