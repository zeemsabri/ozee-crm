<?php
// database/seeders/ComponentSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class ComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $components = Config::get('components.components', []);

        if (empty($components)) {
            $this->command->error("No components found in config/components.php");
            return;
        }

        $this->command->info('Seeding components...');

        foreach ($components as $type => $def) {
            // Find the corresponding icon ID
            $icon = DB::table('icons')->where('name', $def['icon'])->first();

            DB::table('components')->updateOrInsert(
                ['type' => $type],
                [
                    'name'       => $def['name'],
                    'category'   => $def['category'],
                    'definition' => json_encode($def['default']),
                    'icon_id'    => $icon ? $icon->id : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Components seeded successfully!');
    }
}
