<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSlackWebhookToCommunities extends AbstractMigration
{
    /**
     * Add Slack webhook fields to communities table
     */
    public function change(): void
    {
        $table = $this->table('communities');
        
        // Check if columns don't already exist before adding
        if (!$table->hasColumn('slack_webhook_url')) {
            $table->addColumn('slack_webhook_url', 'text', [
                'null' => true,
                'after' => 'private',
                'comment' => 'Slack incoming webhook URL for notifications'
            ]);
        }
        
        if (!$table->hasColumn('slack_enabled')) {
            $table->addColumn('slack_enabled', 'boolean', [
                'default' => false,
                'null' => false,
                'after' => 'slack_webhook_url',
                'comment' => 'Enable/disable Slack notifications for this community'
            ]);
        }
        
        $table->update();
    }
}
