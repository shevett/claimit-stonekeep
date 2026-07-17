<?php

/**
 * Admin user management page template
 */

if (!isLoggedIn()) {
    redirect('login');
}

if (!isAdmin()) {
    setFlashMessage('You do not have permission to access this page', 'error');
    redirect('home');
}

$allUsers = getAllUsers();
$userCommunities = getAllUserCommunityMemberships();
$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>👥 User Management</h1>
        <p class="page-subtitle">All registered users and their information</p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="action-bar">
            <a href="?page=admin" class="btn btn-secondary">← Back to Admin</a>
        </div>

        <div class="user-management-section">
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-col="0">Name / Email <span class="sort-indicator"></span></th>
                            <th class="sortable" data-col="1">Display Name <span class="sort-indicator"></span></th>
                            <th class="sortable" data-col="2">Last Login <span class="sort-indicator"></span></th>
                            <th class="sortable" data-col="3">Created <span class="sort-indicator"></span></th>
                            <th class="sortable" data-col="4">Notifications <span class="sort-indicator"></span></th>
                            <th class="sortable" data-col="5">Communities <span class="sort-indicator"></span></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allUsers)) : ?>
                            <tr>
                                <td colspan="7" class="no-data">No users found</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($allUsers as $user) : ?>
                                <tr>
                                    <td class="user-name">
                                        <?php if (!empty($user['picture'])) : ?>
                                            <img src="<?php echo escape($user['picture']); ?>" alt="" class="user-avatar">
                                        <?php endif; ?>
                                        <div>
                                            <div class="user-name-line">
                                                <span><?php echo escape($user['name']); ?></span>
                                                <?php
                                                // Check if user is super admin (defined in config)
                                                $isSuperAdmin = defined('ADMIN_USER_ID') && $user['id'] === ADMIN_USER_ID;
                                                $isAdmin = isset($user['is_admin']) && $user['is_admin'];

                                                if ($isSuperAdmin) : ?>
                                                    <span class="badge badge-super-admin">SUPER</span>
                                                <?php elseif ($isAdmin) : ?>
                                                    <span class="badge badge-admin">Admin</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-email-line"><?php echo escape($user['email']); ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo escape($user['display_name'] ?? '-'); ?></td>
                                    <td class="date-cell" data-sort-value="<?php echo $user['last_login'] ? strtotime($user['last_login']) : 0; ?>">
                                        <?php
                                        if ($user['last_login']) {
                                            $date = new DateTime($user['last_login']);
                                            echo escape($date->format('M j, Y g:i A'));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="date-cell" data-sort-value="<?php echo $user['created_at'] ? strtotime($user['created_at']) : 0; ?>">
                                        <?php
                                        if ($user['created_at']) {
                                            $date = new DateTime($user['created_at']);
                                            echo escape($date->format('M j, Y'));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="notifications-cell">
                                        <?php
                                        $notifications = [];
                                        if (isset($user['email_notifications']) && $user['email_notifications']) {
                                            $notifications[] = 'Email';
                                        }
                                        if (isset($user['new_listing_notifications']) && $user['new_listing_notifications']) {
                                            $notifications[] = 'Listings';
                                        }
                                        echo !empty($notifications) ? escape(implode(', ', $notifications)) : '-';
                                        ?>
                                    </td>
                                    <td class="communities-cell">
                                        <?php
                                        $communities = $userCommunities[$user['id']] ?? [];
                                        echo !empty($communities) ? escape(implode(', ', $communities)) : '-';
                                        ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="btn-icon btn-edit" onclick="editUser('<?php echo escape($user['id']); ?>')" title="Edit User">
                                            ✏️
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="user-stats">
                <strong>Total Users:</strong> <?php echo count($allUsers); ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit User</h2>
            <button class="modal-close" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form id="editUserForm">
            <input type="hidden" id="editUserId" name="user_id">

            <div class="form-group">
                <label for="editUserName">Name</label>
                <input type="text" id="editUserName" name="name" readonly disabled class="readonly-field">
                <small class="form-help">Name from Google account (read-only)</small>
            </div>

            <div class="form-group">
                <label for="editUserEmail">Email</label>
                <input type="email" id="editUserEmail" name="email" readonly disabled class="readonly-field">
                <small class="form-help">Email from Google account (read-only)</small>
            </div>

            <div class="form-group">
                <label for="editDisplayName">Display Name</label>
                <input type="text" id="editDisplayName" name="display_name" maxlength="100">
                <small class="form-help">Optional display name shown to other users</small>
            </div>

            <div class="form-group">
                <label for="editZipcode">Zipcode</label>
                <input type="text" id="editZipcode" name="zipcode" maxlength="10">
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="editIsAdmin" name="is_admin" value="1">
                    <span>Administrator privileges</span>
                </label>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="editEmailNotifications" name="email_notifications" value="1">
                    <span>Email notifications enabled</span>
                </label>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="editNewListingNotifications" name="new_listing_notifications" value="1">
                    <span>New listing notifications enabled</span>
                </label>
            </div>

            <div class="form-group">
                <label>Community Memberships</label>
                <small class="form-help" style="display: block; margin-bottom: 0.5rem;">Select which communities this user is subscribed to</small>
                <div id="editUserCommunities" class="community-checkboxes">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="btn-text">Save Changes</span>
                    <span class="btn-loading" style="display: none;">Saving...</span>
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.action-bar {
    max-width: 1200px;
    margin: 0 auto 1.5rem;
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

.user-management-section {
    max-width: 1200px;
    margin: 0 auto;
}

.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
}

.user-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.user-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.user-table th {
    padding: 1rem 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #495057;
    white-space: nowrap;
}

.user-table td {
    padding: 0.875rem 0.75rem;
    border-bottom: 1px solid #f1f3f4;
    color: #333;
}

.user-table tbody tr:hover {
    background: #f8f9fa;
}

.user-table tbody tr:last-child td {
    border-bottom: none;
}

.user-name {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.user-name-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.user-email-line {
    color: #666;
    font-size: 0.85rem;
    font-weight: normal;
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-super-admin {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 700;
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
}

.badge-admin {
    background: #ffeaa7;
    color: #856404;
}

.date-cell {
    color: #666;
    font-size: 0.85rem;
    white-space: nowrap;
}

.notifications-cell,
.communities-cell {
    color: #666;
    font-size: 0.85rem;
}

.no-data {
    text-align: center;
    color: #999;
    padding: 2rem !important;
    font-style: italic;
}

.user-stats {
    padding: 1rem;
    background: white;
    border-radius: 0 0 12px 12px;
    border-top: 1px solid #e9ecef;
    color: #666;
}

.btn-icon {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.25rem;
    transition: transform 0.2s ease;
}

.btn-icon:hover {
    transform: scale(1.2);
}

.user-table th.sortable {
    cursor: pointer;
    user-select: none;
}

.user-table th.sortable:hover {
    background: #e9ecef;
}

.sort-indicator {
    display: inline-block;
    width: 1em;
    text-align: center;
    font-size: 0.75em;
    color: #adb5bd;
}

.user-table th.sort-asc .sort-indicator::after {
    content: '▲';
    color: #495057;
}

.user-table th.sort-desc .sort-indicator::after {
    content: '▼';
    color: #495057;
}

/* Edit User Modal */
#editUserModal {
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

#editUserModal.show {
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

#editUserForm {
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

.form-group input[type="text"],
.form-group input[type="email"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1rem;
    font-family: inherit;
    transition: border-color 0.2s ease;
}

.form-group input[type="text"]:focus,
.form-group input[type="email"]:focus {
    outline: none;
    border-color: #007bff;
}

.readonly-field {
    background: #f8f9fa !important;
    cursor: not-allowed;
}

.form-help {
    display: block;
    color: #666;
    font-size: 0.875rem;
    margin-top: 0.25rem;
    line-height: 1.4;
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

.community-checkboxes {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
    max-height: 200px;
    overflow-y: auto;
}

.community-checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.community-checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.community-checkbox-item label {
    cursor: pointer;
    margin: 0 !important;
    font-weight: normal !important;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

@media (max-width: 768px) {
    .user-table {
        font-size: 0.8rem;
    }

    .user-table th,
    .user-table td {
        padding: 0.5rem;
    }

    .user-avatar {
        width: 24px;
        height: 24px;
    }
}
</style>

<script>
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

// Edit User Functions
function editUser(userId) {
    fetch('/?page=admin-users&action=get_user&user_id=' + encodeURIComponent(userId))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                const communities = data.communities || [];
                const userCommunities = data.user_communities || [];

                // Populate form fields
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editUserName').value = user.name || '';
                document.getElementById('editUserEmail').value = user.email || '';
                document.getElementById('editDisplayName').value = user.display_name || '';
                document.getElementById('editZipcode').value = user.zipcode || '';
                document.getElementById('editIsAdmin').checked = user.is_admin == 1;
                document.getElementById('editEmailNotifications').checked = user.email_notifications == 1;
                document.getElementById('editNewListingNotifications').checked = user.new_listing_notifications == 1;

                // Populate communities
                const container = document.getElementById('editUserCommunities');
                let html = '';
                communities.forEach(comm => {
                    const isChecked = userCommunities.includes(comm.id);
                    html += `
                        <div class="community-checkbox-item">
                            <input type="checkbox"
                                   name="communities[]"
                                   value="${comm.id}"
                                   id="user_community_${comm.id}"
                                   ${isChecked ? 'checked' : ''}>
                            <label for="user_community_${comm.id}">${comm.full_name}</label>
                        </div>
                    `;
                });
                container.innerHTML = html;

                // Show modal
                document.getElementById('editUserModal').classList.add('show');
            } else {
                showMessage('Error loading user: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading user', 'error');
        });
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.remove('show');
    document.getElementById('editUserForm').reset();
}

// Handle edit user form submission
document.addEventListener('DOMContentLoaded', function() {
    const editForm = document.getElementById('editUserForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');

            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';

            const formData = new FormData(this);
            const body = new URLSearchParams();
            body.append('action', 'update_user');

            for (const [key, value] of formData.entries()) {
                body.append(key, value);
            }

            fetch('/?page=admin-users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message || 'User updated successfully!', 'success');
                    closeEditUserModal();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error saving user', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
            });
        });
    }

    // Close modal when clicking outside
    const modal = document.getElementById('editUserModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditUserModal();
            }
        });
    }

    initTableSort();
});

