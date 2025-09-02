<?php
// Get user ID from query parameter
$userId = $_GET['id'] ?? '';

if (empty($userId)) {
    redirect('items');
    exit;
}

$items = [];
$claimedItems = [];
$error = null;
$userName = '';
$userEmail = '';

try {
    $awsService = getAwsService();
    if (!$awsService) {
        throw new Exception('AWS service not available');
    }
    
    $result = $awsService->listObjects();
    $objects = $result['objects'] ?? [];
    
    if (!empty($objects)) {
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
                if ($data && isset($data['description']) && isset($data['price']) && isset($data['contact_email'])) {
                    // Only include items by this user
                    $itemUserId = $data['user_id'] ?? 'legacy_user';
                    if ($itemUserId !== $userId) {
                        continue;
                    }
                    
                    // Store user info from first item
                    if (empty($userName)) {
                        $userName = $data['user_name'] ?? 'Legacy User';
                        $userEmail = $data['user_email'] ?? $data['contact_email'] ?? '';
                    }
                    
                    // Check if corresponding image exists
                    $imageKey = null;
                    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    foreach ($imageExtensions as $ext) {
                        $possibleImageKey = $trackingNumber . '.' . $ext;
                        foreach ($objects as $imgObj) {
                            if ($imgObj['key'] === $possibleImageKey) {
                                $imageKey = $possibleImageKey;
                                break 2;
                            }
                        }
                    }
                    
                    $title = $data['title'] ?? $data['description'] ?? 'Untitled';
                    $description = $data['description'];
                    
                    $items[] = [
                        'tracking_number' => $trackingNumber,
                        'title' => $title,
                        'description' => $description,
                        'price' => $data['price'],
                        'contact_email' => $data['contact_email'],
                        'image_key' => $imageKey,
                        'posted_date' => $data['submitted_at'] ?? 'Unknown',
                        'yaml_key' => $object['key'],
                        'claimed_by' => $data['claimed_by'] ?? null,
                        'claimed_by_name' => $data['claimed_by_name'] ?? null,
                        'claimed_at' => $data['claimed_at'] ?? null,
                        'user_id' => $itemUserId,
                        'user_name' => $data['user_name'] ?? 'Legacy User',
                        'user_email' => $data['user_email'] ?? $data['contact_email'] ?? ''
                    ];
                }
            } catch (Exception $e) {
                // Skip invalid YAML files
                continue;
            }
        }
        
        // Sort items by tracking number (newest first)
        usort($items, function($a, $b) {
            return strcmp($b['tracking_number'], $a['tracking_number']);
        });
        
        // Get items claimed by this user
        $claimedItems = getItemsClaimedByUser($userId);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flashMessage = showFlashMessage();

// Check if current user is viewing their own listings
$currentUser = getCurrentUser();
$isOwnListings = $currentUser && $currentUser['id'] === $userId;
?>

