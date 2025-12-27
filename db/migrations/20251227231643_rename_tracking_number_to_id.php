<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameTrackingNumberToId extends AbstractMigration
{
    public function up(): void
    {
        // Rename columns using MySQL 8.0 RENAME COLUMN syntax (simpler and avoids issues)
        $this->execute('ALTER TABLE claims RENAME COLUMN item_tracking_number TO item_id');
        $this->execute('ALTER TABLE items_communities RENAME COLUMN item_tracking_number TO item_id');
        $this->execute('ALTER TABLE items RENAME COLUMN tracking_number TO id');
    }

    public function down(): void
    {
        // Revert using RENAME COLUMN
        $this->execute('ALTER TABLE items RENAME COLUMN id TO tracking_number');
        $this->execute('ALTER TABLE claims RENAME COLUMN item_id TO item_tracking_number');
        $this->execute('ALTER TABLE items_communities RENAME COLUMN item_id TO item_tracking_number');
    }
}
