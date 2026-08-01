<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Job statuses, priorities, job types, and asset types are all
        // seeded per-property now (JobStatus/Priority/JobType/AssetType::
        // seedDefaultsForProperty), triggered automatically by
        // PropertyController::store() - nothing to pre-seed globally here,
        // since each table's property_id is required and no property exists
        // yet at this point. Suppliers were never pre-seeded either way -
        // real per-account business contacts, not a template list.

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
