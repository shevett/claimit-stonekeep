<?php

/**
 * Community management functions
 */

/**
 * Get all communities
 * @return array Array of all communities
 */
function getAllCommunities()
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT * FROM communities ORDER BY short_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting all communities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a community by ID
 * @param int $id Community ID
 * @return array|null Community data or null if not found
 */
function getCommunityById($id)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM communities WHERE id = ?");
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

