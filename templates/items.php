<?php

// Check if AWS credentials are configured
if (!hasAwsCredentials()) {
    ?>
    <div class="page-header compact">
        <div class="container">
            <h1>S3 Assets</h1>
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

// Handle file download request
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['key'])) {
    $key = $_GET['key'];
    
    if (!isValidS3Key($key)) {
        setFlashMessage('Invalid file key provided.', 'error');
        redirect('items');
    }
    
    try {
        $awsService = getAwsService();
        if (!$awsService) {
            throw new Exception('AWS service not available');
        }
        
        $object = $awsService->getObject($key);
        
        // Set headers for file download
        header('Content-Type: ' . $object['content_type']);
        header('Content-Length: ' . $object['content_length']);
        header('Content-Disposition: attachment; filename="' . basename($key) . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        echo $object['content'];
        exit;
        
    } catch (Exception $e) {
        setFlashMessage('Failed to download file: ' . $e->getMessage(), 'error');
        redirect('items');
    }
}

// Handle presigned URL generation
if (isset($_GET['action']) && $_GET['action'] === 'presigned' && isset($_GET['key'])) {
    $key = $_GET['key'];
    
    if (!isValidS3Key($key)) {
        setFlashMessage('Invalid file key provided.', 'error');
        redirect('items');
    }
    
    try {
        $awsService = getAwsService();
        if (!$awsService) {
            throw new Exception('AWS service not available');
        }
        
        $url = $awsService->getPresignedUrl($key, 3600); // 1 hour expiration
        setFlashMessage('Presigned URL generated successfully.', 'success');
        
        // Store URL in session for display
        $_SESSION['presigned_url'] = $url;
        
    } catch (Exception $e) {
        setFlashMessage('Failed to generate presigned URL: ' . $e->getMessage(), 'error');
    }
    
    redirect('items');
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
    
    // Use efficient function that batches S3 operations
    $items = getAllItemsEfficiently($showGoneItems);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flashMessage = showFlashMessage();
$presignedUrl = $_SESSION['presigned_url'] ?? null;
if ($presignedUrl) {
    unset($_SESSION['presigned_url']);
}
?>

<div class="page-header compact">
    <div class="container">
        <h1>Available Items</h1>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage): ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <?php if ($presignedUrl): ?>
            <div class="alert alert-info">
                <h4>Presigned URL Generated</h4>
                <p>This URL is valid for 1 hour:</p>
                <div class="url-container">
                    <input type="text" value="<?php echo escape($presignedUrl); ?>" readonly onclick="this.select()" style="width: 100%; padding: 0.5rem; margin: 0.5rem 0; font-family: monospace; font-size: 0.9rem;">
                </div>
                <button onclick="copyToClipboard('<?php echo escape($presignedUrl); ?>')" class="btn btn-secondary">Copy URL</button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>Error:</strong> <?php echo escape($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($items)): ?>
            <div class="no-items">
                <p>No items available at the moment.</p>
            </div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach ($items as $item): ?>
                    <?php
                    // Set context for the unified template
                    $context = 'listing';
                    $isOwnListings = false;
                    $currentUser = $currentUser ?? null;
                    ?>
                    <?php include __DIR__ . '/item-card.php'; ?>
                <?php endforeach; ?>
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
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('URL copied to clipboard!');
    }, function(err) {
        console.error('Could not copy text: ', err);
        // Fallback for older browsers
        var textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            alert('URL copied to clipboard!');
        } catch (err) {
            alert('Failed to copy URL');
        }
        document.body.removeChild(textArea);
    });
}

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

