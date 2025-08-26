<?php
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission';
    }
    
    // Validate form fields
    $claimType = trim($_POST['claim_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    
    if (empty($claimType)) {
        $errors[] = 'Claim type is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if (empty($amount) || !is_numeric($amount)) {
        $errors[] = 'Valid amount is required';
    }
    
    if (empty($contactEmail) || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required';
    }
    
    if (empty($errors)) {
        // Process the claim (in a real app, this would save to database)
        $claimId = 'CLM-' . date('Y') . '-' . sprintf('%06d', rand(1, 999999));
        setFlashMessage("Your claim has been submitted successfully! Claim ID: {$claimId}", 'success');
        redirect('claim');
    }
}

$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>Submit a Claim</h1>
        <p class="page-subtitle">Fill out the form below to submit your claim</p>
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

        <form method="POST" class="claim-form">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="claim_type">Claim Type</label>
                <select name="claim_type" id="claim_type" required>
                    <option value="">Select claim type...</option>
                    <option value="insurance" <?php echo ($claimType ?? '') === 'insurance' ? 'selected' : ''; ?>>Insurance Claim</option>
                    <option value="warranty" <?php echo ($claimType ?? '') === 'warranty' ? 'selected' : ''; ?>>Warranty Claim</option>
                    <option value="refund" <?php echo ($claimType ?? '') === 'refund' ? 'selected' : ''; ?>>Refund Request</option>
                    <option value="compensation" <?php echo ($claimType ?? '') === 'compensation' ? 'selected' : ''; ?>>Compensation</option>
                    <option value="other" <?php echo ($claimType ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="5" required placeholder="Describe your claim in detail..."><?php echo escape($description ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="amount">Claim Amount ($)</label>
                <input type="number" name="amount" id="amount" step="0.01" min="0" required value="<?php echo escape($amount ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email</label>
                <input type="email" name="contact_email" id="contact_email" required value="<?php echo escape($contactEmail ?? ''); ?>">
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Submit Claim</button>
                <a href="?page=home" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div> 