<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDiscordWebhookToCommunities extends AbstractMigration
{
    /**
     * Add Discord webhook fields to communities table
     */
    public function change(): void
    {
        $table = $this->table('communities');

        if (!$table->hasColumn('discord_webhook_url')) {
            $table->addColumn('discord_webhook_url', 'text', [
                'null' => true,
                'after' => 'slack_enabled',
                'comment' => 'Discord incoming webhook URL for notifications'
            ]);
        }

        if (!$table->hasColumn('discord_enabled')) {
            $table->addColumn('discord_enabled', 'boolean', [
                'default' => false,
                'null' => false,
                'after' => 'discord_webhook_url',
                'comment' => 'Enable/disable Discord notifications for this community'
            ]);
        }

        $table->update();
    }
}
