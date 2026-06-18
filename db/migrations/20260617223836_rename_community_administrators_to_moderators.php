<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameCommunityAdministratorsToModerators extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('community_administrators') && !$this->hasTable('community_moderators')) {
            $this->table('community_administrators')->rename('community_moderators')->update();
        }
    }
}
