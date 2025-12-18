<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateItemsTable extends AbstractMigration
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
        $table = $this->table('items');
        $table->addColumn('tracking_number', 'string', ['limit' => 19])
              ->addColumn('user_id', 'string', ['limit' => 255])
              ->addColumn('title', 'string', ['limit' => 255])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00'])
              ->addColumn('status', 'string', ['limit' => 50, 'default' => 'available'])
              ->addColumn('image_file', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('additional_images', 'text', ['null' => true])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['tracking_number'], ['unique' => true])
              ->addIndex(['user_id'])
              ->addIndex(['status'])
              ->addIndex(['created_at'])
              ->create();
    }
}
