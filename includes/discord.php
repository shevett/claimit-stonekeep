<?php
/**
 * Discord Integration Functions
 */

/**
 * Send a notification to a Discord webhook
 *
 * @param string $webhookUrl The Discord webhook URL
 * @param array $item The item data (id, title, description, price, etc.)
 * @param array $community The community data (short_name, full_name)
 * @return bool True if successful, false otherwise
 */
function sendDiscordItemNotification($webhookUrl, $item, $community) {
    if (empty($webhookUrl)) {
        error_log("Discord notification skipped: No webhook URL provided");
        return false;
    }

    $baseUrl = getBaseUrl();
    $itemUrl = $baseUrl . '/?page=item&id=' . urlencode($item['id']);
    $userUrl = $baseUrl . '/?page=user-listings&id=' . urlencode($item['user_id']);
    $userName = $item['user_name'] ?? 'Unknown User';

    error_log("Preparing Discord notification for item {$item['id']}, URL: $itemUrl");

    $message = [
        'embeds' => [
            [
                'title' => $item['title'],
                'url' => $itemUrl,
                'description' => "New item posted in **{$community['short_name']}** by [{$userName}]({$userUrl})",
                'color' => 5814783, // #58b9ff blue
            ]
        ]
    ];

    error_log("Discord message payload: " . json_encode($message));
    error_log("Sending to webhook: " . substr($webhookUrl, 0, 50) . "...");

    try {
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("Discord API response - HTTP $httpCode, Response: '$response', cURL Error: '$curlError'");

        // Discord returns 204 No Content on success
        if ($httpCode === 204) {
            error_log("✅ Discord notification sent successfully for item {$item['id']} to community {$community['id']}");
            return true;
        } else {
            $errorMsg = $curlError ?: "Discord returned HTTP $httpCode with response: $response";
            error_log("❌ Discord notification failed for item {$item['id']}: $errorMsg");
            return false;
        }
    } catch (Exception $e) {
        error_log("❌ Discord notification error for item {$item['id']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Send Discord item notifications to all communities that have Discord enabled
 *
 * @param array $item The item data
 * @param array $communityIds Array of community IDs the item was posted to
 * @return int Number of successful notifications sent
 */
function sendDiscordNotificationsToCommunities($item, $communityIds) {
    error_log("sendDiscordNotificationsToCommunities called with item ID: " . ($item['id'] ?? 'unknown') . ", communities: " . json_encode($communityIds));

    if (empty($communityIds)) {
        error_log("No communities provided for Discord notification");
        return 0;
    }

    require_once __DIR__ . '/communities.php';

    $successCount = 0;

    foreach ($communityIds as $communityId) {
        error_log("Processing community $communityId for Discord notification");
        $community = getCommunityById($communityId);

        if (!$community) {
            error_log("Community not found for Discord notification: $communityId");
            continue;
        }

        error_log("Community $communityId found: " . $community['short_name'] . ", discord_enabled=" . ($community['discord_enabled'] ?? 'null') . ", webhook=" . (empty($community['discord_webhook_url']) ? 'empty' : 'present'));

        if (!empty($community['discord_enabled']) && !empty($community['discord_webhook_url'])) {
            error_log("Sending Discord notification to community {$community['short_name']} (ID: $communityId)");
            $success = sendDiscordItemNotification($community['discord_webhook_url'], $item, $community);
            if ($success) {
                $successCount++;
            }
        } else {
            error_log("Discord notification skipped for community $communityId: enabled=" . ($community['discord_enabled'] ?? '0') . ", webhook=" . (empty($community['discord_webhook_url']) ? 'empty' : 'present'));
        }
    }

    error_log("Discord notifications complete: $successCount successful out of " . count($communityIds) . " communities");
    return $successCount;
}
