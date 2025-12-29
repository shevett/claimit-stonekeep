<?php

/**
 * Item management functions
 */

/**
 * Get all items efficiently with minimal S3 API calls
 * This function batches operations to avoid N+1 query problems
 *
 * @param bool $includeGoneItems Whether to include gone items
 * @param int|array|null $communityId Filter by community ID(s) - single int, array of ints, or null for no filter
 * @return array Array of items
 */
if (!function_exists('getAllItemsEfficiently')) {
function getAllItemsEfficiently($includeGoneItems = false, $communityId = null)
{
    static $itemsCache = null;
    static $cacheTime = null;
    static $cacheKey = null;

    // Check if cache should be cleared
    if (shouldClearItemsCache()) {
        $itemsCache = null;
        $cacheTime = null;
        $cacheKey = null;
        error_log('Items cache fully cleared');
    }

    // Create cache key based on parameters
    $communityKeyPart = is_array($communityId) ? implode(',', $communityId) : ($communityId ?? 'all');
    $currentCacheKey = md5(
        ($includeGoneItems ? 'with_gone' : 'without_gone') . 
        '_comm_' . $communityKeyPart
    );

    // Use longer cache for better performance (5 minutes)
    $cacheExpiry = 300; // 5 minutes cache for items

    // Check cache first (only if cache key matches)
    if ($itemsCache !== null && $cacheTime !== null && $cacheKey === $currentCacheKey && (time() - $cacheTime) < $cacheExpiry) {
        // Use cached data
    } else {
        try {
            $startTime = microtime(true);

            // Get items from database
            $dbItems = getAllItemsFromDb($includeGoneItems, $communityId);
            debugLog("Loaded " . count($dbItems) . " items from database");

            // Get all claims from database in one query for efficiency
            $pdo = getDbConnection();
            $claimsStmt = $pdo->query("SELECT * FROM claims WHERE status = 'active' ORDER BY claimed_at ASC");
            $allClaims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Group claims by item
            $claimsByItem = [];
            foreach ($allClaims as $claim) {
                $trackingNumber = $claim['item_id'];
                if (!isset($claimsByItem[$trackingNumber])) {
                    $claimsByItem[$trackingNumber] = [];
                }
                $claimsByItem[$trackingNumber][] = $claim;
            }

            debugLog("Loaded claims for " . count($claimsByItem) . " items");

            // Process each item
            $items = [];
            $currentUser = getCurrentUser();

            foreach ($dbItems as $dbItem) {
                $trackingNumber = $dbItem['id'];

                // Get image key and URL
                $imageKey = $dbItem['image_file'];
                $imageUrl = null;
                if ($imageKey) {
                    $imageUrl = getCloudFrontUrl($imageKey);
                }

                // Pre-compute item states
                $isItemGone = (bool)$dbItem['gone'];
                $canEditItem = canUserEditItem($dbItem['user_id']);

                // Get claims for this item
                $activeClaims = $claimsByItem[$trackingNumber] ?? [];
                $primaryClaim = !empty($activeClaims) ? $activeClaims[0] : null;

                // User-specific claim data
                $isUserClaimed = false;
                $canUserClaim = false;
                if ($currentUser) {
                    foreach ($activeClaims as $claim) {
                        if ($claim['user_id'] === $currentUser['id']) {
                            $isUserClaimed = true;
                            break;
                        }
                    }
                    $canUserClaim = !$isItemGone && !$isUserClaimed;
                }

                $items[] = [
                    'id' => $trackingNumber,
                    'title' => $dbItem['title'],
                    'description' => $dbItem['description'],
                    'price' => $dbItem['price'],
                    'contact_email' => $dbItem['contact_email'],
                    'image_key' => $imageKey,
                    'image_url' => $imageUrl,
                    'image_width' => $dbItem['image_width'],
                    'image_height' => $dbItem['image_height'],
                    'posted_date' => $dbItem['submitted_at'],
                    'submitted_timestamp' => $dbItem['submitted_timestamp'],
                    'user_id' => $dbItem['user_id'],
                    'user_name' => $dbItem['user_name'],
                    'user_email' => $dbItem['user_email'],
                    'gone' => $dbItem['gone'],
                    'gone_at' => $dbItem['gone_at'],
                    'gone_by' => $dbItem['gone_by'],
                    'relisted_at' => $dbItem['relisted_at'],
                    'relisted_by' => $dbItem['relisted_by'],
                    'is_item_gone' => $isItemGone,
                    'can_edit_item' => $canEditItem,
                    'active_claims' => $activeClaims,
                    'primary_claim' => $primaryClaim,
                    'is_user_claimed' => $isUserClaimed,
                    'can_user_claim' => $canUserClaim
                ];
            }

            $endTime = microtime(true);
            $totalTime = round(($endTime - $startTime) * 1000, 2);
            debugLog("Performance: Loaded " . count($items) . " items from database in {$totalTime}ms");

            // Cache the results
            $itemsCache = $items;
            $cacheTime = time();
            $cacheKey = $currentCacheKey;
        } catch (Exception $e) {
            error_log('Error loading items efficiently: ' . $e->getMessage());
            return [];
        }
    }

    // Return all items
    return $itemsCache;
}
}

