<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersCommunitiesTable extends AbstractMigration
{
    public function change(): void
    {
        // Check if table already exists (idempotent)
        if ($this->hasTable('users_communities')) {
            return;
        }

        // Create users_communities junction table
        $table = $this->table('users_communities');
        $table->addColumn('user_id', 'string', [
                  'limit' => 255,
                  'null' => false,
                  'comment' => 'Foreign key to users.id'
              ])
              ->addColumn('community_id', 'integer', [
                  'null' => false,
                  'comment' => 'Foreign key to communities.id'
              ])
              ->addColumn('created_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP',
                  'null' => false
              ])
              ->addIndex(['user_id'])
              ->addIndex(['community_id'])
              ->addIndex(['user_id', 'community_id'], ['unique' => true])
              ->create();
    }
}
