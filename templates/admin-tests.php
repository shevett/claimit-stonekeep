<?php

/**
 * Admin diagnostics/tests page template
 */

if (!isLoggedIn()) {
    redirect('login');
}

if (!isAdmin()) {
    setFlashMessage('You do not have permission to access this page', 'error');
    redirect('home');
}

$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>🧪 Tests</h1>
        <p class="page-subtitle">Run diagnostic checks on system components</p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <form id="adminForm" class="admin-form">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="testDatabase" name="testDatabase">
                        <span class="checkbox-text">Test database connection</span>
                    </label>
                    <small class="form-help">Test connection to RDS MySQL database and show details</small>
                </div>

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
                    <a href="?page=admin" class="btn btn-secondary">← Back to Admin</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.admin-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    border: 2px solid #ffeaa7;
    max-width: 700px;
    margin: 0 auto;
}

.admin-form {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
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

@media (max-width: 768px) {
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

        const testDatabase = document.getElementById('testDatabase').checked;
        const sendTestEmail = document.getElementById('sendTestEmail').checked;

        if (!testDatabase && !sendTestEmail) {
            showMessage('Please select at least one action to perform', 'error');
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';

        // Build request body
        let body = '';
        if (testDatabase) body += 'test_database=on&';
        if (sendTestEmail) body += 'send_test_email=on&';

        // Send AJAX request to execute admin actions
        fetch('/?page=admin-tests&action=execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let message = data.message || 'Action completed successfully!';
                // Add details if available
                if (data.details) {
                    message += '\n\nDetails:\n' + JSON.stringify(data.details, null, 2);
                }
                showMessage(message, 'success');
                // Reset form
                document.getElementById('testDatabase').checked = false;
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
