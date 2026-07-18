<?php

/**
 * Activity log functions
 */

/**
 * Human-readable label for each activity_log.action value.
 * Shared by the admin activity log page and the item detail activity section
 * so the two views never drift.
 */
if (!function_exists('getActivityActionLabels')) {
    function getActivityActionLabels()
    {
        return [
            'item_posted' => 'Item posted',
            'item_edited' => 'Item edited',
            'item_gone' => 'Marked gone',
            'item_relisted' => 'Relisted',
            'item_deleted' => 'Item deleted',
            'item_visibility_toggled' => 'Visibility changed',
            'claim_added' => 'Claimed',
            'claim_removed' => 'Claim removed',
            'community_joined' => 'Joined community',
            'community_left' => 'Left community',
            'community_created' => 'Community created',
            'community_updated' => 'Community updated',
            'community_deleted' => 'Community deleted',
            'moderator_added' => 'Moderator added',
            'moderator_removed' => 'Moderator removed',
            'allowlist_added' => 'Added to allowlist',
            'allowlist_removed' => 'Removed from allowlist',
            'denylist_added' => 'Added to denylist',
            'denylist_removed' => 'Removed from denylist',
        ];
    }
}

/**
 * Get the client's IP address, preferring X-Forwarded-For (set by a trusted
 * upstream proxy/load balancer) over REMOTE_ADDR.
 *
 * @return string|null
 */
if (!function_exists('getClientIp')) {
    function getClientIp()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedFor = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($forwardedFor[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}

/**
 * Record an activity log entry. Never throws — a logging failure must not
 * break the mutation it's attached to.
 *
 * @param string $action One of the keys from getActivityActionLabels()
 * @param array $context Optional keys: item_id, community_id, target_user_id,
 *                        details (array, JSON-encoded), user_id (override;
 *                        defaults to the current logged-in user)
 */
if (!function_exists('logEvent')) {
    function logEvent($action, array $context = [])
    {
        $pdo = getDbConnection();
        if (!$pdo) {
            return;
        }

        try {
            $currentUser = getCurrentUser();
            $userId = $context['user_id'] ?? ($currentUser['id'] ?? null);
            $details = isset($context['details']) ? json_encode($context['details']) : null;

            $stmt = $pdo->prepare(
                "INSERT INTO activity_log (action, user_id, ip_address, item_id, community_id, target_user_id, details)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $action,
                $userId,
                getClientIp(),
                $context['item_id'] ?? null,
                $context['community_id'] ?? null,
                $context['target_user_id'] ?? null,
                $details
            ]);
        } catch (Exception $e) {
            error_log("Error logging activity event '{$action}': " . $e->getMessage());
        }
    }
}

/**
 * Fetch activity log rows, most recent first.
 *
 * @param array $filters Optional keys: item_id, community_id
 * @param int $limit
 * @return array
 */
if (!function_exists('getActivityLog')) {
    function getActivityLog(array $filters = [], $limit = 500)
    {
        $pdo = getDbConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $where = [];
            $params = [];

            if (!empty($filters['item_id'])) {
                $where[] = 'item_id = ?';
                $params[] = $filters['item_id'];
            }
            if (!empty($filters['community_id'])) {
                $where[] = 'community_id = ?';
                $params[] = $filters['community_id'];
            }

            $sql = 'SELECT * FROM activity_log';
            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY occurred_at DESC LIMIT ' . (int)$limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching activity log: " . $e->getMessage());
            return [];
        }
    }
}
