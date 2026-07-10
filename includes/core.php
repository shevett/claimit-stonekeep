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
 * Set the database name to be used by getDbConnection() for the rest of
 * this request, overriding the DB_NAME constant. Used to resolve a tenant
 * subdomain request to that tenant's own database at bootstrap, before
 * any connection has been opened. Must be called before the first
 * getDbConnection() call in a request - the connection is memoized, so
 * calling this after a connection has already been opened has no effect.
 * @param string $dbName Database name to connect to
 */
function setResolvedDatabaseName($dbName)
{
    $GLOBALS['__resolved_db_name'] = $dbName;
}

/**
 * The database name to use for this request: the tenant-resolved override
 * if one was set via setResolvedDatabaseName(), otherwise the DB_NAME
 * constant (today's default, single-instance behavior).
 * @return string Database name
 */
function getResolvedDatabaseName()
{
    return $GLOBALS['__resolved_db_name'] ?? DB_NAME;
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
            getResolvedDatabaseName(),
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
