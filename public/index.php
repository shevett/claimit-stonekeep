<?php

/**
 * ClaimIt Web Application
 * Main entry point
 */

// Suppress PHP 8.4 deprecation warnings while keeping actual errors
error_reporting(0); // Suppress all errors for production-like experience
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set upload limits for file uploads (try multiple approaches)
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '51M'); // Slightly larger than upload_max_filesize to account for form data
ini_set('max_file_uploads', '1');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '256M');

// Alternative approach - try to set via environment variables
putenv('PHP_UPLOAD_MAX_FILESIZE=10M');
putenv('PHP_POST_MAX_SIZE=11M');

// Custom error handler to filter out AWS SDK warnings from browser but keep in environment logs
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Log AWS SDK compatibility warnings to environment logs but don't display them
    if (strpos($errfile, 'aws-sdk-php') !== false && strpos($errstr, 'syntax error') !== false) {
        error_log("AWS SDK PHP 8.4 Compatibility Warning: $errstr in $errfile on line $errline");
        return true; // Suppress from browser display
    }
    // Let other errors through to default handler
    return false;
});

// Start output buffering to allow redirects from templates
ob_start();

// Start performance monitoring
$startTime = microtime(true);
$timingLogs = [];


// Clear caches for development (remove in production)
if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === '1') {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    error_log('Cache cleared via URL parameter');
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration (this sets session settings)
require_once __DIR__ . '/../config/config.php';

// Start session (after session settings are configured)
session_start();



// Load includes
require_once __DIR__ . '/../includes/functions.php';


// Load authentication service
require_once __DIR__ . '/../src/AuthService.php';

// Handle AJAX requests before any HTML output
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && $_GET['page'] === 'home') {
    // AJAX request for home page items
    header('Content-Type: text/html');

    $ajaxStartTime = microtime(true);
    debugLog("AJAX: Starting home page items request");


    try {
        // Check if user wants to see gone items (lazy auth loading)
        $currentUser = null;
        $showGoneItems = false;

        // Only check user settings if we have a session (avoid AWS initialization)
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
            $currentUser = getCurrentUser();
            $showGoneItems = $currentUser ? getUserShowGoneItems($currentUser['id']) : false;
        }

        // Determine which communities to show items from
        $communityIds = [1]; // Default to General for anonymous users
        if ($currentUser) {
            // Get user's subscribed communities
            $userCommunities = getUserCommunityIds($currentUser['id']);
            if (!empty($userCommunities)) {
                $communityIds = $userCommunities;
            }
            debugLog("User {$currentUser['id']} subscribed to communities: " . implode(', ', $communityIds));
        }

        // Load items
        $items = [];

        if (hasAwsCredentials()) {
            $itemsStartTime = microtime(true);
            debugLog("AJAX: Starting getAllItemsEfficiently for communities: " . implode(', ', $communityIds));
            $items = getAllItemsEfficiently($showGoneItems, $communityIds);
            $itemsEndTime = microtime(true);
            $itemsTime = round(($itemsEndTime - $itemsStartTime) * 1000, 2);
            debugLog("AJAX: getAllItemsEfficiently completed in {$itemsTime}ms, loaded " . count($items) . " items");
        }

        // Render items
        if (empty($items)) {
            echo '<div class="no-items"><p>No items available at the moment.</p></div>';
        } else {
            $renderStartTime = microtime(true);
            debugLog("AJAX: Starting template rendering for " . count($items) . " items");

            $itemCount = 0;
            foreach ($items as $item) {
                $itemStartTime = microtime(true);
                $context = 'home';
                $isOwnListings = false;
                include __DIR__ . '/../templates/item-card.php';
                $itemEndTime = microtime(true);
                $itemTime = round(($itemEndTime - $itemStartTime) * 1000, 2);
                $itemCount++;
            }

            $renderEndTime = microtime(true);
            $renderTime = round(($renderEndTime - $renderStartTime) * 1000, 2);
            debugLog("AJAX: Template rendering completed in {$renderTime}ms");
        }
    } catch (Exception $e) {
        error_log('AJAX Error: ' . $e->getMessage());
        echo '<div class="no-items"><p>Error loading items: ' . escape($e->getMessage()) . '</p></div>';
    }

    $ajaxEndTime = microtime(true);
    $ajaxTotalTime = round(($ajaxEndTime - $ajaxStartTime) * 1000, 2);
    debugLog("AJAX: Total request completed in {$ajaxTotalTime}ms");

    exit;
}

// Handle AJAX search requests
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['query'])) {
    // Clear any output and set JSON header
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json');

    $searchQuery = trim($_GET['query']);

    if (empty($searchQuery)) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }

    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            throw new Exception('Database not available');
        }

        // Get current user's settings for showing gone items
        $currentUser = getCurrentUser();
        $userId = $currentUser ? $currentUser['id'] : '';
        $showGone = $userId ? getUserShowGoneItems($userId) : false;

        // Build SQL query to search across multiple fields
        $sql = "SELECT * FROM items WHERE (
                    title LIKE ? OR 
                    description LIKE ? OR 
                    id LIKE ? OR
                    user_name LIKE ? OR
                    contact_email LIKE ?
                )";

        // Add gone filter if user doesn't want to see gone items
        if (!$showGone) {
            $sql .= " AND gone = 0";
        }

        $sql .= " ORDER BY created_at DESC LIMIT 100";

        // Prepare search term with wildcards
        $searchTerm = '%' . $searchQuery . '%';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);

        $matchingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert database format to expected format for frontend
        foreach ($matchingItems as &$item) {
            // Ensure status field exists
            if (!isset($item['status'])) {
                $item['status'] = $item['gone'] ? 'gone' : 'available';
            }

            // Convert gone boolean to status if needed
            if ($item['gone'] && $item['status'] !== 'gone') {
                $item['status'] = 'gone';
            }
        }

        echo json_encode(['success' => true, 'items' => $matchingItems, 'count' => count($matchingItems)]);
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}

