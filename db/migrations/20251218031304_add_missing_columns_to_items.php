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
        // Add missing columns to items table (check if they exist first)
        $items = $this->table('items');
        
        if (!$items->hasColumn('contact_email')) {
            $items->addColumn('contact_email', 'string', ['limit' => 255, 'null' => true]);
        }
        if (!$items->hasColumn('image_width')) {
            $items->addColumn('image_width', 'integer', ['null' => true]);
        }
        if (!$items->hasColumn('image_height')) {
            $items->addColumn('image_height', 'integer', ['null' => true]);
        }
        if (!$items->hasColumn('user_name')) {
            $items->addColumn('user_name', 'string', ['limit' => 255, 'null' => true]);
        }
        if (!$items->hasColumn('user_email')) {
            $items->addColumn('user_email', 'string', ['limit' => 255, 'null' => true]);
        }
        if (!$items->hasColumn('submitted_at')) {
            $items->addColumn('submitted_at', 'datetime', ['null' => true]);
        }
        if (!$items->hasColumn('submitted_timestamp')) {
            $items->addColumn('submitted_timestamp', 'integer', ['null' => true]);
        }
        if (!$items->hasColumn('gone')) {
            $items->addColumn('gone', 'boolean', ['default' => false]);
        }
        if (!$items->hasColumn('gone_at')) {
            $items->addColumn('gone_at', 'datetime', ['null' => true]);
        }
        if (!$items->hasColumn('gone_by')) {
            $items->addColumn('gone_by', 'string', ['limit' => 255, 'null' => true]);
        }
        if (!$items->hasColumn('relisted_at')) {
            $items->addColumn('relisted_at', 'datetime', ['null' => true]);
        }
        if (!$items->hasColumn('relisted_by')) {
            $items->addColumn('relisted_by', 'string', ['limit' => 255, 'null' => true]);
        }
        $items->update();
        
        // Create claims table (only if it doesn't exist)
        if (!$this->hasTable('claims')) {
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
}
