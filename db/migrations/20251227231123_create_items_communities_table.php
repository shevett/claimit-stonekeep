<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateItemsCommunitiesTable extends AbstractMigration
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
        // Check if table already exists
        if ($this->hasTable('items_communities')) {
            return;
        }

        // Create items_communities junction table
        $table = $this->table('items_communities');
        $table->addColumn('item_tracking_number', 'string', [
                  'limit' => 19,
                  'null' => false,
                  'comment' => 'Foreign key to items.tracking_number'
              ])
              ->addColumn('community_id', 'integer', [
                  'null' => false,
                  'comment' => 'Foreign key to communities.id'
              ])
              ->addColumn('created_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP',
                  'null' => false
              ])
              ->addIndex(['item_tracking_number'])
              ->addIndex(['community_id'])
              ->addIndex(['item_tracking_number', 'community_id'], ['unique' => true])
              ->create();
    }
}