// Handle AJAX delete requests before any HTML output
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    header('Content-Type: application/json');

    $trackingNumber = $_POST['id'];

    if (!preg_match('/^\d{14}$/', $trackingNumber)) {
        echo json_encode(['success' => false, 'message' => 'Invalid tracking number']);
        exit;
    }

    // Check if user is logged in
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    // Check if user can edit this item (owner or admin)
    $item = getItem($trackingNumber);
    if (!$item || !canUserEditItem($item['user_id'] ?? null)) {
        echo json_encode(['success' => false, 'message' => 'You can only delete your own items or be an administrator']);
        exit;
    }

    try {
        $awsService = getAwsService();
        if (!$awsService) {
            throw new Exception('AWS service not available');
        }

        // Delete both YAML and image files
        $yamlKey = $trackingNumber . '.yaml';
        $imageDeleted = false;
        $yamlDeleted = false;

        // Try to delete the YAML file
        try {
            $awsService->deleteObject($yamlKey);
            $yamlDeleted = true;
        } catch (Exception $e) {
            // YAML file might not exist, continue
        }

        // Try to delete the image file (try different extensions)
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        foreach ($imageExtensions as $ext) {
            $imageKey = $trackingNumber . '.' . $ext;
            try {
                if ($awsService->objectExists($imageKey)) {
                    $awsService->deleteObject($imageKey);
                    $imageDeleted = true;
                    break;
                }
            } catch (Exception $e) {
                // Continue to next extension
            }
        }

        if ($yamlDeleted || $imageDeleted) {
            echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No files found to delete']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete item: ' . $e->getMessage()]);
    }

    exit;
}

// Handle GET request for item communities (for edit modal)
if (isset($_GET['action']) && $_GET['action'] === 'get_item_communities' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    
    $trackingNumber = $_GET['id'];
    
    // Support both old format (YmdHis) and new format (YmdHis-xxxx)
    if (!preg_match('/^(\d{14}|\d{14}-[a-f0-9]{4})$/', $trackingNumber)) {
        echo json_encode(['success' => false, 'message' => 'Invalid tracking number']);
        exit;
    }
    
    // Get all communities and the item's current communities
    $allCommunities = getAllCommunities();
    $itemCommunities = getItemCommunities($trackingNumber);
    
    echo json_encode([
        'success' => true,
        'communities' => $allCommunities,
        'itemCommunities' => $itemCommunities
    ]);
    exit;
}

// Handle AJAX claim requests before any HTML output
// Support both POST body and GET parameters for action/id
$ajaxAction = $_POST['action'] ?? $_GET['action'] ?? null;
$ajaxId = $_POST['id'] ?? $_GET['id'] ?? null;

error_log("Checking for AJAX request - action: " . ($ajaxAction ?? 'not set') . ", id: " . ($ajaxId ?? 'not set'));

