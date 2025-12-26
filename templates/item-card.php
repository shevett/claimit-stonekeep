<?php

/**
 * Unified Item Card Template
 *
 * @param array $item - Item data
 * @param string $context - Display context: 'listing', 'detail', 'dashboard', 'claimed'
 * @param array|null $currentUser - Current logged-in user data
 * @param bool $isOwnListings - Whether viewing own listings
 */

// Ensure required variables are set
if (!isset($item)) {
    return;
}

$context = $context ?? 'listing';
$currentUser = $currentUser ?? null;
$isOwnListings = $isOwnListings ?? false;

// Use pre-computed claim data to avoid expensive AWS calls during template rendering
$activeClaims = $item['active_claims'] ?? [];
$primaryClaim = $item['primary_claim'] ?? null;
$isUserClaimed = $item['is_user_claimed'] ?? false;
$canUserClaim = $item['can_user_claim'] ?? false;

// Determine if this is the current user's item
$isOwnItem = ($item['user_id'] ?? null) === ($currentUser['id'] ?? null);

// Determine if the current user can edit this item (owner or admin)
// Use pre-computed values to avoid expensive function calls during template rendering
$canEditItem = $item['can_edit_item'] ?? false;
$isItemGone = $item['is_item_gone'] ?? false;

// Use pre-generated CloudFront image URL (generated during getAllItemsEfficiently)
$imageUrl = $item['image_url'] ?? null;
?>

