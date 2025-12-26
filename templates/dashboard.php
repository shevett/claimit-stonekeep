<?php

/**
 * User Dashboard template
 */

// Require authentication
requireAuth();

$currentUser = getCurrentUser();
$flashMessage = showFlashMessage();

// Get user's items
$authService = getAuthService();
$userItems = [];
if ($authService) {
    $userItems = $authService->getUserItems($currentUser['id']);
}
?>

<div class="page-header">
    <div class="container">
        <div class="dashboard-header">
            <div class="user-welcome">
                <?php if (!empty($currentUser['picture'])) : ?>
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
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3><?php echo count($userItems); ?></h3>
                <p>Items Posted</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($userItems, function ($item) {
    return $item['price'] == 0;
                    })); ?></h3>
                <p>Free Items</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($userItems, function ($item) {
    return $item['price'] > 0;
                    })); ?></h3>
                <p>For Sale</p>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="section-header">
                <h2>Your Posted Items</h2>
                <?php if (empty($userItems)) : ?>
                    <p class="text-muted">You haven't posted any items yet.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($userItems)) : ?>
                <div class="items-grid">
                    <?php foreach ($userItems as $item) : ?>
                        <div class="item-card">
                            <a href="?page=item&id=<?php echo escape($item['tracking_number']); ?>" class="item-link">
                                <div class="item-image">
                                    <?php if ($item['image_key']) : ?>
                                        <?php
                                        $imageUrl = getCloudFrontUrl($item['image_key']);
                                        ?>
                                        <img src="<?php echo escape($imageUrl); ?>" alt="<?php echo escape($item['title']); ?>">
                                    <?php else : ?>
                                        <div class="no-image-placeholder">
                                            <span>📦</span>
                                            <p>No Image</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="item-details">
                                    <div class="item-header">
                                        <h4 class="item-title"><?php echo escape($item['title']); ?></h4>
                                        <div class="item-price">
                                            <?php if ($item['price'] == 0) : ?>
                                                <span class="price-free">FREE</span>
                                            <?php else : ?>
                                                <span class="price-amount">$<?php echo escape(number_format($item['price'], 2)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <p class="item-description">
                                        <?php
                                        $description = $item['description'];
                                        echo escape(strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description);
                                        ?>
                                    </p>
                                    
                                    <div class="item-meta">
                                        <span class="item-id">ID: <?php echo escape($item['tracking_number']); ?></span>
                                        <span class="item-date">Posted: <?php echo escape(date('M j, Y', strtotime($item['submitted_at']))); ?></span>
                                    </div>
                                </div>
                            </a>
                            
                            <div class="item-actions">
                                <a href="?page=item&id=<?php echo escape($item['tracking_number']); ?>" 
                                   class="btn btn-secondary" 
                                   title="View details">
                                    👁️ View
                                </a>
                                <button onclick="deleteItem('<?php echo escape($item['tracking_number']); ?>')" 
                                        class="btn btn-danger" 
                                        title="Delete this item">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h3>No items posted yet</h3>
                    <p>Ready to share something with the community?</p>
                    <a href="?page=claim" class="btn btn-primary btn-large">Post Your First Item</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay" style="display: none;">
    <div class="delete-modal">
        <h3>Delete Item</h3>
        <p>Are you sure you want to delete this item? This action cannot be undone.</p>
        <div class="modal-actions">
            <button onclick="hideDeleteModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="confirmDelete()" class="btn btn-danger">Delete</button>
        </div>
    </div>
</div>

<div id="messageContainer" style="display: none;"></div>



.item-image {
    width: 100%;
    height: 200px;
    overflow: hidden;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.item-link:hover .item-image img {
    transform: scale(1.02);
}

.no-image-placeholder {
    text-align: center;
    color: #6c757d;
}

.no-image-placeholder span {
    font-size: 3rem;
    display: block;
    margin-bottom: 0.5rem;
}

.item-details {
    padding: 1rem;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
    gap: 1rem;
}

.item-title {
    color: #2c3e50;
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    flex: 1;
    line-height: 1.3;
}

.item-price .price-free {
    background: #28a745;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.item-price .price-amount {
    color: #007bff;
    font-weight: 700;
    font-size: 1.1rem;
}

.item-description {
    color: #6c757d;
    margin: 0 0 1rem 0;
    line-height: 1.4;
    font-size: 0.9rem;
}

.item-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 1rem;
}

/* Item actions styling is now handled by item-card.php */

/* Button styling is now handled by item-card.php */

/* Button styling is now handled by item-card.php */

/* Button styling is now handled by item-card.php */

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 2rem;
}

.btn-large {
    padding: 1rem 2rem;
    font-size: 1.1rem;
}

/* Modal styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.delete-modal {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
    text-align: center;
}

.delete-modal h3 {
    color: #dc3545;
    margin-bottom: 1rem;
}

.delete-modal p {
    color: #6c757d;
    margin-bottom: 2rem;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .user-welcome {
        flex-direction: column;
        align-items: flex-start;
        text-align: center;
        width: 100%;
    }
    
    .dashboard-actions {
        width: 100%;
    }
    
    .dashboard-actions .btn {
        width: 100%;
    }
    
    .items-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .item-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    /* Item actions responsive styling is now handled by item-card.php */
}
</style>

<script>
let itemToDelete = null;

function deleteItem(trackingNumber) {
    itemToDelete = trackingNumber;
    showDeleteModal();
}

function showDeleteModal() {
    document.getElementById('deleteModal').style.display = 'flex';
    document.addEventListener('keydown', handleModalKeydown);
}

function hideDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.removeEventListener('keydown', handleModalKeydown);
    itemToDelete = null;
}

function handleModalKeydown(e) {
    if (e.key === 'Escape') {
        hideDeleteModal();
    }
}

function confirmDelete() {
    if (!itemToDelete) return;
    
    fetch('/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete&tracking_number=${encodeURIComponent(itemToDelete)}`
    })
    .then(response => response.json())
    .then(data => {
        hideDeleteModal();
        
        if (data.success) {
            showMessage('Item deleted successfully', 'success');
            // Reload the page to update the items list
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showMessage(data.message || 'Failed to delete item', 'error');
        }
    })
    .catch(error => {
        hideDeleteModal();
        showMessage('Error deleting item', 'error');
        console.error('Delete error:', error);
    });
}

function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    container.style.display = 'block';
    
    setTimeout(() => {
        container.style.display = 'none';
    }, 5000);
}
</script> 