<?php

/**
 * Admin reports page template
 */

if (!isLoggedIn()) {
    redirect('login');
}

if (!isAdmin()) {
    setFlashMessage('You do not have permission to access this page', 'error');
    redirect('home');
}

$adminStats = getAdminStats();
$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>📊 Reports</h1>
        <p class="page-subtitle">System statistics and activity overview</p>
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

        <div class="reports-section">
            <div class="admin-card">
                <div class="reports-grid">
                    <div class="admin-section">
                        <h3>Items</h3>
                        <div class="stat-item"><span class="stat-label">Total</span><span><?php echo (int)($adminStats['items']['total'] ?? 0); ?></span></div>
                        <div class="stat-item"><span class="stat-label">Open</span><span><?php echo (int)($adminStats['items']['open'] ?? 0); ?></span></div>
                        <div class="stat-item"><span class="stat-label">Claimed</span><span><?php echo (int)($adminStats['items']['claimed'] ?? 0); ?></span></div>
                        <div class="stat-item"><span class="stat-label">Gone</span><span><?php echo (int)($adminStats['items']['gone'] ?? 0); ?></span></div>
                    </div>
                    <div class="admin-section">
                        <h3>Users</h3>
                        <div class="stat-item"><span class="stat-label">Total</span><span><?php echo (int)($adminStats['users']['total'] ?? 0); ?></span></div>
                        <div class="stat-item"><span class="stat-label">Active (30 days)</span><span><?php echo (int)($adminStats['users']['active_30d'] ?? 0); ?></span></div>
                    </div>
                </div>
            </div>
        </div>
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

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.reports-section {
    max-width: 1200px;
    margin: 0 auto;
}

.admin-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    border: 2px solid #ffeaa7;
    padding: 2rem;
}

.admin-section {
    padding: 0 0 1.5rem 0;
}

.admin-section h3 {
    margin: 0 0 1rem 0;
    color: #333;
    font-size: 1.1rem;
}

.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 0.3rem 0;
    border-bottom: 1px solid #f1f3f4;
    font-size: 0.9rem;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    color: #666;
}

@media (max-width: 768px) {
    .reports-grid {
        grid-template-columns: 1fr;
    }
}
</style>
