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
 * Create a short-lived, signed handoff token carrying a verified Google
 * profile across the apex-to-tenant OAuth redirect (Google's redirect_uri
 * must stay fixed at the apex, so the tenant subdomain never talks to
 * Google directly - this token lets the apex callback hand a verified
 * identity to the tenant so it can complete login against its own database).
 * @param array $googleProfile Verified profile from AuthService::exchangeCodeForProfile()
 * @param string $tenantPrefix Tenant this login is destined for
 * @return string Signed token
 */
function createOAuthHandoffToken(array $googleProfile, string $tenantPrefix)
{
    $payload = json_encode(['profile' => $googleProfile, 'prefix' => $tenantPrefix, 'exp' => time() + 60]);
    $b64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $b64, OAUTH_HANDOFF_SECRET);
    return $b64 . '.' . $sig;
}

/**
 * Verify and decode an OAuth handoff token created by createOAuthHandoffToken().
 * Checks signature integrity, expiry (60s), and that the token's tenant
 * prefix matches the current request's resolved tenant - a token minted for
 * one tenant subdomain cannot be replayed against another, even if leaked.
 * @param string $token Token from the handoff redirect
 * @param string $expectedPrefix The current request's resolved tenant prefix
 * @return array|null The verified Google profile, or null if invalid/expired/mismatched
 */
function verifyOAuthHandoffToken($token, $expectedPrefix)
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }
    [$b64, $sig] = $parts;

    if (!hash_equals(hash_hmac('sha256', $b64, OAUTH_HANDOFF_SECRET), $sig)) {
        return null;
    }

    $decoded = base64_decode(strtr($b64, '-_', '+/'), true);
    if ($decoded === false) {
        return null;
    }

    $payload = json_decode($decoded, true);
    if (
        !is_array($payload)
        || !isset($payload['profile'], $payload['prefix'], $payload['exp'])
        || $payload['exp'] < time()
        || $payload['prefix'] !== $expectedPrefix
    ) {
        return null;
    }

    return $payload['profile'];
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
