<?php

/**
 * Add/Edit Community page template
 */

if (!isLoggedIn()) {
    redirect('login');
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    redirect('login');
}

$isAdmin = isAdmin();
$communityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditing = $communityId > 0;
$community = null;

if ($isEditing) {
    $community = getCommunityById($communityId);
    if (!$community) {
        setFlashMessage('Community not found', 'error');
        redirect('communities');
    }
    if (!$isAdmin && !isCommunityModerator($currentUser['id'], $communityId)) {
        setFlashMessage('Not authorized for this community', 'error');
        redirect('communities');
    }
} else {
    if (!$isAdmin) {
        setFlashMessage('Administrator privileges required', 'error');
        redirect('communities');
    }
}

$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1><?php echo $isEditing ? 'Edit Community' : 'Add New Community'; ?></h1>
        <p class="page-subtitle"><?php echo $isEditing ? escape($community['full_name']) : 'Create a new community'; ?></p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="community-edit-container">
            <div class="action-bar">
                <a href="?page=communities" class="btn btn-secondary">← Back to Communities</a>
            </div>

            <div class="community-edit-card">
                <div class="tab-nav">
                    <button type="button" class="tab-btn active" data-tab="details">Details</button>
                    <button type="button" class="tab-btn" data-tab="settings">Settings</button>
                    <button type="button" class="tab-btn" data-tab="notifications">Notifications</button>
                    <?php if ($isEditing) : ?>
                    <button type="button" class="tab-btn" data-tab="moderators">Moderators</button>
                    <?php endif; ?>
                </div>

                <form id="communityForm">
                    <input type="hidden" id="communityId" name="id" value="<?php echo $isEditing ? escape($community['id']) : ''; ?>">

                    <div class="tab-panel active" data-tab="details">
                        <div class="form-group">
                            <label for="shortName">Short Name <span class="required">*</span></label>
                            <input type="text" id="shortName" name="short_name" required maxlength="50"
                                   placeholder="e.g., downtown"
                                   value="<?php echo $isEditing ? escape($community['short_name']) : ''; ?>">
                            <small class="form-help">Unique identifier (lowercase, no spaces)</small>
                        </div>

                        <div class="form-group">
                            <label for="fullName">Full Name <span class="required">*</span></label>
                            <input type="text" id="fullName" name="full_name" required maxlength="255"
                                   placeholder="e.g., Downtown Neighborhood"
                                   value="<?php echo $isEditing ? escape($community['full_name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"
                                      placeholder="Describe this community..."><?php echo $isEditing ? escape($community['description'] ?? '') : ''; /* nosemgrep: php.lang.security.injection.echoed-request.echoed-request */ ?></textarea>
                        </div>
                    </div>

                    <div class="tab-panel" data-tab="settings">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="private" name="private" value="1" <?php echo ($isEditing && !empty($community['private'])) ? 'checked' : ''; ?>>
                                <span>Make this community private, meaning a user must be a member of the community to see the items listed</span>
                            </label>
                            <small class="form-help">Private communities are only visible to members. Items in both private and General communities will be visible to everyone.</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="moderated" name="moderated" value="1" <?php echo ($isEditing && !empty($community['moderated'])) ? 'checked' : ''; ?>>
                                <span>Enable moderation</span>
                            </label>
                            <small class="form-help">Lets moderators see pending/hidden items and manually hide or approve any listing. Whether new listings start hidden is controlled separately below.</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="hideNewItemsByDefault" name="hide_new_items_by_default" value="1" <?php echo (!$isEditing || (int)($community['hide_new_items_by_default'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                <span>Hide new postings by default</span>
                            </label>
                            <small class="form-help">When moderation is enabled, new listings start Hidden until a moderator approves them. Uncheck to have new listings start Visible even with moderation enabled — moderators can still hide individual items manually using the toggle on each listing.</small>
                        </div>

                        <div class="form-group">
                            <label for="ownerId">Owner ID <span class="required">*</span></label>
                            <input type="text" id="ownerId" name="owner_id" required
                                   placeholder="User ID of the community owner"
                                   value="<?php echo $isEditing ? escape($community['owner_id']) : escape($currentUser['id']); /* nosemgrep: php.lang.security.injection.echoed-request.echoed-request */ ?>"
                                   <?php echo $isAdmin ? '' : 'readonly'; ?>>
                            <?php if (!$isAdmin) : ?>
                            <small class="form-help">Only a site administrator can transfer ownership.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-panel" data-tab="notifications">
                        <div class="form-group">
                            <label for="slackWebhookUrl">Webhook URL (Slack or Discord)</label>
                            <input type="url" id="slackWebhookUrl" name="slack_webhook_url"
                                   placeholder="Slack: https://hooks.slack.com/services/… — or Discord: https://discord.com/api/webhooks/…"
                                   value="<?php echo $isEditing ? escape($community['slack_webhook_url'] ?? '') : ''; /* nosemgrep: php.lang.security.injection.echoed-request.echoed-request */ ?>">
                            <small class="form-help">Enter a single webhook URL for either Slack or Discord — not both. ClaimIt detects which platform the URL belongs to and sends to that one.</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="slackEnabled" name="slack_enabled" value="1" <?php echo ($isEditing && !empty($community['slack_enabled'])) ? 'checked' : ''; ?>>
                                <span>Enable webhook notifications for this community</span>
                            </label>
                            <small class="form-help">When enabled, a message is posted to the Slack or Discord channel above (whichever the URL matches) whenever a new item is posted to this community.</small>
                        </div>

                        <div class="form-group">
                            <button type="button" class="btn btn-secondary" id="testSlackBtn" onclick="testSlackWebhook()">
                                🧪 Send Test Message
                            </button>
                            <small class="form-help">Test your webhook before enabling notifications.</small>
                        </div>

                        <div class="form-group">
                            <label for="discordWebhookUrl">Discord Webhook URL</label>
                            <input type="url" id="discordWebhookUrl" name="discord_webhook_url"
                                   placeholder="https://discord.com/api/webhooks/YOUR/WEBHOOK/URL"
                                   value="<?php echo $isEditing ? escape($community['discord_webhook_url'] ?? '') : ''; /* nosemgrep: php.lang.security.injection.echoed-request.echoed-request */ ?>">
                            <small class="form-help">Enter your Discord incoming webhook URL to receive notifications when items are posted to this community.</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="discordEnabled" name="discord_enabled" value="1" <?php echo ($isEditing && !empty($community['discord_enabled'])) ? 'checked' : ''; ?>>
                                <span>Enable Discord notifications for this community</span>
                            </label>
                            <small class="form-help">When enabled, a message will be posted to Discord whenever a new item is posted to this community.</small>
                        </div>

                        <div class="form-group">
                            <button type="button" class="btn btn-secondary" id="testDiscordBtn" onclick="testDiscordWebhook()">
                                🧪 Send Test Message
                            </button>
                            <small class="form-help">Test your Discord webhook before enabling notifications.</small>
                        </div>
                    </div>

                    <?php if ($isEditing) : ?>
                    <div class="tab-panel" data-tab="moderators">
                        <div class="form-group community-moderators-section" id="communityModeratorsSection">
                            <label>Moderators</label>
                            <small class="form-help">Moderators help manage this community. The owner is implicitly a moderator and cannot be removed here.</small>

                            <div id="moderatorListBody" class="moderator-list">
                                <div class="moderator-list-empty">Loading…</div>
                            </div>

                            <div class="moderator-add-row">
                                <input type="email" id="moderatorEmailInput" placeholder="Enter user's email"
                                       onkeydown="if (event.key === 'Enter') { event.preventDefault(); addCommunityModerator(); }">
                                <button type="button" class="btn btn-secondary" id="addModeratorBtn" onclick="addCommunityModerator()">
                                    ➕ Add moderator
                                </button>
                            </div>
                            <div id="moderatorFormFeedback" class="moderator-form-feedback" style="display: none;"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-actions" id="formActions">
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-text">Save</span>
                            <span class="btn-loading" style="display: none;">Saving...</span>
                        </button>
                        <a href="?page=communities" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.community-edit-container {
    max-width: 700px;
    margin: 0 auto;
}

.action-bar {
    margin-bottom: 1.5rem;
}

.community-edit-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
}

.tab-nav {
    display: flex;
    gap: 0.5rem;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 1.5rem;
    overflow-x: auto;
}

.tab-btn {
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    font-weight: 500;
    color: #666;
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.2s ease, border-color 0.2s ease;
}

.tab-btn:hover {
    color: #333;
}

.tab-btn.active {
    color: #007bff;
    border-bottom-color: #007bff;
}

.tab-panel {
    display: none;
}

.tab-panel.active {
    display: block;
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

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
}

.required {
    color: #dc3545;
}

.form-group input[type="text"],
.form-group input[type="url"],
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1rem;
    font-family: inherit;
    transition: border-color 0.2s ease;
}

.form-group input[type="text"]:focus,
.form-group input[type="url"]:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #007bff;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: normal !important;
}

