<?php
// Simple YAML parser for our specific format
function parseSimpleYaml($yamlContent) {
    $data = [];
    $lines = explode("\n", $yamlContent);
    $i = 0;
    
    while ($i < count($lines)) {
        $line = trim($lines[$i]);
        
        // Skip comments and empty lines
        if (empty($line) || $line[0] === '#') {
            $i++;
            continue;
        }
        
        // Parse key: value pairs
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Handle multi-line values (starts with |)
            if ($value === '|') {
                $multilineValue = '';
                $i++; // Move to next line
                
                // Read subsequent indented lines
                while ($i < count($lines)) {
                    $nextLine = $lines[$i];
                    if (empty(trim($nextLine))) {
                        $i++;
                        break; // End of multiline block
                    }
                    if (preg_match('/^  (.*)$/', $nextLine, $matches)) {
                        if ($multilineValue !== '') {
                            $multilineValue .= ' ';
                        }
                        $multilineValue .= $matches[1];
                        $i++;
                    } else {
                        break; // End of multiline block
                    }
                }
                $data[$key] = $multilineValue;
                continue;
            }
            
            // Handle regular values
            // Remove quotes if present
            if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            
            $data[$key] = $value;
        }
        $i++;
    }
    
    return $data;
}

// Check if AWS credentials are configured
if (!hasAwsCredentials()) {
    ?>
    <div class="page-header">
        <div class="container">
            <h1>S3 Assets</h1>
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

// Handle file download request
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['key'])) {
    $key = $_GET['key'];
    
    if (!isValidS3Key($key)) {
        setFlashMessage('Invalid file key provided.', 'error');
        redirect('s3');
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
        redirect('s3');
    }
}

// Handle presigned URL generation
if (isset($_GET['action']) && $_GET['action'] === 'presigned' && isset($_GET['key'])) {
    $key = $_GET['key'];
    
    if (!isValidS3Key($key)) {
        setFlashMessage('Invalid file key provided.', 'error');
        redirect('s3');
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
    
    redirect('s3');
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
                        
                        $items[] = [
                            'tracking_number' => $trackingNumber,
                            'description' => $data['description'],
                            'price' => $data['price'],
                            'contact_email' => $data['contact_email'],
                            'image_key' => $imageKey,
                            'posted_date' => $data['submitted_at'] ?? 'Unknown',
                            'yaml_key' => $object['key']
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
$presignedUrl = $_SESSION['presigned_url'] ?? null;
if ($presignedUrl) {
    unset($_SESSION['presigned_url']);
}
?>

<div class="page-header">
    <div class="container">
        <h1>Available Items</h1>
        <p class="page-subtitle">Browse items posted for sale</p>
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

        <?php if ($awsService): ?>
            <?php if (empty($items)): ?>
                <div class="no-items">
                    <div class="no-items-content">
                        <h3>No items posted yet</h3>
                        <p>There are currently no items available for sale.</p>
                        <a href="?page=claim" class="btn btn-primary">Post your first item</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="items-list">
                    <h3>Available Items (<?php echo count($items); ?>)</h3>
                    
                    <div class="items-grid">
                        <?php foreach ($items as $item): ?>
                            <div class="item-card">
                                <div class="item-image">
                                    <?php if ($item['image_key']): ?>
                                        <?php 
                                        $imageUrl = $awsService->getPresignedUrl($item['image_key'], 3600);
                                        ?>
                                        <img src="<?php echo escape($imageUrl); ?>" 
                                             alt="<?php echo escape($item['description']); ?>" 
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="no-image-placeholder">
                                            <span>📷</span>
                                            <p>No Image Available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="item-details">
                                    <h4 class="item-price">$<?php echo escape(number_format($item['price'], 2)); ?></h4>
                                    <p class="item-description"><?php echo escape($item['description']); ?></p>
                                    
                                    <div class="item-meta">
                                        <div class="item-contact">
                                            <strong>Contact:</strong> 
                                            <a href="mailto:<?php echo escape($item['contact_email']); ?>">
                                                <?php echo escape($item['contact_email']); ?>
                                            </a>
                                        </div>
                                        <div class="item-posted">
                                            <strong>Posted:</strong> <?php echo escape($item['posted_date']); ?>
                                        </div>
                                        <div class="item-tracking">
                                            <strong>ID:</strong> #<?php echo escape($item['tracking_number']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="item-actions">
                                        <a href="mailto:<?php echo escape($item['contact_email']); ?>?subject=Interest in item #<?php echo escape($item['tracking_number']); ?>" 
                                           class="btn btn-primary">
                                            📧 Contact Seller
                                        </a>
                                        <?php if ($item['image_key']): ?>
                                            <a href="?page=s3&action=presigned&key=<?php echo urlencode($item['image_key']); ?>" 
                                               class="btn btn-secondary">
                                                🔗 Share Image
                                            </a>
                                        <?php endif; ?>
                                        <button onclick="deleteItem('<?php echo escape($item['tracking_number']); ?>')" 
                                                class="btn btn-danger delete-btn" 
                                                title="Delete this item">
                                            🗑️ Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
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
    formData.append('action', 'delete');
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

.item-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: #28a745;
    margin: 0 0 1rem 0;
}

.item-description {
    color: #2c3e50;
    line-height: 1.5;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
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

.item-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.item-actions .btn {
    flex: 1;
    min-width: 120px;
    text-align: center;
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
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
        gap: 1.5rem;
    }
    
    .item-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .item-actions .btn {
        min-width: auto;
        flex: none;
    }
}

@media (max-width: 480px) {
    .item-actions {
        gap: 0.5rem;
    }
    
    .item-actions .btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
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