<?php

/**
 * Tenant (multitenant control-plane) management functions
 */

/**
 * Get all tenants
 * @return array Array of all tenant rows
 */
function getAllTenants()
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT * FROM tenants ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting all tenants: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a tenant by ID
 * @param int $id Tenant ID
 * @return array|null Tenant data or null if not found
 */
function getTenantById($id)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Error getting tenant by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get a tenant by its subdomain prefix
 * @param string $prefix Subdomain prefix
 * @return array|null Tenant data or null if not found
 */
function getTenantByPrefix($prefix)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE prefix = ?");
        $stmt->execute([$prefix]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Error getting tenant by prefix: " . $e->getMessage());
        return null;
    }
}

/**
 * Create a new tenant
 * @param array $data Tenant data (prefix, name, status, enabled)
 * @return int|false The new tenant ID or false on failure
 */
function createTenant($data)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $sql = "INSERT INTO tenants (prefix, name, status, enabled, db_name, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['prefix'],
            $data['name'],
            $data['status'] ?? 'new',
            isset($data['enabled']) ? (int)$data['enabled'] : 1,
            getTenantDatabaseName($data['prefix'])
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating tenant: " . $e->getMessage());
        return false;
    }
}

/**
 * Update a tenant
 * @param int $id Tenant ID
 * @param array $data Tenant data to update (prefix, name, status, enabled)
 * @return bool True on success, false on failure
 */
function updateTenant($id, $data)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $sql = "UPDATE tenants
                SET prefix = ?, name = ?, status = ?, enabled = ?, db_name = ?, updated_at = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['prefix'],
            $data['name'],
            $data['status'] ?? 'new',
            isset($data['enabled']) ? (int)$data['enabled'] : 1,
            getTenantDatabaseName($data['prefix']),
            $id
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating tenant: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a tenant
 * @param int $id Tenant ID
 * @return bool True on success, false on failure
 */
function deleteTenant($id)
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    } catch (Exception $e) {
        error_log("Error deleting tenant: " . $e->getMessage());
        return false;
    }
}

/**
 * Build the MySQL database name for a tenant from its subdomain prefix,
 * matching the 'claimit_<name>' naming scheme documented in multitenant.md.
 * The result is strictly whitelisted (lowercase alphanumeric + underscore
 * only) since it gets interpolated into a CREATE DATABASE statement, which
 * cannot be parameterized via PDO placeholders.
 * @param string $prefix Tenant subdomain prefix
 * @return string Sanitized database name
 */
function getTenantDatabaseName($prefix)
{
    $normalized = strtolower(str_replace('-', '_', $prefix));
    $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);
    return 'claimit_' . $normalized;
}

/**
 * Resolve the tenant subdomain prefix from the current request's Host
 * header, e.g. 'acme' from 'acme.claimit.cc'. Returns null if this isn't a
 * tenant request at all - the control-plane host itself, a self-hosted
 * instance, localhost, or any other unrelated host - so non-multitenant
 * deployments are completely unaffected by this function's existence.
 * @return string|null Tenant prefix, or null if this is not a tenant request
 */
function resolveTenantPrefixFromHost()
{
    if (!defined('CONTROL_PLANE_HOST')) {
        return null;
    }

    $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    $suffix = '.' . strtolower(CONTROL_PLANE_HOST);

    if ($host === strtolower(CONTROL_PLANE_HOST) || !str_ends_with($host, $suffix)) {
        return null;
    }

    return substr($host, 0, -strlen($suffix));
}

/**
 * Build the base URL (scheme + host, no path) for a tenant subdomain,
 * preserving the current request's port for local dev testing (e.g.
 * acme.localhost:8010).
 * @param string $prefix Tenant subdomain prefix
 * @return string Base URL, e.g. 'https://acme.claimit.cc' or 'http://acme.localhost:8010'
 */
function buildTenantBaseUrl($prefix)
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $currentHost = $_SERVER['HTTP_HOST'] ?? CONTROL_PLANE_HOST;
    $portPart = strpos($currentHost, ':') !== false ? ':' . explode(':', $currentHost)[1] : '';
    return $scheme . '://' . $prefix . '.' . CONTROL_PLANE_HOST . $portPart;
}

