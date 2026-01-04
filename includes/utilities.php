<?php

/**
 * Utility and helper functions
 */

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
                $metaTags['url'] = $baseUrl . '?page=item&id=' . urlencode($item['id']);

                // Add image if available
                if (isset($item['image_key']) && $item['image_key']) {
                    // Use permanent CloudFront URL for Open Graph metadata
                    // This ensures Slack and other platforms can cache the image URL indefinitely
                    // without it expiring (unlike presigned URLs which expire after 1 hour)
                    require_once __DIR__ . '/images.php';
                    $imageUrl = getCloudFrontUrl($item['image_key']);
                    $metaTags['image'] = $imageUrl;
                    $metaTags['image_width'] = '1200';
                    $metaTags['image_height'] = '630';
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