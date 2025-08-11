<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Initial Agreed Amount',
            'Out of Scope',
        ];

        foreach ($types as $name) {
            TransactionType::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
