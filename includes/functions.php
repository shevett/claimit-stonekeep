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

?> 