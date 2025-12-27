<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCommunitiesTable extends AbstractMigration
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
        $table = $this->table('communities');
        $table->addColumn('short_name', 'string', ['limit' => 50, 'null' => false])
              ->addColumn('full_name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('owner_id', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addColumn('updated_at', 'datetime', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['short_name'], ['unique' => true])
              ->addIndex(['owner_id'])
              ->create();
    }
}
