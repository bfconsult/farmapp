<?php

use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('the job page totals and lists labour logged against it', function () {
    $user = User::factory()->create();
    $worker = User::factory()->create(['name' => 'Doug Dolittle', 'hourly_rate' => 60]);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $user->id]);

    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $worker->id, 'farm_job_id' => $job->id,
        'started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addHours(3),
        'status' => WorkSession::FINALISED,
    ]);

    // A session with no end time yet (still active) - no billing amount,
    // but should still be listed with an hours-less, amount-less entry.
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $worker->id, 'farm_job_id' => $job->id,
        'started_at' => now(), 'status' => WorkSession::DRAFT,
    ]);

    // Belongs to a different job entirely - must not be included.
    $otherJob = FarmJob::create(['name' => 'Unrelated job', 'property_id' => $property->id, 'user_id' => $user->id]);
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $worker->id, 'farm_job_id' => $otherJob->id,
        'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHours(5),
        'status' => WorkSession::FINALISED,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('jobs.show', $job->id))
        ->assertInertia(fn ($page) => $page
            ->component('Jobs/Show')
            ->has('labourEntries', 2)
            ->where('labourTotal', 180)
            ->where('labourEntries.0.user_name', 'Doug Dolittle'));
});

test('a job with no time logged shows a zero labour total and no entries', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('jobs.show', $job->id))
        ->assertInertia(fn ($page) => $page
            ->component('Jobs/Show')
            ->has('labourEntries', 0)
            ->where('labourTotal', 0));
});
