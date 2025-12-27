<?php

/**
 * Core functions: Database, escaping, redirects, CSRF
 */

/**
 * Escape HTML output to prevent XSS attacks
 */
function escape($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get database connection
 * Returns a PDO instance or null on failure
 */
function getDbConnection()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Test database connection
 * Returns array with status and message
 */
function testDbConnection()
{
    try {
        $pdo = getDbConnection();

        if ($pdo === null) {
            return [
                'success' => false,
                'message' => 'Failed to connect to database'
            ];
        }

        // Test query
        $stmt = $pdo->query('SELECT VERSION() as version, DATABASE() as db_name');
        $result = $stmt->fetch();

        return [
            'success' => true,
            'message' => 'Database connection successful',
            'details' => [
                'mysql_version' => $result['version'],
                'database' => $result['db_name'],
                'host' => DB_HOST,
                'charset' => DB_CHARSET
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Database test failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Redirect to a specific page
 */
function redirect($page = 'home')
{
    // Determine the correct base URL based on the environment
    $isLocalhost = isset($_SERVER['HTTP_HOST']) && (
        $_SERVER['HTTP_HOST'] === 'localhost:8000' ||
        $_SERVER['HTTP_HOST'] === '127.0.0.1:8000' ||
        $_SERVER['HTTP_HOST'] === 'localhost:8080' ||
        $_SERVER['HTTP_HOST'] === '127.0.0.1:8080'
    );

    $baseUrl = $isLocalhost
        ? 'http://' . $_SERVER['HTTP_HOST'] . '/'
        : 'https://claimit.stonekeep.com/';

    // Discard any output buffer to allow redirect
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Location: ' . $baseUrl . '?page=' . urlencode($page));
    exit;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

