<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JobStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'Backlog', 'order' => 1, 'can_book_time' => true],
            ['name' => 'In Progress', 'order' => 2, 'can_book_time' => true],
            ['name' => 'Completed', 'order' => 3, 'can_book_time' => false],
            ['name' => 'Cancelled', 'order' => 4, 'can_book_time' => false],
        ];
    
        foreach ($statuses as $status) {
            \App\Models\JobStatus::create($status);
        }
    }
    
}