if ($ajaxAction && in_array($ajaxAction, ['add_claim', 'remove_claim', 'remove_claim_by_owner', 'delete_item', 'edit_item', 'rotate_image', 'mark_gone', 'relist_item', 'upload_additional_image', 'delete_image']) && $ajaxId) {
    error_log("AJAX Handler reached - action: " . $ajaxAction . ", id: " . $ajaxId);
    header('Content-Type: application/json');

    $trackingNumber = $ajaxId;
    $action = $ajaxAction;

    // Support both old format (YmdHis) and new format (YmdHis-xxxx)
    if (!preg_match('/^(\d{14}|\d{14}-[a-f0-9]{4})$/', $trackingNumber)) {
        echo json_encode(['success' => false, 'message' => 'Invalid tracking number']);
        exit;
    }

    // Check if user is logged in
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'You must be logged in to claim items']);
        exit;
    }

    $currentUser = getCurrentUser();
    if (!$currentUser) {
        echo json_encode(['success' => false, 'message' => 'User information not available']);
        exit;
    }

    try {
        switch ($action) {
            case 'add_claim':
                $claim = addClaimToItem($trackingNumber);
                $position = getUserClaimPosition($trackingNumber, $claim['user_id']);
                echo json_encode([
                    'success' => true,
                    'message' => 'You\'re now ' . $position . getOrdinalSuffix($position) . ' in line!',
                    'position' => $position
                ]);
                break;

            case 'remove_claim':
                try {
                    removeMyClaim($trackingNumber);
                    echo json_encode(['success' => true, 'message' => 'You\'ve been removed from the waitlist']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

            case 'remove_claim_by_owner':
                $claimUserId = $_POST['claim_user_id'] ?? $_GET['claim_user_id'] ?? null;
                if (!$claimUserId) {
                    echo json_encode(['success' => false, 'message' => 'Claim user ID required']);
                    exit;
                }
                removeClaimFromItem($trackingNumber, $claimUserId);
                echo json_encode(['success' => true, 'message' => 'Claim removed successfully']);
                break;

            case 'delete_item':
                // Check if user can edit this item (owner or admin)
                $item = getItem($trackingNumber);
                if (!$item || !canUserEditItem($item['user_id'] ?? null)) {
                    echo json_encode(['success' => false, 'message' => 'You can only delete your own items or be an administrator']);
                    exit;
                }

                // Delete the item from S3
                $awsService = getAwsService();
                if (!$awsService) {
                    throw new Exception('AWS service not available');
                }

                // Delete the item YAML file
                $yamlKey = $trackingNumber . '.yaml';
                $awsService->deleteObject($yamlKey);

                // Delete all images for this item
                $allImages = getItemImages($trackingNumber);
                foreach ($allImages as $imageKey) {
                    try {
                        $awsService->deleteObject($imageKey);
                    } catch (Exception $e) {
                        error_log("Failed to delete image {$imageKey}: " . $e->getMessage());
                    }
                }

                // Clear caches since we deleted an item
                clearItemsCache();
                clearImageUrlCache();

                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
                break;

            case 'edit_item':
                // Check if the current user can edit this item (owner or admin)
                $item = getItemFromDb($trackingNumber);
                if (!$item || !canUserEditItem($item['user_id'] ?? null)) {
                    echo json_encode(['success' => false, 'message' => 'You can only edit your own items or be an administrator']);
                    exit;
                }

                // Validate required fields
                if (!isset($_POST['title']) || !isset($_POST['description'])) {
                    echo json_encode(['success' => false, 'message' => 'Title and description are required']);
                    exit;
                }

                $title = trim($_POST['title']);
                $description = trim($_POST['description']);

                if (empty($title) || empty($description)) {
                    echo json_encode(['success' => false, 'message' => 'Title and description cannot be empty']);
                    exit;
                }

                // Update the item in database
                $updates = [
                    'title' => $title,
                    'description' => $description
                ];

                if (!updateItemInDb($trackingNumber, $updates)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to update item in database']);
                    exit;
                }

                // Handle community associations
                $communities = $_POST['communities'] ?? [];
                $communityIds = [];
                
                // Collect selected community IDs (empty = invisible/staging)
                foreach ($communities as $commValue) {
                    if (is_numeric($commValue)) {
                        $communityIds[] = (int)$commValue;
                    }
                }
                
                // Save community associations (empty array is allowed for staging)
                setItemCommunities($trackingNumber, $communityIds);
                
                // Clear items cache since we updated an item
                clearItemsCache();

                echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
                exit;

            case 'rotate_image':
                // Check if the current user can edit this item (owner or admin)
                $item = getItem($trackingNumber);
                if (!$item || !canUserEditItem($item['user_id'] ?? null)) {
                    echo json_encode(['success' => false, 'message' => 'You can only rotate images for your own items or be an administrator']);
                    exit;
                }

                // Get image index if provided (for rotating specific images)
                $imageIndex = isset($_POST['image_index']) && $_POST['image_index'] !== 'null' ? intval($_POST['image_index']) : null;

                // Determine the image key using getItemImages helper
                $awsService = getAwsService();
                if (!$awsService) {
                    echo json_encode(['success' => false, 'message' => 'AWS service not available']);
                    exit;
                }

                // Get all images for this item
                $allImages = getItemImages($trackingNumber);

                if (empty($allImages)) {
                    echo json_encode(['success' => false, 'message' => 'No images found for this item']);
                    exit;
                }

                // Find the specific image to rotate
                $imageKey = null;

                if ($imageIndex === null) {
                    // Rotate primary image (first in the array)
                    $imageKey = $allImages[0];
                } else {
                    // Rotate specific indexed image
                    foreach ($allImages as $img) {
                        if (getImageIndex($img) === $imageIndex) {
                            $imageKey = $img;
                            break;
                        }
                    }
                }

                // Check if we found the image
                if (empty($imageKey)) {
                    echo json_encode(['success' => false, 'message' => 'Image not found']);
                    exit;
                }

                try {
                    // Download the image from S3
                    $imageObject = $awsService->getObject($imageKey);
                    $imageContent = $imageObject['content'];
                    $contentType = $imageObject['content_type'];

                    // Rotate the image using GD library
                    $rotatedImageContent = rotateImage90Degrees($imageContent, $contentType);

                    if ($rotatedImageContent === false) {
                        throw new Exception('Failed to rotate image');
                    }

                    // Upload the rotated image back to S3
                    $result = $awsService->putObject($imageKey, $rotatedImageContent, $contentType);

                    // Invalidate CloudFront cache for this image
                    // CloudFront serves images without the 'images/' prefix, so strip it for invalidation
                    $cloudFrontPath = str_replace('images/', '', $imageKey);
                    try {
                        $invalidationResult = $awsService->createInvalidation([$cloudFrontPath]);
                        error_log("CloudFront invalidation created for path: /{$cloudFrontPath} (ID: " . $invalidationResult['invalidation_id'] . ")");
                    } catch (Exception $cfException) {
                        // Log but don't fail - cache will eventually expire
                        error_log("CloudFront invalidation failed (non-critical): " . $cfException->getMessage());
                    }

                    // Clear image URL cache since the image was modified
                    clearImageUrlCache();

                    // Generate a direct S3 presigned URL for immediate viewing (bypasses CloudFront)
                    // This ensures user sees rotated image instantly while CloudFront invalidation propagates
                    $directImageUrl = $awsService->getPresignedUrl($imageKey, 3600);

                    // Add cache-busting timestamp to force browser refresh
                    $cacheBuster = time();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Image rotated successfully',
                        'cache_buster' => $cacheBuster,
                        'direct_image_url' => $directImageUrl  // Direct S3 URL bypassing CloudFront
                    ]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to rotate image: ' . $e->getMessage()]);
                }
                break;

            case 'upload_additional_image':
                // Verify user owns this item
                $item = getItem($trackingNumber);
                if (!$item || !canUserEditItem($item['user_id'] ?? null)) {
                    echo json_encode(['success' => false, 'message' => 'You can only add images to your own items']);
                    exit;
                }

                // Check if file was uploaded
                if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] === UPLOAD_ERR_NO_FILE) {
                    echo json_encode(['success' => false, 'message' => 'No image file provided']);
                    exit;
                }

                $uploadedFile = $_FILES['image_file'];

                // Validate upload
                if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'File upload error']);
                    exit;
                }

                // Validate file type
                $imageExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                if (!in_array($imageExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed']);
                    exit;
                }

                // Validate file size (50MB)
                if ($uploadedFile['size'] > 52428800) {
                    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 50MB']);
                    exit;
                }

                // Check image count limit
                $existingImages = getItemImages($trackingNumber);
                if (count($existingImages) >= 10) {
                    echo json_encode(['success' => false, 'message' => 'Maximum of 10 images per item']);
                    exit;
                }

                try {
                    $awsService = getAwsService();
                    if (!$awsService) {
                        throw new Exception('AWS service not available');
                    }

                    // Get next available index
                    $nextIndex = getNextImageIndex($trackingNumber);
                    $imageKey = 'images/' . $trackingNumber . '-' . $nextIndex . '.' . $imageExtension;

                    // Resize the image
                    $tempResizedPath = tempnam(sys_get_temp_dir(), 'claimit_resized_');

                    if (resizeImageToFitSize($uploadedFile['tmp_name'], $tempResizedPath, 512000)) {
                        $imageContent = file_get_contents($tempResizedPath);
                        $mimeType = mime_content_type($tempResizedPath);
                        unlink($tempResizedPath);
                    } else {
                        error_log('Image resizing failed, using original image');
                        $imageContent = file_get_contents($uploadedFile['tmp_name']);
                        $mimeType = mime_content_type($uploadedFile['tmp_name']);
                        if (file_exists($tempResizedPath)) {
                            unlink($tempResizedPath);
                        }
                    }

                    // Upload to S3
                    $awsService->putObject($imageKey, $imageContent, $mimeType);

                    // Clear caches
                    clearImageUrlCache();
                    clearItemsCache();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Image uploaded successfully',
                        'image_key' => $imageKey
                    ]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload image: ' . $e->getMessage()]);
                }
                break;

            case 'delete_image':
                // Verify user owns this item
                $item = getItem($trackingNumber);
                if (!$item || !canUserEditItem($item['user_id'] ?? null)) {
                    echo json_encode(['success' => false, 'message' => 'You can only delete images from your own items']);
                    exit;
                }

                // Get image index
                if (!isset($_POST['image_index'])) {
                    echo json_encode(['success' => false, 'message' => 'Image index not provided']);
                    exit;
                }

                $imageIndex = intval($_POST['image_index']);

                try {
                    deleteImageFromS3($trackingNumber, $imageIndex);

                    // Clear caches
                    clearImageUrlCache();
                    clearItemsCache();

                    echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

            case 'mark_gone':
                try {
                    markItemAsGone($trackingNumber);
                    clearItemsCache(); // Clear cache since item status changed
                    echo json_encode(['success' => true, 'message' => 'Item marked as gone']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

            case 'relist_item':
                try {
                    relistItem($trackingNumber);
                    clearItemsCache(); // Clear cache since item status changed
                    echo json_encode(['success' => true, 'message' => 'Item re-listed successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}


// Handle settings AJAX requests before any HTML output
if (isset($_GET['page']) && $_GET['page'] === 'settings' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $currentUser = getCurrentUser();
    if (!$currentUser) {
        echo json_encode(['success' => false, 'message' => 'User information not available']);
        exit;
    }

    $action = $_GET['action'];

    if ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }

        $displayName = trim($_POST['display_name'] ?? '');
        $zipcode = trim($_POST['zipcode'] ?? '');
        $showGoneItems = isset($_POST['show_gone_items']) && $_POST['show_gone_items'] === 'on';
        $emailNotifications = isset($_POST['email_notifications']) && $_POST['email_notifications'] === 'on';
        $newListingNotifications = isset($_POST['new_listing_notifications']) && $_POST['new_listing_notifications'] === 'on';

        if (empty($displayName)) {
            echo json_encode(['success' => false, 'message' => 'Display name is required']);
            exit;
        }

        try {
            // Update user in database
            $userData = [
                'id' => $currentUser['id'],
                'email' => $currentUser['email'],
                'name' => $currentUser['name'],
                'picture' => $currentUser['picture'],
                'verified_email' => $currentUser['verified_email'],
                'locale' => $currentUser['locale'],
                'last_login' => $currentUser['last_login'],
                'display_name' => $displayName,
                'zipcode' => $zipcode,
                'show_gone_items' => $showGoneItems,
                'email_notifications' => $emailNotifications,
                'new_listing_notifications' => $newListingNotifications
            ];

            if (!updateUser($currentUser['id'], $userData)) {
                throw new Exception('Failed to update user in database');
            }

            // Update session with new values
            $_SESSION['user']['display_name'] = $displayName;
            $_SESSION['user']['zipcode'] = $zipcode;
            $_SESSION['user']['show_gone_items'] = $showGoneItems;
            $_SESSION['user']['email_notifications'] = $emailNotifications;
            $_SESSION['user']['new_listing_notifications'] = $newListingNotifications;

            echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $e->getMessage()]);
        }

        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Handle admin AJAX requests before any HTML output
if (isset($_GET['page']) && $_GET['page'] === 'admin' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Administrator privileges required']);
        exit;
    }

    $currentUser = getCurrentUser();
    if (!$currentUser) {
        echo json_encode(['success' => false, 'message' => 'User information not available']);
        exit;
    }

    $action = $_GET['action'];

    if ($action === 'execute') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }

        $testDatabase = isset($_POST['test_database']) && $_POST['test_database'] === 'on';
        $sendTestEmail = isset($_POST['send_test_email']) && $_POST['send_test_email'] === 'on';

        try {
            $messages = [];
            $details = null;

            // Test database connection if requested
            if ($testDatabase) {
                $dbTest = testDbConnection();
                if ($dbTest['success']) {
                    $messages[] = $dbTest['message'];
                    $details = $dbTest['details'] ?? null;
                } else {
                    $messages[] = $dbTest['message'];
                }
            }

            // Send test email if requested
            if ($sendTestEmail) {
                try {
                    $emailService = getEmailService();
                    if ($emailService) {
                        $testEmailSent = $emailService->sendTestEmail($currentUser);
                        $messages[] = $testEmailSent ? 'Test email sent successfully' : 'Test email failed to send';
                    } else {
                        $messages[] = 'Email service not available';
                    }
                } catch (Exception $e) {
                    error_log("Failed to send test email: " . $e->getMessage());
                    $messages[] = 'Test email failed: ' . $e->getMessage();
                }
            }

            $message = empty($messages) ? 'No actions performed' : implode('. ', $messages);
            $response = ['success' => true, 'message' => $message];
            if ($details) {
                $response['details'] = $details;
            }
            echo json_encode($response);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to execute admin action: ' . $e->getMessage()]);
        }

        exit;
    }

    if ($action === 'get_user') {
        if (!isset($_GET['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'User ID required']);
            exit;
        }

        try {
            $userId = $_GET['user_id'];
            $user = getUserById($userId);
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            // Get all communities
            $communities = getAllCommunities();
            
            // Get user's community memberships
            $userCommunities = getUserCommunityIds($userId);
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'communities' => $communities,
                'user_communities' => $userCommunities
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error loading user: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Handle admin POST requests for updating users
if (isset($_POST['action']) && $_POST['action'] === 'update_user' && isset($_GET['page']) && $_GET['page'] === 'admin') {
    header('Content-Type: application/json');

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Administrator privileges required']);
        exit;
    }

    if (!isset($_POST['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }

    try {
        $userId = $_POST['user_id'];
        
        // Load existing user data first
        $existingUser = getUserById($userId);
        if (!$existingUser) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Merge admin updates with existing data (preserving name, email, etc.)
        $updates = array_merge($existingUser, [
            'display_name' => trim($_POST['display_name'] ?? ''),
            'zipcode' => trim($_POST['zipcode'] ?? ''),
            'is_admin' => isset($_POST['is_admin']) ? 1 : 0,
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'new_listing_notifications' => isset($_POST['new_listing_notifications']) ? 1 : 0
        ]);
        
        if (!updateUser($userId, $updates)) {
            echo json_encode(['success' => false, 'message' => 'Failed to update user']);
            exit;
        }
        
        // Update community memberships
        $selectedCommunities = isset($_POST['communities']) && is_array($_POST['communities']) ? $_POST['communities'] : [];
        $currentCommunities = getUserCommunityIds($userId);
        
        // Remove communities that are no longer selected
        foreach ($currentCommunities as $communityId) {
            if (!in_array($communityId, $selectedCommunities)) {
                leaveCommunity($userId, $communityId);
            }
        }
        
        // Add newly selected communities
        foreach ($selectedCommunities as $communityId) {
            if (!in_array($communityId, $currentCommunities)) {
                joinCommunity($userId, (int)$communityId);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch (Exception $e) {
        error_log("Error updating user: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()]);
    }
    
    exit;
}

// Handle community membership AJAX requests (join/leave) - for all logged-in users
if (isset($_POST['action']) && in_array($_POST['action'], ['join', 'leave']) && isset($_POST['community_id'])) {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        echo json_encode(['success' => false, 'message' => 'User information not available']);
        exit;
    }
    
    $communityId = (int)$_POST['community_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'join') {
            $success = joinCommunity($currentUser['id'], $communityId);
            $message = $success ? 'Successfully joined community' : 'Failed to join community';
        } else {
            $success = leaveCommunity($currentUser['id'], $communityId);
            $message = $success ? 'Successfully left community' : 'Failed to leave community';
        }
        
        echo json_encode(['success' => $success, 'message' => $message]);
    } catch (Exception $e) {
        error_log("Community membership error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Handle communities AJAX requests before any HTML output
if (isset($_GET['page']) && $_GET['page'] === 'communities' && (isset($_GET['action']) || (isset($_POST['action']) && $_SERVER['REQUEST_METHOD'] === 'POST'))) {
    header('Content-Type: application/json');

    // Check authentication and authorization
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Administrator privileges required']);
        exit;
    }

    $currentUser = getCurrentUser();
    if (!$currentUser) {
        echo json_encode(['success' => false, 'message' => 'User information not available']);
        exit;
    }

    // Handle GET request for single community
    if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
        $community = getCommunityById((int)$_GET['id']);
        if ($community) {
            echo json_encode(['success' => true, 'community' => $community]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Community not found']);
        }
        exit;
    }

    // Handle POST requests (create, update, delete)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Action can be in either GET or POST
        $action = $_GET['action'] ?? $_POST['action'] ?? null;
        
        if (!$action) {
            echo json_encode(['success' => false, 'message' => 'Action required']);
            exit;
        }

        try {
            switch ($action) {
                case 'create':
                    if (empty($_POST['short_name']) || empty($_POST['full_name']) || empty($_POST['owner_id'])) {
                        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                        exit;
                    }

                    $data = [
                        'short_name' => trim($_POST['short_name']),
                        'full_name' => trim($_POST['full_name']),
                        'description' => trim($_POST['description'] ?? ''),
                        'private' => isset($_POST['private']) ? 1 : 0,
                        'slack_webhook_url' => !empty($_POST['slack_webhook_url']) ? trim($_POST['slack_webhook_url']) : null,
                        'slack_enabled' => isset($_POST['slack_enabled']) ? 1 : 0,
                        'owner_id' => trim($_POST['owner_id'])
                    ];

                    $newId = createCommunity($data);
                    if ($newId) {
                        echo json_encode(['success' => true, 'message' => 'Community created successfully', 'id' => $newId]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to create community']);
                    }
                    break;

                case 'update':
                    if (empty($_POST['id']) || empty($_POST['short_name']) || empty($_POST['full_name'])) {
                        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                        exit;
                    }

                    $data = [
                        'short_name' => trim($_POST['short_name']),
                        'full_name' => trim($_POST['full_name']),
                        'description' => trim($_POST['description'] ?? ''),
                        'private' => isset($_POST['private']) ? 1 : 0,
                        'slack_webhook_url' => !empty($_POST['slack_webhook_url']) ? trim($_POST['slack_webhook_url']) : null,
                        'slack_enabled' => isset($_POST['slack_enabled']) ? 1 : 0
                    ];

                    $success = updateCommunity((int)$_POST['id'], $data);
                    if ($success) {
                        echo json_encode(['success' => true, 'message' => 'Community updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update community']);
                    }
                    break;

                case 'delete':
                    if (empty($_POST['id'])) {
                        echo json_encode(['success' => false, 'message' => 'Community ID required']);
                        exit;
                    }

                    $success = deleteCommunity((int)$_POST['id']);
                    if ($success) {
                        echo json_encode(['success' => true, 'message' => 'Community deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete community']);
                    }
                    break;

                case 'test_slack':
                    if (empty($_POST['webhook_url'])) {
                        echo json_encode(['success' => false, 'message' => 'Webhook URL required']);
                        exit;
                    }

                    $webhookUrl = trim($_POST['webhook_url']);

                    // Validate webhook URL format
                    if (!filter_var($webhookUrl, FILTER_VALIDATE_URL) || 
                        !str_starts_with($webhookUrl, 'https://hooks.slack.com/services/')) {
                        echo json_encode(['success' => false, 'message' => 'Invalid Slack webhook URL format']);
                        exit;
                    }

                    // Send test message to Slack
                    $testMessage = [
                        'text' => '🧪 Test message from ClaimIt',
                        'blocks' => [
                            [
                                'type' => 'section',
                                'text' => [
                                    'type' => 'mrkdwn',
                                    'text' => "*Test Message from ClaimIt*\n\nIf you can see this message, your Slack webhook is configured correctly! :white_check_mark:"
                                ]
                            ],
                            [
                                'type' => 'context',
                                'elements' => [
                                    [
                                        'type' => 'mrkdwn',
                                        'text' => '_Sent at ' . date('Y-m-d H:i:s') . '_'
                                    ]
                                ]
                            ]
                        ]
                    ];

                    try {
                        error_log("Slack webhook test - sending to: " . $webhookUrl);
                        error_log("Slack webhook test - message: " . json_encode($testMessage));
                        
                        $ch = curl_init($webhookUrl);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testMessage));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curlError = curl_error($ch);
                        curl_close($ch);

                        error_log("Slack webhook test - HTTP code: $httpCode, Response: '$response', cURL Error: '$curlError'");

                        // Slack returns "ok" on success (case insensitive, might have whitespace)
                        if ($httpCode === 200 && trim(strtolower($response)) === 'ok') {
                            echo json_encode(['success' => true, 'message' => 'Test message sent successfully! Check your Slack channel.']);
                        } else {
                            $errorMsg = $curlError ?: "Slack returned HTTP $httpCode with response: $response";
                            error_log("Slack webhook test failed: $errorMsg");
                            echo json_encode(['success' => false, 'message' => "Failed to send message: $errorMsg"]);
                        }
                    } catch (Exception $e) {
                        error_log("Slack webhook test error: " . $e->getMessage());
                        echo json_encode(['success' => false, 'message' => 'Error sending test message: ' . $e->getMessage()]);
                    }
                    break;

                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
        } catch (Exception $e) {
            error_log("Communities error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Handle authentication routes before any HTML output
if (isset($_GET['page']) && $_GET['page'] === 'auth' && isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'google') {
        // Redirect to Google OAuth
        try {
            $authService = getAuthService();
            if ($authService) {
                $authUrl = $authService->getAuthUrl();
                header('Location: ' . $authUrl);
                exit;
            } else {
                setFlashMessage('Authentication service unavailable', 'error');
                redirect('login');
            }
        } catch (Exception $e) {
            // Log authentication errors for debugging
            error_log('Authentication Error: ' . $e->getMessage());
            setFlashMessage('Authentication error: ' . $e->getMessage(), 'error');
            redirect('login');
        }
    } elseif ($action === 'callback') {
        // Handle Google OAuth callback
        try {
            if (isset($_GET['code'])) {
                $authService = getAuthService();
                if ($authService) {
                    $user = $authService->handleCallback($_GET['code']);
                    setFlashMessage('Welcome, ' . $user['name'] . '!', 'success');
                    redirect('home');
                } else {
                    throw new Exception('Authentication service unavailable');
                }
            } elseif (isset($_GET['error'])) {
                throw new Exception('OAuth error: ' . $_GET['error']);
            } else {
                throw new Exception('No authorization code received');
            }
        } catch (Exception $e) {
            // Log authentication errors for debugging
            error_log('Authentication Error: ' . $e->getMessage());
            setFlashMessage('Login failed: ' . $e->getMessage(), 'error');
            redirect('login');
        }
    } elseif ($action === 'logout') {
        // Handle logout
        try {
            $authService = getAuthService();
            if ($authService) {
                $authService->logout();
            }
            setFlashMessage('You have been logged out successfully', 'success');
            redirect('home');
        } catch (Exception $e) {
            setFlashMessage('Logout error: ' . $e->getMessage(), 'error');
            redirect('home');
        }
    }
}



// Simple routing based on URL parameter
$page = $_GET['page'] ?? 'home';

// Basic security - only allow alphanumeric characters and hyphens
$page = preg_replace('/[^a-zA-Z0-9\-]/', '', $page);

// Define available pages
$availablePages = ['home', 'about', 'contact', 'claim', 'items', 'item', 'login', 'user-listings', 'settings', 'admin', 'changelog', 'communities', 'community'];

if (!in_array($page, $availablePages)) {
    $page = 'home';
}

// Log routing completion
$timingLogs['routing'] = round((microtime(true) - $startTime) * 1000, 2);
debugLog("Performance: Routing completed in {$timingLogs['routing']}ms");

// Get current user for navigation and page context
// Always check authentication for navigation bar display
$currentUser = null;
$isLoggedIn = false;

// Check authentication for navigation (but don't initialize AWS unless needed)
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
    // Only initialize AWS if we actually need it for this page
    $authRequiredPages = ['dashboard', 'claim', 'settings', 'admin'];
    if (in_array($page, $authRequiredPages)) {
        $currentUser = getCurrentUser();
        $isLoggedIn = isLoggedIn();
    } else {
        // For other pages, just check session without initializing AWS
        $isLoggedIn = true;
        $currentUser = $_SESSION['user'] ?? null;

        // Debug: Check if user data is available
        if (!$currentUser && isset($_SESSION['authenticated'])) {
            error_log("Warning: Session shows authenticated but no user data found");
        }
    }
}

// Log authentication completion
$timingLogs['auth'] = round((microtime(true) - $startTime) * 1000, 2);
debugLog("Performance: Authentication completed in {$timingLogs['auth']}ms");

// Prepare data for Open Graph meta tags
$ogData = [];

// Get page-specific data for meta tags
if ($page === 'item' && isset($_GET['id'])) {
    $trackingNumber = $_GET['id'];

    // For item pages, we need the actual title and description for proper link unfurling
    try {
        $dbItem = getItemFromDb($trackingNumber);

        if ($dbItem) {
            $title = $dbItem['title'];
            $description = $dbItem['description'];
            $price = $dbItem['price'] ?? 0;
            $imageKey = $dbItem['image_file'];

            $ogData['item'] = [
                'id' => $trackingNumber,
                'title' => 'Item #' . $trackingNumber . ' - ' . $title,
                'description' => $description . ($price > 0 ? ' - $' . $price : ' - Free'),
                'image_key' => $imageKey
            ];
        } else {
            // Fallback if item not found
            $ogData['item'] = [
                'id' => $trackingNumber,
                'title' => 'Item #' . $trackingNumber . ' - View on ClaimIt',
                'description' => 'View this item on ClaimIt',
                'image_key' => null
            ];
        }
    } catch (Exception $e) {
        // Fallback on any error
        $ogData['item'] = [
            'id' => $trackingNumber,
            'title' => 'Item #' . $trackingNumber . ' - View on ClaimIt',
            'description' => 'View this item on ClaimIt',
            'image_key' => null
        ];
    }
} elseif ($page === 'user-listings' && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $ogData['userId'] = $userId;

    // Query database for user's display name and item count
    try {
        $pdo = getDbConnection();
        if ($pdo) {
            // Get user's display name
            $userStmt = $pdo->prepare("SELECT display_name, name FROM users WHERE id = ? LIMIT 1");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $ogData['userName'] = $user['display_name'] ?: $user['name'];
            } else {
                $ogData['userName'] = 'User';
            }

            // Get item count for this user
            $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM items WHERE user_id = ?");
            $countStmt->execute([$userId]);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $itemCount = $countResult['count'] ?? 0;

            // Create dummy array with correct count for OG tag generation
            $ogData['items'] = array_fill(0, $itemCount, null);
        } else {
            $ogData['userName'] = 'User';
            $ogData['items'] = [];
        }
    } catch (Exception $e) {
        error_log("Error fetching user data for OG tags: " . $e->getMessage());
        $ogData['userName'] = 'User';
        $ogData['items'] = [];
    }
}

// Log Open Graph data preparation completion
$timingLogs['og_data'] = round((microtime(true) - $startTime) * 1000, 2);
debugLog("Performance: Open Graph data prepared in {$timingLogs['og_data']}ms");

// Log performance metrics
$loadTime = microtime(true) - $startTime;
debugLog("Performance: Page '{$page}' loaded in " . round($loadTime * 1000, 2) . "ms");

// Store timing data for display
$performanceData = [
    'total_time' => round($loadTime * 1000, 2),
    'routing_time' => $timingLogs['routing'],
    'auth_time' => $timingLogs['auth'],
    'og_data_time' => $timingLogs['og_data'],
    'before_template_time' => $timingLogs['before_template'] ?? 0,
    'after_template_time' => $timingLogs['after_template'] ?? 0
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClaimIt</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="/assets/images/claimit-logo.jpg">
    
    <!-- Open Graph Meta Tags for Social Media Previews -->
    <?php echo generateOpenGraphTags($page, $ogData); ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <a href="/?page=home" class="nav-logo">
                    <img src="/assets/images/claimit-logo.jpg" alt="ClaimIt Logo" class="nav-logo-image">
                    <span>ClaimIt</span>
                </a>
                
                <!-- Hamburger Menu Button (Mobile Only) -->
                <button class="hamburger-menu" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                
                <!-- Search Box -->
                <div class="nav-search">
                    <input type="text" id="searchInput" placeholder="Search items..." autocomplete="off">
                    <button id="clearSearch" style="display: none;" title="Clear search">&times;</button>
                </div>
                
                <ul class="nav-menu" id="navMenu">
                    <li><a href="/?page=items" class="nav-link <?php echo $page === 'items' ? 'active' : ''; ?>">View available items</a></li>
                    <li><a href="/?page=communities" class="nav-link <?php echo $page === 'communities' ? 'active' : ''; ?>">Browse communities</a></li>
                    <?php if ($isLoggedIn) : ?>
                        <li><a href="/?page=claim" class="nav-link <?php echo $page === 'claim' ? 'active' : ''; ?>">Make a new posting</a></li>
                        <li><a href="/?page=user-listings&id=<?php echo escape($currentUser['id']); ?>" class="nav-link <?php echo $page === 'user-listings' ? 'active' : ''; ?>">My Listings</a></li>
                        <li class="nav-user-menu">
                            <div class="nav-user-dropdown">
                                <button class="nav-user-trigger" onclick="toggleUserDropdown()">
                                    <?php if (!empty($currentUser['picture'])) : ?>
                                        <img src="<?php echo escape($currentUser['picture']); ?>" 
                                             alt="Profile" 
                                             class="nav-user-avatar"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                                             referrerpolicy="no-referrer">
                                        <div class="nav-user-avatar-fallback" style="display:none;">
                                            <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="nav-user-avatar-fallback">
                                            <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="nav-user-name"><?php echo escape($currentUser['name']); ?></span>
                                    <span class="nav-user-arrow">▼</span>
                                </button>
                                <div class="nav-user-dropdown-menu" id="userDropdown">
                                    <a href="/?page=settings" class="nav-dropdown-item">
                                        <span class="nav-dropdown-icon">⚙️</span>
                                        Settings
                                    </a>
                                    <?php if (isAdmin()) : ?>
                                    <a href="/?page=admin" class="nav-dropdown-item">
                                        <span class="nav-dropdown-icon">👑</span>
                                        Admin
                                    </a>
                                    <?php endif; ?>
                                    <a href="/?page=auth&action=logout" class="nav-dropdown-item">
                                        <span class="nav-dropdown-icon">🚪</span>
                                        Log out
                                    </a>
                                </div>
                            </div>
                        </li>
                    <?php else : ?>
                        <li><a href="/?page=login" class="nav-link <?php echo $page === 'login' ? 'active' : ''; ?>">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <?php
        // Log before template rendering
        $timingLogs['before_template'] = round((microtime(true) - $startTime) * 1000, 2);
        debugLog("Performance: Before template rendering in {$timingLogs['before_template']}ms");

        // Include the appropriate page template
        $templateFile = __DIR__ . "/../templates/{$page}.php";
        if (file_exists($templateFile)) {
            include $templateFile;
        } else {
            include __DIR__ . '/../templates/404.php';
        }

        // Log after template rendering
        $timingLogs['after_template'] = round((microtime(true) - $startTime) * 1000, 2);
        debugLog("Performance: After template rendering in {$timingLogs['after_template']}ms");
        ?>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> ClaimIt by Stonekeep.com. All rights reserved. | <a href="/?page=about" class="footer-link">About</a> | <a href="/?page=contact" class="footer-link">Contact</a> | <a href="/changelog" class="footer-link">Changelog</a></p>
            <div style="font-size: 12px; color: #666; margin-top: 10px;">
                Performance: Total: <?php echo $performanceData['total_time']; ?>ms | 
                Routing: <?php echo $performanceData['routing_time']; ?>ms | 
                Auth: <?php echo $performanceData['auth_time']; ?>ms | 
                OG Data: <?php echo $performanceData['og_data_time']; ?>ms | 
                Before Template: <?php echo $performanceData['before_template_time']; ?>ms | 
                After Template: <?php echo $performanceData['after_template_time']; ?>ms
            </div>
        </div>
    </footer>


    <!-- Edit Item Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Item</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="editTrackingNumber" name="trackingNumber">
                    <div class="form-group">
                        <label for="editTitle">Title:</label>
                        <input type="text" id="editTitle" name="title" required>
                        <small>Enter a descriptive title for your item</small>
                    </div>
                    <div class="form-group">
                        <label for="editDescription">Description:</label>
                        <textarea id="editDescription" name="description" rows="4" required></textarea>
                        <small>Provide details about the item's condition, features, etc.</small>
                    </div>
                    <div class="form-group">
                        <label>Visible in Communities:</label>
                        <small style="color: #666; font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">
                            Select which communities can see this item (at least one required)
                        </small>
                        <div id="editCommunityCheckboxes" class="community-checkboxes">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
    .community-checkboxes {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 6px;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .community-checkbox-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .community-checkbox-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .community-checkbox-item label {
        cursor: pointer;
        margin: 0;
        font-weight: normal;
    }
    </style>

    <script src="/assets/js/app.js?v=1735365542"></script>
    
    <!-- Claim Management JavaScript Functions -->
    <script>
    function addClaimToItem(trackingNumber) {
        const button = document.querySelector(`button[onclick="addClaimToItem('${trackingNumber}')"]`);
        if (!button) return;
        
        button.disabled = true;
        button.textContent = 'Adding...';
        
        fetch('?page=claim&action=add_claim', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${encodeURIComponent(trackingNumber)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showMessage(data.message, 'success');
                // Reload the page to update the UI
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showMessage(data.message, 'error');
                button.disabled = false;
                button.textContent = '🎯 Claim This!';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Network error while claiming item: ' + error.message, 'error');
            button.disabled = false;
            button.textContent = '🎯 Claim This!';
        });
    }
    
    function removeMyClaim(trackingNumber) {
        console.log('removeMyClaim called with trackingNumber:', trackingNumber);
        
        const button = document.querySelector(`button[onclick="removeMyClaim('${trackingNumber}')"]`);
        if (!button) {
            console.error('Button not found for trackingNumber:', trackingNumber);
            return;
        }
        
        button.disabled = true;
        button.textContent = 'Removing...';
        
        const url = '?page=claim&action=remove_claim';
        const body = `id=${encodeURIComponent(trackingNumber)}`;
        
        console.log('Making fetch request to:', url);
        console.log('Request body:', body);
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // Show success message
                showMessage(data.message, 'success');
                // Reload the page to update the UI
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showMessage(data.message, 'error');
                button.disabled = false;
                button.textContent = '🚫 Remove My Claim';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showMessage('An error occurred while removing your claim', 'error');
            button.disabled = false;
            button.textContent = '🚫 Remove My Claim';
        });
    }
    
    function deleteItem(trackingNumber) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            return;
        }
        
        const button = document.querySelector(`button[onclick="deleteItem('${trackingNumber}')"]`);
        if (!button) return;
        
        button.disabled = true;
        button.textContent = 'Deleting...';
        
        fetch('?page=claim&action=delete_item', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${encodeURIComponent(trackingNumber)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showMessage(data.message, 'error');
                button.disabled = false;
                button.textContent = '🗑️ Delete';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred while deleting the item', 'error');
            button.disabled = false;
            button.textContent = '🗑️ Delete';
        });
    }
    

    </script>
    
    <?php if ($page === 'home') : ?>
    <script>
        // Load items after page loads for maximum performance
        document.addEventListener('DOMContentLoaded', function() {
            const itemsGrid = document.getElementById('items-grid');
            const loadingIndicator = document.getElementById('loading-indicator');
            
            if (itemsGrid && loadingIndicator) {
                // Load items via AJAX
                fetch('?page=home&ajax=1')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text();
                    })
                    .then(html => {
                        loadingIndicator.remove();
                        itemsGrid.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Error loading items:', error);
                        loadingIndicator.innerHTML = '<div style="text-align: center; padding: 1rem;"><p style="color: var(--red-600); font-weight: 500;">Error loading items: ' + error.message + '</p><p style="color: var(--gray-600); font-size: 0.9rem; margin-top: 0.5rem;">Please refresh the page to try again.</p></div>';
                    });
            } else {
                console.error('Could not find items-grid or loading-indicator elements');
                if (loadingIndicator) {
                    loadingIndicator.innerHTML = '<div style="text-align: center; padding: 1rem;"><p style="color: var(--red-600); font-weight: 500;">Error: Could not find required elements</p><p style="color: var(--gray-600); font-size: 0.9rem; margin-top: 0.5rem;">Please refresh the page to try again.</p></div>';
                }
            }
        });
    </script>
    <?php endif; ?>
    
    <!-- Search functionality -->
    <script>
    (function() {
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.getElementById('clearSearch');
        const mainContent = document.querySelector('.main-content');
        
        if (!searchInput || !mainContent) return;
        
        let searchTimeout;
        let originalContent = null;
        let isSearching = false;
        
        // Handle search input
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Show/hide clear button
            if (query) {
                clearBtn.style.display = 'flex';
            } else {
                clearBtn.style.display = 'none';
                restoreOriginalView();
                return;
            }
        });
        
        // Handle Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
        
        // Handle clear button
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            restoreOriginalView();
            searchInput.focus();
        });
        
        function performSearch() {
            const query = searchInput.value.trim();
            
            if (!query) {
                restoreOriginalView();
                return;
            }
            
            // Save original content if not already saved
            if (!isSearching) {
                originalContent = mainContent.innerHTML;
                isSearching = true;
            }
            
            // Show loading state
            mainContent.innerHTML = '<div class="loading-search" style="text-align: center; padding: 3rem;"><p style="color: var(--gray-600); font-size: 1.1rem;">🔍 Searching...</p></div>';
            
            // Perform search
            fetch('/?action=search&query=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displaySearchResults(data.items, query);
                    } else {
                        mainContent.innerHTML = '<div class="error-message" style="text-align: center; padding: 3rem;"><p style="color: var(--error-600);">Error: ' + (data.error || 'Search failed') + '</p></div>';
                    }
                })
                .catch(error => {
                    mainContent.innerHTML = '<div class="error-message" style="text-align: center; padding: 3rem;"><p style="color: var(--error-600);">Error performing search. Please try again.</p></div>';
                    console.error('Search error:', error);
                });
        }
        
        function displaySearchResults(items, query) {
            if (items.length === 0) {
                mainContent.innerHTML = `
                    <div class="search-results">
                        <div class="search-header" style="text-align: center; padding: 2rem 1rem 1rem;">
                            <h2 style="color: var(--gray-700); margin-bottom: 0.5rem;">No results found for "${escapeHtml(query)}"</h2>
                            <p style="color: var(--gray-500);">Try a different search term</p>
                        </div>
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="search-results">
                    <div class="search-header" style="padding: 1rem 0 2rem; text-align: center;">
                        <h2 style="color: var(--gray-700); margin-bottom: 0.5rem;">Found ${items.length} result${items.length !== 1 ? 's' : ''} for "${escapeHtml(query)}"</h2>
                    </div>
                    <div class="items-grid">
            `;
            
            items.forEach(item => {
                html += generateItemCard(item);
            });
            
            html += '</div></div>';
            mainContent.innerHTML = html;
        }
        
        function generateItemCard(item) {
            const imageUrl = item.image_file ? 
                'https://dpwmq6brmwcyc.cloudfront.net/' + item.image_file.replace('images/', '') :
                '/assets/images/placeholder.jpg';
            
            const itemUrl = '/?page=item&id=' + encodeURIComponent(item.id);
            const statusClass = item.status === 'gone' ? 'status-gone' : '';
            const statusBadge = item.status === 'gone' ? '<span class="gone-badge">GONE</span>' : '';
            
            return `
                <div class="item-card ${statusClass}">
                    <a href="${itemUrl}" class="item-link">
                        <div class="item-image-container">
                            <img src="${imageUrl}" alt="${escapeHtml(item.title || 'Item')}" class="item-image" loading="lazy">
                            ${statusBadge}
                        </div>
                        <div class="item-content">
                            <h3 class="item-title">${escapeHtml(item.title || 'Untitled')}</h3>
                            <p class="item-description">${escapeHtml(item.description || '').substring(0, 100)}${(item.description || '').length > 100 ? '...' : ''}</p>
                            <div class="item-meta">
                                <span class="item-price">${parseFloat(item.price || 0) > 0 ? '$' + parseFloat(item.price || 0).toFixed(2) : 'Free'}</span>
                            </div>
                        </div>
                    </a>
                </div>
            `;
        }
        
        function restoreOriginalView() {
            if (isSearching && originalContent) {
                mainContent.innerHTML = originalContent;
                originalContent = null;
                isSearching = false;
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    })();
    </script>
</body>
</html> 