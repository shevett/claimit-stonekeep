<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDbNameToTenants extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('tenants');
        if (!$table->hasColumn('db_name')) {
            $table->addColumn('db_name', 'string', [
                      'limit' => 255,
                      'null' => true,
                      'comment' => 'Tenant MySQL database name, derived from prefix; matched against DATABASE() by the tenant_info view'
                  ])
                  ->update();
        }
    }
}
