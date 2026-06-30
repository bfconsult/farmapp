<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JobTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Maintenance'],
            ['name' => 'Improvement'],
            ['name' => 'Proposed'],
        ];
    
        foreach ($types as $type) {
            \App\Models\JobType::create($type);
        }
    }
}
