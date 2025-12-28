<?php
// Require authentication to post items
requireAuth();

$currentUser = getCurrentUser();

// Initialize form variables with defaults
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $contactEmail = $currentUser['email'] ?? '';
} else {
    $contactEmail = trim($_POST['contact_email'] ?? '');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission';
    }

    // Validate form fields
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = trim($_POST['amount'] ?? '');

    if (empty($title)) {
        $errors[] = 'Title is required';
    }

    if (empty($description)) {
        $errors[] = 'Description is required';
    }

    if ($amount === '' || !is_numeric($amount) || (float)$amount < 0) {
        $errors[] = 'Valid amount is required (must be 0 or greater)';
    }

    if (empty($contactEmail) || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required';
    }

    // Validate uploaded file if present
    $uploadedFile = $_FILES['item_photo'] ?? null;
    if ($uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE) {
        // Check for PHP upload errors first
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            switch ($uploadedFile['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'Picture uploads are limited to 8MB';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'File upload was interrupted. Please try again.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errors[] = 'Server configuration error. Please contact support.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errors[] = 'File upload failed due to server permissions.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errors[] = 'File upload blocked by server configuration.';
                    break;
                default:
                    $errors[] = 'Error uploading file. Please try again.';
            }
        } elseif ($uploadedFile['size'] > 8388608) { // 8MB limit (8388608 bytes)
            $errors[] = 'Picture uploads are limited to 8MB';
        } elseif (!in_array(strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
            $errors[] = 'File must be a valid image (JPG, PNG, GIF)';
        }
    }

    if (empty($errors)) {
        try {
            // Generate tracking number (timestamp + random suffix for uniqueness)
            $trackingNumber = date('YmdHis') . '-' . bin2hex(random_bytes(2));

            // Get AWS service
            $awsService = getAwsService();
            if (!$awsService) {
                throw new Exception('AWS service not available');
            }

            // Upload image if provided
            $imageKey = null;
            $imageWidth = null;
            $imageHeight = null;

            if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
                $imageExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                $imageKey = 'images/' . $trackingNumber . '.' . $imageExtension;

                // Create a temporary file for the resized image
                $tempResizedPath = tempnam(sys_get_temp_dir(), 'claimit_resized_');

                if (resizeImageToFitSize($uploadedFile['tmp_name'], $tempResizedPath, 512000)) {
                    // Use the resized image
                    $imageContent = file_get_contents($tempResizedPath);
                    $mimeType = mime_content_type($tempResizedPath);

                    // Get dimensions of resized image
                    $imageInfo = getimagesize($tempResizedPath);
                    if ($imageInfo) {
                        $imageWidth = $imageInfo[0];
                        $imageHeight = $imageInfo[1];
                    }

                    // Clean up temporary file
                    unlink($tempResizedPath);
                } else {
                    // If resizing failed, use original image (fallback)
                    error_log('Image resizing failed, using original image');
                    $imageContent = file_get_contents($uploadedFile['tmp_name']);
                    $mimeType = mime_content_type($uploadedFile['tmp_name']);

                    // Get dimensions of original image
                    $imageInfo = getimagesize($uploadedFile['tmp_name']);
                    if ($imageInfo) {
                        $imageWidth = $imageInfo[0];
                        $imageHeight = $imageInfo[1];
                    }

                    // Clean up temporary file if it exists
                    if (file_exists($tempResizedPath)) {
                        unlink($tempResizedPath);
                    }
                }

                $awsService->putObject($imageKey, $imageContent, $mimeType);
            }

            // Create item data for database
            $itemData = [
                'id' => $trackingNumber,
                'title' => $title,
                'description' => $description,
                'price' => floatval($amount),
                'contact_email' => $contactEmail,
                'image_file' => $imageKey,
                'image_width' => $imageWidth,
                'image_height' => $imageHeight,
                'user_id' => $currentUser['id'],
                'user_name' => $currentUser['name'],
                'user_email' => $currentUser['email'],
                'submitted_at' => date('Y-m-d H:i:s'),
                'submitted_timestamp' => time()
            ];

            // Save item to database
            if (!createItemInDb($itemData)) {
                throw new Exception('Failed to save item to database');
            }

            // Handle community associations
            $communities = $_POST['communities'] ?? [];
            $communityIds = [];
            
            // If "all" is not in the array, collect specific community IDs
            if (!in_array('all', $communities)) {
                foreach ($communities as $commValue) {
                    if ($commValue !== 'all' && is_numeric($commValue)) {
                        $communityIds[] = (int)$commValue;
                    }
                }
            }
            // If "all" is selected or array is empty, $communityIds stays empty (visible to all)
            
            // Save community associations
            setItemCommunities($trackingNumber, $communityIds);

            // Clear items cache since we added a new item
            clearItemsCache();

            // Send new listing notifications to users who have it enabled
            try {
                $emailService = getEmailService();
                if ($emailService) {
                    // Prepare item data for email
                    $itemForEmail = [
                        'id' => $trackingNumber,
                        'title' => $title,
                        'description' => $description,
                        'price' => floatval($amount),
                        'image_key' => $imageKey
                    ];

                    // Prepare item owner data
                    $itemOwner = [
                        'id' => $currentUser['id'],
                        'name' => $currentUser['name'],
                        'email' => $currentUser['email']
                    ];

                    // Send notifications
                    $emailService->sendNewListingNotifications($itemForEmail, $itemOwner);
                }
            } catch (Exception $e) {
                // Log error but don't fail the item posting process
                error_log("Failed to send new listing notifications for item $trackingNumber: " . $e->getMessage());
            }

            setFlashMessage("Your item has been posted successfully! Tracking number: {$trackingNumber}", 'success');
            redirect('items');
        } catch (Exception $e) {
            $errors[] = 'Failed to submit posting: ' . $e->getMessage();
        }
    }
}

