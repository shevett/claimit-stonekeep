<?php
// Require authentication to post items
requireAuth();

$currentUser = getCurrentUser();

// Ensure a staging ID exists in session for image management
$stagingId = getStagingId();

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

    // Validate staging ID matches session
    $postedStagingId = $_POST['staging_id'] ?? '';
    $sessionStagingId = $_SESSION['staging_id'] ?? '';
    if ($postedStagingId !== $sessionStagingId) {
        $errors[] = 'Invalid form session. Please reload the page and try again.';
    }

    if (empty($errors)) {
        try {
            // Generate tracking number (timestamp + random suffix for uniqueness)
            $trackingNumber = date('YmdHis') . '-' . bin2hex(random_bytes(2));

            // Promote staging images to permanent S3 locations
            $imageKey = null;
            $imageWidth = null;
            $imageHeight = null;

            if (!empty($sessionStagingId)) {
                $promoted = promoteStagingImages($sessionStagingId, $trackingNumber);
                if ($promoted) {
                    $imageKey    = $promoted['image_key'];
                    $imageWidth  = $promoted['image_width'];
                    $imageHeight = $promoted['image_height'];
                }
            }

            // Create item data for database
            // Handle community associations BEFORE creating the item
            $communities = $_POST['communities'] ?? [];
            $communityIds = [];

            // Collect selected community IDs (empty = invisible/staging)
            foreach ($communities as $commValue) {
                if (is_numeric($commValue)) {
                    $communityIds[] = (int)$commValue;
                }
            }

            // If no communities selected, pass null to allow default or empty
            $communityIdsForCreate = !empty($communityIds) ? $communityIds : null;

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

            // Save item to database with community associations
            // This will trigger Slack notifications to the correct communities
            if (!createItemInDb($itemData, $communityIdsForCreate)) {
                throw new Exception('Failed to save item to database');
            }

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

            clearStagingId();
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

        <form method="POST" class="claim-form">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="staging_id" value="<?php echo escape($stagingId); ?>">
            
            <div class="form-group">
                <label for="title">Item Title</label>
                <input type="text" name="title" id="title" required placeholder="Give your item a descriptive title..." value="<?php echo escape($title ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="5" required placeholder="Describe the item in detail..."><?php echo escape($description ?? ''); ?></textarea>
            </div>

            <div class="form-group" id="stagingImageSection">
                <label>Photos</label>
                <small style="color: var(--gray-500); font-size: 0.875rem; display: block; margin-bottom: 0.75rem;">
                    Upload up to 10 photos (JPG, PNG, GIF, max 50MB each). The first photo will be the main photo.
                    You can rotate or remove photos before posting.
                </small>

                <!-- Staged image thumbnails -->
                <div id="stagingThumbnails" style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;"></div>

                <!-- Upload button -->
                <div id="stagingUploadArea">
                    <label for="stagingFileInput" class="btn btn-primary" style="cursor: pointer; display: inline-block;">
                        + Add Photos
                    </label>
                    <input type="file" id="stagingFileInput" accept="image/jpeg,image/png,image/gif" multiple style="display: none;">
                    <small style="color: var(--gray-500); font-size: 0.875rem; margin-left: 0.5rem;">or press Ctrl+V / Cmd+V to paste</small>
                </div>

                <div id="stagingError" style="display: none; margin-top: 0.75rem; padding: 0.75rem 1rem; background: var(--error-50); border: 2px solid var(--error-300); border-radius: 8px; color: var(--error-700);"></div>
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
                    Select which communities can see this item (leave empty to create an invisible/staging item)
                </small>
                <div class="community-checkboxes">
                    <?php
                    $allCommunities = getAllCommunities();
                    foreach ($allCommunities as $comm) :
                        // Default to General (community 1) being checked
                        $isChecked = ($comm['id'] == 1);
                        ?>
                    <div class="community-checkbox-item">
                        <input type="checkbox" 
                               name="communities[]" 
                               value="<?php echo escape($comm['id']); ?>" 
                               id="community_<?php echo escape($comm['id']); ?>"
                               class="community-checkbox"
                               <?php echo $isChecked ? 'checked' : ''; ?>>
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

<style>
.staging-thumb {
    position: relative;
    width: 120px;
    flex-shrink: 0;
}
.staging-thumb img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid var(--gray-200);
    display: block;
}
.staging-thumb.primary img {
    border-color: var(--primary-500);
}
.staging-thumb .thumb-label {
    font-size: 0.7rem;
    color: var(--primary-600);
    font-weight: 600;
    text-align: center;
    margin-top: 0.2rem;
}
.staging-thumb .thumb-actions {
    position: absolute;
    top: 4px;
    right: 4px;
    display: flex;
    gap: 3px;
}
.staging-thumb .thumb-btn {
    background: rgba(0,0,0,0.6);
    color: #fff;
    border: none;
    border-radius: 4px;
    width: 26px;
    height: 26px;
    cursor: pointer;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
.staging-thumb .thumb-btn:hover {
    background: rgba(0,0,0,0.85);
}
.staging-thumb .thumb-spinner {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.7);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
(function() {
    const STAGING_ID = <?php echo json_encode($stagingId); ?>;
    const MAX_IMAGES = 10;

    const thumbnailsEl = document.getElementById('stagingThumbnails');
    const fileInput     = document.getElementById('stagingFileInput');
    const errorEl       = document.getElementById('stagingError');

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = 'block';
        setTimeout(() => { errorEl.style.display = 'none'; }, 5000);
    }

    function buildThumb(img, index) {
        const div = document.createElement('div');
        div.className = 'staging-thumb' + (index === 0 ? ' primary' : '');
        div.dataset.key = img.key;

        const image = document.createElement('img');
        image.src = img.url;
        image.alt = 'Photo ' + (index + 1);

        const actions = document.createElement('div');
        actions.className = 'thumb-actions';

        const rotBtn = document.createElement('button');
        rotBtn.type = 'button';
        rotBtn.className = 'thumb-btn';
        rotBtn.title = 'Rotate clockwise';
        rotBtn.innerHTML = '&#8635;';
        rotBtn.addEventListener('click', () => rotateStagingImage(div, img.key, image));

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'thumb-btn';
        delBtn.title = 'Delete photo';
        delBtn.innerHTML = '&times;';
        delBtn.addEventListener('click', () => deleteStagingImage(div, img.key));

        actions.appendChild(rotBtn);
        actions.appendChild(delBtn);
        div.appendChild(image);
        div.appendChild(actions);

        if (index === 0) {
            const label = document.createElement('div');
            label.className = 'thumb-label';
            label.textContent = 'Main photo';
            div.appendChild(label);
        }

        return div;
    }

    function renderThumbnails(images) {
        thumbnailsEl.innerHTML = '';
        images.forEach((img, i) => {
            thumbnailsEl.appendChild(buildThumb(img, i));
        });
    }

    function setThumbBusy(thumbEl, busy) {
        let spinner = thumbEl.querySelector('.thumb-spinner');
        if (busy) {
            if (!spinner) {
                spinner = document.createElement('div');
                spinner.className = 'thumb-spinner';
                spinner.innerHTML = '<div class="spinner" style="width:30px;height:30px;border:3px solid #ddd;border-top-color:var(--primary-600);border-radius:50%;animation:spin 0.8s linear infinite;"></div>';
                thumbEl.appendChild(spinner);
            }
        } else if (spinner) {
            spinner.remove();
        }
    }

    async function uploadFiles(files) {
        for (const file of files) {
            const current = thumbnailsEl.querySelectorAll('.staging-thumb').length;
            if (current >= MAX_IMAGES) {
                showError('Maximum of ' + MAX_IMAGES + ' photos allowed.');
                break;
            }

            // Show a placeholder thumb while uploading
            const placeholder = document.createElement('div');
            placeholder.className = 'staging-thumb';
            placeholder.innerHTML = '<div style="width:120px;height:120px;background:var(--gray-100);border-radius:6px;border:2px dashed var(--gray-300);display:flex;align-items:center;justify-content:center;"><div class="spinner" style="width:30px;height:30px;border:3px solid #ddd;border-top-color:var(--primary-600);border-radius:50%;animation:spin 0.8s linear infinite;"></div></div>';
            thumbnailsEl.appendChild(placeholder);

            const fd = new FormData();
            fd.append('action', 'upload_staging_image');
            fd.append('staging_id', STAGING_ID);
            fd.append('image_file', file);

            try {
                const resp = await fetch('', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.success) {
                    renderThumbnails(data.allImages);
                } else {
                    placeholder.remove();
                    showError(data.message || 'Upload failed');
                }
            } catch (e) {
                placeholder.remove();
                showError('Upload failed: ' + e.message);
            }
        }
    }

    async function rotateStagingImage(thumbEl, key, imgTag) {
        setThumbBusy(thumbEl, true);
        const fd = new FormData();
        fd.append('action', 'rotate_staging_image');
        fd.append('staging_id', STAGING_ID);
        fd.append('image_key', key);
        try {
            const resp = await fetch('', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                imgTag.src = data.url;
            } else {
                showError(data.message || 'Rotate failed');
            }
        } catch (e) {
            showError('Rotate failed: ' + e.message);
        } finally {
            setThumbBusy(thumbEl, false);
        }
    }

    async function deleteStagingImage(thumbEl, key) {
        setThumbBusy(thumbEl, true);
        const fd = new FormData();
        fd.append('action', 'delete_staging_image');
        fd.append('staging_id', STAGING_ID);
        fd.append('image_key', key);
        try {
            const resp = await fetch('', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                renderThumbnails(data.allImages);
            } else {
                showError(data.message || 'Delete failed');
                setThumbBusy(thumbEl, false);
            }
        } catch (e) {
            showError('Delete failed: ' + e.message);
            setThumbBusy(thumbEl, false);
        }
    }

    // File input change
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            uploadFiles(Array.from(this.files));
            this.value = '';
        }
    });

    // Paste support
    document.addEventListener('paste', function(event) {
        if (!document.getElementById('stagingImageSection')) return;
        const items = (event.clipboardData || event.originalEvent.clipboardData).items;
        const imageFiles = [];
        for (const item of items) {
            if (item.type.indexOf('image') !== -1) {
                const blob = item.getAsFile();
                if (blob) {
                    const file = new File([blob], 'pasted-' + Date.now() + '.png', { type: blob.type });
                    imageFiles.push(file);
                }
            }
        }
        if (imageFiles.length > 0) {
            event.preventDefault();
            uploadFiles(imageFiles);
        }
    });

    // Show loading overlay on submit
    document.querySelector('form').addEventListener('submit', function(event) {
        const title = document.querySelector('input[name="title"]').value.trim();
        const description = document.querySelector('textarea[name="description"]').value.trim();
        const amount = document.querySelector('input[name="amount"]').value;
        const email = document.querySelector('input[name="contact_email"]').value.trim();

        if (title && description && amount !== '' && email) {
            const overlay = document.getElementById('uploadingOverlay');
            overlay.style.display = 'flex';
            document.getElementById('submitBtn').disabled = true;
        }
    });
})();
</script> 