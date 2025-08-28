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

// Handle AJAX claim/unclaim requests before any HTML output
if (isset($_POST['action']) && ($_POST['action'] === 'claim' || $_POST['action'] === 'unclaim') && isset($_POST['tracking_number'])) {
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
        $awsService = getAwsService();
        if (!$awsService) {
            throw new Exception('AWS service not available');
        }
        
        $yamlKey = $trackingNumber . '.yaml';
        
        // Check if item exists
        if (!$awsService->objectExists($yamlKey)) {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            exit;
        }
        
        // Get current YAML data
        $objectData = $awsService->getObject($yamlKey);
        $yamlContent = $objectData['content'];
        $data = parseSimpleYaml($yamlContent);
        
        // Check if user is trying to claim their own item
        if (isset($data['user_id']) && $data['user_id'] === $currentUser['id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot claim your own item']);
            exit;
        }
        
        if ($action === 'claim') {
            // Check if item is already claimed
            if (isset($data['claimed_by'])) {
                if ($data['claimed_by'] === $currentUser['id']) {
                    echo json_encode(['success' => false, 'message' => 'You have already claimed this item']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'This item has already been claimed by someone else']);
                }
                exit;
            }
            
            // Add claim information
            $data['claimed_by'] = $currentUser['id'];
            $data['claimed_by_name'] = $currentUser['name'];
            $data['claimed_by_email'] = $currentUser['email'];
            $data['claimed_at'] = date('Y-m-d H:i:s');
            
            $message = 'Item claimed successfully!';
            
        } else { // unclaim
            // Check if user has claimed this item
            if (!isset($data['claimed_by']) || $data['claimed_by'] !== $currentUser['id']) {
                echo json_encode(['success' => false, 'message' => 'You have not claimed this item']);
                exit;
            }
            
            // Remove claim information
            unset($data['claimed_by']);
            unset($data['claimed_by_name']);
            unset($data['claimed_by_email']);
            unset($data['claimed_at']);
            
            $message = 'Item unclaimed successfully!';
        }
        
        // Regenerate YAML content
        $newYamlContent = "tracking_number: '" . $data['tracking_number'] . "'\n";
        $newYamlContent .= "title: '" . str_replace("'", "''", $data['title']) . "'\n";
        $newYamlContent .= "description: |\n";
        $newYamlContent .= "  " . str_replace("\n", "\n  ", $data['description']) . "\n";
        $newYamlContent .= "price: " . $data['price'] . "\n";
        $newYamlContent .= "contact_email: '" . $data['contact_email'] . "'\n";
        $newYamlContent .= "image_file: " . (isset($data['image_file']) && $data['image_file'] ? "'" . $data['image_file'] . "'" : "null") . "\n";
        $newYamlContent .= "user_id: '" . $data['user_id'] . "'\n";
        $newYamlContent .= "user_name: '" . str_replace("'", "''", $data['user_name']) . "'\n";
        $newYamlContent .= "user_email: '" . $data['user_email'] . "'\n";
        $newYamlContent .= "submitted_at: '" . $data['submitted_at'] . "'\n";
        $newYamlContent .= "submitted_timestamp: " . $data['submitted_timestamp'] . "\n";
        
        // Add claim information if item is claimed
        if (isset($data['claimed_by'])) {
            $newYamlContent .= "claimed_by: '" . $data['claimed_by'] . "'\n";
            $newYamlContent .= "claimed_by_name: '" . str_replace("'", "''", $data['claimed_by_name']) . "'\n";
            $newYamlContent .= "claimed_by_email: '" . $data['claimed_by_email'] . "'\n";
            $newYamlContent .= "claimed_at: '" . $data['claimed_at'] . "'\n";
        }
        
        // Update the YAML file
        $awsService->putObject($yamlKey, $newYamlContent, 'text/plain');
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'action' => $action,
            'claimed' => isset($data['claimed_by']),
            'claimed_by_current_user' => isset($data['claimed_by']) && $data['claimed_by'] === $currentUser['id']
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to ' . $action . ' item: ' . $e->getMessage()]);
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
                    redirect('dashboard');
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClaimIt</title>
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
                    <li><a href="?page=about" class="nav-link <?php echo $page === 'about' ? 'active' : ''; ?>">About</a></li>
                    <li><a href="?page=items" class="nav-link <?php echo $page === 'items' ? 'active' : ''; ?>">View available items</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li><a href="?page=claim" class="nav-link <?php echo $page === 'claim' ? 'active' : ''; ?>">Make a new posting</a></li>
                        <li><a href="?page=dashboard" class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>">My Listings</a></li>
                        <li class="nav-user-menu">
                            <span class="nav-user-info">
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
                                <?php echo escape($currentUser['name']); ?>
                            </span>
                            <a href="?page=auth&action=logout" class="nav-link">Logout</a>
                        </li>
                    <?php else: ?>
                        <li><a href="?page=login" class="nav-link <?php echo $page === 'login' ? 'active' : ''; ?>">Login</a></li>
                    <?php endif; ?>
                    <li><a href="?page=contact" class="nav-link <?php echo $page === 'contact' ? 'active' : ''; ?>">Contact</a></li>
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
            <p>&copy; <?php echo date('Y'); ?> ClaimIt by Stonekeep.com. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/app.js"></script>
</body>
</html> 