$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>Post an Item</h1>
        <p class="page-subtitle">Fill out the form below to post your item for sale or for giveaway</p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)) : ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo escape($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="claim-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="52428800">
            
            <div class="form-group">
                <label for="title">Item Title</label>
                <input type="text" name="title" id="title" required placeholder="Give your item a descriptive title..." value="<?php echo escape($title ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="5" required placeholder="Describe the item in detail..."><?php echo escape($description ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="item_photo">Upload a picture of your item</label>
                <input type="file" name="item_photo" id="item_photo" accept="image/*">
                <small style="color: var(--gray-500); font-size: 0.875rem;">Accepted formats: JPG, PNG, GIF (max 8MB - will be automatically resized)</small>
                <small style="color: var(--primary-600); font-size: 0.875rem; display: block; margin-top: 0.25rem;">💡 You can add more images later (up to 10 total)</small>
                <small style="color: var(--primary-600); font-size: 0.875rem; display: block; margin-top: 0.25rem;">📋 Pro tip: Press Ctrl+V (or Cmd+V) to paste an image from your clipboard!</small>
                <div id="pastePreview" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--success-50); border: 2px solid var(--success-300); border-radius: 8px;">
                    <p style="color: var(--success-700); margin: 0; font-weight: 500;">✅ Image pasted successfully!</p>
                    <img id="pastePreviewImg" style="max-width: 300px; max-height: 200px; margin-top: 0.5rem; border-radius: 4px; border: 1px solid var(--gray-200);" alt="Pasted image preview">
                </div>
                <div id="pasteError" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--error-50); border: 2px solid var(--error-300); border-radius: 8px;">
                    <p style="color: var(--error-700); margin: 0; font-weight: 500;">⚠️ <span id="pasteErrorMsg"></span></p>
                </div>
            </div>

            <div class="form-group">
                <label for="amount">Item price ($)</label>
                <input type="number" name="amount" id="amount" step="0.01" min="0" required value="<?php echo escape($amount ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email</label>
                <input type="email" name="contact_email" id="contact_email" required value="<?php echo escape($contactEmail); ?>">
            </div>

            <div class="form-group">
                <label>Visible in Communities</label>
                <small style="color: var(--gray-500); font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">
                    Select which communities can see this item (at least one required)
                </small>
                <div class="community-checkboxes">
                    <div class="community-checkbox-item">
                        <input type="checkbox" 
                               name="communities[]" 
                               value="all" 
                               id="community_all" 
                               checked 
                               onchange="handleAllCommunities(this)">
                        <label for="community_all">All Communities</label>
                    </div>
                    <?php
                    $allCommunities = getAllCommunities();
                    foreach ($allCommunities as $comm):
                    ?>
                    <div class="community-checkbox-item">
                        <input type="checkbox" 
                               name="communities[]" 
                               value="<?php echo escape($comm['id']); ?>" 
                               id="community_<?php echo escape($comm['id']); ?>"
                               class="specific-community"
                               onchange="handleSpecificCommunity()">
                        <label for="community_<?php echo escape($comm['id']); ?>">
                            <?php echo escape($comm['full_name']); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary" id="submitBtn">Post Item</button>
                <a href="?page=home" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Loading overlay -->
