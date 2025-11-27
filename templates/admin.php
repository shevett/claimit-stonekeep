<?php
/**
 * Admin page template
 */

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login');
}

// Check if user is admin
if (!isAdmin()) {
    setFlashMessage('You do not have permission to access this page', 'error');
    redirect('home');
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
        <h1>👑 Administration</h1>
        <p class="page-subtitle">Manage system settings and administrative functions</p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage): ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="admin-container">
            <div class="admin-card">
                <div class="admin-header">
                    <h2>System Tools</h2>
                    <p>Administrative tools and system configuration options</p>
                </div>

                <form id="adminForm" class="admin-form">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="sendTestEmail" name="sendTestEmail">
                            <span class="checkbox-text">Send a test email to me</span>
                        </label>
                        <small class="form-help">Send a test email to verify SMTP configuration is working</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-text">Execute</span>
                            <span class="btn-loading" style="display: none;">Processing...</span>
                        </button>
                        <a href="?page=home" class="btn btn-secondary">Back to Home</a>
                    </div>
                </form>
            </div>

            <div class="admin-info">
                <div class="info-card">
                    <h3>Administrator Information</h3>
                    <div class="info-item">
                        <label>Your Email:</label>
                        <span><?php echo escape($currentUser['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>User ID:</label>
                        <span><?php echo escape($currentUser['id']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>S3 Bucket:</label>
                        <span><?php 
                            $awsService = getAwsService();
                            echo $awsService ? escape($awsService->getBucketName()) : '<em style="color: #999;">Not configured</em>';
                        ?></span>
                    </div>
                </div>

                <div class="info-card warning-card">
                    <h3>⚠️ Administrator Notice</h3>
                    <p>You have administrator privileges. Please use these tools responsibly.</p>
                    <ul>
                        <li>Changes may affect all users</li>
                        <li>Always test in development first</li>
                        <li>Keep credentials secure</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.admin-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.admin-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    border: 2px solid #ffeaa7;
}

.admin-header {
    background: #fff3cd;
    padding: 2rem;
    border-bottom: 1px solid #ffeaa7;
}

.admin-header h2 {
    margin: 0 0 0.5rem 0;
    color: #856404;
    font-size: 1.5rem;
}

.admin-header p {
    margin: 0;
    color: #856404;
    font-size: 0.95rem;
}

.admin-form {
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

.admin-info {
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

.warning-card {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
}

.warning-card h3 {
    color: #856404;
}

.warning-card p,
.warning-card li {
    color: #856404;
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
    .admin-container {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .admin-header,
    .admin-form {
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
    const form = document.getElementById('adminForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const sendTestEmail = document.getElementById('sendTestEmail').checked;
        
        if (!sendTestEmail) {
            showMessage('Please select at least one action to perform', 'error');
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
        
        // Send AJAX request to execute admin actions
        fetch('/?page=admin&action=execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: (sendTestEmail ? '&send_test_email=on' : '')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message || 'Action completed successfully!', 'success');
                // Reset form
                document.getElementById('sendTestEmail').checked = false;
            } else {
                showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error executing action. Please try again.', 'error');
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