function initTableSort() {
    const table = document.querySelector('.user-table');
    if (!table) return;

    const headers = table.querySelectorAll('th.sortable');
    let currentCol = 2; // Last Login
    let currentAsc = false; // descending by default

    headers.forEach(th => {
        th.addEventListener('click', function() {
            const col = parseInt(this.dataset.col);
            if (col === currentCol) {
                currentAsc = !currentAsc;
            } else {
                currentCol = col;
                currentAsc = true;
            }
            sortTable(table, currentCol, currentAsc);
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            this.classList.add(currentAsc ? 'sort-asc' : 'sort-desc');
        });
    });

    // Apply default sort
    sortTable(table, currentCol, currentAsc);
    headers[currentCol].classList.add('sort-desc');
}

function sortTable(table, col, asc) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    // Skip the "no data" row
    if (rows.length === 1 && rows[0].querySelector('.no-data')) return;

    rows.sort((a, b) => {
        const cellA = a.querySelectorAll('td')[col];
        const cellB = b.querySelectorAll('td')[col];
        if (!cellA || !cellB) return 0;

        const sortValA = cellA.dataset.sortValue;
        const sortValB = cellB.dataset.sortValue;

        let valA, valB;
        if (sortValA !== undefined && sortValB !== undefined) {
            valA = parseFloat(sortValA);
            valB = parseFloat(sortValB);
        } else {
            valA = cellA.textContent.trim().toLowerCase();
            valB = cellB.textContent.trim().toLowerCase();
        }

        if (valA < valB) return asc ? -1 : 1;
        if (valA > valB) return asc ? 1 : -1;
        return 0;
    });

    rows.forEach(row => tbody.appendChild(row));
}
</script>
