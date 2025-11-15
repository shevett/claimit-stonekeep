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

// Get items efficiently with minimal S3 API calls
$items = [];
$error = null;

try {
    // Check if user wants to see gone items (lazy auth loading)
    $currentUser = null;
    $showGoneItems = false;
    
    // Only check user settings if we have a session (avoid AWS initialization)
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
        $currentUser = getCurrentUser();
        $showGoneItems = $currentUser ? getUserShowGoneItems($currentUser['id']) : false;
    }
    
    // Skip loading items on initial page load for maximum performance
    // Items will be loaded via JavaScript after page loads
    $items = [];
    $error = null;
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>Welcome to ClaimIt</h1>
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
        <?php else: ?>
            <div class="items-grid" id="items-grid">
                <div class="loading-indicator" id="loading-indicator">
                    <div class="spinner"></div>
                    <p>Loading items<span class="loading-dots"><span></span><span></span><span></span></span></p>
                </div>
            </div>
            
        <?php endif; ?>
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


<script src="/assets/js/app.js?v=1757534999"></script>
<script>
function deleteItem(trackingNumber) {
    // Store the context for the modal
    window.deleteItemContext = {
        trackingNumber: trackingNumber,
        itemCard: event.target.closest('.item-card'),
        deleteBtn: event.target
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
    hideDeleteModal();
    
    const context = window.deleteItemContext;
    if (!context) return;
    
    const { trackingNumber, itemCard, deleteBtn } = context;
    
    // Disable the delete button and show loading state
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '⏳ Deleting...';
    deleteBtn.style.opacity = '0.6';
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'delete_item');
    formData.append('tracking_number', trackingNumber);
    
    // Send AJAX request
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Fade out and remove the item card
            itemCard.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            itemCard.style.opacity = '0';
            itemCard.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                itemCard.remove();
                
                // Check if there are no more items
                const remainingItems = document.querySelectorAll('.item-card');
                if (remainingItems.length === 0) {
                    // Reload the page to show the "no items" message
                    window.location.reload();
                }
            }, 500);
            
            // Show success message
            showMessage(data.message, 'success');
        } else {
            // Re-enable the button and show error
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = '🗑️ Delete';
            deleteBtn.style.opacity = '1';
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Re-enable the button
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = '🗑️ Delete';
        deleteBtn.style.opacity = '1';
        
        showMessage('An error occurred while deleting the item. Please try again.', 'error');
    });
}

function showMessage(message, type) {
    // Create a temporary alert message
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.maxWidth = '400px';
    alertDiv.style.opacity = '0';
    alertDiv.style.transform = 'translateX(100%)';
    alertDiv.style.transition = 'all 0.3s ease';
    alertDiv.innerHTML = message;
    
    document.body.appendChild(alertDiv);
    
    // Animate in
    setTimeout(() => {
        alertDiv.style.opacity = '1';
        alertDiv.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 4 seconds
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        alertDiv.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 300);
    }, 4000);
}
</script>

<style>

/* Delete Modal Styles */
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
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    min-width: 400px;
    max-width: 90vw;
}

.modal-header {
    padding: 1.5rem 1.5rem 0 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h3 {
    margin: 0 0 1rem 0;
    color: #dc3545;
    font-size: 1.25rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-body p {
    margin: 0 0 0.5rem 0;
    color: #495057;
}

.warning-text {
    color: #dc3545;
    font-weight: 500;
}

.modal-footer {
    padding: 0 1.5rem 1.5rem 1.5rem;
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

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