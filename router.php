<?php
// Suppress PHP 8.4 deprecation warnings while keeping actual errors
error_reporting(0); // Suppress all errors for development server
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set upload limits for file uploads
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '6M'); // Slightly larger than upload_max_filesize to account for form data
ini_set('max_file_uploads', '1');

// Custom error handler to filter out AWS SDK warnings from browser but keep in environment logs
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log AWS SDK compatibility warnings to environment logs but don't display them
    if (strpos($errfile, 'aws-sdk-php') !== false && strpos($errstr, 'syntax error') !== false) {
        error_log("AWS SDK PHP 8.4 Compatibility Warning: $errstr in $errfile on line $errline");
        return true; // Suppress from browser display
    }
    // Let other errors through to default handler
    return false;
});

// Router for PHP development server
// This ensures static files are served correctly

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);



// Handle auth routes for local development (production uses .htaccess)
if (preg_match('/^\/auth\/google\/callback/', $path)) {
    $_GET['page'] = 'auth';
    $_GET['action'] = 'callback';
    require_once __DIR__ . '/public/index.php';
    exit;
} elseif (preg_match('/^\/auth\/google/', $path)) {
    $_GET['page'] = 'auth';
    $_GET['action'] = 'google';
    require_once __DIR__ . '/public/index.php';
    exit;
} elseif (preg_match('/^\/auth\/logout/', $path)) {
    $_GET['page'] = 'auth';
    $_GET['action'] = 'logout';
    require_once __DIR__ . '/public/index.php';
    exit;
}

// Handle static files
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/', $path)) {
    // For static files, check if they exist and serve them directly
    $filePath = __DIR__ . $path;
    
    // If not found at root, try public/ directory (for assets moved to public/assets/)
    if (!file_exists($filePath)) {
        $filePath = __DIR__ . '/public' . $path;
    }
    
    if (file_exists($filePath)) {
        // Set correct content type
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
        
        // Clear any previous headers and set the correct content type
        header_remove();
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($filePath));
        
        // Output the file and exit
        readfile($filePath);
        exit;
    } else {
        // File not found
        http_response_code(404);
        echo "File not found";
        exit;
    }
}

// For all other requests, serve through index.php
require_once __DIR__ . '/public/index.php'; 