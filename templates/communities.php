<?php

/**
 * Communities page template - Public view, admin controls
 */

// Get current user (may be null for logged out users)
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$isAdmin = isAdmin();

// Communities the current user owns (for showing the Edit affordance to non-site-admin owners)
$ownedCommunityIds = [];
if ($currentUser) {
    foreach (getCommunitiesOwnedByUser($currentUser['id']) as $owned) {
        $ownedCommunityIds[] = (int)$owned['id'];
    }
}
$canManageAny = $isAdmin || !empty($ownedCommunityIds);

// Get all communities
$allCommunities = getAllCommunities();

// Filter out private communities for non-admins, but always show ones the current user owns
$communities = [];
foreach ($allCommunities as $community) {
    $userOwnsThis = $currentUser && in_array((int)$community['id'], $ownedCommunityIds, true);
    if ($isAdmin || empty($community['private']) || $userOwnsThis) {
        $communities[] = $community;
    }
}

// Get flash message if any
$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>🏘️ Communities</h1>
        <p class="page-subtitle">Browse local communities</p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="communities-container">
            <!-- Admin Controls (only visible to administrators) -->
            <?php if ($isAdmin): ?>
            <div class="action-bar">
                <button id="addCommunityBtn" class="btn btn-primary">
                    ➕ Add New Community
                </button>
                <a href="?page=admin" class="btn btn-secondary">← Back to Admin</a>
            </div>
            <?php endif; ?>

            <!-- Communities Table -->
            <div class="table-container">
                <table class="communities-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Short Name</th>
                            <th>Full Name</th>
                            <th>Description</th>
                            <th>Members</th>
                            <th>Owner</th>
                            <th>Created</th>
                            <?php if ($currentUser): ?>
                            <th>Membership</th>
                            <?php endif; ?>
                            <?php if ($canManageAny): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($communities)): ?>
                            <tr>
                                <?php
                                $colspan = 7; // Base columns (ID, Short Name, Full Name, Description, Members, Owner, Created)
                                if ($currentUser) {
                                    $colspan++; // Add membership column
                                }
                                if ($canManageAny) {
                                    $colspan++; // Add actions column
                                }
                                ?>
                                <td colspan="<?php echo $colspan; ?>" class="no-data">
                                    <?php echo $isAdmin ? 'No communities found. Create one to get started!' : 'No communities available yet.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($communities as $community): ?>
                                <?php 
                                $memberCount = getCommunityMemberCount($community['id']);
                                $isMember = $currentUser ? isUserInCommunity($currentUser['id'], $community['id']) : false;
                                ?>
                                <tr>
                                    <td><?php echo escape($community['id']); ?></td>
                                    <td class="short-name"><?php echo escape($community['short_name']); ?></td>
                                    <td>
                                        <a href="/?page=community&id=<?php echo escape($community['id']); ?>" class="community-link">
                                            <?php echo escape($community['full_name']); ?>
                                        </a>
                                    </td>
                                    <td class="description-cell">
                                        <?php 
                                        $desc = $community['description'] ?? '';
                                        echo escape(strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc); 
                                        ?>
                                    </td>
                                    <td class="member-count"><?php echo escape($memberCount); ?></td>
                                    <td class="owner-cell">
                                        <?php if ($community['owner_name']): ?>
                                            <a href="/?page=user-listings&id=<?php echo escape($community['owner_id']); ?>" class="owner-link">
                                                <?php echo escape($community['owner_name']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="owner-unknown">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="date-cell">
                                        <?php 
                                        if ($community['created_at']) {
                                            $date = new DateTime($community['created_at']);
                                            echo escape($date->format('M j, Y'));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <?php if ($currentUser): ?>
                                    <td class="membership-cell">
                                        <button class="btn-mini <?php echo $isMember ? 'btn-secondary' : 'btn-primary'; ?>" 
                                                onclick="toggleMembershipFromList(<?php echo $community['id']; ?>, <?php echo $isMember ? 'true' : 'false'; ?>, this)">
                                            <?php echo $isMember ? 'Leave' : 'Join'; ?>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($canManageAny): ?>
                                    <td class="actions-cell">
                                        <?php $rowOwned = in_array((int)$community['id'], $ownedCommunityIds, true); ?>
                                        <?php if ($isAdmin || $rowOwned): ?>
                                            <button class="btn-icon btn-edit" onclick="editCommunity(<?php echo escape($community['id']); ?>)" title="Edit">
                                                ✏️
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?>
                                            <button class="btn-icon btn-delete" onclick="deleteCommunity(<?php echo escape($community['id']); ?>)" title="Delete">
                                                🗑️
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Community Modal -->
<div id="communityModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Community</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="communityForm">
            <input type="hidden" id="communityId" name="id">
            
            <div class="form-group">
                <label for="shortName">Short Name <span class="required">*</span></label>
                <input type="text" id="shortName" name="short_name" required maxlength="50" 
                       placeholder="e.g., downtown">
                <small class="form-help">Unique identifier (lowercase, no spaces)</small>
            </div>

            <div class="form-group">
                <label for="fullName">Full Name <span class="required">*</span></label>
                <input type="text" id="fullName" name="full_name" required maxlength="255" 
                       placeholder="e.g., Downtown Neighborhood">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" 
                          placeholder="Describe this community..."></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="private" name="private" value="1">
                    <span>Make this community private, meaning a user must be a member of the community to see the items listed</span>
                </label>
                <small class="form-help">Private communities are only visible to members. Items in both private and General communities will be visible to everyone.</small>
            </div>

            <div class="form-group">
                <label for="slackWebhookUrl">Slack or Discord webhook URL</label>
                <input type="url" id="slackWebhookUrl" name="slack_webhook_url"
                       placeholder="Slack: https://hooks.slack.com/services/… or Discord: https://discord.com/api/webhooks/…">
                <small class="form-help">Slack incoming webhook or Discord channel webhook. ClaimIt sends the correct format for each when new items are posted.</small>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="slackEnabled" name="slack_enabled" value="1">
                    <span>Enable webhook notifications for this community</span>
                </label>
                <small class="form-help">When enabled, a message is posted to your Slack or Discord channel whenever a new item is posted to this community.</small>
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
                       placeholder="https://discord.com/api/webhooks/YOUR/WEBHOOK/URL">
                <small class="form-help">Enter your Discord incoming webhook URL to receive notifications when items are posted to this community.</small>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="discordEnabled" name="discord_enabled" value="1">
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

            <div class="form-group">
                <label for="ownerId">Owner ID <span class="required">*</span></label>
                <input type="text" id="ownerId" name="owner_id" required
                       placeholder="User ID of the community owner"
                       <?php echo $isAdmin ? '' : 'readonly'; ?>>
                <?php if (!$isAdmin): ?>
                <small class="form-help">Only a site administrator can transfer ownership.</small>
                <?php endif; ?>
            </div>

            <div class="form-group community-admins-section" id="communityAdminsSection" style="display: none;">
                <label>Administrators</label>
                <small class="form-help">Administrators help manage this community. The owner is implicitly an administrator and cannot be removed here.</small>

                <div id="adminListBody" class="admin-list">
                    <div class="admin-list-empty">Loading…</div>
                </div>

                <div class="admin-add-row">
                    <input type="email" id="adminEmailInput" placeholder="Enter user's email"
                           onkeydown="if (event.key === 'Enter') { event.preventDefault(); addCommunityAdmin(); }">
                    <button type="button" class="btn btn-secondary" id="addAdminBtn" onclick="addCommunityAdmin()">
                        ➕ Add administrator
                    </button>
                </div>
                <div id="adminFormFeedback" class="admin-form-feedback" style="display: none;"></div>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="btn-text">Save</span>
                    <span class="btn-loading" style="display: none;">Saving...</span>
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.communities-container {
    max-width: 1400px;
    margin: 0 auto;
}

.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
}

.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
}

