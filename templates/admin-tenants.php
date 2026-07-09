<?php

/**
 * Tenant management page template - Super-admin only, control-plane host only
 */

if (!isLoggedIn()) {
    redirect('login');
}

if (!isSuperAdmin() || !isControlPlaneHost()) {
    setFlashMessage('You do not have permission to access this page', 'error');
    redirect('home');
}

$tenants = getAllTenants();
$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>🏢 Tenant Management</h1>
        <p class="page-subtitle">Manage multitenant customer instances</p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="tenants-container">
            <div class="action-bar">
                <a href="?page=tenant-edit" class="btn btn-primary">
                    ➕ Add New Tenant
                </a>
                <a href="?page=admin" class="btn btn-secondary">← Back to Admin</a>
            </div>

            <div class="table-container">
                <table class="tenants-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Prefix</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Database</th>
                            <th>Enabled</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tenants)) : ?>
                            <tr>
                                <td colspan="8" class="no-data">No tenants found. Create one to get started!</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($tenants as $tenant) : ?>
                                <?php $dbExists = tenantDatabaseExists($tenant['prefix']); ?>
                                <tr>
                                    <td><?php echo escape($tenant['id']); ?></td>
                                    <td class="short-name"><?php echo escape($tenant['prefix']); ?></td>
                                    <td><?php echo escape($tenant['name']); ?></td>
                                    <td><?php echo escape($tenant['status']); ?></td>
                                    <td>
                                        <?php if ($dbExists) : ?>
                                            <span class="db-badge db-badge-yes">✅ Provisioned</span>
                                        <?php else : ?>
                                            <span class="db-badge db-badge-no">❌ Not provisioned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $tenant['enabled'] ? '✅' : '❌'; ?></td>
                                    <td class="date-cell">
                                        <?php
                                        if ($tenant['created_at']) {
                                            $date = new DateTime($tenant['created_at']);
                                            echo escape($date->format('M j, Y'));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="actions-cell">
                                        <a class="btn-icon btn-edit" href="?page=tenant-edit&id=<?php echo escape($tenant['id']); ?>" title="Edit">
                                            ✏️
                                        </a>
                                        <button class="btn-icon btn-delete" onclick="deleteTenant(<?php echo escape($tenant['id']); ?>)" title="Delete">
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

<style>
.tenants-container {
    max-width: 1200px;
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

.tenants-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.tenants-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.tenants-table th {
    padding: 1rem 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #495057;
    white-space: nowrap;
}

.tenants-table td {
    padding: 0.875rem 0.75rem;
    border-bottom: 1px solid #f1f3f4;
    color: #333;
}

.tenants-table tbody tr:hover {
    background: #f8f9fa;
}

.tenants-table tbody tr:last-child td {
    border-bottom: none;
}

.short-name {
    font-family: 'Courier New', monospace;
    background: #f8f9fa;
    font-weight: 600;
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

.db-badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.db-badge-yes {
    background: #d4edda;
    color: #155724;
}

.db-badge-no {
    background: #f8d7da;
    color: #721c24;
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

    .tenants-table {
        font-size: 0.8rem;
    }

    .tenants-table th,
    .tenants-table td {
        padding: 0.5rem;
    }
}
</style>

<script>
function deleteTenant(id) {
    if (!confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
        return;
    }

    fetch('?page=admin-tenants', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Tenant deleted successfully!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error deleting tenant', 'error');
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
