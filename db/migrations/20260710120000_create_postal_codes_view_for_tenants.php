<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePostalCodesViewForTenants extends AbstractMigration
{
    /**
     * postal_codes is a large, static, GeoNames-style reference dataset that
     * only needs to exist once. It's control-plane data, not per-tenant
     * data - but CreatePostalCodesTable runs against every tenant database
     * too (provisioning replays every migration), so each tenant ended up
     * with its own full duplicate copy.
     *
     * Fix: on any database that is NOT the control plane, drop the local
     * duplicate (if present) and replace it with a view onto the
     * control-plane table instead - same pattern as tenant_info
     * (CreateTenantInfoView), a same-host cross-database view needs no
     * second connection, so getLocationByZipcode()/getZipcodeCoordinates()
     * (includes/users.php) keep working unmodified.
     *
     * The control-plane database itself is untouched - it keeps the real,
     * populated table created by CreatePostalCodesTable.
     */
    public function up(): void
    {
        if (strtolower(DB_NAME) === strtolower((string) $this->query('SELECT DATABASE()')->fetchColumn())) {
            return;
        }

        if ($this->hasTable('postal_codes')) {
            $this->table('postal_codes')->drop()->save();
        }

        $this->execute(
            'CREATE OR REPLACE VIEW postal_codes AS SELECT * FROM `' . DB_NAME . '`.postal_codes'
        );
    }

    public function down(): void
    {
        if (strtolower(DB_NAME) === strtolower((string) $this->query('SELECT DATABASE()')->fetchColumn())) {
            return;
        }

        $this->execute('DROP VIEW IF EXISTS postal_codes');
    }
}