.checkbox-label input[type="checkbox"] {
    margin-top: 0.25rem;
    cursor: pointer;
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.checkbox-label span {
    flex: 1;
    line-height: 1.5;
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
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.community-moderators-section {
    border-top: 1px solid #e9ecef;
    padding-top: 1rem;
}

.moderator-list {
    margin: 0.75rem 0;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    overflow: hidden;
}

.moderator-list-empty {
    padding: 0.75rem 1rem;
    color: #666;
    font-style: italic;
}

.moderator-list-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #f1f1f1;
    gap: 0.75rem;
}

.moderator-list-row:last-child {
    border-bottom: none;
}

.moderator-list-row .moderator-name {
    font-weight: 500;
}

.moderator-list-row .moderator-email {
    color: #666;
    font-size: 0.875rem;
}

.moderator-add-row {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.moderator-add-row input[type="email"] {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 1rem;
}

.moderator-form-feedback {
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.9rem;
}

.moderator-form-feedback.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.moderator-form-feedback.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

@media (max-width: 768px) {
    .btn {
        width: 100%;
    }

    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
let editingCommunityId = <?php echo $isEditing ? (int)$community['id'] : 'null'; /* nosemgrep: php.lang.security.injection.echoed-request.echoed-request */ ?>;

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.querySelector(`.tab-panel[data-tab="${btn.dataset.tab}"]`).classList.add('active');
        document.getElementById('formActions').style.display = btn.dataset.tab === 'moderators' ? 'none' : 'flex';
    });
});

