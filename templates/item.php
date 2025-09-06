<?php
/**
 * Individual Item View Template
 */

// Get the item ID from URL parameter
$itemId = $_GET['id'] ?? null;

if (!$itemId || !preg_match('/^\d{14}$/', $itemId)) {
    setFlashMessage('Invalid or missing item ID.', 'error');
    redirect('items');
}

// Get item data from S3
$item = null;
$error = null;
$awsService = null;

try {
    $awsService = getAwsService();
    if ($awsService) {
        // Try to get the YAML file for this item
        $yamlKey = $itemId . '.yaml';
        
        try {
            $yamlObject = $awsService->getObject($yamlKey);
            $yamlContent = $yamlObject['content'];
            
            // Parse YAML content
            $data = parseSimpleYaml($yamlContent);
            if ($data && isset($data['description']) && isset($data['price']) && isset($data['contact_email'])) {
                // Check if corresponding image exists
                $imageKey = null;
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                foreach ($imageExtensions as $ext) {
                    $possibleImageKey = $itemId . '.' . $ext;
                    try {
                        if ($awsService->objectExists($possibleImageKey)) {
                            $imageKey = $possibleImageKey;
                            break;
                        }
                    } catch (Exception $e) {
                        // Continue to next extension
                    }
                }
                
                // Handle backward compatibility - use description as title if title is missing
                $title = $data['title'];
                $description = $data['description'];
                
                $item = [
                    'tracking_number' => $itemId,
                    'title' => $title,
                    'description' => $description,
                    'price' => $data['price'],
                    'contact_email' => $data['contact_email'],
                    'image_key' => $imageKey,
                    'posted_date' => $data['submitted_at'] ?? 'Unknown',
                    'submitted_timestamp' => $data['submitted_timestamp'] ?? null,
                    'yaml_key' => $yamlKey,
                    // For backward compatibility, keep old fields but populate from new system
                    'claimed_by' => null,
                    'claimed_by_name' => null,
                    'claimed_at' => null,
                    'user_id' => $data['user_id'] ?? 'legacy_user',
                    'user_name' => $data['user_name'] ?? 'Legacy User',
                    'user_email' => $data['user_email'] ?? $data['contact_email'] ?? ''
                ];
            }
        } catch (Exception $e) {
            // Item not found
            $error = 'Item not found.';
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

if (!$item && !$error) {
    $error = 'Item not found or invalid data.';
}

if ($error) {
    setFlashMessage($error, 'error');
    redirect('items');
}

$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <div class="header-with-back">
            <a href="?page=items" class="back-link">← Back to All Items</a>
            <h1><?php echo escape($item['title']); ?></h1>
            <p class="page-subtitle">Item #<?php echo escape($item['tracking_number']); ?></p>
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

        <div class="item-detail-view">
            <div class="item-detail-image">
                <?php if ($item['image_key']): ?>
                    <?php 
                        $imageUrl = $awsService->getPresignedUrl($item['image_key'], 3600);
                    ?>
                    <img src="<?php echo escape($imageUrl); ?>" 
                         alt="<?php echo escape($item['title']); ?>" 
                         class="detail-image">
                <?php else: ?>
                    <div class="no-image-placeholder">
                        <span>📷</span>
                        <p>No Image Available</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="item-detail-info">
                <div class="price-section">
                    <h2 class="item-price-large">
                        <?php if ($item['price'] == 0): ?>
                            <span class="free-badge">FREE</span>
                        <?php else: ?>
                            $<?php echo escape(number_format($item['price'], 2)); ?>
                        <?php endif; ?>
                    </h2>
                </div>
                
                <div class="description-section">
                    <h3>Description</h3>
                    <p class="item-description-large"><?php echo nl2br(escape($item['description'])); ?></p>
                </div>
                
                <div class="details-section">
                    <h3>Details</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <strong>Item ID:</strong>
                            <span>#<?php echo escape($item['tracking_number']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Posted:</strong>
                            <span><?php echo escape($item['posted_date']); ?></span>
                        </div>
                        <div class="detail-item">
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
                        <?php 
                        // Get active claims for this item
                        $activeClaims = getActiveClaims($item['tracking_number']);
                        $primaryClaim = getPrimaryClaim($item['tracking_number']);
                        ?>
                        
                        <?php if ($primaryClaim): ?>
                            <div class="detail-item">
                                <strong>Primary Claim:</strong>
                                <span>
                                    <?php 
                                    $currentUser = getCurrentUser();
                                    if ($currentUser && $primaryClaim['user_id'] === $currentUser['id']) {
                                        echo 'You! (' . escape($primaryClaim['user_name']) . ')';
                                    } else {
                                        echo escape($primaryClaim['user_name']);
                                    }
                                    ?>
                                </span>
                                <span class="claim-date">(<?php echo escape(date('M j, Y', strtotime($primaryClaim['claimed_at']))); ?>)</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (count($activeClaims) > 1): ?>
                            <div class="detail-item">
                                <strong>Waitlist:</strong>
                                <span><?php echo count($activeClaims) - 1; ?> person<?php echo (count($activeClaims) - 1) !== 1 ? 's' : ''; ?> waiting</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="actions-section">
                    <div class="action-buttons">
                        <a href="mailto:<?php echo escape($item['contact_email']); ?>?subject=<?php echo rawurlencode('ClaimIt Interest - ' . $item['title']); ?>&body=<?php echo rawurlencode("Hi! I'm interested in your item: " . $item['title'] . "\n\nView the item here: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                           class="btn btn-primary btn-large">
                            📧 Contact Seller
                        </a>
                        
                        <?php 
                        $currentUser = getCurrentUser();
                        $isOwnItem = currentUserOwnsItem($item['tracking_number']);
                        $canEditItem = canUserEditItem($item['user_id'] ?? null);
                        $isUserClaimed = $currentUser ? isUserClaimed($item['tracking_number'], $currentUser['id']) : false;
                        $canUserClaim = $currentUser ? canUserClaim($item['tracking_number'], $currentUser['id']) : false;
                        $userClaimPosition = $currentUser ? getUserClaimPosition($item['tracking_number'], $currentUser['id']) : null;
                        
                        // Debug output
                        
                        ?>
                        
                        <?php if (!$isOwnItem && $currentUser): ?>
                            <?php if ($isUserClaimed): ?>
                                <button onclick="removeMyClaim('<?php echo escape($item['tracking_number']); ?>')" 
                                        class="btn btn-warning btn-large claim-btn" 
                                        title="Remove yourself from the waitlist">
                                    🚫 Remove me from list (<?php echo $userClaimPosition . getOrdinalSuffix($userClaimPosition); ?> in line)
                                </button>
                            <?php elseif ($canUserClaim): ?>
                                <button onclick="addClaimToItem('<?php echo escape($item['tracking_number']); ?>')" 
                                        class="btn btn-primary btn-large claim-btn" 
                                        title="Claim this item">
                                    🏆 Claim this!
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-large" disabled title="You cannot claim this item">
                                    ❌ Cannot claim
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($canEditItem): ?>
                            <button onclick="openEditModal('<?php echo escape($item['tracking_number']); ?>', '<?php echo addslashes(htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8')); ?>', '<?php echo addslashes(htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8')); ?>')" 
                                    class="btn btn-primary btn-large edit-btn" 
                                    title="Edit this item">
                                ✏️ Edit...
                            </button>
                            
                            <button onclick="deleteItem('<?php echo escape($item['tracking_number']); ?>')" 
                                    class="btn btn-danger btn-large delete-btn" 
                                    title="Delete this item">
                                🗑️ Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($activeClaims)): ?>
                <div class="waitlist-section">
                    <h3>Waitlist</h3>
                    <div class="waitlist-container">
                        <?php foreach ($activeClaims as $index => $claim): ?>
                            <div class="waitlist-item <?php echo $index === 0 ? 'primary-claim' : ''; ?>">
                                <div class="waitlist-position">
                                    <?php if ($index === 0): ?>
                                        <span class="position-badge primary">1st</span>
                                    <?php else: ?>
                                        <span class="position-badge"><?php echo ($index + 1) . getOrdinalSuffix($index + 1); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="waitlist-info">
                                    <div class="claimant-name">
                                        <?php 
                                        $currentUser = getCurrentUser();
                                        if ($currentUser && $claim['user_id'] === $currentUser['id']) {
                                            echo 'You! (' . escape($claim['user_name']) . ')';
                                        } else {
                                            echo escape($claim['user_name']);
                                        }
                                        ?>
                                    </div>
                                    <div class="claim-date">Claimed <?php echo escape(date('M j, Y g:i A', strtotime($claim['claimed_at']))); ?></div>
                                </div>
                                <?php if ($isOwnItem): ?>
                                    <div class="waitlist-actions">
                                        <button onclick="removeClaimByOwner('<?php echo escape($item['tracking_number']); ?>', '<?php echo escape($claim['user_id']); ?>')" 
                                                class="btn btn-sm btn-danger" 
                                                title="Remove <?php 
                                                $currentUser = getCurrentUser();
                                                if ($currentUser && $claim['user_id'] === $currentUser['id']) {
                                                    echo 'You! (' . escape($claim['user_name']) . ')';
                                                } else {
                                                    echo escape($claim['user_name']);
                                                }
                                                ?> from waitlist">
                                            🗑️
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="modalOverlay" class="modal-overlay" onclick="hideDeleteModal()"></div>
<div id="deleteModal" class="delete-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🗑️ Delete Item</h3>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this item?</p>
            <p class="warning-text">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete Item</button>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Item</h2>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editForm">
                <input type="hidden" id="editTrackingNumber" name="trackingNumber">
                <div class="form-group">
                    <label for="editTitle">Title:</label>
                    <input type="text" id="editTitle" name="title" required>
                    <small>Enter a descriptive title for your item</small>
                </div>
                <div class="form-group">
                    <label for="editDescription">Description:</label>
                    <textarea id="editDescription" name="description" rows="4" required></textarea>
                    <small>Provide details about the item's condition, features, etc.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.header-with-back {
    position: relative;
}

.back-link {
    color: #666;
    text-decoration: none;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    display: inline-block;
    transition: color 0.3s ease;
}

.back-link:hover {
    color: #333;
}

.item-detail-view {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    max-width: 1200px;
    margin: 0 auto;
}

.item-detail-image {
    position: sticky;
    top: 2rem;
    height: fit-content;
}

.detail-image {
    width: 100%;
    max-width: 500px;
    max-height: 70vh;
    height: auto;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    object-fit: contain;
    display: block;
}

.no-image-placeholder {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 4rem 2rem;
    text-align: center;
    color: #6c757d;
    max-width: 500px;
}

.no-image-placeholder span {
    font-size: 4rem;
    display: block;
    margin-bottom: 1rem;
}

.item-detail-info {
    padding: 1rem 0;
}

.price-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #eee;
}

