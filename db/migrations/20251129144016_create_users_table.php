<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
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
        $table = $this->table('users', ['id' => false, 'primary_key' => 'id']);
        
        // Google OAuth fields (from user JSON)
        $table->addColumn('id', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('picture', 'string', ['limit' => 500, 'null' => true])
              ->addColumn('verified_email', 'boolean', ['default' => false, 'null' => false])
              ->addColumn('locale', 'string', ['limit' => 10, 'null' => true])
              ->addColumn('last_login', 'datetime', ['null' => true])
              ->addColumn('created_at', 'datetime', ['null' => false])
              
              // User settings fields (from user YAML)
              ->addColumn('display_name', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('zipcode', 'string', ['limit' => 10, 'null' => true])
              ->addColumn('show_gone_items', 'boolean', ['default' => false, 'null' => false])
              ->addColumn('email_notifications', 'boolean', ['default' => false, 'null' => false])
              ->addColumn('new_listing_notifications', 'boolean', ['default' => false, 'null' => false])
              ->addColumn('updated_at', 'datetime', ['null' => true])
              
              // Indexes
              ->addIndex(['email'], ['unique' => true])
              ->create();
    }
}