.communities-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.communities-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.communities-table th {
    padding: 1rem 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #495057;
    white-space: nowrap;
}

.communities-table td {
    padding: 0.875rem 0.75rem;
    border-bottom: 1px solid #f1f3f4;
    color: #333;
}

.communities-table tbody tr:hover {
    background: #f8f9fa;
}

.communities-table tbody tr:last-child td {
    border-bottom: none;
}

.short-name {
    font-family: 'Courier New', monospace;
    background: #f8f9fa;
    font-weight: 600;
}

.description-cell {
    max-width: 300px;
    color: #666;
    font-size: 0.85rem;
}

.date-cell {
    color: #666;
    font-size: 0.85rem;
    white-space: nowrap;
}

.actions-cell {
    white-space: nowrap;
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

.btn-icon {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.25rem;
    transition: transform 0.2s ease;
    min-width: auto;
}

.btn-icon:hover {
    transform: scale(1.2);
}

.btn-edit {
    margin-right: 0.5rem;
}

.btn-mini {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 60px;
}

.community-link, .owner-link {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
}

.community-link:hover, .owner-link:hover {
    text-decoration: underline;
}

.owner-cell {
    color: #666;
}

.owner-unknown {
    color: #999;
    font-style: italic;
}

.member-count {
    text-align: center;
    font-weight: 600;
    color: #666;
}

.membership-cell {
    text-align: center;
}

.no-data {
    text-align: center;
    color: #999;
    padding: 2rem !important;
    font-style: italic;
}

/* Modal Styles */
#communityModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

#communityModal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #333;
}

