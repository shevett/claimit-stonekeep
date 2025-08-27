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
    header("Location: ?page=" . urlencode($page));
    exit;
}

/**
 * Check if user is logged in (placeholder function)
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data (placeholder function)
 */
function getCurrentUser() {
    if (isLoggedIn()) {
        return $_SESSION['user_data'] ?? null;
    }
    return null;
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
    try {
        return new ClaimIt\AwsService();
    } catch (Exception $e) {
        error_log('AWS Service initialization failed: ' . $e->getMessage());
        return null;
    }
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

?> 