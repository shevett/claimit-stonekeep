<?php
/**
 * ClaimIt Web Application
 * Main entry point
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration (this sets session settings)
require_once __DIR__ . '/../config/config.php';

// Start session (after session settings are configured)
session_start();

// Load includes
require_once __DIR__ . '/../includes/functions.php';

// Simple routing based on URL parameter
$page = $_GET['page'] ?? 'home';

// Basic security - only allow alphanumeric characters and hyphens
$page = preg_replace('/[^a-zA-Z0-9\-]/', '', $page);

// Define available pages
$availablePages = ['home', 'about', 'contact', 'claim'];

if (!in_array($page, $availablePages)) {
    $page = 'home';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($page); ?> - ClaimIt</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <h1 class="nav-logo">ClaimIt</h1>
                <ul class="nav-menu">
                    <li><a href="?page=home" class="nav-link <?php echo $page === 'home' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="?page=about" class="nav-link <?php echo $page === 'about' ? 'active' : ''; ?>">About</a></li>
                    <li><a href="?page=claim" class="nav-link <?php echo $page === 'claim' ? 'active' : ''; ?>">Make a Claim</a></li>
                    <li><a href="?page=contact" class="nav-link <?php echo $page === 'contact' ? 'active' : ''; ?>">Contact</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <?php
        // Include the appropriate page template
        $templateFile = __DIR__ . "/../templates/{$page}.php";
        if (file_exists($templateFile)) {
            include $templateFile;
        } else {
            include __DIR__ . '/../templates/404.php';
        }
        ?>
    </main>

    <footer>
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> ClaimIt by Stonekeep.com. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/app.js"></script>
</body>
</html> 