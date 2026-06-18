<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddModeratedToCommunities extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('communities');

        if (!$table->hasColumn('moderated')) {
            $table->addColumn('moderated', 'boolean', [
                'default' => false,
                'null' => false,
                'after' => 'private',
                'comment' => 'Whether new items posted to this community require moderator approval before becoming visible'
            ])
            ->update();
        }
    }
}
