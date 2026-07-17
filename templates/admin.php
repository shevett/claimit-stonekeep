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
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="admin-container">
            <div class="admin-left-col">
                <div class="admin-card">
                    <div class="admin-header">
                        <h2>Management</h2>
                        <p>System management and configuration</p>
                    </div>
                    <div class="admin-section">
                        <div class="admin-links">
                            <a href="?page=admin-users" class="admin-link">
                                <span class="link-icon">👥</span>
                                <span class="link-content">
                                    <strong>User Management</strong>
                                    <small>Manage registered users</small>
                                </span>
                            </a>
                            <a href="?page=communities" class="admin-link">
                                <span class="link-icon">🏘️</span>
                                <span class="link-content">
                                    <strong>Community Management</strong>
                                    <small>Create and manage communities</small>
                                </span>
                            </a>
                            <?php if (isSuperAdmin() && isControlPlaneHost()) : ?>
                            <a href="?page=admin-tenants" class="admin-link">
                                <span class="link-icon">🏢</span>
                                <span class="link-content">
                                    <strong>Tenant Management</strong>
                                    <small>Manage multitenant customer instances</small>
                                </span>
                            </a>
                            <?php endif; ?>
                            <a href="?page=admin-reports" class="admin-link">
                                <span class="link-icon">📊</span>
                                <span class="link-content">
                                    <strong>Reports</strong>
                                    <small>System statistics and activity overview</small>
                                </span>
                            </a>
                            <a href="?page=admin-tests" class="admin-link">
                                <span class="link-icon">🧪</span>
                                <span class="link-content">
                                    <strong>Tests</strong>
                                    <small>Run diagnostic checks on system components</small>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
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
                    <div class="info-item">
                        <label>Database:</label>
                        <span><?php echo defined('DB_NAME') ? escape(DB_NAME) : '<em style="color: #999;">Not configured</em>'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Environment:</label>
                        <span><?php echo DEVELOPMENT_MODE ? 'Development' : 'Production'; ?></span>
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

.admin-left-col {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
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

.admin-section {
    padding: 0 2rem 1.5rem 2rem;
    border-bottom: 1px solid #f1f3f4;
}

.admin-section:last-child {
    border-bottom: none;
}

.admin-links {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.admin-link {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid #e9ecef;
}

.admin-link:hover {
    background: #e9ecef;
    transform: translateX(4px);
}

.link-icon {
    font-size: 2rem;
    line-height: 1;
}

.link-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.link-content strong {
    color: #007bff;
    font-size: 1rem;
}

.link-content small {
    color: #666;
    font-size: 0.875rem;
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

    .admin-header {
        padding: 1.5rem;
    }
}
</style>
