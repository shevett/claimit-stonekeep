<?php
/**
 * SMTP Configuration Example
 * 
 * Copy this file to 'smtp-config.php' and configure your SMTP settings.
 * The actual config file will be ignored by git for security.
 */

return [
    // SMTP Server Configuration
    'host' => 'smtp.gmail.com', // Your SMTP server hostname
    'port' => 587, // SMTP port (587 for TLS, 465 for SSL, 25 for plain)
    'encryption' => 'tls', // 'tls', 'ssl', or null for plain text
    'timeout' => 30, // Connection timeout in seconds
    'verify_ssl_cert' => true, // Set to false for internal servers with self-signed certificates
    'helo_hostname' => 'claimit.stonekeep.com', // Fully-qualified hostname for EHLO/HELO command
    
    // Authentication
    'username' => 'your-email@gmail.com', // SMTP username (usually your email)
    'password' => 'your-app-password', // SMTP password or app-specific password
    
    // Email Settings
    'from_email' => 'your-email@gmail.com', // From email address
    'from_name' => 'ClaimIt', // From name
    
    // Rate limiting (optional)
    'rate_limit' => [
        'enabled' => true,
        'max_emails_per_hour' => 100,
        'max_emails_per_day' => 1000
    ]
];
