<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameTrackingNumberToId extends AbstractMigration
{
    public function up(): void
    {
        // Check if the auto-increment id column exists (it does in prod, but not in dev after our clean migration)
        $items = $this->table('items');
        if ($items->hasColumn('id')) {
            // Production case: Need to drop the auto-increment id column first
            // Step 1: Remove AUTO_INCREMENT from the id column (required before dropping PRIMARY KEY)
            $this->execute('ALTER TABLE items MODIFY COLUMN id INT NOT NULL');
            // Step 2: Drop the primary key
            $this->execute('ALTER TABLE items DROP PRIMARY KEY');
            // Step 3: Drop the old id column
            $this->execute('ALTER TABLE items DROP COLUMN id');
        }
        
        // Rename columns using MySQL 8.0 RENAME COLUMN syntax
        $this->execute('ALTER TABLE claims RENAME COLUMN item_tracking_number TO item_id');
        $this->execute('ALTER TABLE items_communities RENAME COLUMN item_tracking_number TO item_id');
        $this->execute('ALTER TABLE items RENAME COLUMN tracking_number TO id');
        
        // Make the new id column the primary key
        $this->execute('ALTER TABLE items ADD PRIMARY KEY (id)');
    }

    public function down(): void
    {
        // Remove primary key from id
        $this->execute('ALTER TABLE items DROP PRIMARY KEY');
        
        // Revert using RENAME COLUMN
        $this->execute('ALTER TABLE items RENAME COLUMN id TO tracking_number');
        $this->execute('ALTER TABLE claims RENAME COLUMN item_id TO item_tracking_number');
        $this->execute('ALTER TABLE items_communities RENAME COLUMN item_id TO item_tracking_number');
        
        // Re-add the auto-increment id column
        $this->execute('ALTER TABLE items ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST');
    }
}
