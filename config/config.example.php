<?php
/**
 * Configuration file for ClaimIt application
 * 
 * Copy this file to 'config.php' and fill in your actual values.
 * The actual config.php file will be ignored by git for security.
 */

// Define constants
define('APP_NAME', 'ClaimIt');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');

// Error reporting (set to false in production)
define('DEVELOPMENT_MODE', true);

// Database configuration
// RDS MySQL endpoint in AWS us-east-1
define('DB_HOST', 'your-rds-endpoint.us-east-1.rds.amazonaws.com');
define('DB_PORT', 3306);
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// Use different databases for dev and prod
if (DEVELOPMENT_MODE) {
    define('DB_NAME', 'claimit_dev');
} else {
    define('DB_NAME', 'claimit_prod');
}

// Debug logging (set to 'yes' to enable detailed timing logs)
define('DEBUG', 'yes');

// CloudFront CDN configuration
// Replace with your actual CloudFront domain (e.g., 'd1234567890.cloudfront.net')
define('CLOUDFRONT_DOMAIN', 'your-cloudfront-domain.cloudfront.net');

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

