<?php

/**
 * User management functions
 */

/**
 * Get user by ID from database
 * Returns user array or null if not found
 */
function getUserById($userId)
{
    try {
        $pdo = getDbConnection();
        if ($pdo === null) {
            return null;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        // Convert database fields to match existing format
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'picture' => $user['picture'],
            'verified_email' => (bool)$user['verified_email'],
            'locale' => $user['locale'],
            'last_login' => $user['last_login'],
            'created_at' => $user['created_at'],
            'display_name' => $user['display_name'],
            'zipcode' => $user['zipcode'],
            'show_gone_items' => (bool)$user['show_gone_items'],
            'email_notifications' => (bool)$user['email_notifications'],
            'new_listing_notifications' => (bool)$user['new_listing_notifications']
        ];
    } catch (Exception $e) {
        error_log("Error getting user by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Create a new user in database
 * Returns true on success, false on failure
 */
function createUser($userData)
{
    try {
        $pdo = getDbConnection();
        if ($pdo === null) {
            return false;
        }

        $sql = "INSERT INTO users (
            id, email, name, picture, verified_email, locale,
            last_login, created_at, display_name, zipcode,
            show_gone_items, email_notifications, new_listing_notifications,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $userData['id'],
            $userData['email'] ?? null,
            $userData['name'] ?? null,
            $userData['picture'] ?? null,
            isset($userData['verified_email']) ? (int)$userData['verified_email'] : null,
            $userData['locale'] ?? null,
            $userData['last_login'] ?? date('Y-m-d H:i:s'),
            $userData['created_at'] ?? date('Y-m-d H:i:s'),
            $userData['display_name'] ?? null,
            $userData['zipcode'] ?? null,
            isset($userData['show_gone_items']) ? (int)$userData['show_gone_items'] : 1,
            isset($userData['email_notifications']) ? (int)$userData['email_notifications'] : 1,
            isset($userData['new_listing_notifications']) ? (int)$userData['new_listing_notifications'] : 1,
            date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log("Error creating user: " . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing user in database
 * Returns true on success, false on failure
 */
function updateUser($userId, $userData)
{
    try {
        $pdo = getDbConnection();
        if ($pdo === null) {
            return false;
        }

        $sql = "UPDATE users SET 
            email = ?, name = ?, picture = ?, verified_email = ?, locale = ?,
            last_login = ?, display_name = ?, zipcode = ?,
            show_gone_items = ?, email_notifications = ?, new_listing_notifications = ?,
            updated_at = ?
        WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $userData['email'] ?? null,
            $userData['name'] ?? null,
            $userData['picture'] ?? null,
            isset($userData['verified_email']) ? (int)$userData['verified_email'] : null,
            $userData['locale'] ?? null,
            $userData['last_login'] ?? date('Y-m-d H:i:s'),
            $userData['display_name'] ?? null,
            $userData['zipcode'] ?? null,
            isset($userData['show_gone_items']) ? (int)$userData['show_gone_items'] : 1,
            isset($userData['email_notifications']) ? (int)$userData['email_notifications'] : 1,
            isset($userData['new_listing_notifications']) ? (int)$userData['new_listing_notifications'] : 1,
            date('Y-m-d H:i:s'),
            $userId
        ]);
    } catch (Exception $e) {
        error_log("Error updating user: " . $e->getMessage());
        return false;
    }
}

/**
 * Save or update user (upsert operation)
 * Returns true on success, false on failure
 */
function saveUser($userData)
{
    $existingUser = getUserById($userData['id']);

    if ($existingUser) {
        return updateUser($userData['id'], $userData);
    } else {
        return createUser($userData);
    }
}

/**
 * Get all users from database
 * @return array Array of all users
 */
function getAllUsers()
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting all users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get stats for user's items (always includes all items, even gone ones)
 * This provides accurate counts for the dashboard regardless of display preferences
 *
 * @param string $userId The user ID
 * @return array Array with item counts (total, free, for_sale, gone, with_claims)
 */
function getUserItemStats($userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [
            'total' => 0,
            'free' => 0,
            'for_sale' => 0,
            'gone' => 0,
            'with_claims' => 0
        ];
    }

    try {
        // Get aggregated stats for all user's items
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN price = 0 THEN 1 ELSE 0 END) as free,
                SUM(CASE WHEN price > 0 THEN 1 ELSE 0 END) as for_sale,
                SUM(CASE WHEN gone = 1 THEN 1 ELSE 0 END) as gone
            FROM items 
            WHERE user_id = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get count of items with active claims
        $claimsSql = "
            SELECT COUNT(DISTINCT item_id) as with_claims
            FROM claims
            WHERE item_id IN (
                SELECT id FROM items WHERE user_id = ?
            )
            AND status = 'active'
        ";

        $claimsStmt = $pdo->prepare($claimsSql);
        $claimsStmt->execute([$userId]);
        $claimsStats = $claimsStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int)$stats['total'],
            'free' => (int)$stats['free'],
            'for_sale' => (int)$stats['for_sale'],
            'gone' => (int)$stats['gone'],
            'with_claims' => (int)$claimsStats['with_claims']
        ];
    } catch (Exception $e) {
        error_log("Error getting user item stats: " . $e->getMessage());
        return [
            'total' => 0,
            'free' => 0,
            'for_sale' => 0,
            'gone' => 0,
            'with_claims' => 0
        ];
    }
}

