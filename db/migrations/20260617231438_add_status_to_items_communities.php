<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStatusToItemsCommunities extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('items_communities');

        if (!$table->hasColumn('status')) {
            $table->addColumn('status', 'string', [
                'limit' => 20,
                'default' => 'online',
                'null' => false,
                'comment' => 'Per-community visibility of this item: online or hidden (pending moderator approval)'
            ])
            ->update();
        }
    }
}
