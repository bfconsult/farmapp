<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $priorities = [
            ['name' => 'Low', 'order' => 1],
            ['name' => 'Medium', 'order' => 2],
            ['name' => 'High', 'order' => 3],
            ['name' => 'Critical', 'order' => 4],
        ];
    
        foreach ($priorities as $priority) {
            \App\Models\Priority::create($priority);
        }
    }
    
}
