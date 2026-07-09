<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTenantsTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('tenants');
        $table->addColumn('prefix', 'string', [
                  'limit' => 63,
                  'null' => false,
                  'comment' => 'Subdomain identifier, e.g. "acme" for acme.claimit.cc'
              ])
              ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('status', 'string', ['limit' => 50, 'null' => false, 'default' => 'new'])
              ->addColumn('enabled', 'boolean', ['default' => true, 'null' => false])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addColumn('updated_at', 'datetime', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['prefix'], ['unique' => true])
              ->create();
    }
}