.item-price-large {
    font-size: 2.5rem;
    font-weight: bold;
    color: #28a745;
    margin: 0;
}

.free-badge {
    background: #17a2b8;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-size: 1.5rem;
    font-weight: bold;
}

.description-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #eee;
}

.description-section h3,
.details-section h3 {
    color: #333;
    margin-bottom: 1rem;
    font-size: 1.25rem;
}

.item-description-large {
    font-size: 1.1rem;
    line-height: 1.6;
    color: #555;
    margin: 0;
}

.details-section {
    margin-bottom: 2rem;
}

.detail-grid {
    display: grid;
    gap: 1rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.detail-item strong {
    color: #333;
    min-width: 100px;
}

.detail-item a {
    color: #007bff;
    text-decoration: none;
}

.detail-item a:hover {
    text-decoration: underline;
}

.actions-section {
    margin-top: 3rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-large {
    padding: 0.75rem 1.5rem;
    font-size: 1.1rem;
    min-width: 150px;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .item-detail-view {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .item-detail-image {
        position: static;
    }
    
    .item-price-large {
        font-size: 2rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-large {
        width: 100%;
    }
}

/* Include existing modal and button styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9998;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
}

.delete-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.7);
    z-index: 9999;
    display: none;
    opacity: 0;
    transition: all 0.3s ease;
}

.delete-modal.show {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
}

.modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    max-width: 400px;
    width: 90vw;
}

.modal-header {
    padding: 1.5rem 1.5rem 0;
    border-bottom: 1px solid #eee;
}

.modal-header h3 {
    margin: 0 0 1rem 0;
    color: #dc3545;
}

.modal-body {
    padding: 1.5rem;
}

.modal-body p {
    margin: 0 0 0.5rem 0;
    color: #555;
}

.warning-text {
    color: #dc3545;
    font-weight: 500;
}

.modal-footer {
    padding: 0 1.5rem 1.5rem;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.claim-btn {
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    color: white !important;
    transition: all 0.3s ease !important;
}

.claim-btn:hover {
    background-color: #218838 !important;
    border-color: #1e7e34 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
}

.delete-btn {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
    color: white !important;
    transition: all 0.3s ease !important;
}

.delete-btn:hover {
    background-color: #c82333 !important;
    border-color: #bd2130 !important;
    transform: translateY(-1px);
}

.delete-btn:disabled {
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    cursor: not-allowed !important;
    transform: none !important;
}

/* Waitlist Styles */
.waitlist-section {
    margin-top: 2rem;
    padding: 1.5rem;
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
}

.waitlist-section h3 {
    margin-bottom: 1rem;
    color: var(--gray-900);
}

.waitlist-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.waitlist-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-200);
    transition: all 0.2s ease;
}

.waitlist-item.primary-claim {
    border-color: var(--primary-500);
    background: var(--primary-50);
}

.waitlist-position {
    flex-shrink: 0;
}

.position-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: var(--gray-200);
    color: var(--gray-700);
    font-weight: 600;
    font-size: 0.875rem;
}

.position-badge.primary {
    background: var(--primary-500);
    color: white;
}

.waitlist-info {
    flex: 1;
}

.claimant-name {
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 0.25rem;
}

.claim-date {
    font-size: 0.875rem;
    color: var(--gray-600);
}

.waitlist-actions {
    flex-shrink: 0;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    border-radius: var(--radius-sm);
}
</style>

<script>
function addClaimToItem(trackingNumber) {
    const button = document.querySelector(`button[onclick="addClaimToItem('${trackingNumber}')"]`);
    
    // Show loading state
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '⏳ Adding to list...';
    
    // Send AJAX request
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_claim&tracking_number=${encodeURIComponent(trackingNumber)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showMessage(data.message, 'success');
            
            // Reload the page to update the display
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Show error message
            showMessage(data.message, 'error');
            
            // Restore button
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
        
        // Restore button
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

function removeMyClaim(trackingNumber) {
    const button = document.querySelector(`button[onclick="removeMyClaim('${trackingNumber}')"]`);
    
    // Show loading state
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '⏳ Removing...';
    
    // Send AJAX request
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=remove_claim&tracking_number=${encodeURIComponent(trackingNumber)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showMessage(data.message, 'success');
            
            // Reload the page to update the display
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Show error message
            showMessage(data.message, 'error');
            
            // Restore button
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
        
        // Restore button
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

function removeClaimByOwner(trackingNumber, claimUserId) {
    if (!confirm('Are you sure you want to remove this person from the waitlist?')) {
        return;
    }
    
    // Send AJAX request
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=remove_claim_by_owner&tracking_number=${encodeURIComponent(trackingNumber)}&claim_user_id=${encodeURIComponent(claimUserId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showMessage(data.message, 'success');
            
            // Reload the page to update the display
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Show error message
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
    });
}

function showMessage(message, type) {
    // Create a temporary alert div
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.maxWidth = '400px';
    alertDiv.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
    
    document.body.appendChild(alertDiv);
    
    // Remove after 3 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 3000);
}

function deleteItem(trackingNumber) {
    // Store the context for the modal
    window.deleteItemContext = {
        trackingNumber: trackingNumber,
        // For individual item page, we'll redirect after deletion
        isIndividualPage: true
    };
    
    // Show the confirmation modal
    showDeleteModal();
}

function showDeleteModal() {
    const modal = document.getElementById('deleteModal');
    const overlay = document.getElementById('modalOverlay');
    
    modal.style.display = 'block';
    overlay.style.display = 'block';
    
    // Add keyboard support
    document.addEventListener('keydown', handleModalKeydown);
    
    // Animate in
    setTimeout(() => {
        overlay.classList.add('show');
        modal.classList.add('show');
    }, 10);
}

function hideDeleteModal() {
    const modal = document.getElementById('deleteModal');
    const overlay = document.getElementById('modalOverlay');
    
    modal.classList.remove('show');
    overlay.classList.remove('show');
    
    // Remove keyboard support
    document.removeEventListener('keydown', handleModalKeydown);
    
    setTimeout(() => {
        modal.style.display = 'none';
        overlay.style.display = 'none';
    }, 300);
}

function handleModalKeydown(event) {
    if (event.key === 'Escape') {
        hideDeleteModal();
    }
}

function confirmDelete() {
    if (!window.deleteItemContext) {
        return;
    }
    
    const { trackingNumber } = window.deleteItemContext;
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('tracking_number', trackingNumber);
    
    // Send AJAX request
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideDeleteModal();
        
        if (data.success) {
            // Show success message and redirect to home page
            alert('Item deleted successfully!');
            window.location.href = '?page=home';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        hideDeleteModal();
        alert('An error occurred while deleting the item. Please try again.');
    });
}

function showMessage(message, type) {
    // Create a temporary alert at the top of the page
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '10000';
    alertDiv.style.maxWidth = '300px';
    
    document.body.appendChild(alertDiv);
    
    // Remove after 3 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 3000);
}

// Edit Modal Functions
function openEditModal(trackingNumber, title, description) {
    console.log('openEditModal called with:', { trackingNumber, title, description });
    const modal = document.getElementById('editModal');
    if (modal) {
        // Populate the form fields
        document.getElementById('editTrackingNumber').value = trackingNumber;
        document.getElementById('editTitle').value = title;
        document.getElementById('editDescription').value = description;

        // Show the modal
        modal.style.display = 'block';
        console.log('Modal should now be visible');
    } else {
        console.error('Modal element not found!');
    }
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Handle edit form submission
document.addEventListener('DOMContentLoaded', function() {
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveItemEdit();
        });
    }
});

function saveItemEdit() {
    const form = document.getElementById('editForm');
    const formData = new FormData(form);
    const trackingNumber = formData.get('trackingNumber');
    const title = formData.get('title');
    const description = formData.get('description');

    if (!title.trim() || !description.trim()) {
        showMessage('Title and description are required', 'error');
        return;
    }

    fetch('?page=claim&action=edit_item', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `tracking_number=${encodeURIComponent(trackingNumber)}&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            closeEditModal();
            // Reload the page to show the updated content
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error saving item edit:', error);
        showMessage('An error occurred while saving changes', 'error');
    });
}
</script> 