/**
 * Clear items cache when items are modified
 * This ensures fresh data after item updates
 */
if (!function_exists('clearItemsCache')) {
function clearItemsCache()
{
    // PHP static variables can't be directly cleared from outside the function
    // Instead, we'll use a global flag to force cache invalidation
    global $__itemsCacheCleared;
    $__itemsCacheCleared = true;

    error_log('Items cache invalidation flag set - next request will fetch fresh data');
}
}

/**
 * Check if items cache should be cleared
 * Called from within getAllItemsEfficiently
 */
if (!function_exists('shouldClearItemsCache')) {
function shouldClearItemsCache()
{
    global $__itemsCacheCleared;
    if (isset($__itemsCacheCleared) && $__itemsCacheCleared) {
        $__itemsCacheCleared = false; // Reset flag
        return true;
    }
    return false;
}
}

/**
 * Get all items posted by a specific user (optimized version)
 * Uses the same efficient pattern as getAllItemsEfficiently
 */
if (!function_exists('getUserItemsEfficiently')) {
function getUserItemsEfficiently($userId, $includeGoneItems = false)
{
    static $userItemsCache = [];
    static $cacheTime = [];
    static $cacheKey = null;

    // Create cache key based on parameters
    $currentCacheKey = md5($userId . '_' . ($includeGoneItems ? 'with_gone' : 'without_gone'));

    // Use longer cache for better performance (5 minutes)
    $cacheExpiry = 300; // 5 minutes cache for user items

    // Check cache first (only if cache key matches)
    if (isset($userItemsCache[$currentCacheKey]) && isset($cacheTime[$currentCacheKey]) && (time() - $cacheTime[$currentCacheKey]) < $cacheExpiry) {
        return $userItemsCache[$currentCacheKey];
    }

    try {
        $startTime = microtime(true);

        // Get user's items from database
        $dbItems = getUserItemsFromDb($userId, $includeGoneItems);
        debugLog("Loaded " . count($dbItems) . " items for user {$userId} from database");

        // Get all claims for these items
        $trackingNumbers = array_column($dbItems, 'id');
        if (!empty($trackingNumbers)) {
            $pdo = getDbConnection();
            $placeholders = implode(',', array_fill(0, count($trackingNumbers), '?'));
            $stmt = $pdo->prepare("SELECT * FROM claims WHERE item_id IN ($placeholders) AND status = 'active' ORDER BY claimed_at ASC");
            $stmt->execute($trackingNumbers);
            $allClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $allClaims = [];
        }

        // Group claims by item
        $claimsByItem = [];
        foreach ($allClaims as $claim) {
            $trackingNumber = $claim['item_id'];
            if (!isset($claimsByItem[$trackingNumber])) {
                $claimsByItem[$trackingNumber] = [];
            }
            $claimsByItem[$trackingNumber][] = $claim;
        }

        // Process each item
        $items = [];
        $currentUser = getCurrentUser();

        foreach ($dbItems as $dbItem) {
            $trackingNumber = $dbItem['id'];

            // Get image key and URL
            $imageKey = $dbItem['image_file'];
            $imageUrl = null;
            if ($imageKey) {
                $imageUrl = getCloudFrontUrl($imageKey);
            }

            // Pre-compute item states
            $isItemGone = (bool)$dbItem['gone'];
            $canEditItem = canUserEditItem($dbItem['user_id']);

            // Get claims for this item
            $activeClaims = $claimsByItem[$trackingNumber] ?? [];
            $primaryClaim = !empty($activeClaims) ? $activeClaims[0] : null;

            // User-specific claim data
            $isUserClaimed = false;
            $canUserClaim = false;
            if ($currentUser) {
                foreach ($activeClaims as $claim) {
                    if ($claim['user_id'] === $currentUser['id']) {
                        $isUserClaimed = true;
                        break;
                    }
                }
                $canUserClaim = !$isItemGone && !$isUserClaimed;
            }

            $items[] = [
                'id' => $trackingNumber,
                'title' => $dbItem['title'],
                'description' => $dbItem['description'],
                'price' => $dbItem['price'],
                'contact_email' => $dbItem['contact_email'],
                'image_key' => $imageKey,
                'image_url' => $imageUrl,
                'image_width' => $dbItem['image_width'],
                'image_height' => $dbItem['image_height'],
                'posted_date' => $dbItem['submitted_at'],
                'submitted_timestamp' => $dbItem['submitted_timestamp'],
                'user_id' => $dbItem['user_id'],
                'user_name' => $dbItem['user_name'],
                'user_email' => $dbItem['user_email'],
                'gone' => $dbItem['gone'],
                'gone_at' => $dbItem['gone_at'],
                'gone_by' => $dbItem['gone_by'],
                'relisted_at' => $dbItem['relisted_at'],
                'relisted_by' => $dbItem['relisted_by'],
                'is_item_gone' => $isItemGone,
                'can_edit_item' => $canEditItem,
                'active_claims' => $activeClaims,
                'primary_claim' => $primaryClaim,
                'is_user_claimed' => $isUserClaimed,
                'can_user_claim' => $canUserClaim
            ];
        }

        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2);
        debugLog("Performance: Loaded user items from database in {$totalTime}ms");

        // Cache the results
        $userItemsCache[$currentCacheKey] = $items;
        $cacheTime[$currentCacheKey] = time();

        return $items;
    } catch (Exception $e) {
        error_log('Error loading user items efficiently: ' . $e->getMessage());
        return [];
    }
}
}

