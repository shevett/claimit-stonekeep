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
        
        // Debug output
        error_log("DEBUG: getActiveClaims - trackingNumber: $trackingNumber");
        error_log("DEBUG: getActiveClaims - raw YAML: " . substr($yamlContent, 0, 1000));
        error_log("DEBUG: getActiveClaims - parsed data: " . print_r($data, true));
        
        $claims = $data['claims'] ?? [];
        error_log("DEBUG: getActiveClaims - claims array: " . print_r($claims, true));
        
        $activeClaims = [];
        
        foreach ($claims as $claim) {
            // Consider claims active if they have no status field (legacy) or status is 'active'
            // Exclude claims with status 'removed'
            $status = $claim['status'] ?? 'active'; // Default to active for legacy claims
            error_log("DEBUG: getActiveClaims - claim status: " . $status);
            if ($status === 'active') {
                $activeClaims[] = $claim;
            }
        }
        
        error_log("DEBUG: getActiveClaims - final activeClaims: " . print_r($activeClaims, true));
        
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
    
    // Debug output
    error_log("DEBUG: isUserClaimed - trackingNumber: $trackingNumber, userId: $userId");
    error_log("DEBUG: isUserClaimed - activeClaims: " . print_r($activeClaims, true));
    
    foreach ($activeClaims as $claim) {
        error_log("DEBUG: isUserClaimed - comparing claim user_id: " . $claim['user_id'] . " with userId: $userId");
        if ($claim['user_id'] === $userId) {
            error_log("DEBUG: isUserClaimed - MATCH FOUND!");
            return true;
        }
    }
    
    error_log("DEBUG: isUserClaimed - NO MATCH FOUND");
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

?> 