<?php if ($isEditing) : ?>
document.addEventListener('DOMContentLoaded', function() {
    loadCommunityModerators(editingCommunityId);
});
<?php endif; ?>

document.getElementById('communityForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = this.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');

    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline';

    const formData = new FormData(this);
    const action = editingCommunityId ? 'update' : 'create';

    const body = new URLSearchParams();
    body.append('action', action);
    for (const [key, value] of formData.entries()) {
        body.append(key, value);
    }

    fetch('?page=communities', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message || 'Community saved successfully!', 'success');
            setTimeout(() => { window.location.href = '?page=communities'; }, 1000);
        } else {
            showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error saving community', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    });
});

// Load moderators for the currently-edited community
function loadCommunityModerators(id) {
    const listEl = document.getElementById('moderatorListBody');
    if (!listEl) return;
    listEl.innerHTML = '<div class="moderator-list-empty">Loading…</div>';
    fetch('?page=communities&action=get_moderators&id=' + encodeURIComponent(id))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderModeratorList(data.moderators || []);
            } else {
                listEl.innerHTML = '<div class="moderator-list-empty">Error: ' + escapeHtml(data.message || 'Unknown error') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error loading moderators:', error);
            listEl.innerHTML = '<div class="moderator-list-empty">Error loading moderators</div>';
        });
}

function renderModeratorList(moderators) {
    const listEl = document.getElementById('moderatorListBody');
    if (!listEl) return;
    if (!moderators.length) {
        listEl.innerHTML = '<div class="moderator-list-empty">No moderators yet.</div>';
        return;
    }
    const rows = moderators.map(a => {
        const name = a.display_name || a.name || a.email || a.id;
        return '<div class="moderator-list-row">'
            + '<div>'
            + '<div class="moderator-name">' + escapeHtml(name) + '</div>'
            + '<div class="moderator-email">' + escapeHtml(a.email || '') + '</div>'
            + '</div>'
            + '<button type="button" class="btn-icon btn-delete moderator-remove-btn" title="Remove" data-user-id="' + escapeHtml(a.id) + '">🗑️</button>'
            + '</div>';
    }).join('');
    listEl.innerHTML = rows;
    listEl.querySelectorAll('.moderator-remove-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            removeCommunityModerator(this.getAttribute('data-user-id'));
        });
    });
}

function setModeratorFeedback(message, type) {
    const el = document.getElementById('moderatorFormFeedback');
    if (!el) return;
    if (!message) {
        el.style.display = 'none';
        el.textContent = '';
        el.className = 'moderator-form-feedback';
        return;
    }
    el.textContent = message;
    el.className = 'moderator-form-feedback ' + (type || 'error');
    el.style.display = '';
}