.modal-close {
    background: none;
    border: none;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 32px;
    height: 32px;
}

.modal-close:hover {
    color: #333;
}

#communityForm {
    padding: 1.5rem;
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

.modal-actions {
    display: flex;
    gap: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.community-admins-section {
    border-top: 1px solid #e9ecef;
    padding-top: 1rem;
}

.admin-list {
    margin: 0.75rem 0;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    overflow: hidden;
}

.admin-list-empty {
    padding: 0.75rem 1rem;
    color: #666;
    font-style: italic;
}

.admin-list-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #f1f1f1;
    gap: 0.75rem;
}

.admin-list-row:last-child {
    border-bottom: none;
}

.admin-list-row .admin-name {
    font-weight: 500;
}

.admin-list-row .admin-email {
    color: #666;
    font-size: 0.875rem;
}

.admin-add-row {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.admin-add-row input[type="email"] {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 1rem;
}

.admin-form-feedback {
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.9rem;
}

.admin-form-feedback.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.admin-form-feedback.success {
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
    .action-bar {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .communities-table {
        font-size: 0.8rem;
    }
    
    .communities-table th,
    .communities-table td {
        padding: 0.5rem;
    }
}
</style>

<script>
let editingCommunityId = null;

// Edit community
function editCommunity(id) {
    fetch('?page=communities&action=get&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const community = data.community;
                editingCommunityId = id;
                document.getElementById('modalTitle').textContent = 'Edit Community';
                document.getElementById('communityId').value = community.id;
                document.getElementById('shortName').value = community.short_name;
                document.getElementById('fullName').value = community.full_name;
                document.getElementById('description').value = community.description || '';
                document.getElementById('private').checked = community.private == 1 || community.private === true;
                document.getElementById('slackWebhookUrl').value = community.slack_webhook_url || '';
                document.getElementById('slackEnabled').checked = community.slack_enabled == 1 || community.slack_enabled === true;
                document.getElementById('discordWebhookUrl').value = community.discord_webhook_url || '';
                document.getElementById('discordEnabled').checked = community.discord_enabled == 1 || community.discord_enabled === true;
                document.getElementById('ownerId').value = community.owner_id;

                // Show the administrators section when editing
                const adminsSection = document.getElementById('communityAdminsSection');
                if (adminsSection) {
                    adminsSection.style.display = '';
                    setAdminFeedback('', null);
                    loadCommunityAdmins(id);
                }

                document.getElementById('communityModal').classList.add('show');
            } else {
                showMessage('Error loading community: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading community', 'error');
        });
}

// Load administrators for the currently-edited community
function loadCommunityAdmins(id) {
    const listEl = document.getElementById('adminListBody');
    if (!listEl) return;
    listEl.innerHTML = '<div class="admin-list-empty">Loading…</div>';
    fetch('?page=communities&action=get_admins&id=' + encodeURIComponent(id))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderAdminList(data.administrators || []);
            } else {
                listEl.innerHTML = '<div class="admin-list-empty">Error: ' + escapeHtml(data.message || 'Unknown error') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error loading admins:', error);
            listEl.innerHTML = '<div class="admin-list-empty">Error loading administrators</div>';
        });
}

function renderAdminList(admins) {
    const listEl = document.getElementById('adminListBody');
    if (!listEl) return;
    if (!admins.length) {
        listEl.innerHTML = '<div class="admin-list-empty">No administrators yet.</div>';
        return;
    }
    const rows = admins.map(a => {
        const name = a.display_name || a.name || a.email || a.id;
        return '<div class="admin-list-row">'
            + '<div>'
            + '<div class="admin-name">' + escapeHtml(name) + '</div>'
            + '<div class="admin-email">' + escapeHtml(a.email || '') + '</div>'
            + '</div>'
            + '<button type="button" class="btn-icon btn-delete admin-remove-btn" title="Remove" data-user-id="' + escapeHtml(a.id) + '">🗑️</button>'
            + '</div>';
    }).join('');
    listEl.innerHTML = rows;
    listEl.querySelectorAll('.admin-remove-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            removeCommunityAdmin(this.getAttribute('data-user-id'));
        });
    });
}

function setAdminFeedback(message, type) {
    const el = document.getElementById('adminFormFeedback');
    if (!el) return;
    if (!message) {
        el.style.display = 'none';
        el.textContent = '';
        el.className = 'admin-form-feedback';
        return;
    }
    el.textContent = message;
    el.className = 'admin-form-feedback ' + (type || 'error');
    el.style.display = '';
}