/**
 * Get a single item by tracking number
 *
 * @param string $trackingNumber The item tracking number
 * @return array|null The item data or null if not found
 */
if (!function_exists('getItem')) {
function getItem($trackingNumber)
{
    // Simple wrapper around getItemFromDb for backward compatibility
    return getItemFromDb($trackingNumber);
}
}

/**
 * Mark an item as gone
 *
 * @param string $trackingNumber The item tracking number
 * @return bool True on success, false on failure
 */
if (!function_exists('markItemAsGone')) {
function markItemAsGone($trackingNumber)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }

    // Check if user owns this item
    if (!currentUserOwnsItem($trackingNumber)) {
        throw new Exception('You can only mark your own items as gone');
    }

    // Update item in database
    $updates = [
        'gone' => 1,
        'gone_at' => date('Y-m-d H:i:s'),
        'gone_by' => $currentUser['id']
    ];

    if (!updateItemInDb($trackingNumber, $updates)) {
        throw new Exception('Failed to update item in database');
    }

    return true;
}
}

/**
 * Re-list an item (mark as not gone)
 *
 * @param string $trackingNumber The item tracking number
 * @return bool True on success, false on failure
 */
if (!function_exists('relistItem')) {
function relistItem($trackingNumber)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }

    // Check if user owns this item
    if (!currentUserOwnsItem($trackingNumber)) {
        throw new Exception('You can only re-list your own items');
    }

    // Update item in database
    $updates = [
        'gone' => 0,
        'relisted_at' => date('Y-m-d H:i:s'),
        'relisted_by' => $currentUser['id']
    ];

    if (!updateItemInDb($trackingNumber, $updates)) {
        throw new Exception('Failed to update item in database');
    }

    return true;
}
}

/**
 * Check if an item is marked as gone
 *
 * @param array $itemData The item data array
 * @return bool True if item is gone, false otherwise
 */
if (!function_exists('isItemGone')) {
function isItemGone($itemData)
{
    // Handle both database boolean format and legacy YAML 'yes'/'no' format
    return isset($itemData['gone']) && ($itemData['gone'] === true || $itemData['gone'] === 1 || $itemData['gone'] === 'yes');
}
}

/**
 * Get all items from database with optional filtering
 *
 * @param bool $includeGone Whether to include items marked as gone
 * @param int|array|null $communityId Filter by community ID(s) - single int, array of ints, or null for no filter
 * @return array Array of items with all fields
 */
