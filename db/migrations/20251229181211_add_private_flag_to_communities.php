<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPrivateFlagToCommunities extends AbstractMigration
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
        $table = $this->table('communities');
        
        // Check if private column already exists (idempotent)
        if (!$table->hasColumn('private')) {
            $table->addColumn('private', 'boolean', [
                'default' => false,
                'null' => false,
                'after' => 'description',
                'comment' => 'Whether this community is private (requires membership to see items)'
            ])
            ->update();
        }
    }
}
