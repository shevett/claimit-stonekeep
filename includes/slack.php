<?php

/**
 * Slack and Discord webhook notifications for new items
 */

/**
 * @param string $url Webhook URL
 * @return bool True if this is a Slack incoming webhook URL
 */
function isSlackWebhookUrl(string $url): bool
{
    return str_starts_with($url, 'https://hooks.slack.com/services/');
}

/**
 * @param string $url Webhook URL
 * @return bool True if this is a Discord execute-webhook URL
 */
function isDiscordWebhookUrl(string $url): bool
{
    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || $parts['scheme'] !== 'https' || empty($parts['host'])) {
        return false;
    }
    $host = strtolower($parts['host']);
    if (!in_array($host, ['discord.com', 'www.discord.com', 'discordapp.com', 'www.discordapp.com'], true)) {
        return false;
    }
    $path = $parts['path'] ?? '';
    return str_contains($path, '/api/webhooks/');
}

/**
 * @param string $url Webhook URL
 * @return bool True if supported for community notifications
 */
function isSupportedCommunityWebhookUrl(string $url): bool
{
    return isSlackWebhookUrl($url) || isDiscordWebhookUrl($url);
}

/**
 * POST JSON to a webhook URL; returns [httpCode, responseBody, curlError]
 *
 * @return array{0:int,1:string,2:string}
 */
function postWebhookJson(string $webhookUrl, array $payload): array
{
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $response = '';
    }

    return [$httpCode, $response, $curlError];
}

/**
 * Truncate a string for Discord field limits (UTF-8 safe when mbstring available).
 */
function truncateForDiscord(string $text, int $maxLen): string
{
    if (strlen($text) <= $maxLen) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLen - 1, 'UTF-8') . '…';
    }
    return substr($text, 0, $maxLen - 3) . '...';
}

/**
 * Send a notification to a Slack incoming webhook
 *
 * @param string $webhookUrl The Slack webhook URL
 * @param array $item The item data (id, title, description, price, etc.)
 * @param array $community The community data (short_name, full_name)
 * @return bool True if successful, false otherwise
 */
