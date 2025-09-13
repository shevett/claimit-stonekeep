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
                    
                    // Check if item should be filtered out based on gone status
                    $currentUser = getCurrentUser();
                    $showGoneItems = $currentUser ? getUserShowGoneItems($currentUser['id']) : false;
                    $isItemGone = isItemGone($data);
                    
                    // Skip gone items unless user wants to see them
                    if ($isItemGone && !$showGoneItems) {
                        continue;
                    }
                    
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
                        'user_email' => $data['user_email'] ?? $data['contact_email'] ?? '',
                        // Include all YAML fields
                        'gone' => $data['gone'] ?? null,
                        'gone_at' => $data['gone_at'] ?? null,
                        'gone_by' => $data['gone_by'] ?? null,
                        'relisted_at' => $data['relisted_at'] ?? null,
                        'relisted_by' => $data['relisted_by'] ?? null
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

// Check if current user is viewing their own listings or is an admin
$currentUser = getCurrentUser();
$isOwnListings = $currentUser && ($currentUser['id'] === $userId || isAdmin());
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
            
            <?php if (empty($claimedItems)): ?>
                <div class="empty-state">
                    <p>You haven't claimed any items yet.</p>
                </div>
            <?php else: ?>
                <div class="items-grid">
                    <?php foreach ($claimedItems as $item): ?>
                        <?php
                        // Set context for the unified template
                        $context = 'claimed';
                        $isOwnListings = false;
                        ?>
                        <?php include __DIR__ . '/item-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                <?php if (empty($items)): ?>
                    <div class="empty-state">
                        <p>This user doesn't have any active items posted.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        // Set context for the unified template
                        $context = 'dashboard';
                        $isOwnListings = $isOwnListings;
                        ?>
                        <?php include __DIR__ . '/item-card.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
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
                    <?php
                    // Set context for the unified template
                    $context = 'claimed';
                    $isOwnListings = false;
                    ?>
                    <?php include __DIR__ . '/item-card.php'; ?>
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

/* Item actions styling is now handled by item-card.php */

@media (max-width: 768px) {
    .profile-stats {
        flex-direction: column;
        gap: 1.5rem;
    }
}
</style>

<script src="/assets/js/app.js?v=1757534999"></script> 