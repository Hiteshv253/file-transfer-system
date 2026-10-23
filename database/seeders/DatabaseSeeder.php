<?php

namespace Database\Seeders;

use Common\Tags\Tag;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // create tag for starring file entries
        Tag::firstOrCreate([
            'name' => 'starred',
            'display_name' => 'Starred',
            'type' => 'label',
        ]);

        $this->call([
            S3AccessUsersSeeder::class,
            SeedDemoProducts::class,
        ]);
    }
}
