<?php
/**
 * Configuration file for ClaimIt application
 */

// Define constants
define('APP_NAME', 'ClaimIt');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');

// Database configuration (uncomment and configure when needed)
/*
define('DB_HOST', 'localhost');
define('DB_NAME', 'claimit');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_CHARSET', 'utf8mb4');
*/

// Error reporting (set to false in production)
define('DEVELOPMENT_MODE', true);

// Debug logging (set to 'yes' to enable detailed timing logs)
define('DEBUG', 'no');

if (DEVELOPMENT_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('America/New_York');

// Administrator configuration
// Set this to the Google user ID of the administrator
// You can find this in the Google OAuth response or in the user's YAML file
define('ADMIN_USER_ID', '112659624466139657672'); // Replace with actual admin user ID

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS

?> 
