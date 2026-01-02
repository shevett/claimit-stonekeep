<?php
/**
 * Slack Integration Functions
 */

/**
 * Send a notification to a Slack webhook
 * 
 * @param string $webhookUrl The Slack webhook URL
 * @param array $item The item data (id, title, description, price, etc.)
 * @param array $community The community data (short_name, full_name)
 * @return bool True if successful, false otherwise
 */
function sendSlackItemNotification($webhookUrl, $item, $community) {
    if (empty($webhookUrl)) {
        error_log("Slack notification skipped: No webhook URL provided");
        return false;
    }

    // Build the item URL
    $baseUrl = getBaseUrl();
    $itemUrl = $baseUrl . '/?page=item&id=' . urlencode($item['id']);

    error_log("Preparing Slack notification for item {$item['id']}, URL: $itemUrl");

    // Create a simple message with the URL - Slack will unfurl it automatically
    $message = [
        'text' => "📦 New item posted in {$community['short_name']}: {$item['title']}",
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*New item posted in {$community['short_name']}*\n\n<{$itemUrl}|{$item['title']}>"
                ]
            ]
        ],
        // Enable link unfurling
        'unfurl_links' => true,
        'unfurl_media' => true
    ];

    error_log("Slack message payload: " . json_encode($message));
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

        error_log("Slack API response - HTTP $httpCode, Response: '$response', cURL Error: '$curlError'");

        if ($httpCode === 200 && trim(strtolower($response)) === 'ok') {
            error_log("✅ Slack notification sent successfully for item {$item['id']} to community {$community['id']}");
            return true;
        } else {
            $errorMsg = $curlError ?: "Slack returned HTTP $httpCode with response: $response";
            error_log("❌ Slack notification failed for item {$item['id']}: $errorMsg");
            return false;
        }
    } catch (Exception $e) {
        error_log("❌ Slack notification error for item {$item['id']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Send item notifications to all communities that have Slack enabled
 * 
 * @param array $item The item data
 * @param array $communityIds Array of community IDs the item was posted to
 * @return int Number of successful notifications sent
 */
function sendItemNotificationsToCommunities($item, $communityIds) {
    error_log("sendItemNotificationsToCommunities called with item ID: " . ($item['id'] ?? 'unknown') . ", communities: " . json_encode($communityIds));
    
    if (empty($communityIds)) {
        error_log("No communities provided for Slack notification");
        return 0;
    }

    require_once __DIR__ . '/communities.php';
    
    $successCount = 0;

    foreach ($communityIds as $communityId) {
        error_log("Processing community $communityId for Slack notification");
        $community = getCommunityById($communityId);
        
        if (!$community) {
            error_log("Community not found for Slack notification: $communityId");
            continue;
        }

        error_log("Community $communityId found: " . $community['short_name'] . ", slack_enabled=" . ($community['slack_enabled'] ?? 'null') . ", webhook=" . (empty($community['slack_webhook_url']) ? 'empty' : 'present'));

        // Check if Slack notifications are enabled for this community
        if (!empty($community['slack_enabled']) && !empty($community['slack_webhook_url'])) {
            error_log("Sending Slack notification to community {$community['short_name']} (ID: $communityId)");
            $success = sendSlackItemNotification($community['slack_webhook_url'], $item, $community);
            if ($success) {
                $successCount++;
            }
        } else {
            error_log("Slack notification skipped for community $communityId: enabled=" . ($community['slack_enabled'] ?? '0') . ", webhook=" . (empty($community['slack_webhook_url']) ? 'empty' : 'present'));
        }
    }

    error_log("Slack notifications complete: $successCount successful out of " . count($communityIds) . " communities");
    return $successCount;
}

/**
 * Get the base URL of the application
 * 
 * @return string The base URL
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