/**
 * Get user's display name, with fallback to regular name
 *
 * @param string $userId User ID
 * @param string $defaultName Optional default if user not found
 * @return string Display name or default
 */
function getUserDisplayName($userId, $defaultName = '')
{
    $user = getUserById($userId);

    if (!$user) {
        return $defaultName;
    }

    // Prioritize display_name if set, otherwise use name
    if (!empty($user['display_name'])) {
        return $user['display_name'];
    }

    return $user['name'] ?? $defaultName;
}

/**
 * Get user's zipcode
 *
 * @param string $userId User ID
 * @return string|null Zipcode or null if not set
 */
function getUserZipcode($userId)
{
    $user = getUserById($userId);

    if (!$user) {
        return null;
    }

    return $user['zipcode'] ?? null;
}

/**
 * Get user's email notification preference from database
 *
 * @param string $userId User ID
 * @return bool Email notification preference
 */
function getUserEmailNotifications($userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return true; // Default to enabled
    }

    try {
        $stmt = $pdo->prepare("SELECT email_notifications FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        if ($result && isset($result['email_notifications'])) {
            return (bool)$result['email_notifications'];
        }

        return true; // Default to enabled if not found
    } catch (Exception $e) {
        error_log("Error getting user email notifications: " . $e->getMessage());
        return true; // Default to enabled on error
    }
}

/**
 * Get user's new listing notification preference from database
 *
 * @param string $userId User ID
 * @return bool New listing notification preference
 */
function getUserNewListingNotifications($userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false; // Default to disabled
    }

    try {
        $stmt = $pdo->prepare("SELECT new_listing_notifications FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        if ($result && isset($result['new_listing_notifications'])) {
            return (bool)$result['new_listing_notifications'];
        }

        return false; // Default to disabled if not found
    } catch (Exception $e) {
        error_log("Error getting user new listing notifications: " . $e->getMessage());
        return false; // Default to disabled on error
    }
}

/**
 * Get user's "show gone items" preference from database
 *
 * @param string $userId User ID
 * @return bool Show gone items preference
 */
function getUserShowGoneItems($userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return true; // Default to show
    }

    try {
        $stmt = $pdo->prepare("SELECT show_gone_items FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        if ($result && isset($result['show_gone_items'])) {
            return (bool)$result['show_gone_items'];
        }

        return true; // Default to show if not found
    } catch (Exception $e) {
        error_log("Error getting user show gone items preference: " . $e->getMessage());
        return true; // Default to show on error
    }
}

/**
 * Save user's "show gone items" preference to database
 *
 * @param string $userId User ID
 * @param bool $showGoneItems Whether to show gone items
 * @return bool Success status
 */
function saveUserShowGoneItems($userId, $showGoneItems)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET show_gone_items = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([(int)$showGoneItems, $userId]);
    } catch (Exception $e) {
        error_log("Error saving user show gone items preference: " . $e->getMessage());
        return false;
    }
}

/**
 * Simple in-memory cache for user settings to avoid repeated DB calls
 * Cache expires after 5 minutes to balance performance vs data freshness
 */
function getUserSettingsCache($userId, $key = null)
{
    static $cache = [];
    static $cacheTime = [];
    $cacheExpiry = 300; // 5 minutes

    $cacheKey = $userId . ($key ? '_' . $key : '');

    // Check if cache is still valid
    if (isset($cache[$cacheKey]) && isset($cacheTime[$cacheKey])) {
        if (time() - $cacheTime[$cacheKey] < $cacheExpiry) {
            return $cache[$cacheKey];
        } else {
            // Cache expired, remove it
            unset($cache[$cacheKey], $cacheTime[$cacheKey]);
        }
    }

    return null;
}

/**
 * Set user settings cache
 */
function setUserSettingsCache($userId, $value, $key = null)
{
    static $cache = [];
    static $cacheTime = [];

    $cacheKey = $userId . ($key ? '_' . $key : '');
    $cache[$cacheKey] = $value;
    $cacheTime[$cacheKey] = time();
}

