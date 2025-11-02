<?php

// database/seeders/IconSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class IconSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $icons = Config::get('components.icons', []);

        if (empty($icons)) {
            $this->command->error('No icons found in config/components.php');

            return;
        }

        $this->command->info('Seeding icons...');

        foreach ($icons as $name => $svgContent) {
            DB::table('icons')->updateOrInsert(
                ['name' => $name],
                [
                    'svg_content' => $svgContent,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Icons seeded successfully!');
    }
}
