<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePostalCodesTable extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('postal_codes')) {
            return;
        }

        $table = $this->table('postal_codes');
        $table
            ->addColumn('country_code', 'char', ['limit' => 2, 'null' => false, 'comment' => 'ISO 3166-1 alpha-2 country code'])
            ->addColumn('postal_code', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('place_name', 'string', ['limit' => 180, 'null' => false])
            ->addColumn('admin_name1', 'string', ['limit' => 100, 'null' => true, 'comment' => 'State / 1st-order subdivision name'])
            ->addColumn('admin_code1', 'string', ['limit' => 20, 'null' => true, 'comment' => 'State code (e.g. AK)'])
            ->addColumn('admin_name2', 'string', ['limit' => 100, 'null' => true, 'comment' => 'County / 2nd-order subdivision name'])
            ->addColumn('admin_code2', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('admin_name3', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Community / 3rd-order subdivision name'])
            ->addColumn('admin_code3', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('latitude', 'decimal', ['precision' => 9, 'scale' => 6, 'null' => true, 'comment' => 'WGS84 latitude'])
            ->addColumn('longitude', 'decimal', ['precision' => 9, 'scale' => 6, 'null' => true, 'comment' => 'WGS84 longitude'])
            ->addColumn('accuracy', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'null' => true, 'comment' => '1=estimated, 4=geonameid, 6=centroid'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['country_code', 'postal_code', 'place_name'], ['unique' => true, 'name' => 'idx_postal_codes_unique'])
            ->addIndex(['country_code', 'postal_code'], ['name' => 'idx_postal_codes_lookup'])
            ->addIndex(['latitude', 'longitude'], ['name' => 'idx_postal_codes_geo'])
            ->create();
    }
}