function sendSlackItemNotification($webhookUrl, $item, $community)
{
    if (empty($webhookUrl)) {
        error_log("Slack notification skipped: No webhook URL provided");
        return false;
    }

    $baseUrl = getBaseUrl();
    $itemUrl = $baseUrl . '/?page=item&id=' . urlencode($item['id']);
    $userUrl = $baseUrl . '/?page=user-listings&id=' . urlencode($item['user_id']);
    $userName = $item['user_name'] ?? 'Unknown User';

    error_log("Preparing Slack notification for item {$item['id']}, URL: $itemUrl");

    // Include bare URL in top-level text so consumers that only scan `text` can unfurl
    $message = [
        'text' => "📦 New item posted in {$community['short_name']} by {$userName}: {$item['title']}\n{$itemUrl}",
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*New item posted in {$community['short_name']} by <{$userUrl}|{$userName}>*\n\n"
                        . "<{$itemUrl}|{$item['title']}>",
                ]
            ]
        ],
        'unfurl_links' => true,
        'unfurl_media' => true
    ];

    error_log("Slack message payload: " . json_encode($message));
    error_log("Sending to webhook: " . substr($webhookUrl, 0, 50) . "...");

    try {
        [$httpCode, $response, $curlError] = postWebhookJson($webhookUrl, $message);

        error_log("Slack API response - HTTP $httpCode, Response: '$response', cURL Error: '$curlError'");

        if ($httpCode === 200 && trim(strtolower($response)) === 'ok') {
            error_log(
                "✅ Slack notification sent successfully for item {$item['id']} "
                . "to community {$community['id']}"
            );
            return true;
        }
        $errorMsg = $curlError ?: "Slack returned HTTP $httpCode with response: $response";
        error_log("❌ Slack notification failed for item {$item['id']}: $errorMsg");
        return false;
    } catch (Exception $e) {
        error_log("❌ Slack notification error for item {$item['id']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Send a notification to a Discord channel webhook (Execute Webhook API)
 *
 * @param string $webhookUrl https://discord.com/api/webhooks/...
 * @param array $item Item row fields including image_file when present
 * @param array $community Community row
 * @return bool Success
 */
function sendDiscordItemNotification(string $webhookUrl, array $item, array $community): bool
{
    if ($webhookUrl === '') {
        error_log('Discord notification skipped: No webhook URL provided');
        return false;
    }

    $baseUrl = getBaseUrl();
    $itemUrl = $baseUrl . '/?page=item&id=' . urlencode($item['id']);
    $userName = $item['user_name'] ?? 'Unknown User';
    $title = $item['title'] ?? 'New item';
    $description = trim((string) ($item['description'] ?? ''));
    $price = isset($item['price']) ? (float) $item['price'] : 0.0;
    $priceLine = $price > 0 ? ('$' . $price) : 'Free';
    $embedDescription = $description !== ''
        ? truncateForDiscord($description . "\n\n" . $priceLine, 4096)
        : $priceLine;

    // Omit raw URL from content to avoid a second link preview alongside the embed
    $content = truncateForDiscord(
        "📦 **New item** in **{$community['short_name']}** by **{$userName}**",
        2000
    );

    $embed = [
        'title' => truncateForDiscord($title, 256),
        'url' => $itemUrl,
        'description' => $embedDescription,
        'color' => 3447003,
    ];

    $imageKey = $item['image_file'] ?? null;
    if (!empty($imageKey)) {
        require_once __DIR__ . '/images.php';
        $imageUrl = getCloudFrontUrl($imageKey);
        if ($imageUrl !== '') {
            $embed['image'] = ['url' => $imageUrl];
        }
    }

    $payload = [
        'username' => 'ClaimIt',
        'content' => $content,
        'embeds' => [$embed],
    ];

    error_log('Discord webhook payload (summary): item ' . ($item['id'] ?? '') . ' url ' . $itemUrl);

    try {
        [$httpCode, $response, $curlError] = postWebhookJson($webhookUrl, $payload);

        error_log("Discord API response - HTTP $httpCode, Response: '$response', cURL Error: '$curlError'");

        // Execute Webhook returns 204 No Content on success unless wait=true
        if ($httpCode === 204 || ($httpCode >= 200 && $httpCode < 300)) {
            error_log(
                "✅ Discord notification sent successfully for item {$item['id']} "
                . "to community {$community['id']}"
            );
            return true;
        }
        $errorMsg = $curlError ?: "Discord returned HTTP $httpCode with response: $response";
        error_log("❌ Discord notification failed for item {$item['id']}: $errorMsg");
        return false;
    } catch (Exception $e) {
        error_log('❌ Discord notification error for item ' . ($item['id'] ?? '') . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Send item notification using the appropriate payload for Slack or Discord
 *
 * @param string $webhookUrl Incoming webhook URL
 * @param array $item Item data
 * @param array $community Community data
 * @return bool Success
 */
function sendCommunityItemWebhookNotification(string $webhookUrl, array $item, array $community): bool
{
    if (isDiscordWebhookUrl($webhookUrl)) {
        return sendDiscordItemNotification($webhookUrl, $item, $community);
    }
    if (isSlackWebhookUrl($webhookUrl)) {
        return sendSlackItemNotification($webhookUrl, $item, $community);
    }
    error_log(
        'Unsupported webhook URL host for community notification: '
        . substr($webhookUrl, 0, 80)
    );
    return false;
}

/**
 * Send item notifications to all communities that have Slack enabled
 *
 * @param array $item The item data
 * @param array $communityIds Array of community IDs the item was posted to
 * @return int Number of successful notifications sent
 */
function sendItemNotificationsToCommunities($item, $communityIds)
{
    error_log(
        'sendItemNotificationsToCommunities called with item ID: '
        . ($item['id'] ?? 'unknown') . ', communities: ' . json_encode($communityIds)
    );

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

        $webhookState = empty($community['slack_webhook_url']) ? 'empty' : 'present';
        $slackEn = $community['slack_enabled'] ?? 'null';
        error_log(
            "Community $communityId found: {$community['short_name']}, "
            . "slack_enabled={$slackEn}, webhook={$webhookState}"
        );

        // Check if Slack notifications are enabled for this community
        if (!empty($community['slack_enabled']) && !empty($community['slack_webhook_url'])) {
            error_log("Sending webhook notification to community {$community['short_name']} (ID: $communityId)");
            $success = sendCommunityItemWebhookNotification($community['slack_webhook_url'], $item, $community);
            if ($success) {
                $successCount++;
            }
        } else {
            $en = $community['slack_enabled'] ?? '0';
            $wh = empty($community['slack_webhook_url']) ? 'empty' : 'present';
            error_log("Slack notification skipped for community $communityId: enabled={$en}, webhook={$wh}");
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
function getBaseUrl()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}
