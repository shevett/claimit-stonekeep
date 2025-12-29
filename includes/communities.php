<?php

/**
 * Community management functions
 */

/**
 * Get all communities with owner information
 * @return array Array of all communities with owner display names
 */
function getAllCommunities()
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $sql = "SELECT c.*, 
                       COALESCE(u.display_name, u.name) as owner_name
                FROM communities c
                LEFT JOIN users u ON c.owner_id = u.id
                ORDER BY c.short_name ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting all communities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a community by ID with owner information
 * @param int $id Community ID
 * @return array|null Community data with owner display name or null if not found
 */
function getCommunityById($id)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $sql = "SELECT c.*, 
                       COALESCE(u.display_name, u.name) as owner_name
                FROM communities c
                LEFT JOIN users u ON c.owner_id = u.id
                WHERE c.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Error getting community by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Create a new community
 * @param array $data Community data (short_name, full_name, description, owner_id)
 * @return int|false The new community ID or false on failure
 */
function createCommunity($data)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $sql = "INSERT INTO communities (short_name, full_name, description, owner_id, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['short_name'],
            $data['full_name'],
            $data['description'] ?? null,
            $data['owner_id']
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating community: " . $e->getMessage());
        return false;
    }
}

/**
 * Update a community
 * @param int $id Community ID
 * @param array $data Community data to update
 * @return bool True on success, false on failure
 */
function updateCommunity($id, $data)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $sql = "UPDATE communities 
                SET short_name = ?, full_name = ?, description = ?, updated_at = NOW() 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['short_name'],
            $data['full_name'],
            $data['description'] ?? null,
            $id
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating community: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a community
 * @param int $id Community ID
 * @return bool True on success, false on failure
 */
function deleteCommunity($id)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM communities WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    } catch (Exception $e) {
        error_log("Error deleting community: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all community IDs that a user is subscribed to
 * @param string $userId User ID
 * @return array Array of community IDs
 */
function getUserCommunityIds($userId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT community_id FROM users_communities WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error getting user communities: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a user is a member of a community
 * @param string $userId User ID
 * @param int $communityId Community ID
 * @return bool True if user is a member, false otherwise
 */
function isUserInCommunity($userId, $communityId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_communities WHERE user_id = ? AND community_id = ?");
        $stmt->execute([$userId, $communityId]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Error checking community membership: " . $e->getMessage());
        return false;
    }
}

/**
 * Join a community
 * @param string $userId User ID
 * @param int $communityId Community ID
 * @return bool True on success, false on failure
 */
function joinCommunity($userId, $communityId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        // Check if already a member
        if (isUserInCommunity($userId, $communityId)) {
            return true; // Already a member
        }

        $sql = "INSERT INTO users_communities (user_id, community_id, created_at) VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $communityId]);
        return true;
    } catch (Exception $e) {
        error_log("Error joining community: " . $e->getMessage());
        return false;
    }
}

/**
 * Leave a community
 * @param string $userId User ID
 * @param int $communityId Community ID
 * @return bool True on success, false on failure
 */
function leaveCommunity($userId, $communityId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users_communities WHERE user_id = ? AND community_id = ?");
        $stmt->execute([$userId, $communityId]);
        return true;
    } catch (Exception $e) {
        error_log("Error leaving community: " . $e->getMessage());
        return false;
    }
}

/**
 * Get member count for a community
 * @param int $communityId Community ID
 * @return int Number of members
 */
function getCommunityMemberCount($communityId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_communities WHERE community_id = ?");
        $stmt->execute([$communityId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting community member count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get communities associated with an item
 * @param string $itemId Item ID
 * @return array Array of community IDs
 */
function getItemCommunities($itemId)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT community_id FROM items_communities WHERE item_id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error getting item communities: " . $e->getMessage());
        return [];
    }
}

/**
 * Set communities for an item
 * @param string $itemId Item ID
 * @param array $communityIds Array of community IDs (empty array = invisible/staging, no communities)
 * @return bool True on success, false on failure
 */
function setItemCommunities($itemId, $communityIds)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete existing associations
        $stmt = $pdo->prepare("DELETE FROM items_communities WHERE item_id = ?");
        $stmt->execute([$itemId]);
        
        // Add new associations
        // Empty array means item is not visible in any community (staging/invisible)
        if (!empty($communityIds)) {
            $sql = "INSERT INTO items_communities (item_id, community_id, created_at) VALUES (?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            foreach ($communityIds as $communityId) {
                $stmt->execute([$itemId, (int)$communityId]);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error setting item communities: " . $e->getMessage());
        return false;
    }
}

