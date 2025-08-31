<?php
/**
 * ClaimIt Web Application
 * Main entry point
 */

// Suppress PHP 8.4 deprecation warnings while keeping actual errors
error_reporting(0); // Suppress all errors for production-like experience
ini_set('display_errors', '0');
ini_set('log_errors', '1');

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
    
    // Check if user owns this item
    if (!currentUserOwnsItem($trackingNumber)) {
        echo json_encode(['success' => false, 'message' => 'You can only delete your own items']);
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
if (isset($_POST['action']) && in_array($_POST['action'], ['add_claim', 'remove_claim', 'remove_claim_by_owner']) && isset($_POST['tracking_number'])) {
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
                removeMyClaim($trackingNumber);
                echo json_encode(['success' => true, 'message' => 'You\'ve been removed from the waitlist']);
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
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
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
$availablePages = ['home', 'about', 'contact', 'claim', 'items', 'item', 'login', 'dashboard', 'user-listings'];

if (!in_array($page, $availablePages)) {
    $page = 'home';
}

// Get current user for navigation and page context
$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();

// Prepare data for Open Graph meta tags
$ogData = [];

// Get page-specific data for meta tags
if ($page === 'item' && isset($_GET['id'])) {
    $trackingNumber = $_GET['id'];
    $awsService = getAwsService();
    if ($awsService) {
        try {
            $yamlKey = $trackingNumber . '.yaml';
            $yamlObject = $awsService->getObject($yamlKey);
            $yamlContent = $yamlObject['content'];
            $data = parseSimpleYaml($yamlContent);
            
            if ($data) {
                // Check for image
                $imageKey = null;
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                foreach ($imageExtensions as $ext) {
                    $possibleImageKey = $trackingNumber . '.' . $ext;
                    if ($awsService->objectExists($possibleImageKey)) {
                        $imageKey = $possibleImageKey;
                        break;
                    }
                }
                
                $ogData['item'] = [
                    'tracking_number' => $trackingNumber,
                    'title' => $data['title'] ?? $data['description'] ?? 'Untitled',
                    'description' => $data['description'] ?? '',
                    'image_key' => $imageKey
                ];
            }
        } catch (Exception $e) {
            // Item not found, continue without meta data
        }
    }
} elseif ($page === 'user-listings' && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $awsService = getAwsService();
    if ($awsService) {
        try {
            $result = $awsService->listObjects();
            $objects = $result['objects'] ?? [];
            $items = [];
            $userName = '';
            
            foreach ($objects as $object) {
                if (str_ends_with($object['key'], '.yaml')) {
                    $yamlObject = $awsService->getObject($object['key']);
                    $yamlContent = $yamlObject['content'];
                    $data = parseSimpleYaml($yamlContent);
                    
                    if ($data && isset($data['user_id']) && $data['user_id'] === $userId) {
                        if (empty($userName)) {
                            $userName = $data['user_name'] ?? 'Legacy User';
                        }
                        $items[] = $data;
                    }
                }
            }
            
            $ogData['userName'] = $userName;
            $ogData['items'] = $items;
            $ogData['userId'] = $userId;
        } catch (Exception $e) {
            // Error loading user data, continue without meta data
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClaimIt</title>
    
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
                <a href="?page=home" class="nav-logo">ClaimIt</a>
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
                                    <a href="#" class="nav-dropdown-item">
                                        <span class="nav-dropdown-icon">⚙️</span>
                                        Settings...
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
        // Include the appropriate page template
        $templateFile = __DIR__ . "/../templates/{$page}.php";
        if (file_exists($templateFile)) {
            include $templateFile;
        } else {
            include __DIR__ . '/../templates/404.php';
        }
        ?>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> ClaimIt by Stonekeep.com. All rights reserved. | <a href="?page=about" class="footer-link">About</a> | <a href="?page=contact" class="footer-link">Contact</a></p>
        </div>
    </footer>

    <script src="/assets/js/app.js"></script>
</body>
</html> 