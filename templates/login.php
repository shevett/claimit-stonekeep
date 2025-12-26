<?php

/**
 * Login page template
 */

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('dashboard');
}

// Get flash message if any
$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>Welcome to ClaimIt</h1>
        <p class="page-subtitle">Sign in to post and manage your items</p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h2>Sign In</h2>
                    <p>Use your Google account to get started</p>
                </div>

                <div class="login-content">
                    <a href="?page=auth&action=google" class="btn btn-google">
                        <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20">
                            <path fill="#4285f4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34a853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#fbbc05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#ea4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Continue with Google
                    </a>

                    <div class="login-benefits">
                        <h3>Why sign in?</h3>
                        <ul>
                            <li>✅ Post items for sale or giveaway</li>
                            <li>✅ Manage your posted items</li>
                            <li>✅ Edit or delete your posts</li>
                            <li>✅ Track your marketplace activity</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="login-info">
                <h3>Browse without signing in</h3>
                <p>You can browse available items without an account. <a href="?page=items" class="link">View available items</a></p>
                
                <div class="privacy-note">
                    <p><small>We only use your Google account to verify your identity. We never post on your behalf or access your private information.</small></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.login-container {
    max-width: 500px;
    margin: 0 auto;
    padding: 2rem 0;
}

.login-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    padding: 2rem;
    margin-bottom: 2rem;
}

.login-header {
    text-align: center;
    margin-bottom: 2rem;
}

.login-header h2 {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.login-header p {
    color: #6c757d;
    margin: 0;
}

.login-content {
    text-align: center;
}

.btn-google {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    background: white;
    border: 2px solid #e9ecef;
    color: #495057;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 100%;
    margin-bottom: 2rem;
}

.btn-google:hover {
    border-color: #4285f4;
    box-shadow: 0 2px 10px rgba(66, 133, 244, 0.1);
    transform: translateY(-1px);
}

.google-icon {
    flex-shrink: 0;
}

.login-benefits {
    text-align: left;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
}

.login-benefits h3 {
    color: #2c3e50;
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.login-benefits ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.login-benefits li {
    color: #495057;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.login-info {
    text-align: center;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
}

.login-info h3 {
    color: #2c3e50;
    margin-bottom: 1rem;
}

.login-info p {
    color: #6c757d;
    margin-bottom: 1rem;
}

.link {
    color: #007bff;
    text-decoration: none;
}

.link:hover {
    text-decoration: underline;
}

.privacy-note {
    border-top: 1px solid #e9ecef;
    padding-top: 1rem;
    margin-top: 1rem;
}

.privacy-note p {
    color: #6c757d;
    margin: 0;
}

@media (max-width: 768px) {
    .login-container {
        padding: 1rem;
    }
    
    .login-card {
        padding: 1.5rem;
    }
}
</style> 