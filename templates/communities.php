<?php

/**
 * Communities page template - Public view, admin controls
 */

// Get current user (may be null for logged out users)
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$isAdmin = isAdmin();

// Communities the current user owns or moderates (for showing the Edit affordance to non-site-admins)
$manageableCommunityIds = [];
if ($currentUser) {
    foreach (getCommunitiesOwnedByUser($currentUser['id']) as $owned) {
        $manageableCommunityIds[] = (int)$owned['id'];
    }
    foreach (getCommunitiesModeratedByUser($currentUser['id']) as $moderated) {
        $manageableCommunityIds[] = (int)$moderated['id'];
    }
    $manageableCommunityIds = array_unique($manageableCommunityIds);
}
$canManageAny = $isAdmin || !empty($manageableCommunityIds);

// Get all communities
$allCommunities = getAllCommunities();

// Filter out private communities for non-admins, but always show ones the current user can manage
$communities = [];
foreach ($allCommunities as $community) {
    $userCanManageThis = $currentUser && in_array((int)$community['id'], $manageableCommunityIds, true);
    if ($isAdmin || empty($community['private']) || $userCanManageThis) {
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
            <?php if ($isAdmin) : ?>
            <div class="action-bar">
                <a href="?page=community-edit" class="btn btn-primary">
                    ➕ Add New Community
                </a>
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
                            <th>Items</th>
                            <th>Owner</th>
                            <th>Created</th>
                            <?php if ($currentUser) : ?>
                            <th>Membership</th>
                            <?php endif; ?>
                            <?php if ($canManageAny) : ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($communities)) : ?>
                            <tr>
                                <?php
                                $colspan = 8; // Base columns (ID, Short Name, Full Name, Description, Members, Items, Owner, Created)
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
                        <?php else : ?>
                            <?php foreach ($communities as $community) : ?>
                                <?php
                                $memberCount = getCommunityMemberCount($community['id']);
                                $itemCount = getCommunityItemCount($community['id']);
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
                                    <td class="item-count">
                                        <?php echo escape($itemCount['total']); ?>
                                        <?php if ($itemCount['hidden'] > 0) : ?>
                                            <span class="hidden-count">(<?php echo escape($itemCount['hidden']); ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="owner-cell">
                                        <?php if ($community['owner_name']) : ?>
                                            <a href="/?page=user-listings&id=<?php echo escape($community['owner_id']); ?>" class="owner-link">
                                                <?php echo escape($community['owner_name']); ?>
                                            </a>
                                        <?php else : ?>
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
                                    <?php if ($currentUser) : ?>
                                    <td class="membership-cell">
                                        <button class="btn-mini <?php echo $isMember ? 'btn-secondary' : 'btn-primary'; ?>" 
                                                onclick="toggleMembershipFromList(<?php echo $community['id']; ?>, <?php echo $isMember ? 'true' : 'false'; ?>, this)">
                                            <?php echo $isMember ? 'Leave' : 'Join'; ?>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($canManageAny) : ?>
                                    <td class="actions-cell">
                                        <?php $rowManageable = in_array((int)$community['id'], $manageableCommunityIds, true); ?>
                                        <?php if ($isAdmin || $rowManageable) : ?>
                                            <a class="btn-icon btn-edit" href="?page=community-edit&id=<?php echo escape($community['id']); ?>" title="Edit">
                                                ✏️
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($isAdmin) : ?>
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

.member-count,
.item-count {
    text-align: center;
    font-weight: 600;
    color: #666;
}

.item-count .hidden-count {
    font-weight: 600;
    color: #dc3545;
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

