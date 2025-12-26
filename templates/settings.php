<?php

/**
 * Settings page template
 */

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    redirect('login');
}

// Get flash message if any
$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>Settings</h1>
        <p class="page-subtitle">Manage your account preferences and email notifications</p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="settings-container">
            <div class="settings-card">
                <div class="settings-header">
                    <h2>Account Settings</h2>
                    <p>Manage your display preferences and notification settings</p>
                </div>

                <form id="settingsForm" class="settings-form">
                    <div class="form-group">
                        <label for="displayName">Display Name</label>
                        <input type="text" id="displayName" name="displayName" value="<?php echo escape(getUserDisplayName($currentUser['id'], $currentUser['name'])); ?>" required>
                        <small class="form-help">This name will be displayed on your listings and claims</small>
                    </div>

                    <div class="form-group">
                        <label for="zipcode">Zip Code</label>
                        <input type="text" id="zipcode" name="zipcode" value="<?php echo escape(getUserZipcode($currentUser['id'])); ?>" maxlength="10" pattern="[0-9]{5}(-[0-9]{4})?" placeholder="12345">
                        <small class="form-help">Your zip code helps show items near you (optional)</small>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="showGoneItems" name="showGoneItems" <?php
                            if (getUserShowGoneItems($currentUser['id'])) {
                                echo 'checked';
                            }
                            ?>>
                            <span class="checkbox-text">Show gone items in listings</span>
                        </label>
                        <small class="form-help">When enabled, items marked as "gone" will still appear in item listings</small>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="emailNotifications" name="emailNotifications" <?php
                            if (getUserEmailNotifications($currentUser['id'])) {
                                echo 'checked';
                            }
                            ?>>
                            <span class="checkbox-text">Notify me when someone claims one of my items</span>
                        </label>
                        <small class="form-help">When enabled, you'll receive email notifications when someone claims your items</small>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="newListingNotifications" name="newListingNotifications" <?php
                            if (getUserNewListingNotifications($currentUser['id'])) {
                                echo 'checked';
                            }
                            ?>>
                            <span class="checkbox-text">Notify me of any new listings</span>
                        </label>
                        <small class="form-help">When enabled, you'll receive email notifications whenever anyone posts a new item</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-text">Save Changes</span>
                            <span class="btn-loading" style="display: none;">Saving...</span>
                        </button>
                        <a href="?page=home" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <div class="settings-info">
                <div class="info-card">
                    <h3>Account Information</h3>
                    <div class="info-item">
                        <label>Email:</label>
                        <span><?php echo escape($currentUser['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Name:</label>
                        <span><?php echo escape($currentUser['name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Zip Code:</label>
                        <span><?php
                            $zipcode = getUserZipcode($currentUser['id']);
                            echo $zipcode ? escape($zipcode) : '<em style="color: #999;">Not set</em>';
                        ?></span>
                    </div>
                    <div class="info-item">
                        <label>Member since:</label>
                        <span><?php echo formatDate($currentUser['created_at'] ?? date('Y-m-d H:i:s')); ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Email Notifications</h3>
                    <p>Email notifications help you stay informed about activity on your items. You can enable or disable them at any time.</p>
                    <ul>
                        <li>Get notified when someone claims your items</li>
                        <li>Notifications are sent to your registered email address</li>
                        <li>You can disable notifications anytime in these settings</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.settings-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.settings-header {
    background: #f8f9fa;
    padding: 2rem;
    border-bottom: 1px solid #e9ecef;
}

.settings-header h2 {
    margin: 0 0 0.5rem 0;
    color: #333;
    font-size: 1.5rem;
}

.settings-header p {
    margin: 0;
    color: #666;
    font-size: 0.95rem;
}

.settings-form {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
}

.checkbox-label {
    display: flex !important;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 500 !important;
}

.checkbox-label input[type="checkbox"] {
    margin: 0;
    width: auto;
    min-width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.checkbox-text {
    flex: 1;
    line-height: 1.4;
}

.form-help {
    display: block;
    color: #666;
    font-size: 0.875rem;
    margin-top: 0.25rem;
    line-height: 1.4;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 120px;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.settings-info {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.info-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
}

.info-card h3 {
    margin: 0 0 1rem 0;
    color: #333;
    font-size: 1.25rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f4;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item label {
    font-weight: 600;
    color: #666;
    margin: 0;
}

.info-item span {
    color: #333;
}

.info-card p {
    color: #666;
    line-height: 1.5;
    margin: 0 0 1rem 0;
}

.info-card ul {
    margin: 0;
    padding-left: 1.25rem;
    color: #666;
}

.info-card li {
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

@media (max-width: 768px) {
    .settings-container {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .settings-header,
    .settings-form {
        padding: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('settingsForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const displayName = document.getElementById('displayName').value.trim();
        const zipcode = document.getElementById('zipcode').value.trim();
        const showGoneItems = document.getElementById('showGoneItems').checked;
        const emailNotifications = document.getElementById('emailNotifications').checked;
        const newListingNotifications = document.getElementById('newListingNotifications').checked;
        
        if (!displayName) {
            showMessage('Display name is required', 'error');
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
        
        // Send AJAX request to save settings
        fetch('/?page=settings&action=save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'display_name=' + encodeURIComponent(displayName) + 
                  '&zipcode=' + encodeURIComponent(zipcode) +
                  (showGoneItems ? '&show_gone_items=on' : '') +
                  (emailNotifications ? '&email_notifications=on' : '') +
                  (newListingNotifications ? '&new_listing_notifications=on' : '')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message || 'Settings saved successfully!', 'success');
                // Reload page to show updated settings
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showMessage('Error saving settings: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error saving settings. Please try again.', 'error');
        })
        .finally(() => {
            // Restore button state
            submitBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        });
    });
});

function showMessage(message, type = 'info') {
    // Create a simple message display
    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${type}`;
    messageDiv.textContent = message;
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        max-width: 400px;
        word-wrap: break-word;
    `;
    
    if (type === 'success') {
        messageDiv.style.backgroundColor = '#28a745';
    } else if (type === 'error') {
        messageDiv.style.backgroundColor = '#dc3545';
    } else {
        messageDiv.style.backgroundColor = '#17a2b8';
    }
    
    document.body.appendChild(messageDiv);
    
    // Remove the message after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.parentNode.removeChild(messageDiv);
        }
    }, 5000);
}
</script>
