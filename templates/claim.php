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
            // Debug: Log the actual PHP limits
            error_log('DEBUG: PHP upload limits - upload_max_filesize: ' . ini_get('upload_max_filesize') . ', post_max_size: ' . ini_get('post_max_size'));
            error_log('DEBUG: File upload error code: ' . $uploadedFile['error'] . ', file size: ' . ($uploadedFile['size'] ?? 'unknown'));
            
            switch ($uploadedFile['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'Picture uploads are limited to 10MB (PHP limit: ' . ini_get('upload_max_filesize') . ')';
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
        } elseif ($uploadedFile['size'] > 10485760) { // 10MB limit (10485760 bytes)
            $errors[] = 'Picture uploads are limited to 10MB';
        } elseif (!in_array(strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
            $errors[] = 'File must be a valid image (JPG, PNG, GIF)';
        }
    }
    
    if (empty($errors)) {
        try {
            // Generate tracking number (reverse datestamp)
            $trackingNumber = date('YmdHis');
            
            // Get AWS service
            $awsService = getAwsService();
            if (!$awsService) {
                throw new Exception('AWS service not available');
            }
            
            // Upload image if provided
            $imageKey = null;
            if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
                $imageExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                $imageKey = $trackingNumber . '.' . $imageExtension;
                
                // Create a temporary file for the resized image
                $tempResizedPath = tempnam(sys_get_temp_dir(), 'claimit_resized_');
                
                // Resize the image to keep it under 500KB
                if (resizeImageToFitSize($uploadedFile['tmp_name'], $tempResizedPath, 512000)) {
                    // Use the resized image
                    $imageContent = file_get_contents($tempResizedPath);
                    $mimeType = mime_content_type($tempResizedPath);
                    
                    // Clean up temporary file
                    unlink($tempResizedPath);
                } else {
                    // If resizing failed, use original image (fallback)
                    error_log('Image resizing failed, using original image');
                    $imageContent = file_get_contents($uploadedFile['tmp_name']);
                    $mimeType = mime_content_type($uploadedFile['tmp_name']);
                    
                    // Clean up temporary file if it exists
                    if (file_exists($tempResizedPath)) {
                        unlink($tempResizedPath);
                    }
                }
                
                $awsService->putObject($imageKey, $imageContent, $mimeType);
            }
            
            // Create YAML content
            $yamlData = [
                'tracking_number' => $trackingNumber,
                'title' => $title,
                'description' => $description,
                'price' => floatval($amount),
                'contact_email' => $contactEmail,
                'image_file' => $imageKey,
                'user_id' => $currentUser['id'],
                'user_name' => $currentUser['name'],
                'user_email' => $currentUser['email'],
                'submitted_at' => date('Y-m-d H:i:s'),
                'submitted_timestamp' => time()
            ];
            
            // Convert to YAML format
            $yamlContent = "# Item Posting Data\n";
            $yamlContent .= "tracking_number: '" . $yamlData['tracking_number'] . "'\n";
            $yamlContent .= "title: '" . str_replace("'", "''", $yamlData['title']) . "'\n";
            $yamlContent .= "description: |\n";
            $yamlContent .= "  " . str_replace("\n", "\n  ", $yamlData['description']) . "\n";
            $yamlContent .= "price: " . $yamlData['price'] . "\n";
            $yamlContent .= "contact_email: '" . $yamlData['contact_email'] . "'\n";
            $yamlContent .= "image_file: " . ($yamlData['image_file'] ? "'" . $yamlData['image_file'] . "'" : "null") . "\n";
            $yamlContent .= "user_id: '" . $yamlData['user_id'] . "'\n";
            $yamlContent .= "user_name: '" . str_replace("'", "''", $yamlData['user_name']) . "'\n";
            $yamlContent .= "user_email: '" . $yamlData['user_email'] . "'\n";
            $yamlContent .= "submitted_at: '" . $yamlData['submitted_at'] . "'\n";
            $yamlContent .= "submitted_timestamp: " . $yamlData['submitted_timestamp'] . "\n";
            
            // Upload YAML file
            $yamlKey = $trackingNumber . '.yaml';
            $awsService->putObject($yamlKey, $yamlContent, 'text/plain');
            
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
        <?php if ($flashMessage): ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo escape($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="claim-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="512000">
            
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
                <small style="color: var(--gray-500); font-size: 0.875rem;">Accepted formats: JPG, PNG, GIF (max 10MB - will be automatically resized)</small>
            </div>

            <div class="form-group">
                <label for="amount">Item price ($)</label>
                <input type="number" name="amount" id="amount" step="0.01" min="0" required value="<?php echo escape($amount ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email</label>
                <input type="email" name="contact_email" id="contact_email" required value="<?php echo escape($contactEmail ?? ''); ?>">
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Post Item</button>
                <a href="?page=home" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div> 