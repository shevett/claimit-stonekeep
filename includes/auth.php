<?php

/**
 * Authentication and authorization functions
 */

/**
 * Get Authentication service instance (lazy loading)
 */
function getAuthService()
{
    static $authService = null;

    // Always attempt initialization if not already done
    // This ensures fresh initialization on each request
    if ($authService === null) {
        try {
            $authService = new \ClaimIt\AuthService();
        } catch (Exception $e) {
            error_log('Failed to initialize Auth service: ' . $e->getMessage());
            return null;
        }
    }

    return $authService;
}

/**
 * Get Email service instance (lazy loading)
 */
function getEmailService()
{
    static $emailService = null;

    if ($emailService === null) {
        try {
            $awsService = getAwsService();
            if (!$awsService) {
                throw new Exception('AWS service not available');
            }
            $emailService = new \ClaimIt\EmailService($awsService);
        } catch (Exception $e) {
            error_log('Failed to initialize Email service: ' . $e->getMessage());
            return null;
        }
    }

    return $emailService;
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    $authService = getAuthService();
    return $authService ? $authService->isLoggedIn() : false;
}

/**
 * Get current authenticated user
 */
function getCurrentUser()
{
    $authService = getAuthService();
    return $authService ? $authService->getCurrentUser() : null;
}

/**
 * Require authentication (redirect to login if not authenticated)
 */
function requireAuth()
{
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
function currentUserOwnsItem(string $trackingNumber): bool
{
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }

    // Get item from database and check user_id
    $item = getItemFromDb($trackingNumber);
    return $item && ($item['user_id'] === $user['id']);
}

/**
 * Check if the current user is an administrator
 */
function isAdmin()
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }

    $userId = $currentUser['id'] ?? null;

    // First check if user is the master admin from config
    if ($userId === ADMIN_USER_ID) {
        return true;
    }

    // Then check if user has admin flag in database
    if (isset($currentUser['is_admin']) && $currentUser['is_admin']) {
        return true;
    }

    return false;
}

/**
 * Check if the current user is the literal site super-admin (config
 * ADMIN_USER_ID), as opposed to isAdmin() which also allows any user with
 * the is_admin DB flag. Used to gate multitenant control-plane features.
 */
function isSuperAdmin()
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }

    return ($currentUser['id'] ?? null) === ADMIN_USER_ID;
}

/**
 * Check if the app is currently being accessed via the control-plane host
 * (CONTROL_PLANE_HOST config constant, e.g. claimit.cc). Used to hide
 * multitenant management features on tenant subdomains and self-hosted
 * instances. This is a minimal host check only - it does not perform any
 * tenant resolution/routing.
 */
function isControlPlaneHost()
{
    if (!defined('CONTROL_PLANE_HOST')) {
        return false;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = explode(':', $host)[0];

    return strcasecmp($host, CONTROL_PLANE_HOST) === 0;
}

/**
 * Check if the current user can edit/delete an item (either owner or admin)
 */
function canUserEditItem($itemUserId)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }

    // User can edit if they own the item OR if they are an admin
    return ($currentUser['id'] === $itemUserId) || isAdmin();
}
