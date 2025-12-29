<?php

/**
 * Communities page template - Public view, admin controls
 */

// Get current user (may be null for logged out users)
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$isAdmin = isAdmin();

// Get all communities
$allCommunities = getAllCommunities();

// Filter out private communities for non-admins
$communities = [];
foreach ($allCommunities as $community) {
    // Show all communities to admins, hide private ones from non-admins
    if ($isAdmin || empty($community['private'])) {
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
                            <?php if ($isAdmin): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($communities)): ?>
                            <tr>
                                <?php 
                                $colspan = 7; // Base columns (ID, Short Name, Full Name, Description, Members, Owner, Created)
                                if ($currentUser) $colspan++; // Add membership column
                                if ($isAdmin) $colspan++; // Add actions column
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
                                    <?php if ($isAdmin): ?>
                                    <td class="actions-cell">
                                        <button class="btn-icon btn-edit" onclick="editCommunity(<?php echo escape($community['id']); ?>)" title="Edit">
                                            ✏️
                                        </button>
                                        <button class="btn-icon btn-delete" onclick="deleteCommunity(<?php echo escape($community['id']); ?>)" title="Delete">
                                            🗑️
                                        </button>
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
                <label for="ownerId">Owner ID <span class="required">*</span></label>
                <input type="text" id="ownerId" name="owner_id" required 
                       placeholder="User ID of the community owner">
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
                document.getElementById('ownerId').value = community.owner_id;
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
    editingCommunityId = null;
}

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add new community button
    document.getElementById('addCommunityBtn').addEventListener('click', function() {
        editingCommunityId = null;
        document.getElementById('modalTitle').textContent = 'Add New Community';
        document.getElementById('communityForm').reset();
        document.getElementById('communityId').value = '';
        // Default owner to current user
        <?php if ($currentUser): ?>
        document.getElementById('ownerId').value = '<?php echo escape($currentUser['id']); ?>';
        <?php endif; ?>
        document.getElementById('communityModal').classList.add('show');
    });

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
</script>

