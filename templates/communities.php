<?php

/**
 * Communities management page template
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

// Get all communities
$communities = getAllCommunities();

// Get flash message if any
$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>🏘️ Community Management</h1>
        <p class="page-subtitle">Create and manage communities</p>
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
            <!-- Add New Community Button -->
            <div class="action-bar">
                <button id="addCommunityBtn" class="btn btn-primary">
                    ➕ Add New Community
                </button>
                <a href="?page=admin" class="btn btn-secondary">← Back to Admin</a>
            </div>

            <!-- Communities Table -->
            <div class="table-container">
                <table class="communities-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Short Name</th>
                            <th>Full Name</th>
                            <th>Description</th>
                            <th>Owner</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($communities)): ?>
                            <tr>
                                <td colspan="7" class="no-data">No communities found. Create one to get started!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($communities as $community): ?>
                                <tr>
                                    <td><?php echo escape($community['id']); ?></td>
                                    <td class="short-name"><?php echo escape($community['short_name']); ?></td>
                                    <td><?php echo escape($community['full_name']); ?></td>
                                    <td class="description-cell">
                                        <?php 
                                        $desc = $community['description'] ?? '';
                                        echo escape(strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc); 
                                        ?>
                                    </td>
                                    <td><?php echo escape($community['owner_id']); ?></td>
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
                                    <td class="actions-cell">
                                        <button class="btn-icon btn-edit" onclick="editCommunity(<?php echo escape($community['id']); ?>)" title="Edit">
                                            ✏️
                                        </button>
                                        <button class="btn-icon btn-delete" onclick="deleteCommunity(<?php echo escape($community['id']); ?>)" title="Delete">
                                            🗑️
                                        </button>
                                    </td>
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
<div id="communityModal" class="modal" style="display: none;">
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

.no-data {
    text-align: center;
    color: #999;
    padding: 2rem !important;
    font-style: italic;
}

/* Modal Styles */
#communityModal {
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

// Add new community
document.getElementById('addCommunityBtn').addEventListener('click', function() {
    editingCommunityId = null;
    document.getElementById('modalTitle').textContent = 'Add New Community';
    document.getElementById('communityForm').reset();
    document.getElementById('communityId').value = '';
    document.getElementById('communityModal').classList.add('show');
});

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
    
    fetch('?page=communities&action=delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id
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