<div id="uploadingOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 3rem; border-radius: 12px; text-align: center; max-width: 400px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);">
        <div style="margin-bottom: 1.5rem;">
            <div class="spinner" style="width: 60px; height: 60px; border: 4px solid var(--gray-200); border-top: 4px solid var(--primary-600); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
        </div>
        <h2 style="color: var(--gray-900); margin-bottom: 0.5rem; font-size: 1.5rem;">Posting Your Listing...</h2>
        <p style="color: var(--gray-600); margin: 0; font-size: 0.95rem;">Please wait while we upload your item and image.</p>
        <p style="color: var(--gray-500); margin-top: 0.5rem; font-size: 0.85rem;">This may take a few moments.</p>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.community-checkboxes {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
    max-height: 300px;
    overflow-y: auto;
}

.community-checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.community-checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.community-checkbox-item label {
    cursor: pointer;
    margin: 0;
    font-weight: normal;
}

#community_all {
    accent-color: #007bff;
}
</style>

<script>
// Community checkbox handling
function handleAllCommunities(checkbox) {
    const specificCheckboxes = document.querySelectorAll('.specific-community');
    if (checkbox.checked) {
        // If "All" is checked, uncheck all specific communities
        specificCheckboxes.forEach(cb => cb.checked = false);
    }
}

function handleSpecificCommunity() {
    const allCheckbox = document.getElementById('community_all');
    const specificCheckboxes = document.querySelectorAll('.specific-community');
    const anySpecificChecked = Array.from(specificCheckboxes).some(cb => cb.checked);
    
    if (anySpecificChecked) {
        // If any specific community is checked, uncheck "All"
        allCheckbox.checked = false;
    }
}

// Validate at least one community is selected
function validateCommunities() {
    const allCheckbox = document.getElementById('community_all');
    const specificCheckboxes = document.querySelectorAll('.specific-community');
    const anySpecificChecked = Array.from(specificCheckboxes).some(cb => cb.checked);
    
    if (!allCheckbox.checked && !anySpecificChecked) {
        alert('Please select at least one community for this item.');
        return false;
    }
    return true;
}