<div class="page-header">
    <div class="container">
        <?php if ($isOwnListings): ?>
            <div class="dashboard-header">
                <div class="user-welcome">
                    <?php if (!empty($currentUser['picture'])): ?>
                        <img src="<?php echo escape($currentUser['picture']); ?>" alt="Profile" class="user-avatar">
                    <?php endif; ?>
                    <div>
                        <h1>Welcome back, <?php echo escape($currentUser['name']); ?>!</h1>
                        <p class="page-subtitle">Manage your posted items</p>
                    </div>
                </div>
                <div class="dashboard-actions">
                    <a href="?page=claim" class="btn btn-primary">Post New Item</a>
                </div>
            </div>
        <?php else: ?>
            <h1>Items by <?php echo escape($userName ?: 'User'); ?></h1>
            <p class="page-subtitle">
                <?php if (count($items) > 0): ?>
                    Showing <?php echo count($items); ?> item<?php echo count($items) !== 1 ? 's' : ''; ?>
                <?php else: ?>
                    No active listings found
                <?php endif; ?>
            </p>
            <div style="margin-top: 1rem;">
                <a href="?page=items" class="btn btn-secondary">← Back to All Items</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage): ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <?php if ($isOwnListings): ?>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo count($items); ?></h3>
                    <p>Items Posted</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_filter($items, function($item) { return $item['price'] == 0; })); ?></h3>
                    <p>Free Items</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_filter($items, function($item) { return $item['price'] > 0; })); ?></h3>
                    <p>For Sale</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count($claimedItems); ?></h3>
                    <p>Items Claimed</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                Error loading items: <?php echo escape($error); ?>
            </div>
        <?php elseif (empty($items) && empty($claimedItems)): ?>
            <div class="empty-state">
                <div class="empty-state-content">
                    <div class="empty-state-icon">📦</div>
                    <h3>No Active Listings</h3>
                    <p><?php echo escape($userName ?: 'This user'); ?> doesn't have any active items posted.</p>
                    <a href="?page=items" class="btn btn-primary">Browse All Items</a>
                </div>
            </div>
        <?php elseif (empty($items) && !empty($claimedItems) && $isOwnListings): ?>
            <!-- User has claimed items but no posted items -->
            <div class="empty-state">
                <div class="empty-state-content">
                    <div class="empty-state-icon">📦</div>
                    <h3>No Items Posted Yet</h3>
                    <p>You haven't posted any items yet, but you have claimed <?php echo count($claimedItems); ?> item<?php echo count($claimedItems) !== 1 ? 's' : ''; ?>.</p>
                    <a href="?page=claim" class="btn btn-primary">Post Your First Item</a>
                </div>
            </div>
            
            <!-- Show claimed items section even when no posted items -->
            <div class="dashboard-content" style="margin-top: 3rem;">
                <div class="section-header">
                    <h2>Items You've Claimed</h2>
                    <p class="text-muted">Items you've claimed or are on the waiting list for</p>
                </div>
            </div>
            
            <div class="items-grid">
                <?php foreach ($claimedItems as $item): ?>
                    <div class="item-card">
                        <a href="?page=item&id=<?php echo escape($item['tracking_number']); ?>" class="item-link">
                            <div class="item-image">
                                <?php if ($item['image_key']): ?>
                                    <?php
                                    try {
                                        $awsService = getAwsService();
                                        $imageUrl = $awsService->getPresignedUrl($item['image_key'], 3600);
                                    } catch (Exception $e) {
                                        $imageUrl = null;
                                    }
                                    ?>
                                    <?php if ($imageUrl): ?>
                                        <img src="<?php echo escape($imageUrl); ?>" alt="<?php echo escape($item['title']); ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="no-image-placeholder">
                                            <span>📷</span>
                                            <p>Image unavailable</p>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="no-image-placeholder">
                                        <span>📷</span>
                                        <p>No image</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="item-info">
                                <h3><?php echo escape($item['title']); ?></h3>
                                <p class="item-description"><?php echo escape($item['description']); ?></p>
                                <div class="item-meta">
                                    <span class="price"><?php echo $item['price'] == 0 ? 'Free' : '$' . number_format($item['price'], 2); ?></span>
                                    <span class="claim-status <?php echo $item['claim_position'] === 1 ? 'primary' : 'waitlist'; ?>">
                                        <?php echo $item['claim_position'] === 1 ? 'Primary Claim' : $item['claim_position'] . getOrdinalSuffix($item['claim_position']) . ' in Line'; ?>
                                    </span>
                                </div>
                                <div class="item-details">
                                    <span class="claim-date">Claimed <?php echo formatDate($item['claim']['claimed_at']); ?></span>
                                    <span class="item-waitlist"><?php echo $item['total_claims']; ?> total claim<?php echo $item['total_claims'] !== 1 ? 's' : ''; ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php if (!$isOwnListings): ?>
            <!-- User Info Section -->
            <div class="user-profile-section" style="background: var(--gray-50); padding: 2rem; border-radius: var(--radius-lg); margin-bottom: 2rem;">
                <h2 style="margin: 0 0 1rem 0; color: var(--gray-900);">About <?php echo escape($userName); ?></h2>
                <div class="user-profile-info">
                    <div class="profile-stats">
                        <div class="stat-item">
                            <strong><?php echo count($items); ?></strong>
                            <span>Active Item<?php echo count($items) !== 1 ? 's' : ''; ?></span>
                        </div>
                        <?php if ($userEmail): ?>
                        <div class="stat-item">
                            <a href="mailto:<?php echo escape($userEmail); ?>" class="btn btn-primary">
                                📧 Contact <?php echo escape(explode(' ', $userName)[0]); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isOwnListings): ?>
            <div class="dashboard-content">
                <div class="section-header">
                    <h2>Your Posted Items</h2>
                    <?php if (empty($items)): ?>
                        <p class="text-muted">You haven't posted any items yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Posted Items Grid -->
            <div class="items-grid">
                <?php foreach ($items as $item): ?>
                    <div class="item-card">
                        <a href="?page=item&id=<?php echo escape($item['tracking_number']); ?>" class="item-link">
                            <div class="item-image">
                                <?php if ($item['image_key']): ?>
                                    <?php
                                    try {
                                        $imageUrl = $awsService->getPresignedUrl($item['image_key'], 3600);
                                        echo '<img src="' . escape($imageUrl) . '" alt="' . escape($item['title']) . '" loading="lazy">';
                                    } catch (Exception $e) {
                                        echo '<div class="no-image-placeholder"><span>📷</span><p>Image Unavailable</p></div>';
                                    }
                                    ?>
                                <?php else: ?>
                                    <div class="no-image-placeholder">
                                        <span>📷</span>
                                        <p>No Image Available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-details">
                                <div class="item-header">
                                    <h4 class="item-title"><?php echo escape($item['title']); ?></h4>
                                    <div class="item-price">
                                        <?php if ($item['price'] == 0): ?>
                                            <span class="price-free">FREE</span>
                                        <?php else: ?>
                                            <span class="price-amount">$<?php echo escape(number_format($item['price'], 2)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="item-description"><?php echo escape(strlen($item['description']) > 100 ? substr($item['description'], 0, 100) . '...' : $item['description']); ?></p>
                                
                                <div class="item-meta">
                                    <div class="item-posted">
                                        <strong>Posted:</strong> <?php echo escape($item['posted_date']); ?>
                                    </div>
                                    <?php if ($item['claimed_by']): ?>
                                        <div class="item-claimed">
                                            <strong>Claimed by:</strong> 
                                            <?php 
                                            $currentUser = getCurrentUser();
                                            if ($currentUser && $item['claimed_by'] === $currentUser['id']) {
                                                echo 'You! (' . escape($item['claimed_by_name']) . ')';
                                            } else {
                                                echo escape($item['claimed_by_name']);
                                            }
                                            ?>
                                            <?php if ($item['claimed_at']): ?>
                                                <span class="claim-date">(<?php echo escape(date('M j, Y', strtotime($item['claimed_at']))); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="item-tracking">
                                        <strong>ID:</strong> #<?php echo escape($item['tracking_number']); ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                        
                        <?php if ($isOwnListings): ?>
                        <div class="item-actions">
                            <a href="mailto:<?php echo escape($item['contact_email']); ?>?subject=Interest in item #<?php echo escape($item['tracking_number']); ?>" 
                               class="btn btn-secondary">
                                📧 Contact Seller
                            </a>
                            
                            <button onclick="deleteItem('<?php echo escape($item['tracking_number']); ?>')" 
                                    class="btn btn-danger delete-btn" 
                                    title="Delete this item">
                                🗑️ Delete
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($isOwnListings && !empty($claimedItems)): ?>
            <!-- Claimed Items Section -->
            <div class="dashboard-content" style="margin-top: <?php echo empty($items) ? '0' : '3rem'; ?>;">
                <div class="section-header">
                    <h2>Items You've Claimed</h2>
                    <p class="text-muted">Items you've claimed or are on the waiting list for</p>
                </div>
            </div>
            
            <div class="items-grid">
                <?php foreach ($claimedItems as $item): ?>
                    <div class="item-card">
                        <a href="?page=item&id=<?php echo escape($item['tracking_number']); ?>" class="item-link">
                            <div class="item-image">
                                <?php if ($item['image_key']): ?>
                                    <?php
                                    try {
                                        $imageUrl = $awsService->getPresignedUrl($item['image_key'], 3600);
                                        echo '<img src="' . escape($imageUrl) . '" alt="' . escape($item['title']) . '" loading="lazy">';
                                    } catch (Exception $e) {
                                        echo '<div class="no-image-placeholder"><span>📷</span><p>Image Unavailable</p></div>';
                                    }
                                    ?>
                                <?php else: ?>
                                    <div class="no-image-placeholder">
                                        <span>📷</span>
                                        <p>No Image Available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-details">
                                <div class="item-header">
                                    <h4 class="item-title"><?php echo escape($item['title']); ?></h4>
                                    <div class="item-price">
                                        <?php if ($item['price'] == 0): ?>
                                            <span class="price-free">FREE</span>
                                        <?php else: ?>
                                            <span class="price-amount">$<?php echo escape(number_format($item['price'], 2)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="item-description"><?php echo escape(strlen($item['description']) > 100 ? substr($item['description'], 0, 100) . '...' : $item['description']); ?></p>
                                
                                <div class="item-meta">
                                    <div class="item-posted">
                                        <strong>Posted by:</strong> <?php echo escape($item['user_name']); ?>
                                    </div>
                                    <div class="item-claimed">
                                        <strong>Your Claim:</strong> 
                                        <?php if ($item['is_primary_claim']): ?>
                                            <span class="claim-status primary">🏆 Primary Claim</span>
                                        <?php else: ?>
                                            <span class="claim-status waitlist">⏳ <?php echo escape($item['claim_position'] . getOrdinalSuffix($item['claim_position'])); ?> in line</span>
                                        <?php endif; ?>
                                        <span class="claim-date">(<?php echo escape(date('M j, Y', strtotime($item['claim']['claimed_at']))); ?>)</span>
                                    </div>
                                    <?php if ($item['total_claims'] > 1): ?>
                                        <div class="item-waitlist">
                                            <strong>Waitlist:</strong> <?php echo escape($item['total_claims']); ?> total claim<?php echo $item['total_claims'] !== 1 ? 's' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="item-tracking">
                                        <strong>ID:</strong> #<?php echo escape($item['tracking_number']); ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* Items Grid Layout */
.items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.item-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #e9ecef;
}

.item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.item-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

/* User Profile Styles */
.user-profile-section {
    text-align: center;
}

.profile-stats {
    display: flex;
    gap: 2rem;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.stat-item strong {
    font-size: 1.5rem;
    color: var(--primary-600);
}

.stat-item span {
    color: var(--gray-600);
    font-size: 0.875rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-content {
    max-width: 400px;
    margin: 0 auto;
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin-bottom: 1rem;
    color: var(--gray-700);
}

.empty-state p {
    color: var(--gray-500);
    margin-bottom: 2rem;
}

/* Item Image Scaling */
.item-image {
    width: 100%;
    height: 250px;
    position: relative;
    overflow: hidden;
    background: #f8f9fa;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.item-card:hover .item-image img {
    transform: scale(1.05);
}

.no-image-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6c757d;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.no-image-placeholder span {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    opacity: 0.6;
}

/* Claim Status Styles */
.claim-status {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.claim-status.primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.claim-status.waitlist {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.claim-date {
    color: var(--gray-500);
    font-size: 0.875rem;
    margin-left: 0.5rem;
}

.item-waitlist {
    color: var(--gray-600);
    font-size: 0.875rem;
}

/* Item Actions Styles */
.item-actions {
    display: flex;
    gap: 0.75rem;
    padding: 1rem;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
    justify-content: flex-end;
}

.item-actions .btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.item-actions .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.item-actions .btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
}

.item-actions .btn-secondary:hover {
    background: #5a6268;
}

.item-actions .btn-danger {
    background: #dc3545;
    color: white;
    border: none;
}

.item-actions .btn-danger:hover {
    background: #c82333;
}

@media (max-width: 768px) {
    .profile-stats {
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .item-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .item-actions .btn {
        justify-content: center;
    }
}
</style> 