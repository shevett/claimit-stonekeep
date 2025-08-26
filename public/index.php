<?php
/**
 * ClaimIt Web Application
 * Main entry point
 */

// Suppress PHP 8.4 deprecation warnings while keeping actual errors
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

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
$availablePages = ['home', 'about', 'contact', 'claim', 's3'];

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <a href="?page=home" class="nav-logo">ClaimIt</a>
                <ul class="nav-menu">
                    <li><a href="?page=about" class="nav-link <?php echo $page === 'about' ? 'active' : ''; ?>">About</a></li>
                    <li><a href="?page=claim" class="nav-link <?php echo $page === 'claim' ? 'active' : ''; ?>">Make a new posting</a></li>
                    <li><a href="?page=s3" class="nav-link <?php echo $page === 's3' ? 'active' : ''; ?>">View available items</a></li>
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