function addClaimToItem(trackingNumber) {
    // Get the button that was clicked
    const button = document.querySelector(`button[onclick="addClaimToItem('${trackingNumber}')"]`);
    
    // Show loading state
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '⏳ Claiming...';
    
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
    // Get the button that was clicked
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
                } else {
                    // Update the count
                    const countElement = document.querySelector('.items-list h3');
                    if (countElement) {
                        const newCount = remainingItems.length;
                        countElement.textContent = `Available Items (${newCount})`;
                    }
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

.item-image {
    width: 100%;
    height: 250px;
    position: relative;
    overflow: hidden;
    background: #f8f9fa;
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
    opacity: 0.7;
}

.no-image-placeholder p {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 500;
}

.item-details {
    padding: 1.5rem;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.item-title {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #343a40;
    flex-grow: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.item-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: #28a745;
    background: #e9ecef;
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.price-free {
    color: #28a745;
    font-weight: 600;
    font-size: 0.9rem;
}

.price-amount {
    color: #28a745;
    font-weight: 600;
    font-size: 0.9rem;
}

.item-description {
    color: #495057;
    line-height: 1.5;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2; /* Show 2 lines of text */
    -webkit-box-orient: vertical;
}

.item-meta {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 0.85rem;
}

.item-meta > div {
    margin-bottom: 0.5rem;
}

.item-meta > div:last-child {
    margin-bottom: 0;
}

.item-meta strong {
    color: #495057;
}

.item-meta a {
    color: #007bff;
    text-decoration: none;
}

.item-meta a:hover {
    text-decoration: underline;
}

/* Item actions styling is now handled by item-card.php */

/* Delete button styling is now handled by item-card.php */

.item-link {
    text-decoration: none;
    color: inherit;
    display: block;
    transition: all 0.3s ease;
    cursor: pointer;
}

.item-link:hover .item-card {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.item-link:hover .item-image img {
    transform: scale(1.02);
}

.item-card {
    transition: all 0.3s ease;
}

.item-meta a {
    position: relative;
    z-index: 2;
    pointer-events: auto;
}

/* Item actions styling is now handled by item-card.php */

/* Claim button styling is now handled by item-card.php */

.no-items {
    text-align: center;
    padding: 4rem 2rem;
}

.no-items-content {
    max-width: 400px;
    margin: 0 auto;
}

.no-items h3 {
    color: #495057;
    margin-bottom: 1rem;
}

.no-items p {
    color: #6c757d;
    margin-bottom: 2rem;
    line-height: 1.6;
}

.url-container {
    background: #f8f9fa;
    padding: 0.5rem;
    border-radius: 4px;
    border: 1px solid #ddd;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .items-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .item-card {
        margin-bottom: 1rem;
    }
    
    .item-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .item-title {
        white-space: normal;
        line-height: 1.3;
        margin-bottom: 0;
    }
    
    .item-price {
        align-self: flex-end;
    }
}

@media (max-width: 480px) {
    /* Item actions responsive styling is now handled by item-card.php */
}


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
    padding: 1.5rem 2rem 1rem;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h3 {
    margin: 0;
    color: #dc3545;
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-body {
    padding: 1.5rem 2rem;
    color: #495057;
    line-height: 1.6;
}

.modal-body p {
    margin: 0 0 1rem 0;
}

.modal-body p:last-child {
    margin-bottom: 0;
}

.warning-text {
    color: #dc3545 !important;
    font-weight: 500;
    font-size: 0.9rem;
}

.modal-footer {
    padding: 1rem 2rem 1.5rem;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    border-top: 1px solid #e9ecef;
}

.modal-footer .btn {
    min-width: 100px;
    font-weight: 500;
}

/* Mobile responsive modal */
@media (max-width: 480px) {
    .modal-content {
        min-width: 300px;
        margin: 1rem;
    }
    
    .modal-header {
        padding: 1rem 1.5rem 0.75rem;
    }
    
    .modal-body {
        padding: 1rem 1.5rem;
    }
    
    .modal-footer {
        padding: 0.75rem 1.5rem 1rem;
        flex-direction: column-reverse;
    }
    
    .modal-footer .btn {
        min-width: auto;
        width: 100%;
    }
}
</style>
