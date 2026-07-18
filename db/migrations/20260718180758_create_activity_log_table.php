<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateActivityLogTable extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('activity_log')) {
            return;
        }

        $table = $this->table('activity_log');
        $table->addColumn('occurred_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addColumn('action', 'string', ['limit' => 64, 'null' => false, 'comment' => 'e.g. item_posted, item_gone, claim_added'])
              ->addColumn('user_id', 'string', ['limit' => 255, 'null' => true, 'comment' => 'Acting user; FK to users.id, null for system/anonymous'])
              ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
              ->addColumn('item_id', 'string', ['limit' => 32, 'null' => true, 'comment' => 'FK to items.id (tracking number)'])
              ->addColumn('community_id', 'integer', ['null' => true, 'comment' => 'FK to communities.id'])
              ->addColumn('target_user_id', 'string', ['limit' => 255, 'null' => true, 'comment' => 'e.g. moderator added/removed, claim removed by owner'])
              ->addColumn('details', 'text', ['null' => true, 'comment' => 'Free-form JSON context, e.g. {"old_title":...,"new_title":...}'])
              ->addIndex(['occurred_at'])
              ->addIndex(['user_id'])
              ->addIndex(['item_id'])
              ->addIndex(['community_id'])
              ->addIndex(['action'])
              ->create();
    }
}
