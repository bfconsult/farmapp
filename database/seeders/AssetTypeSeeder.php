<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AssetTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Fixed'],
            ['name' => 'Plant'],
            ['name' => 'Stock'],
        ];

        foreach ($types as $type) {
            \App\Models\AssetType::create($type);
        }
    }
}
