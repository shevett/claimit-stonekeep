<?php

// Check if AWS credentials are configured
if (!hasAwsCredentials()) {
    ?>
    <div class="page-header">
        <div class="container">
            <h1>Welcome to ClaimIt</h1>
            <p class="page-subtitle">AWS S3 bucket file management</p>
        </div>
    </div>
    
    <div class="content-section">
        <div class="container">
            <div class="alert alert-error">
                <h3>AWS Credentials Not Configured</h3>
                <p>To use S3 functionality, you need to configure your AWS credentials:</p>
                <ol>
                    <li>Copy <code>config/aws-credentials.example.php</code> to <code>config/aws-credentials.php</code></li>
                    <li>Fill in your AWS Access Key ID, Secret Access Key, and S3 bucket name</li>
                    <li>Refresh this page</li>
                </ol>
                <p><strong>Note:</strong> The credentials file is automatically excluded from git for security.</p>
            </div>
        </div>
    </div>
    <?php
    return;
}

// Get list of S3 objects and parse YAML files for item listings
$items = [];
$error = null;
$awsService = null;

try {
    $awsService = getAwsService();
    if ($awsService) {
        // Get all objects in the bucket
        $result = $awsService->listObjects('', 1000);
        $objects = $result['objects'];
        
        // Find all YAML files and parse them
        foreach ($objects as $object) {
            if (substr($object['key'], -5) === '.yaml') {
                try {
                    // Extract tracking number from filename
                    $trackingNumber = basename($object['key'], '.yaml');
                    
                    // Get YAML content
                    $yamlObject = $awsService->getObject($object['key']);
                    $yamlContent = $yamlObject['content'];
                    
                    // Parse YAML content (simple parser for our specific format)
                    $data = parseSimpleYaml($yamlContent);
                    if ($data && isset($data['description']) && isset($data['price']) && isset($data['contact_email'])) {
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
                        
                        // Handle backward compatibility - use description as title if title is missing
                        $title = $data['title'];
                        $description = $data['description'];
                        
                        // Get active claims for this item
                        $activeClaims = getActiveClaims($trackingNumber);
                        $primaryClaim = getPrimaryClaim($trackingNumber);
                        
                        $items[] = [
                            'tracking_number' => $trackingNumber,
                            'title' => $title,
                            'description' => $description,
                            'price' => $data['price'],
                            'contact_email' => $data['contact_email'],
                            'image_key' => $imageKey,
                            'posted_date' => $data['submitted_at'] ?? 'Unknown',
                            'yaml_key' => $object['key'],
                            // For backward compatibility, keep old fields but populate from new system
                            'claimed_by' => $primaryClaim ? $primaryClaim['user_id'] : null,
                            'claimed_by_name' => $primaryClaim ? $primaryClaim['user_name'] : null,
                            'claimed_at' => $primaryClaim ? $primaryClaim['claimed_at'] : null,
                            'user_id' => $data['user_id'] ?? 'legacy_user',
                            'user_name' => $data['user_name'] ?? 'Legacy User',
                            'user_email' => $data['user_email'] ?? $data['contact_email'] ?? ''
                        ];
                    }
                } catch (Exception $e) {
                    // Skip invalid YAML files
                    continue;
                }
            }
        }
        
        // Sort items by tracking number (newest first)
        usort($items, function($a, $b) {
            return strcmp($b['tracking_number'], $a['tracking_number']);
        });
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>Welcome to ClaimIt</h1>
        <p class="page-subtitle">Browse available items or post something new</p>
        <div class="hero-buttons" style="margin-top: 1rem;">
            <a href="?page=claim" class="btn btn-primary">Post a New Item</a>
        </div>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage): ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                Error loading items: <?php echo escape($error); ?>
            </div>
        <?php elseif (empty($items)): ?>
            <div class="empty-state">
                <div class="empty-state-content">
                    <div class="empty-state-icon">📦</div>
                    <h3>No Items Available</h3>
                    <p>There are currently no items posted. Be the first to post something!</p>
                    <a href="?page=claim" class="btn btn-primary">Post Your First Item</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Items Grid -->
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
                                    <div class="item-listed">
                                        <strong>Listed by:</strong> 
                                        <a href="?page=user-listings&id=<?php echo escape($item['user_id']); ?>">
                                            <?php 
                                            $currentUser = getCurrentUser();
                                            if ($currentUser && $item['user_id'] === $currentUser['id']) {
                                                echo 'You! (' . escape($item['user_name']) . ')';
                                            } else {
                                                echo escape($item['user_name']);
                                            }
                                            ?>
                                        </a>
                                    </div>
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
                    </div>
                <?php endforeach; ?>
            </div>
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
</style> 