function addCommunityAdmin() {
    setAdminFeedback('', null);
    if (!editingCommunityId) {
        setAdminFeedback('Save the community first before adding administrators.', 'error');
        return;
    }
    const emailInput = document.getElementById('adminEmailInput');
    const email = emailInput.value.trim();
    if (!email) {
        setAdminFeedback('Enter an email address.', 'error');
        emailInput.focus();
        return;
    }
    const btn = document.getElementById('addAdminBtn');
    btn.disabled = true;
    const body = new URLSearchParams();
    body.append('action', 'add_admin');
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
            console.error('Non-JSON response from add_admin:', text);
            throw new Error('Server returned an unexpected response');
        }
        if (data.success) {
            emailInput.value = '';
            renderAdminList(data.administrators || []);
            setAdminFeedback(data.message || 'Administrator added', 'success');
            showMessage(data.message || 'Administrator added', 'success');
        } else {
            setAdminFeedback(data.message || 'User not found', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding admin:', error);
        setAdminFeedback(error.message || 'Error adding administrator', 'error');
    })
    .finally(() => {
        btn.disabled = false;
    });
}

function removeCommunityAdmin(userId) {
    if (!editingCommunityId) return;
    if (!confirm('Remove this administrator from the community?')) return;
    const body = new URLSearchParams();
    body.append('action', 'remove_admin');
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
            renderAdminList(data.administrators || []);
            showMessage(data.message || 'Administrator removed', 'success');
        } else {
            showMessage(data.message || 'Failed to remove administrator', 'error');
        }
    })
    .catch(error => {
        console.error('Error removing admin:', error);
        showMessage('Error removing administrator', 'error');
    });
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function(c) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
}

// Delete community
function deleteCommunity(id) {
    if (!confirm('Are you sure you want to delete this community? This action cannot be undone.')) {
        return;
    }
    
    fetch('?page=communities', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Community deleted successfully!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error deleting community', 'error');
    });
}

// Close modal
function closeModal() {
    document.getElementById('communityModal').classList.remove('show');
    document.getElementById('communityForm').reset();
    const adminsSection = document.getElementById('communityAdminsSection');
    if (adminsSection) {
        adminsSection.style.display = 'none';
    }
    setAdminFeedback('', null);
    editingCommunityId = null;
}

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add new community button (only present for site admins)
    const addBtn = document.getElementById('addCommunityBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            editingCommunityId = null;
            document.getElementById('modalTitle').textContent = 'Add New Community';
            document.getElementById('communityForm').reset();
            document.getElementById('communityId').value = '';
            // Admins section only applies to existing communities
            const adminsSection = document.getElementById('communityAdminsSection');
            if (adminsSection) {
                adminsSection.style.display = 'none';
            }
            // Default owner to current user
            <?php if ($currentUser): ?>
            document.getElementById('ownerId').value = '<?php echo escape($currentUser['id']); ?>';
            <?php endif; ?>
            document.getElementById('communityModal').classList.add('show');
        });
    }

    // Submit form
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
                closeModal();
                setTimeout(() => window.location.reload(), 1000);
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

    // Close modal when clicking outside
    document.getElementById('communityModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
});

// Toggle membership from the communities list
function toggleMembershipFromList(communityId, isMember, button) {
    const action = isMember ? 'leave' : 'join';
    const originalText = button.textContent;
    
    button.disabled = true;
    button.textContent = isMember ? 'Leaving...' : 'Joining...';
    
    fetch('/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=' + encodeURIComponent(action) + '&community_id=' + encodeURIComponent(communityId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update button state
            button.textContent = isMember ? 'Join' : 'Leave';
            button.classList.toggle('btn-primary');
            button.classList.toggle('btn-secondary');
            button.onclick = function() {
                toggleMembershipFromList(communityId, !isMember, this);
            };
            button.disabled = false;
            
            // Update member count in the row
            const row = button.closest('tr');
            const memberCountCell = row.querySelector('.member-count');
            if (memberCountCell) {
                const currentCount = parseInt(memberCountCell.textContent) || 0;
                memberCountCell.textContent = isMember ? currentCount - 1 : currentCount + 1;
            }
            
            showMessage(data.message || (isMember ? 'Left community' : 'Joined community'), 'success');
        } else {
            showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
            button.disabled = false;
            button.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error updating membership', 'error');
        button.disabled = false;
        button.textContent = originalText;
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

// Test Slack or Discord webhook
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
    
    // Send test request to server
    fetch('?page=communities&action=test_slack', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'webhook_url=' + encodeURIComponent(webhookUrl)
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', e);
                throw new Error('Server returned invalid JSON: ' + text.substring(0, 200));
            }
        });
    })
    .then(data => {
        console.log('Parsed data:', data);
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

