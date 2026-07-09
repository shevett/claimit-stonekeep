<?php

/**
 * Add/Edit Tenant page template - Super-admin only, control-plane host only
 */

if (!isLoggedIn()) {
    redirect('login');
}

if (!isSuperAdmin() || !isControlPlaneHost()) {
    setFlashMessage('You do not have permission to access this page', 'error');
    redirect('admin-tenants');
}

$tenantId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditing = $tenantId > 0;
$tenant = null;

$dbExists = false;
if ($isEditing) {
    $tenant = getTenantById($tenantId);
    if (!$tenant) {
        setFlashMessage('Tenant not found', 'error');
        redirect('admin-tenants');
    }
    $dbExists = tenantDatabaseExists($tenant['prefix']);
}

$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1><?php echo $isEditing ? 'Edit Tenant' : 'Add New Tenant'; ?></h1>
        <p class="page-subtitle"><?php echo $isEditing ? escape($tenant['name']) : 'Create a new tenant'; ?></p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="tenant-edit-container">
            <div class="tenant-edit-card">
                <div class="action-bar">
                    <a href="?page=admin-tenants" class="btn btn-secondary">← Back to Tenants</a>
                </div>

                <form id="tenantForm">
                    <input type="hidden" id="tenantId" name="id" value="<?php echo $isEditing ? escape($tenant['id']) : ''; ?>">

                    <div class="form-group">
                        <label for="prefix">Prefix <span class="required">*</span></label>
                        <input type="text" id="prefix" name="prefix" required maxlength="63"
                               placeholder="e.g., acme"
                               value="<?php echo $isEditing ? escape($tenant['prefix']) : ''; ?>">
                        <small class="form-help">Subdomain identifier - e.g. "acme" for acme.claimit.cc</small>
                    </div>

                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required maxlength="255"
                               placeholder="e.g., Acme Corporation"
                               value="<?php echo $isEditing ? escape($tenant['name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <input type="text" id="status" name="status" maxlength="50"
                               placeholder="new"
                               value="<?php echo $isEditing ? escape($tenant['status']) : 'new'; ?>">
                        <small class="form-help">Lifecycle status, e.g. new, pending, provisioned, maintenance</small>
                    </div>

                    <div class="form-group form-check">
                        <label>
                            <input type="checkbox" id="enabled" name="enabled"
                                   <?php echo (!$isEditing || $tenant['enabled']) ? 'checked' : ''; ?>>
                            Enabled
                        </label>
                        <small class="form-help">Quick on/off switch, independent of status</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-text">Save</span>
                            <span class="btn-loading" style="display: none;">Saving...</span>
                        </button>
                    </div>
                </form>

                <?php if ($isEditing) : ?>
                <div class="provision-section">
                    <label>Database Provisioning</label>
                    <p class="db-status-line">
                        Current status:
                        <?php if ($dbExists) : ?>
                            <span class="db-badge db-badge-yes">✅ Database exists</span>
                        <?php else : ?>
                            <span class="db-badge db-badge-no">❌ Database not found</span>
                        <?php endif; ?>
                    </p>
                    <small class="form-help">
                        Creates the tenant's database (if it doesn't already exist) and runs every
                        schema migration against it. Does not create an admin user or set up OAuth -
                        those are separate steps.
                    </small>
                    <button type="button" class="btn btn-secondary" id="provisionBtn" onclick="provisionTenant()">
                        <span class="btn-text">🛠️ Provision Database</span>
                        <span class="btn-loading" style="display: none;">Provisioning…</span>
                    </button>

                    <div class="deprovision-subsection">
                        <small class="form-help">
                            Drops the tenant's database entirely. This does <strong>not</strong> clean up
                            S3 assets for this tenant - that must be done manually for now. This action
                            cannot be undone.
                        </small>
                        <button type="button" class="btn btn-danger" id="deprovisionBtn" onclick="deprovisionTenant()">
                            <span class="btn-text">🗑️ Deprovision Database</span>
                            <span class="btn-loading" style="display: none;">Deprovisioning…</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.tenant-edit-container {
    max-width: 600px;
    margin: 0 auto;
}

.tenant-edit-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    padding: 2rem;
}

.action-bar {
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
}

.form-group input[type="text"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1rem;
}

.form-check label {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.5rem;
    font-weight: 500;
}

.form-check input[type="checkbox"] {
    width: auto;
    flex: 0 0 auto;
    margin: 0;
}

.required {
    color: #dc3545;
}

.form-help {
    display: block;
    color: #666;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.form-actions {
    margin-top: 2rem;
}

.provision-section {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #eee;
}

.db-status-line {
    margin: 0 0 0.75rem 0;
    color: #333;
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

.provision-section label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
}

.provision-section .btn {
    margin-top: 0.75rem;
}

.deprovision-subsection {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px dashed #ddd;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #b02a37;
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
</style>

<script>
let editingTenantId = <?php echo $isEditing ? (int)$tenant['id'] : 'null'; /* nosemgrep: php.lang.security.injection.echoed-request.echoed-request */ ?>;
let editingTenantPrefix = <?php echo $isEditing ? json_encode($tenant['prefix']) : 'null'; ?>;

document.getElementById('tenantForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = this.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');

    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline';

    const formData = new FormData(this);
    const action = editingTenantId ? 'update' : 'create';

    const body = new URLSearchParams();
    body.append('action', action);
    for (const [key, value] of formData.entries()) {
        body.append(key, value);
    }

    fetch('?page=admin-tenants', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message || 'Tenant saved successfully!', 'success');
            setTimeout(() => { window.location.href = '?page=admin-tenants'; }, 1000);
        } else {
            showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error saving tenant', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    });
});

function provisionTenant() {
    if (!editingTenantId) return;
    if (!confirm('Create the database and run all schema migrations for this tenant? This does not create an admin user or configure OAuth.')) {
        return;
    }

    const btn = document.getElementById('provisionBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');

    btn.disabled = true;
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline';

    const body = new URLSearchParams();
    body.append('action', 'provision');
    body.append('id', editingTenantId);

    fetch('?page=admin-tenants', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message || 'Tenant database provisioned successfully!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error provisioning tenant database', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    });
}

function deprovisionTenant() {
    if (!editingTenantId) return;

    const typed = prompt(
        'This will permanently drop the database for "' + editingTenantPrefix + '". ' +
        'S3 assets will NOT be removed. This cannot be undone.\n\n' +
        'Type the tenant prefix (' + editingTenantPrefix + ') to confirm:'
    );
    if (typed === null) return;
    if (typed !== editingTenantPrefix) {
        showMessage('Prefix did not match - deprovisioning cancelled', 'error');
        return;
    }

    const btn = document.getElementById('deprovisionBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');

    btn.disabled = true;
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline';

    const body = new URLSearchParams();
    body.append('action', 'deprovision');
    body.append('id', editingTenantId);
    body.append('confirm_prefix', typed);

    fetch('?page=admin-tenants', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message || 'Tenant database deprovisioned', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error deprovisioning tenant database', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
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
