<?php
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
    $contactEmail = trim($_POST['contact_email'] ?? '');
    
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
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading file';
        } elseif ($uploadedFile['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = 'File size must be less than 5MB';
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
                
                $imageContent = file_get_contents($uploadedFile['tmp_name']);
                $mimeType = mime_content_type($uploadedFile['tmp_name']);
                
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
                <small style="color: var(--gray-500); font-size: 0.875rem;">Accepted formats: JPG, PNG, GIF (max 5MB)</small>
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