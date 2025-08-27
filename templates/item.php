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
                
                $item = [
                    'tracking_number' => $itemId,
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'contact_email' => $data['contact_email'],
                    'image_key' => $imageKey,
                    'posted_date' => $data['submitted_at'] ?? 'Unknown',
                    'submitted_timestamp' => $data['submitted_timestamp'] ?? null,
                    'yaml_key' => $yamlKey
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
            <h1>Item Details</h1>
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
                         alt="<?php echo escape($item['description']); ?>" 
                         class="main-item-image">
                <?php else: ?>
                    <div class="no-image-placeholder large">
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
                            <strong>Contact:</strong>
                            <a href="mailto:<?php echo escape($item['contact_email']); ?>">
                                <?php echo escape($item['contact_email']); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="actions-section">
                    <div class="action-buttons">
                        <a href="mailto:<?php echo escape($item['contact_email']); ?>?subject=Interest in item #<?php echo escape($item['tracking_number']); ?>&body=Hi! I'm interested in your item: <?php echo escape($item['description']); ?>" 
                           class="btn btn-primary btn-large">
                            📧 Contact Seller
                        </a>
                        <button onclick="claimItem('<?php echo escape($item['tracking_number']); ?>')" 
                                class="btn btn-success btn-large claim-btn" 
                                title="Claim this item">
                            🏆 Claim this!
                        </button>
                        <button onclick="deleteItem('<?php echo escape($item['tracking_number']); ?>')" 
                                class="btn btn-danger btn-large delete-btn" 
                                title="Delete this item">
                            🗑️ Delete
                        </button>
                    </div>
                </div>
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

.main-item-image {
    width: 100%;
    height: auto;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.no-image-placeholder.large {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 4rem 2rem;
    text-align: center;
    color: #6c757d;
}

.no-image-placeholder.large span {
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
</style>

<script>
function claimItem(trackingNumber) {
    alert('Not implemented yet!\n\nThis feature will allow users to claim items in the future.\n\nTracking Number: ' + trackingNumber);
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
            // Show success message and redirect to items list
            alert('Item deleted successfully!');
            window.location.href = '?page=items';
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
</script> 