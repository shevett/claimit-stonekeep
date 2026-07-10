<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTenantInfoView extends AbstractMigration
{
    /**
     * Creates a view, identical in every tenant database, that exposes only
     * that tenant's own row from the tenants control-plane table. Filtering
     * is done via DATABASE() (the connection's active schema name) matched
     * against tenants.db_name, so the view definition never needs to be
     * customized per tenant. Uses up()/down() rather than change() since a
     * raw CREATE VIEW isn't automatically reversible.
     */
    public function up(): void
    {
        $this->execute(
            'CREATE OR REPLACE VIEW tenant_info AS SELECT * FROM `' . DB_NAME . '`.tenants WHERE db_name = DATABASE()'
        );
    }

    public function down(): void
    {
        $this->execute('DROP VIEW IF EXISTS tenant_info');
    }
}
