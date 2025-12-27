<?php

/**
 * Claims system functions
 */

/**
 * Add current user to item's claims list
 */
if (!function_exists('addClaimToItem')) {
function addClaimToItem($trackingNumber)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in to claim items');
    }

    // Check if user can claim this item
    if (!canUserClaim($trackingNumber, $currentUser['id'])) {
        throw new Exception('You cannot claim this item');
    }

    // Get current item data from database
    $item = getItemFromDb($trackingNumber);
    if (!$item) {
        throw new Exception('Item not found');
    }
    $data = $item; // For compatibility with email code below

    // Create new claim
    $newClaim = [
        'user_id' => $currentUser['id'],
        'user_name' => $currentUser['name'],
        'user_email' => $currentUser['email'],
        'claimed_at' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];

    // Save claim to database
    if (!createClaimInDb($trackingNumber, $newClaim)) {
        throw new Exception('Failed to save claim to database');
    }

    // Send email notification to item owner (if different from claimer)
    try {
        if ($data['user_id'] !== $currentUser['id']) {
            $emailService = getEmailService();
            if ($emailService) {
                // Get item owner information
                $itemOwner = [
                    'id' => $data['user_id'],
                    'email' => $data['user_email'] ?? '',
                    'name' => $data['user_name'] ?? 'Unknown'
                ];

                // Prepare item data for email
                $itemForEmail = [
                    'tracking_number' => $trackingNumber,
                    'title' => $data['title'] ?? 'Untitled Item',
                    'description' => $data['description'] ?? '',
                    'type' => $data['type'] ?? 'Unknown',
                    'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
                    'image_key' => $data['image_key'] ?? null
                ];

                // Send notification
                $emailService->sendItemClaimedNotification($itemOwner, $itemForEmail, $currentUser);
            }
        }
    } catch (Exception $e) {
        // Log error but don't fail the claim process
        error_log("Failed to send email notification for claim on item $trackingNumber: " . $e->getMessage());
    }

    return $newClaim;
}
}

/**
 * Remove a specific claim from an item (owner only)
 */
if (!function_exists('removeClaimFromItem')) {
function removeClaimFromItem($trackingNumber, $claimUserId)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }

    // Check if current user owns the item
    if (!currentUserOwnsItem($trackingNumber)) {
        throw new Exception('Only the item owner can remove claims');
    }

    // Find the claim in database
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $stmt = $pdo->prepare("
        UPDATE claims 
        SET status = 'removed', updated_at = ?
        WHERE item_tracking_number = ? AND user_id = ? AND status = 'active'
    ");

    $stmt->execute([date('Y-m-d H:i:s'), $trackingNumber, $claimUserId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Claim not found or already removed');
    }

    return true;
}
}

/**
 * Remove current user's own claim from an item
 */