/**
 * Look up a tenant by subdomain prefix directly against the control-plane
 * database (DB_NAME), using a dedicated raw connection rather than
 * getDbConnection(). This must never go through getDbConnection() at
 * bootstrap: that function memoizes its PDO connection for the life of the
 * request, so calling it here (before setResolvedDatabaseName() runs) would
 * permanently pin the request to the control-plane database instead of the
 * tenant's own database.
 * @param string $prefix Tenant subdomain prefix
 * @return array|null Tenant row, or null if not found
 */
function getControlPlaneTenantByPrefix($prefix)
{
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE prefix = ?");
        $stmt->execute([$prefix]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Error looking up control-plane tenant by prefix: " . $e->getMessage());
        return null;
    }
}

/**
 * Check whether a tenant's database actually exists on the RDS instance.
 * This is a live check against INFORMATION_SCHEMA, independent of the
 * tenant's `status` field, since that's just a free-form status string that
 * can drift from reality (e.g. a database dropped outside the app).
 * @param string $prefix Tenant subdomain prefix
 * @return bool True if the database exists
 */
function tenantDatabaseExists($prefix)
{
    $dbName = getTenantDatabaseName($prefix);
    if ($dbName === 'claimit_') {
        return false;
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$dbName]);
        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        error_log("Error checking tenant database existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Provision a tenant's database: create it (if it doesn't already exist)
 * and run every Phinx migration against it, using Phinx's PHP API directly
 * rather than shelling out to vendor/bin/phinx. Leaves the database schema
 * in place but otherwise empty - bootstrapping a first admin user and OAuth
 * configuration are separate, later steps.
 * @param int $tenantId Tenant ID
 * @return array ['success' => bool, 'message' => string]
 */
function provisionTenantDatabase($tenantId)
{
    $tenant = getTenantById($tenantId);
    if (!$tenant) {
        return ['success' => false, 'message' => 'Tenant not found'];
    }

    $dbName = getTenantDatabaseName($tenant['prefix']);
    if ($dbName === 'claimit_') {
        return ['success' => false, 'message' => 'Tenant prefix produced an empty database name'];
    }

    try {
        // Raw connection with no dbname, just to issue CREATE DATABASE
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET " . DB_CHARSET);

        $config = new \Phinx\Config\Config([
            'paths' => [
                'migrations' => __DIR__ . '/../db/migrations',
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_environment' => 'tenant',
                'tenant' => [
                    'adapter' => 'mysql',
                    'host' => DB_HOST,
                    'name' => $dbName,
                    'user' => DB_USER,
                    'pass' => DB_PASS,
                    'port' => DB_PORT,
                    'charset' => DB_CHARSET,
                ],
            ],
        ]);

        $manager = new \Phinx\Migration\Manager(
            $config,
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput()
        );
        $manager->migrate('tenant');

        updateTenant($tenantId, array_merge($tenant, ['status' => 'provisioned']));
        return ['success' => true, 'message' => "Database $dbName created and migrated successfully"];
    } catch (Exception $e) {
        error_log("Error provisioning tenant database: " . $e->getMessage());
        updateTenant($tenantId, array_merge($tenant, ['status' => 'provision_failed']));
        return ['success' => false, 'message' => 'Provisioning failed: ' . $e->getMessage()];
    }
}

/**
 * Deprovision a tenant's database: drops it entirely. This does NOT clean up
 * S3 assets for the tenant (images/tenants/<prefix>/..., staging/tenants/
 * <prefix>/...) - that cleanup is a separate, not-yet-built step. This is a
 * destructive, irreversible action; callers must independently verify a
 * confirmation (e.g. the tenant's prefix typed by the admin) before invoking
 * this - it performs no confirmation of its own.
 * @param int $tenantId Tenant ID
 * @return array ['success' => bool, 'message' => string]
 */
function deprovisionTenantDatabase($tenantId)
{
    $tenant = getTenantById($tenantId);
    if (!$tenant) {
        return ['success' => false, 'message' => 'Tenant not found'];
    }

    $dbName = getTenantDatabaseName($tenant['prefix']);
    if ($dbName === 'claimit_') {
        return ['success' => false, 'message' => 'Tenant prefix produced an empty database name'];
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");

        updateTenant($tenantId, array_merge($tenant, ['status' => 'deprovisioned']));
        return [
            'success' => true,
            'message' => "Database $dbName dropped. S3 assets for this tenant have NOT been removed - manual cleanup still required."
        ];
    } catch (Exception $e) {
        error_log("Error deprovisioning tenant database: " . $e->getMessage());
        return ['success' => false, 'message' => 'Deprovisioning failed: ' . $e->getMessage()];
    }
}
