<?php

/**
 * Notifications for communities that use the dedicated Discord fields
 * (`discord_webhook_url` / `discord_enabled`).
 *
 * Must not define sendDiscordItemNotification here: that lives in slack.php and is
 * shared with Slack-field URLs that point at Discord webhooks.
 */

/**
 * Send Discord item notifications to communities with discord_* enabled
 *
 * @param array $item The item data (DB row: id, title, user_id, image_file, etc.)
 * @param array $communityIds Community IDs to notify
 * @return int Number of successful notifications sent
 */
function sendDiscordNotificationsToCommunities($item, $communityIds)
{
    require_once __DIR__ . '/slack.php';

    error_log(
        'sendDiscordNotificationsToCommunities called with item ID: '
        . ($item['id'] ?? 'unknown') . ', communities: ' . json_encode($communityIds)
    );

    if (empty($communityIds)) {
        error_log('No communities provided for Discord notification');
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

        $hasWebhook = !empty($community['discord_webhook_url']);
        $discordEn = $community['discord_enabled'] ?? 'null';
        error_log(
            "Community $communityId found: {$community['short_name']}, "
            . "discord_enabled={$discordEn}, webhook=" . ($hasWebhook ? 'present' : 'empty')
        );

        if (!empty($community['discord_enabled']) && $hasWebhook) {
            error_log("Sending Discord notification to community {$community['short_name']} (ID: $communityId)");
            $success = sendDiscordItemNotification($community['discord_webhook_url'], $item, $community);
            if ($success) {
                $successCount++;
            }
        } else {
            $en = $community['discord_enabled'] ?? '0';
            $wh = $hasWebhook ? 'present' : 'empty';
            error_log("Discord notification skipped for community $communityId: enabled={$en}, webhook={$wh}");
        }
    }

    error_log(
        'Discord notifications complete: '
        . "$successCount successful out of " . count($communityIds) . ' communities'
    );
    return $successCount;
}