if (!function_exists('getAllItemsFromDb')) {
function getAllItemsFromDb($includeGone = false, $communityId = null)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $sql = "SELECT DISTINCT items.* FROM items";
        $params = [];
        
        // Join with items_communities if filtering by community
        if ($communityId !== null) {
            // Handle both single ID and array of IDs
            $communityIds = is_array($communityId) ? $communityId : [$communityId];
            
            // Only filter if we have community IDs
            if (!empty($communityIds)) {
                $sql .= " INNER JOIN items_communities ic ON items.id = ic.item_id";
                $placeholders = implode(',', array_fill(0, count($communityIds), '?'));
                $sql .= " WHERE ic.community_id IN ($placeholders)";
                $params = array_merge($params, $communityIds);
                
                if (!$includeGone) {
                    $sql .= " AND items.gone = 0";
                }
            } else {
                // Empty array means no communities, return empty result
                if (!$includeGone) {
                    $sql .= " WHERE items.gone = 0";
                }
            }
        } else {
            if (!$includeGone) {
                $sql .= " WHERE items.gone = 0";
            }
        }
        
        $sql .= " ORDER BY items.submitted_timestamp DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting items from database: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get items by user ID from database
 *
 * @param string $userId The user ID
 * @param bool $includeGone Whether to include items marked as gone
 * @return array Array of items
 */
if (!function_exists('getUserItemsFromDb')) {
function getUserItemsFromDb($userId, $includeGone = false)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $sql = "SELECT * FROM items WHERE user_id = ?";
        if (!$includeGone) {
            $sql .= " AND gone = 0";
        }
        $sql .= " ORDER BY submitted_timestamp DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting user items from database: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get single item by tracking number from database
 *
 * @param string $trackingNumber The item tracking number
 * @return array|null Item data or null if not found
 */
if (!function_exists('getItemFromDb')) {
function getItemFromDb($trackingNumber)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$trackingNumber]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    } catch (Exception $e) {
        error_log("Error getting item from database: " . $e->getMessage());
        return null;
    }
}
}

/**
 * Create new item in database
 *
 * @param array $itemData Item data
 * @param array|null $communityIds Array of community IDs to link item to (null = default to General)
 * @return bool Success status
 */
if (!function_exists('createItemInDb')) {
function createItemInDb($itemData, $communityIds = null)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $now = date('Y-m-d H:i:s');

        // Start transaction
        $pdo->beginTransaction();

        // Insert item
        $stmt = $pdo->prepare("
            INSERT INTO items (
                id, title, description, price, contact_email,
                image_file, image_width, image_height,
                user_id, user_name, user_email,
                submitted_at, submitted_timestamp,
                gone, gone_at, gone_by, relisted_at, relisted_by,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $success = $stmt->execute([
            $itemData['id'],
            $itemData['title'],
            $itemData['description'],
            $itemData['price'] ?? 0,
            $itemData['contact_email'],
            $itemData['image_file'] ?? null,
            $itemData['image_width'] ?? null,
            $itemData['image_height'] ?? null,
            $itemData['user_id'],
            $itemData['user_name'],
            $itemData['user_email'],
            $itemData['submitted_at'],
            $itemData['submitted_timestamp'],
            isset($itemData['gone']) ? (int)$itemData['gone'] : 0,
            $itemData['gone_at'] ?? null,
            $itemData['gone_by'] ?? null,
            $itemData['relisted_at'] ?? null,
            $itemData['relisted_by'] ?? null,
            $now,
            $now
        ]);

        if (!$success) {
            $pdo->rollBack();
            return false;
        }

        // Link to communities (default to General if not specified)
        if ($communityIds === null) {
            $communityIds = [1]; // Default to General community
        }

        // Insert community associations
        if (!empty($communityIds)) {
            $stmt = $pdo->prepare("
                INSERT INTO items_communities (item_id, community_id, created_at) 
                VALUES (?, ?, NOW())
            ");
            foreach ($communityIds as $communityId) {
                $stmt->execute([$itemData['id'], (int)$communityId]);
            }
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error creating item in database: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Update item in database
 *
 * @param string $trackingNumber The item tracking number
 * @param array $updates Fields to update
 * @return bool Success status
 */
if (!function_exists('updateItemInDb')) {
function updateItemInDb($trackingNumber, $updates)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $updates['updated_at'] = date('Y-m-d H:i:s');

        $fields = [];
        $values = [];
        foreach ($updates as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $trackingNumber;

        $sql = "UPDATE items SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute($values);
    } catch (Exception $e) {
        error_log("Error updating item in database: " . $e->getMessage());
        return false;
    }
}
}