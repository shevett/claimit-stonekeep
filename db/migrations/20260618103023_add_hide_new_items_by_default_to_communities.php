<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddHideNewItemsByDefaultToCommunities extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('communities');

        if (!$table->hasColumn('hide_new_items_by_default')) {
            $table->addColumn('hide_new_items_by_default', 'boolean', [
                'default' => true,
                'null' => false,
                'after' => 'moderated',
                'comment' => 'Whether new items posted to this community start hidden by default when moderation is enabled'
            ])
            ->update();
        }
    }
}