// Handle paste events to allow pasting images directly
document.addEventListener('paste', function(event) {
    // Only handle paste on the claim page
    const fileInput = document.getElementById('item_photo');
    if (!fileInput) return;
    
    // Get clipboard items
    const items = (event.clipboardData || event.originalEvent.clipboardData).items;
    
    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        
        // Check if the item is an image
        if (item.type.indexOf('image') !== -1) {
            event.preventDefault(); // Prevent default paste behavior
            
            // Get the image as a blob
            const blob = item.getAsFile();
            
            if (blob) {
                const maxSize = 8388608; // 8MB in bytes (PHP limit)
                const preview = document.getElementById('pastePreview');
                const previewImg = document.getElementById('pastePreviewImg');
                const errorDiv = document.getElementById('pasteError');
                const errorMsg = document.getElementById('pasteErrorMsg');
                
                // Check file size
                if (blob.size > maxSize) {
                    const sizeMB = (blob.size / 1048576).toFixed(2); // Convert to MB
                    errorMsg.textContent = `Image is too large (${sizeMB}MB). Maximum size is 8MB. Try taking a screenshot or using a smaller image.`;
                    errorDiv.style.display = 'block';
                    preview.style.display = 'none';
                    fileInput.value = ''; // Clear the file input
                    console.log('Image too large:', sizeMB + 'MB');
                    break;
                }
                
                // Hide error if previously shown
                errorDiv.style.display = 'none';
                
                // Create a DataTransfer object to set the file input value
                const dataTransfer = new DataTransfer();
                
                // Create a File object with a proper name
                const fileName = 'pasted-image-' + Date.now() + '.png';
                const file = new File([blob], fileName, { type: blob.type });
                
                // Add the file to the DataTransfer object
                dataTransfer.items.add(file);
                
                // Set the file input's files property
                fileInput.files = dataTransfer.files;
                
                // Create a URL for the blob to display as preview
                const imageUrl = URL.createObjectURL(blob);
                previewImg.src = imageUrl;
                preview.style.display = 'block';
                
                // Optional: Clean up the URL after image loads to free memory
                previewImg.onload = function() {
                    URL.revokeObjectURL(imageUrl);
                };
                
                const sizeKB = (blob.size / 1024).toFixed(2);
                console.log('Image pasted successfully:', fileName, '(' + sizeKB + 'KB)');
            }
            
            break; // Only handle the first image
        }
    }
});

// Also handle file input change to hide/show preview
document.getElementById('item_photo').addEventListener('change', function(event) {
    const preview = document.getElementById('pastePreview');
    const previewImg = document.getElementById('pastePreviewImg');
    const errorDiv = document.getElementById('pasteError');
    const errorMsg = document.getElementById('pasteErrorMsg');
    const maxSize = 8388608; // 8MB in bytes (PHP limit)
    
    if (event.target.files && event.target.files[0]) {
        const file = event.target.files[0];
        
        // Check file size
        if (file.size > maxSize) {
            const sizeMB = (file.size / 1048576).toFixed(2); // Convert to MB
            errorMsg.textContent = `Image is too large (${sizeMB}MB). Maximum size is 8MB. Please select a smaller image.`;
            errorDiv.style.display = 'block';
            preview.style.display = 'none';
            event.target.value = ''; // Clear the file input
            return;
        }
        
        // Hide error if previously shown
        errorDiv.style.display = 'none';
        
        // Show preview for manually selected files
        const imageUrl = URL.createObjectURL(file);
        previewImg.src = imageUrl;
        preview.style.display = 'block';
        
        previewImg.onload = function() {
            URL.revokeObjectURL(imageUrl);
        };
    } else {
        // Hide preview and error if file is cleared
        preview.style.display = 'none';
        errorDiv.style.display = 'none';
    }
});

// Show loading overlay when form is submitted
document.querySelector('form').addEventListener('submit', function(event) {
    // Validate required fields first
    const title = document.querySelector('input[name="title"]').value.trim();
    const description = document.querySelector('textarea[name="description"]').value.trim();
    const amount = document.querySelector('input[name="amount"]').value;
    const email = document.querySelector('input[name="contact_email"]').value.trim();
    
    // Validate communities
    if (!validateCommunities()) {
        event.preventDefault();
        return false;
    }
    
    // Only show overlay if form is valid
    if (title && description && amount !== '' && email) {
        const overlay = document.getElementById('uploadingOverlay');
        overlay.style.display = 'flex';
        // Disable the submit button to prevent double submission
        document.getElementById('submitBtn').disabled = true;
    }
    // Let the form submit normally (don't prevent default)
});
</script> 