if (!function_exists('removeMyClaim')) {
function removeMyClaim($trackingNumber)
{
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('User must be logged in');
    }

    // Remove claim from database
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $stmt = $pdo->prepare("
        UPDATE claims 
        SET status = 'removed', updated_at = ?
        WHERE item_tracking_number = ? AND user_id = ? AND status = 'active'
    ");

    $stmt->execute([date('Y-m-d H:i:s'), $trackingNumber, $currentUser['id']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('You do not have an active claim on this item');
    }

    return true;
}
}

/**
 * Get all active claims for an item in chronological order
 */
if (!function_exists('getActiveClaims')) {
function getActiveClaims($trackingNumber)
{
    // Get claims from database - already uses the helper function
    return getClaimsForItem($trackingNumber);
}
}

/**
 * Get the primary (first) active claim for an item
 */
if (!function_exists('getPrimaryClaim')) {
function getPrimaryClaim($trackingNumber)
{
    $activeClaims = getActiveClaims($trackingNumber);
    return !empty($activeClaims) ? $activeClaims[0] : null;
}
}

/**
 * Check if a user has an active claim on an item
 */
if (!function_exists('isUserClaimed')) {
function isUserClaimed($trackingNumber, $userId)
{
    $activeClaims = getActiveClaims($trackingNumber);

    foreach ($activeClaims as $claim) {
        if ($claim['user_id'] === $userId) {
            return true;
        }
    }

    return false;
}
}

/**
 * Check if a user can claim an item
 */
if (!function_exists('canUserClaim')) {
function canUserClaim($trackingNumber, $userId)
{
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
}

/**
 * Get user's position in the waitlist for an item
 */
if (!function_exists('getUserClaimPosition')) {
function getUserClaimPosition($trackingNumber, $userId)
{
    $activeClaims = getActiveClaims($trackingNumber);

    foreach ($activeClaims as $index => $claim) {
        if ($claim['user_id'] === $userId) {
            return $index + 1; // Position is 1-based
        }
    }

    return null; // User not in waitlist
}
}

/**
 * Get all items that a user has claimed or is on the waiting list for (optimized version)
 * Uses the same efficient pattern as getAllItemsEfficiently
 */
if (!function_exists('getItemsClaimedByUserOptimized')) {
function getItemsClaimedByUserOptimized($userId)
{
    static $claimedItemsCache = [];
    static $cacheTime = [];

    // Create cache key
    $currentCacheKey = md5($userId . '_claimed');

    // Use longer cache (5 minutes)
    $cacheExpiry = 300;

    // Check cache first
    if (isset($claimedItemsCache[$currentCacheKey]) && isset($cacheTime[$currentCacheKey]) && (time() - $cacheTime[$currentCacheKey]) < $cacheExpiry) {
        return $claimedItemsCache[$currentCacheKey];
    }

    try {
        $startTime = microtime(true);

        // Get claimed items from database
        $dbItems = getClaimedItemsByUser($userId);
        debugLog("Loaded " . count($dbItems) . " claimed items for user {$userId} from database");

        // Get all claims for these items  to determine position
        $trackingNumbers = array_column($dbItems, 'tracking_number');
        if (!empty($trackingNumbers)) {
            $pdo = getDbConnection();
            $placeholders = implode(',', array_fill(0, count($trackingNumbers), '?'));
            $stmt = $pdo->prepare("SELECT * FROM claims WHERE item_tracking_number IN ($placeholders) AND status = 'active' ORDER BY claimed_at ASC");
            $stmt->execute($trackingNumbers);
            $allClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $allClaims = [];
        }

        // Group claims by item
        $claimsByItem = [];
        foreach ($allClaims as $claim) {
            $trackingNumber = $claim['item_tracking_number'];
            if (!isset($claimsByItem[$trackingNumber])) {
                $claimsByItem[$trackingNumber] = [];
            }
            $claimsByItem[$trackingNumber][] = $claim;
        }

        // Process each item
        $claimedItems = [];

        foreach ($dbItems as $dbItem) {
            $trackingNumber = $dbItem['tracking_number'];

            // Get image key and URL
            $imageKey = $dbItem['image_file'];
            $imageUrl = null;
            if ($imageKey) {
                $imageUrl = getCloudFrontUrl($imageKey);
            }

            // Find user's claim and position
            $activeClaims = $claimsByItem[$trackingNumber] ?? [];
            $userClaim = null;
            $claimPosition = 0;

            foreach ($activeClaims as $index => $claim) {
                if ($claim['user_id'] === $userId) {
                    $userClaim = $claim;
                    $claimPosition = $index + 1;
                    break;
                }
            }

            $claimedItems[] = [
                'tracking_number' => $trackingNumber,
                'title' => $dbItem['title'],
                'description' => $dbItem['description'],
                'price' => $dbItem['price'],
                'contact_email' => $dbItem['contact_email'],
                'image_key' => $imageKey,
                'image_url' => $imageUrl,
                'image_width' => $dbItem['image_width'],
                'image_height' => $dbItem['image_height'],
                'posted_date' => $dbItem['submitted_at'],
                'user_id' => $dbItem['user_id'],
                'user_name' => $dbItem['user_name'],
                'user_email' => $dbItem['user_email'],
                'claim' => $userClaim,
                'claim_position' => $claimPosition,
                'is_primary_claim' => $claimPosition === 1,
                'total_claims' => count($activeClaims)
            ];
        }

        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2);
        debugLog("Performance: Loaded claimed items from database in {$totalTime}ms");

        // Cache the results
        $claimedItemsCache[$currentCacheKey] = $claimedItems;
        $cacheTime[$currentCacheKey] = time();

        return $claimedItems;
    } catch (Exception $e) {
        error_log('Error loading claimed items: ' . $e->getMessage());
        return [];
    }
}
}

/**
 * Get all items that a user has claimed or is on the waiting list for
 */
if (!function_exists('getItemsClaimedByUser')) {
function getItemsClaimedByUser($userId)
{
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
                        $possibleImageKey = 'images/' . $trackingNumber . '.' . $ext;
                        foreach ($objects as $imgObj) {
                            if ($imgObj['key'] === $possibleImageKey) {
                                $imageKey = $possibleImageKey; // Full S3 path with images/ prefix
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
        usort($claimedItems, function ($a, $b) {
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
}

/**
 * Get claims for an item from database
 *
 * @param string $trackingNumber The item tracking number
 * @return array Array of claims
 */
if (!function_exists('getClaimsForItem')) {
function getClaimsForItem($trackingNumber)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM claims 
            WHERE item_tracking_number = ? 
            ORDER BY claimed_at ASC
        ");
        $stmt->execute([$trackingNumber]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting claims from database: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get items claimed by a user from database
 *
 * @param string $userId The user ID
 * @return array Array of items with claim info
 */
if (!function_exists('getClaimedItemsByUser')) {
function getClaimedItemsByUser($userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT i.*, c.claimed_at, c.status as claim_status, c.id as claim_id
            FROM items i
            INNER JOIN claims c ON i.tracking_number = c.item_tracking_number
            WHERE c.user_id = ?
            ORDER BY c.claimed_at DESC
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting claimed items from database: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Create claim in database
 *
 * @param string $trackingNumber The item tracking number
 * @param array $claimData Claim data
 * @return bool Success status
 */
if (!function_exists('createClaimInDb')) {
function createClaimInDb($trackingNumber, $claimData)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO claims (
                item_tracking_number, user_id, user_name, user_email,
                claimed_at, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $trackingNumber,
            $claimData['user_id'],
            $claimData['user_name'],
            $claimData['user_email'],
            $claimData['claimed_at'] ?? $now,
            $claimData['status'] ?? 'active',
            $now,
            $now
        ]);
    } catch (Exception $e) {
        error_log("Error creating claim in database: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Check if user has claimed an item
 *
 * @param string $trackingNumber The item tracking number
 * @param string $userId The user ID
 * @return bool True if user has claimed this item
 */
if (!function_exists('hasUserClaimedItem')) {
function hasUserClaimedItem($trackingNumber, $userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM claims 
            WHERE item_tracking_number = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$trackingNumber, $userId]);

        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Error checking claim in database: " . $e->getMessage());
        return false;
    }
}
}