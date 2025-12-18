<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddMissingColumnsToItems extends AbstractMigration
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
        // Add missing columns to items table
        $items = $this->table('items');
        $items->addColumn('contact_email', 'string', ['limit' => 255, 'null' => true, 'after' => 'price'])
              ->addColumn('image_width', 'integer', ['null' => true, 'after' => 'image_file'])
              ->addColumn('image_height', 'integer', ['null' => true, 'after' => 'image_width'])
              ->addColumn('user_name', 'string', ['limit' => 255, 'null' => true, 'after' => 'user_id'])
              ->addColumn('user_email', 'string', ['limit' => 255, 'null' => true, 'after' => 'user_name'])
              ->addColumn('submitted_at', 'datetime', ['null' => true, 'after' => 'user_email'])
              ->addColumn('submitted_timestamp', 'integer', ['null' => true, 'after' => 'submitted_at'])
              ->addColumn('gone', 'boolean', ['default' => false, 'after' => 'status'])
              ->addColumn('gone_at', 'datetime', ['null' => true, 'after' => 'gone'])
              ->addColumn('gone_by', 'string', ['limit' => 255, 'null' => true, 'after' => 'gone_at'])
              ->addColumn('relisted_at', 'datetime', ['null' => true, 'after' => 'gone_by'])
              ->addColumn('relisted_by', 'string', ['limit' => 255, 'null' => true, 'after' => 'relisted_at'])
              ->update();
        
        // Create claims table
        $claims = $this->table('claims');
        $claims->addColumn('item_tracking_number', 'string', ['limit' => 19])
               ->addColumn('user_id', 'string', ['limit' => 255])
               ->addColumn('user_name', 'string', ['limit' => 255, 'null' => true])
               ->addColumn('user_email', 'string', ['limit' => 255, 'null' => true])
               ->addColumn('claimed_at', 'datetime')
               ->addColumn('status', 'string', ['limit' => 50, 'default' => 'active'])
               ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
               ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
               ->addIndex(['item_tracking_number'])
               ->addIndex(['user_id'])
               ->addIndex(['status'])
               ->addIndex(['item_tracking_number', 'user_id', 'claimed_at'], ['unique' => true])
               ->create();
    }
}
