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
    // Use absolute URL to avoid path issues
    $baseUrl = 'http://localhost:8000/';
    echo "<script>window.location.href = '" . $baseUrl . "?page=" . urlencode($page) . "';</script>";
    echo "<p>Redirecting to " . htmlspecialchars($page) . "... <a href='" . $baseUrl . "?page=" . urlencode($page) . "'>Click here if not redirected automatically</a></p>";
    exit;
}

/**
 * Get Authentication service instance
 */
function getAuthService() {
    static $authService = null;
    
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
 * Get AWS service instance
 * 
 * @return ClaimIt\AwsService|null
 */
function getAwsService() {
    static $awsService = null;
    
    if ($awsService === null) {
        try {
            $awsService = new ClaimIt\AwsService();
        } catch (Exception $e) {
            error_log('AWS Service initialization failed: ' . $e->getMessage());
            return null;
        }
    }
    
    return $awsService;
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
    
    // Basic meta tags
    $html .= '<meta property="og:title" content="' . htmlspecialchars($metaTags['title']) . '">' . "\n";
    $html .= '<meta property="og:description" content="' . htmlspecialchars($metaTags['description']) . '">' . "\n";
    $html .= '<meta property="og:type" content="' . htmlspecialchars($metaTags['type']) . '">' . "\n";
    $html .= '<meta property="og:url" content="' . htmlspecialchars($metaTags['url']) . '">' . "\n";
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

?> 