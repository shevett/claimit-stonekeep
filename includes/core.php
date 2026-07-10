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
    $baseUrl = getValidatedRedirectBaseUrl();

    // Discard any output buffer to allow redirect
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Location: ' . $baseUrl . '?page=' . urlencode($page));
    exit;
}

/**
 * Build the base URL to redirect to, validating the current request's Host
 * header rather than trusting it blindly (this app's production Apache
 * vhost serves ServerAlias claimit.cc *.claimit.cc, so $_SERVER['HTTP_HOST']
 * isn't necessarily pre-filtered to only those hosts by the time PHP sees
 * it). Only the apex control-plane host, or a subdomain of it, are
 * reflected back; anything else falls back to APP_URL.
 *
 * Deliberately does NOT re-query the tenants table to check the subdomain
 * is a real tenant: this function can run while connected to a *tenant's*
 * own database (e.g. mid-login, right after setResolvedDatabaseName()), and
 * that database doesn't have the control-plane tenants data. Instead this
 * relies on the tenant-resolution gate already run once per request at the
 * very top of public/index.php's bootstrap, which 404s/503s before any
 * other code (including this function) runs for an unprovisioned or
 * disabled tenant - so by the time this executes, a *.CONTROL_PLANE_HOST
 * host has already been fully validated for this exact request.
 * @return string Base URL, always ending in '/'
 */
function getValidatedRedirectBaseUrl()
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    if ($host === '') {
        return rtrim(APP_URL, '/') . '/';
    }

    if (!defined('CONTROL_PLANE_HOST')) {
        // Self-hosted/non-multitenant instance - no tenant concept at all,
        // so there's nothing more specific to check the host against than
        // "this is the host the server actually received."
        return $scheme . '://' . $host . '/';
    }

    $hostWithoutPort = strtolower(explode(':', $host)[0]);
    $controlPlaneHost = strtolower(CONTROL_PLANE_HOST);
    $suffix = '.' . $controlPlaneHost;

    if ($hostWithoutPort === $controlPlaneHost || str_ends_with($hostWithoutPort, $suffix)) {
        return $scheme . '://' . $host . '/';
    }

    // Unrecognized host - don't reflect it back into a redirect target
    return rtrim(APP_URL, '/') . '/';
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