<div class="item-card">
    <a href="?page=item&id=<?php echo escape($item['tracking_number']); ?>" class="item-link">
        <div class="item-image-container">
            <?php if ($imageUrl) : ?>
                <img src="<?php echo escape($imageUrl); ?>" 
                     alt="<?php echo escape($item['title']); ?>" 
                     class="item-image"
                     <?php if (isset($item['image_width']) && isset($item['image_height']) && $item['image_width'] && $item['image_height']) : ?>
                     width="<?php echo escape($item['image_width']); ?>" 
                     height="<?php echo escape($item['image_height']); ?>"
                     <?php endif; ?>>
            <?php else : ?>
                <div class="no-image-placeholder">
                    <span>🖼️</span>
                    <p>No Image</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="item-content">
            <h3 class="item-title"><?php echo escape($item['title']); ?></h3>
            <p class="item-description"><?php echo escape(truncateText($item['description'], 100)); ?></p>
            
            <div class="item-posted-date">
                <span class="posted-date">Posted: <?php echo escape($item['posted_date'] ?? 'Unknown'); ?></span>
            </div>

            <div class="item-meta">
                <span class="item-price"><?php echo $item['price'] > 0 ? '$' . escape(number_format($item['price'], 2)) : 'Free'; ?></span>
                                       <span class="item-posted-by">Listed by:
                           <?php
                            $displayName = getUserDisplayName($item['user_id'], $item['user_name']);
                            if ($isOwnItem) : ?>
                               You! (<?php echo escape($displayName); ?>)
                            <?php else : ?>
                                <?php echo escape($displayName); ?>
                            <?php endif; ?>
                       </span>
            </div>

            <?php if (!empty($activeClaims)) : ?>
                <div class="item-claim-info">
                                                   <?php if ($primaryClaim) : ?>
                                   <span class="claim-status primary">Primary Claim:
                                                        <?php
                                                        $claimDisplayName = getUserDisplayName($primaryClaim['user_id'], $primaryClaim['user_name']);
                                                        if (($primaryClaim['user_id'] ?? null) === ($currentUser['id'] ?? null)) : ?>
                                           You! (<?php echo escape($claimDisplayName); ?>)
                                                        <?php else : ?>
                                                            <?php echo escape($claimDisplayName); ?>
                                                        <?php endif; ?>
                                   </span>
                                                   <?php endif; ?>

                    <?php if (count($activeClaims) > 1) : ?>
                        <span class="waitlist-count">+<?php echo count($activeClaims) - 1; ?> on waitlist</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </a>

    <?php if ($context === 'listing' || $context === 'home') : ?>
        <!-- Action buttons for listing/home context -->
        <div class="item-actions">
            <?php if ($isUserClaimed) : ?>
                <button onclick="removeMyClaim('<?php echo escape($item['tracking_number']); ?>')"
                        class="btn btn-warning">
                    🚫 Remove Claim
                </button>
            <?php elseif ($canUserClaim) : ?>
                <button onclick="addClaimToItem('<?php echo escape($item['tracking_number']); ?>')"
                        class="btn btn-primary">
                    🎯 Claim
                </button>
            <?php elseif (!$currentUser && !$isOwnItem) : ?>
                <a href="?page=login" class="btn btn-primary">
                    🔐 Log in to claim this!
                </a>
            <?php endif; ?>

            <?php if (!$isOwnItem) : ?>
                <a href="mailto:<?php echo escape($item['contact_email']); ?>?subject=<?php echo rawurlencode('ClaimIt Interest - ' . $item['title']); ?>&body=<?php echo rawurlencode("Hi! I'm interested in your item: " . $item['title'] . "\n\nView the item here: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '?page=item&id=' . $item['tracking_number']); ?>"
                   class="btn btn-secondary">
                    📧 Contact
                </a>
            <?php endif; ?>

            <?php if ($canEditItem) : ?>
                <button onclick="openEditModalFromButton(this)"
                        class="btn btn-primary"
                        data-tracking="<?php echo htmlspecialchars($item['tracking_number'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-title="<?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-description="<?php echo htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    ✏️ Edit
                </button>

                <?php if ($isItemGone) : ?>
                    <button onclick="relistItem('<?php echo escape($item['tracking_number']); ?>')"
                            class="btn btn-success">
                        🔄 Re-list
                    </button>
                <?php else : ?>
                    <button onclick="markItemGone('<?php echo escape($item['tracking_number']); ?>')"
                            class="btn btn-warning">
                        ✅ Gone!
                    </button>
                <?php endif; ?>

                <button onclick="deleteItem('<?php echo escape($item['tracking_number']); ?>')"
                        class="btn btn-danger">
                    🗑️ Delete
                </button>
            <?php endif; ?>
        </div>
    <?php elseif ($context === 'dashboard' && ($isOwnListings || isAdmin())) : ?>
        <!-- Action buttons for dashboard context (own listings or admin view) -->
        <div class="item-actions">
            <a href="?page=item&id=<?php echo escape($item['tracking_number']); ?>" 
               class="btn btn-secondary">
                👁️ View
            </a>
            <?php if ($canEditItem) : ?>
                <button onclick="openEditModalFromButton(this)"
                        class="btn btn-primary"
                        data-tracking="<?php echo htmlspecialchars($item['tracking_number'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-title="<?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-description="<?php echo htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    ✏️ Edit
                </button>

                <?php if ($isItemGone) : ?>
                    <button onclick="relistItem('<?php echo escape($item['tracking_number']); ?>')"
                            class="btn btn-success">
                        🔄 Re-list
                    </button>
                <?php else : ?>
                    <button onclick="markItemGone('<?php echo escape($item['tracking_number']); ?>')"
                            class="btn btn-warning">
                        ✅ Gone!
                    </button>
                <?php endif; ?>

                <button onclick="deleteItem('<?php echo escape($item['tracking_number']); ?>')"
                        class="btn btn-danger">
                    🗑️ Delete
                </button>
            <?php endif; ?>
        </div>
    <?php elseif ($context === 'claimed') : ?>
        <!-- Display claim status for claimed items -->
        <div class="item-actions">
            <?php
            $userClaim = null;
            $claimPosition = null;
            foreach ($activeClaims as $index => $claim) {
                if (($claim['user_id'] ?? null) === ($currentUser['id'] ?? null)) {
                    $userClaim = $claim;
                    $claimPosition = $index + 1;
                    break;
                }
            }
            ?>

            <?php if ($userClaim) : ?>
                <?php if (($userClaim['status'] ?? 'active') === 'active' && $claimPosition === 1) : ?>
                    <span class="claim-status primary">Primary Claim</span>
                <?php elseif (($userClaim['status'] ?? 'active') === 'active') : ?>
                    <span class="claim-status waitlist">Waitlist #<?php echo $claimPosition; ?></span>
                <?php endif; ?>

                <button onclick="removeMyClaim('<?php echo escape($item['tracking_number']); ?>')"
                        class="btn btn-warning">
                    🚫 Remove
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

