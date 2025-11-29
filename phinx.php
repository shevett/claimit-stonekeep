<?php
/**
 * Phinx Configuration
 * 
 * Load database credentials from config.php
 */

require_once __DIR__ . '/config/config.php';

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => DEVELOPMENT_MODE ? 'development' : 'production',
        'production' => [
            'adapter' => 'mysql',
            'host' => DB_HOST,
            'name' => 'claimit_prod',
            'user' => DB_USER,
            'pass' => DB_PASS,
            'port' => DB_PORT,
            'charset' => DB_CHARSET,
        ],
        'development' => [
            'adapter' => 'mysql',
            'host' => DB_HOST,
            'name' => 'claimit_dev',
            'user' => DB_USER,
            'pass' => DB_PASS,
            'port' => DB_PORT,
            'charset' => DB_CHARSET,
        ],
    ],
    'version_order' => 'creation'
];
