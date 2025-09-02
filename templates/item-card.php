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
$context = $context ?? 'listing';
$currentUser = $currentUser ?? null;
$isOwnListings = $isOwnListings ?? false;

// Get helper data
$activeClaims = getActiveClaims($item['tracking_number']);
$primaryClaim = getPrimaryClaim($item['tracking_number']);
$isUserClaimed = isUserClaimed($item['tracking_number'], $currentUser['id'] ?? null);
$canUserClaim = canUserClaim($item['tracking_number'], $currentUser['id'] ?? null);

// Determine if this is the current user's item
$isOwnItem = ($item['user_id'] ?? null) === ($currentUser['id'] ?? null);

// Get image URL
$imageUrl = null;
if (!empty($item['image_key'])) {
    try {
        $awsService = getAwsService();
        $imageUrl = $awsService->getPresignedUrl($item['image_key'], 3600);
    } catch (Exception $e) {
        $imageUrl = null;
    }
}
?>

<div class="item-card">
    <a href="?page=item&id=<?php echo escape($item['tracking_number']); ?>" class="item-link">
        <div class="item-image-container">
            <?php if ($imageUrl): ?>
                <img src="<?php echo escape($imageUrl); ?>" alt="<?php echo escape($item['title']); ?>" class="item-image">
            <?php else: ?>
                <div class="no-image-placeholder">
                    <span>🖼️</span>
                    <p>No Image</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="item-content">
            <h3 class="item-title"><?php echo escape($item['title']); ?></h3>
            <p class="item-description"><?php echo escape(truncateText($item['description'], 100)); ?></p>

            <div class="item-meta">
                <span class="item-price"><?php echo $item['price'] > 0 ? '$' . escape(number_format($item['price'], 2)) : 'Free'; ?></span>
                <span class="item-posted-by">Listed by:
                    <?php if ($isOwnItem): ?>
                        You! (<?php echo escape($item['user_name']); ?>)
                    <?php else: ?>
                        <?php echo escape($item['user_name']); ?>
                    <?php endif; ?>
                </span>
            </div>

            <?php if (!empty($activeClaims)): ?>
                <div class="item-claim-info">
                    <?php if ($primaryClaim): ?>
                        <span class="claim-status primary">Primary Claim:
                            <?php if (($primaryClaim['user_id'] ?? null) === ($currentUser['id'] ?? null)): ?>
                                You! (<?php echo escape($primaryClaim['user_name']); ?>)
                            <?php else: ?>
                                <?php echo escape($primaryClaim['user_name']); ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>

                    <?php if (count($activeClaims) > 1): ?>
                        <span class="waitlist-count">+<?php echo count($activeClaims) - 1; ?> on waitlist</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </a>

    <?php if ($context === 'listing' || $context === 'home'): ?>
        <!-- Action buttons for listing/home context -->
        <div class="item-actions">
            <?php if ($isUserClaimed): ?>
                <button onclick="removeMyClaim('<?php echo escape($item['tracking_number']); ?>')"
                        class="btn btn-danger">
                    🚫 Remove My Claim
                </button>
            <?php elseif ($canUserClaim): ?>
                <button onclick="addClaimToItem('<?php echo escape($item['tracking_number']); ?>')"
                        class="btn btn-primary">
                    🎯 Claim This!
                </button>
            <?php endif; ?>

            <?php if (!$isOwnItem): ?>
                <a href="mailto:<?php echo escape($item['contact_email']); ?>?subject=Interest in item #<?php echo escape($item['tracking_number']); ?>"
                   class="btn btn-secondary">
                    📧 Contact Seller
                </a>
            <?php endif; ?>
        </div>
    <?php elseif ($context === 'dashboard' && $isOwnListings): ?>
        <!-- Action buttons for dashboard context (own listings) -->
        <div class="item-actions">
            <a href="mailto:<?php echo escape($item['contact_email']); ?>?subject=Interest in item #<?php echo escape($item['tracking_number']); ?>"
               class="btn btn-secondary">
                📧 Contact Seller
            </a>

            <button onclick="deleteItem('<?php echo escape($item['tracking_number']); ?>')"
                    class="btn btn-danger">
                🗑️ Delete
            </button>
        </div>
    <?php elseif ($context === 'claimed'): ?>
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

            <?php if ($userClaim): ?>
                <?php if (($userClaim['status'] ?? 'active') === 'active' && $claimPosition === 1): ?>
                    <span class="claim-status primary">Primary Claim</span>
                <?php elseif (($userClaim['status'] ?? 'active') === 'active'): ?>
                    <span class="claim-status waitlist">Waitlist #<?php echo $claimPosition; ?></span>
                <?php endif; ?>

                <div class="item-details">
                    <span class="claim-date">Claimed <?php echo formatDate($userClaim['claimed_at'] ?? ''); ?></span>
                    <span class="item-waitlist"><?php echo count($activeClaims); ?> total claim<?php echo count($activeClaims) !== 1 ? 's' : ''; ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
