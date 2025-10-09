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
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log AWS SDK compatibility warnings to environment logs but don't display them
    if (strpos($errfile, 'aws-sdk-php') !== false && strpos($errstr, 'syntax error') !== false) {
        error_log("AWS SDK PHP 8.4 Compatibility Warning: $errstr in $errfile on line $errline");
        return true; // Suppress from browser display
    }
    // Let other errors through to default handler
    return false;
});

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
        
        // Load items
        $items = [];
        
        if (hasAwsCredentials()) {
            $itemsStartTime = microtime(true);
            debugLog("AJAX: Starting getAllItemsEfficiently");
            $items = getAllItemsEfficiently($showGoneItems);
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

// Handle AJAX delete requests before any HTML output
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['tracking_number'])) {
    header('Content-Type: application/json');
    
    $trackingNumber = $_POST['tracking_number'];
    
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

// Handle AJAX claim requests before any HTML output
if (isset($_POST['action']) && in_array($_POST['action'], ['add_claim', 'remove_claim', 'remove_claim_by_owner', 'delete_item', 'edit_item', 'rotate_image', 'mark_gone', 'relist_item']) && isset($_POST['tracking_number'])) {
    header('Content-Type: application/json');
    
    $trackingNumber = $_POST['tracking_number'];
    $action = $_POST['action'];
    
    if (!preg_match('/^\d{14}$/', $trackingNumber)) {
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
                error_log("DEBUG: remove_claim action called for tracking number: $trackingNumber");
                try {
                    removeMyClaim($trackingNumber);
                    error_log("DEBUG: removeMyClaim completed successfully");
                    echo json_encode(['success' => true, 'message' => 'You\'ve been removed from the waitlist']);
                } catch (Exception $e) {
                    error_log("DEBUG: removeMyClaim failed with error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;
                
            case 'remove_claim_by_owner':
                if (!isset($_POST['claim_user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Claim user ID required']);
                    exit;
                }
                $claimUserId = $_POST['claim_user_id'];
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
                
                // Delete the image if it exists
                if (!empty($item['image_key'])) {
                    $awsService->deleteObject($item['image_key']);
                }
                
                // Clear items cache since we deleted an item
                clearItemsCache();
                
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
                break;
                
            case 'edit_item':
                error_log("DEBUG: edit_item case entered for tracking number: " . $trackingNumber);
                // Check if the current user can edit this item (owner or admin)
                $item = getItem($trackingNumber);
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
                
                // Update the item data
                $item['title'] = $title;
                $item['description'] = $description;
                
                // Convert to YAML and save back to S3
                error_log("DEBUG: edit_item - About to convert item to YAML: " . print_r($item, true));
                $awsService = getAwsService();
                if (!$awsService) {
                    throw new Exception('AWS service not available');
                }
                
                $yamlContent = convertToYaml($item);
                error_log("DEBUG: edit_item - Generated YAML content: " . $yamlContent);
                $yamlKey = $trackingNumber . '.yaml';
                error_log("DEBUG: edit_item - Saving to S3 key: " . $yamlKey);
                $result = $awsService->putObject($yamlKey, $yamlContent);
                error_log("DEBUG: edit_item - S3 putObject result: " . print_r($result, true));
                
                // Clear items cache since we updated an item
                clearItemsCache();
                
                echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
                break;
                
            case 'rotate_image':
                // Check if the current user can edit this item (owner or admin)
                $item = getItem($trackingNumber);
                if (!$item || !canUserEditItem($item['user_id'] ?? null)) {
                    echo json_encode(['success' => false, 'message' => 'You can only rotate images for your own items or be an administrator']);
                    exit;
                }
                
                // Determine the image key by checking for image files with different extensions
                $awsService = getAwsService();
                if (!$awsService) {
                    echo json_encode(['success' => false, 'message' => 'AWS service not available']);
                    exit;
                }
                
                $imageKey = null;
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                foreach ($imageExtensions as $ext) {
                    $possibleImageKey = 'images/' . $trackingNumber . '.' . $ext;
                    try {
                        if ($awsService->objectExists($possibleImageKey)) {
                            $imageKey = $possibleImageKey;
                            break;
                        }
                    } catch (Exception $e) {
                        // Continue to next extension
                    }
                }
                
                // Check if item has an image
                if (empty($imageKey)) {
                    echo json_encode(['success' => false, 'message' => 'No image found for this item']);
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
                    
                    // Clear image URL cache since the image was modified
                    clearImageUrlCache();
                    
                    // Add cache-busting timestamp to force browser refresh
                    $cacheBuster = time();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Image rotated successfully',
                        'cache_buster' => $cacheBuster
                    ]);
                    
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to rotate image: ' . $e->getMessage()]);
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

// Handle GET-based AJAX claim requests (for backward compatibility)
if (isset($_GET['page']) && $_GET['page'] === 'claim' && isset($_GET['action']) && in_array($_GET['action'], ['add_claim', 'remove_claim', 'remove_claim_by_owner', 'delete_item', 'edit_item', 'rotate_image', 'mark_gone', 'relist_item'])) {
    header('Content-Type: application/json');
    
    // For GET requests, we need to get the tracking number from POST data
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
    
    if (!isset($_POST['tracking_number'])) {
        echo json_encode(['success' => false, 'message' => 'Tracking number required']);
        exit;
    }
    
    $trackingNumber = $_POST['tracking_number'];
    $action = $_GET['action'];
    
    if (!preg_match('/^\d{14}$/', $trackingNumber)) {
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
                error_log("DEBUG: remove_claim action called for tracking number: $trackingNumber");
                try {
                    removeMyClaim($trackingNumber);
                    error_log("DEBUG: removeMyClaim completed successfully");
                    echo json_encode(['success' => true, 'message' => 'You\'ve been removed from the waitlist']);
                } catch (Exception $e) {
                    error_log("DEBUG: removeMyClaim failed with error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;
                
            case 'remove_claim_by_owner':
                if (!isset($_POST['claim_user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Claim user ID required']);
                    exit;
                }
                $claimUserId = $_POST['claim_user_id'];
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
                
                // Delete the image if it exists
                if (!empty($item['image_key'])) {
                    $awsService->deleteObject($item['image_key']);
                }
                
                // Clear items cache since we deleted an item
                clearItemsCache();
                
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
                break;
                
            case 'edit_item':
                // Check if the current user can edit this item (owner or admin)
                $item = getItem($trackingNumber);
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
                
                // Update the item data
                $item['title'] = $title;
                $item['description'] = $description;
                
                // Convert to YAML and save back to S3
                $awsService = getAwsService();
                if (!$awsService) {
                    throw new Exception('AWS service not available');
                }
                
                $yamlContent = convertToYaml($item);
                $yamlKey = $trackingNumber . '.yaml';
                $awsService->putObject($yamlKey, $yamlContent);
                
                // Clear items cache since we updated an item
                clearItemsCache();
                
                echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
                break;
                
            case 'rotate_image':
                // Check if the current user can edit this item (owner or admin)
                $item = getItem($trackingNumber);
                if (!$item || !canUserEditItem($item['user_id'] ?? null)) {
                    echo json_encode(['success' => false, 'message' => 'You can only rotate images for your own items or be an administrator']);
                    exit;
                }
                
                // Determine the image key by checking for image files with different extensions
                $awsService = getAwsService();
                if (!$awsService) {
                    echo json_encode(['success' => false, 'message' => 'AWS service not available']);
                    exit;
                }
                
                $imageKey = null;
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                foreach ($imageExtensions as $ext) {
                    $possibleImageKey = 'images/' . $trackingNumber . '.' . $ext;
                    try {
                        if ($awsService->objectExists($possibleImageKey)) {
                            $imageKey = $possibleImageKey;
                            break;
                        }
                    } catch (Exception $e) {
                        // Continue to next extension
                    }
                }
                
                // Check if item has an image
                if (empty($imageKey)) {
                    echo json_encode(['success' => false, 'message' => 'No image found for this item']);
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
                    
                    // Clear image URL cache since the image was modified
                    clearImageUrlCache();
                    
                    // Add cache-busting timestamp to force browser refresh
                    $cacheBuster = time();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Image rotated successfully',
                        'cache_buster' => $cacheBuster
                    ]);
                    
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to rotate image: ' . $e->getMessage()]);
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
        $showGoneItems = isset($_POST['show_gone_items']) && $_POST['show_gone_items'] === 'on';
        $emailNotifications = isset($_POST['email_notifications']) && $_POST['email_notifications'] === 'on';
        $newListingNotifications = isset($_POST['new_listing_notifications']) && $_POST['new_listing_notifications'] === 'on';
        $sendTestEmail = isset($_POST['send_test_email']) && $_POST['send_test_email'] === 'on';
        
        if (empty($displayName)) {
            echo json_encode(['success' => false, 'message' => 'Display name is required']);
            exit;
        }
        
        try {
            $awsService = getAwsService();
            if (!$awsService) {
                throw new Exception('AWS service not available');
            }
            
            // Create user settings data
            $userSettings = [
                'user_id' => $currentUser['id'],
                'google_name' => $currentUser['name'],
                'display_name' => $displayName,
                'show_gone_items' => $showGoneItems ? 'yes' : 'no',
                'email_notifications' => $emailNotifications ? 'yes' : 'no',
                'new_listing_notifications' => $newListingNotifications ? 'yes' : 'no',
                'email' => $currentUser['email'],
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_timestamp' => time()
            ];
            
            // Convert to YAML
            $yamlContent = convertToYaml($userSettings);
            
            // Save to S3 in users/ directory
            $yamlKey = 'users/' . $currentUser['id'] . '.yaml';
            $awsService->putObject($yamlKey, $yamlContent);
            
            // Send test email if requested and user is admin
            $testEmailSent = false;
            if ($sendTestEmail && isAdmin()) {
                try {
                    $emailService = getEmailService();
                    if ($emailService) {
                        $testEmailSent = $emailService->sendTestEmail($currentUser);
                    }
                } catch (Exception $e) {
                    error_log("Failed to send test email: " . $e->getMessage());
                }
            }
            
            $message = 'Settings saved successfully';
            if ($sendTestEmail && isAdmin()) {
                $message .= $testEmailSent ? '. Test email sent!' : '. Test email failed to send.';
            }
            
            echo json_encode(['success' => true, 'message' => $message]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $e->getMessage()]);
        }
        
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
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
$availablePages = ['home', 'about', 'contact', 'claim', 'items', 'item', 'login', 'dashboard', 'user-listings', 'settings'];

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
    $authRequiredPages = ['dashboard', 'claim', 'settings'];
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
        $awsService = getAwsService();
        if ($awsService) {
            $yamlKey = $trackingNumber . '.yaml';
            $yamlObject = $awsService->getObject($yamlKey);
            $yamlContent = $yamlObject['content'];
            $data = parseSimpleYaml($yamlContent);
            
            if ($data && isset($data['description'])) {
                $title = $data['title'] ?? $data['description'];
                $description = $data['description'];
                $price = $data['price'] ?? 0;
                
                // Check for image
                $imageKey = null;
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                foreach ($imageExtensions as $ext) {
                    $possibleImageKey = 'images/' . $trackingNumber . '.' . $ext;
                    try {
                        if ($awsService->objectExists($possibleImageKey)) {
                            $imageKey = $possibleImageKey;
                            break;
                        }
                    } catch (Exception $e) {
                        // Continue to next extension
                    }
                }
                
                $ogData['item'] = [
                    'tracking_number' => $trackingNumber,
                    'title' => 'Item #' . $trackingNumber . ' - ' . $title,
                    'description' => $description . ($price > 0 ? ' - $' . $price : ' - Free'),
                    'image_key' => $imageKey
                ];
            } else {
                // Fallback if YAML parsing fails
                $ogData['item'] = [
                    'tracking_number' => $trackingNumber,
                    'title' => 'Item #' . $trackingNumber . ' - View on ClaimIt',
                    'description' => 'View this item on ClaimIt',
                    'image_key' => null
                ];
            }
        } else {
        // Fallback if AWS service unavailable
        $ogData['item'] = [
            'tracking_number' => $trackingNumber,
            'title' => 'Item #' . $trackingNumber . ' - View on ClaimIt',
            'description' => 'View this item on ClaimIt',
            'image_key' => null
        ];
        }
    } catch (Exception $e) {
        // Fallback on any error
        $ogData['item'] = [
            'tracking_number' => $trackingNumber,
            'title' => 'Item #' . $trackingNumber . ' - View on ClaimIt',
            'description' => 'View this item on ClaimIt',
            'image_key' => null
        ];
    }
} elseif ($page === 'user-listings' && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $ogData['userId'] = $userId;
    $ogData['userName'] = 'User #' . $userId;
    $ogData['items'] = [];
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
    'routing_time' => $timingLogs['routing'] ?? 0,
    'auth_time' => $timingLogs['auth'] ?? 0,
    'og_data_time' => $timingLogs['og_data'] ?? 0,
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
                <a href="?page=home" class="nav-logo">
                    <img src="/assets/images/claimit-logo.jpg" alt="ClaimIt Logo" class="nav-logo-image">
                    <span>ClaimIt</span>
                </a>
                <ul class="nav-menu">
                    <li><a href="?page=items" class="nav-link <?php echo $page === 'items' ? 'active' : ''; ?>">View available items</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li><a href="?page=claim" class="nav-link <?php echo $page === 'claim' ? 'active' : ''; ?>">Make a new posting</a></li>
                        <li><a href="?page=user-listings&id=<?php echo escape($currentUser['id']); ?>" class="nav-link <?php echo $page === 'user-listings' ? 'active' : ''; ?>">My Listings</a></li>
                        <li class="nav-user-menu">
                            <div class="nav-user-dropdown">
                                <button class="nav-user-trigger" onclick="toggleUserDropdown()">
                                    <?php if (!empty($currentUser['picture'])): ?>
                                        <img src="<?php echo escape($currentUser['picture']); ?>" 
                                             alt="Profile" 
                                             class="nav-user-avatar"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                                             referrerpolicy="no-referrer">
                                        <div class="nav-user-avatar-fallback" style="display:none;">
                                            <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="nav-user-avatar-fallback">
                                            <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="nav-user-name"><?php echo escape($currentUser['name']); ?></span>
                                    <span class="nav-user-arrow">▼</span>
                                </button>
                                <div class="nav-user-dropdown-menu" id="userDropdown">
                                    <a href="?page=settings" class="nav-dropdown-item">
                                        <span class="nav-dropdown-icon">⚙️</span>
                                        Settings
                                    </a>
                                    <a href="?page=auth&action=logout" class="nav-dropdown-item">
                                        <span class="nav-dropdown-icon">🚪</span>
                                        Log out
                                    </a>
                                </div>
                            </div>
                        </li>
                    <?php else: ?>
                        <li><a href="?page=login" class="nav-link <?php echo $page === 'login' ? 'active' : ''; ?>">Login</a></li>
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
            <p>&copy; <?php echo date('Y'); ?> ClaimIt by Stonekeep.com. All rights reserved. | <a href="?page=about" class="footer-link">About</a> | <a href="?page=contact" class="footer-link">Contact</a></p>
            <?php if (isset($performanceData)): ?>
            <div style="font-size: 12px; color: #666; margin-top: 10px;">
                Performance: Total: <?php echo $performanceData['total_time']; ?>ms | 
                Routing: <?php echo $performanceData['routing_time']; ?>ms | 
                Auth: <?php echo $performanceData['auth_time']; ?>ms | 
                OG Data: <?php echo $performanceData['og_data_time']; ?>ms | 
                Before Template: <?php echo $performanceData['before_template_time']; ?>ms | 
                After Template: <?php echo $performanceData['after_template_time']; ?>ms
            </div>
            <?php endif; ?>
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
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/assets/js/app.js?v=1757534999"></script>
    
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
            body: `tracking_number=${encodeURIComponent(trackingNumber)}`
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
            showMessage('An error occurred while claiming the item', 'error');
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
        const body = `tracking_number=${encodeURIComponent(trackingNumber)}`;
        
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
            body: `tracking_number=${encodeURIComponent(trackingNumber)}`
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
    
    <?php if ($page === 'home'): ?>
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
</body>
</html> 