function addCommunityModerator() {
    setModeratorFeedback('', null);
    if (!editingCommunityId) {
        setModeratorFeedback('Save the community first before adding moderators.', 'error');
        return;
    }
    const emailInput = document.getElementById('moderatorEmailInput');
    const email = emailInput.value.trim();
    if (!email) {
        setModeratorFeedback('Enter an email address.', 'error');
        emailInput.focus();
        return;
    }
    const btn = document.getElementById('addModeratorBtn');
    btn.disabled = true;
    const body = new URLSearchParams();
    body.append('action', 'add_moderator');
    body.append('id', editingCommunityId);
    body.append('email', email);
    fetch('?page=communities', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Non-JSON response from add_moderator:', text);
            throw new Error('Server returned an unexpected response');
        }
        if (data.success) {
            emailInput.value = '';
            renderModeratorList(data.moderators || []);
            setModeratorFeedback(data.message || 'Moderator added', 'success');
            showMessage(data.message || 'Moderator added', 'success');
        } else {
            setModeratorFeedback(data.message || 'User not found', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding moderator:', error);
        setModeratorFeedback(error.message || 'Error adding moderator', 'error');
    })
    .finally(() => {
        btn.disabled = false;
    });
}

function removeCommunityModerator(userId) {
    if (!editingCommunityId) return;
    if (!confirm('Remove this moderator from the community?')) return;
    const body = new URLSearchParams();
    body.append('action', 'remove_moderator');
    body.append('id', editingCommunityId);
    body.append('user_id', userId);
    fetch('?page=communities', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderModeratorList(data.moderators || []);
            showMessage(data.message || 'Moderator removed', 'success');
        } else {
            showMessage(data.message || 'Failed to remove moderator', 'error');
        }
    })
    .catch(error => {
        console.error('Error removing moderator:', error);
        showMessage('Error removing moderator', 'error');
    });
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function(c) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
}

function showMessage(message, type = 'info') {
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
        z-index: 10001;
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

    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.parentNode.removeChild(messageDiv);
        }
    }, 5000);
}

// Test the Slack-or-Discord webhook field (detects which platform the URL belongs to)
function testSlackWebhook() {
    const webhookUrl = document.getElementById('slackWebhookUrl').value.trim();

    if (!webhookUrl) {
        showMessage('Please enter a webhook URL first', 'error');
        return;
    }

    const isSlack = webhookUrl.startsWith('https://hooks.slack.com/services/');
    const isDiscord = /^https:\/\/(www\.)?discord(app)?\.com\/api\/webhooks\//i.test(webhookUrl);
    if (!isSlack && !isDiscord) {
        showMessage('Invalid webhook URL. Use Slack (hooks.slack.com/services/…) or Discord (discord.com/api/webhooks/…).', 'error');
        return;
    }

    const testBtn = document.getElementById('testSlackBtn');
    const originalText = testBtn.innerHTML;
    testBtn.disabled = true;
    testBtn.innerHTML = '⏳ Sending...';

    fetch('?page=communities&action=test_slack', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'webhook_url=' + encodeURIComponent(webhookUrl)
    })
    .then(response => response.text().then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Server returned invalid JSON: ' + text.substring(0, 200));
        }
    }))
    .then(data => {
        if (data.success) {
            showMessage(data.message || 'Test message sent successfully!', 'success');
        } else {
            showMessage('Test failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error sending test message: ' + error.message, 'error');
    })
    .finally(() => {
        testBtn.disabled = false;
        testBtn.innerHTML = originalText;
    });
}

// Test Discord webhook
function testDiscordWebhook() {
    const webhookUrl = document.getElementById('discordWebhookUrl').value.trim();

    if (!webhookUrl) {
        showMessage('Please enter a Discord webhook URL first', 'error');
        return;
    }

    if (!webhookUrl.match(/^https:\/\/discord(?:app)?\.com\/api\/webhooks\//)) {
        showMessage('Invalid Discord webhook URL format. It should start with https://discord.com/api/webhooks/', 'error');
        return;
    }

    const testBtn = document.getElementById('testDiscordBtn');
    const originalText = testBtn.innerHTML;
    testBtn.disabled = true;
    testBtn.innerHTML = '⏳ Sending...';

    fetch('?page=communities&action=test_discord', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'webhook_url=' + encodeURIComponent(webhookUrl)
    })
    .then(response => response.text().then(text => {
        try { return JSON.parse(text); }
        catch (e) { throw new Error('Server returned invalid JSON: ' + text.substring(0, 200)); }
    }))
    .then(data => {
        if (data.success) {
            showMessage('Test message sent successfully! Check your Discord channel.', 'success');
        } else {
            showMessage('Test failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showMessage('Error sending test message: ' + error.message, 'error');
    })
    .finally(() => {
        testBtn.disabled = false;
        testBtn.innerHTML = originalText;
    });
}
</script>
