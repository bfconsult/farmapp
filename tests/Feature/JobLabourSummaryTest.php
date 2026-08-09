<?php

use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('the job page totals and lists only valued, confirmed labour logged against it', function () {
    $user = User::factory()->create();
    $worker = User::factory()->create(['name' => 'Doug Dolittle', 'hourly_rate' => 60]);
    $unpaidWorker = User::factory()->create(['name' => 'No Rate Worker', 'hourly_rate' => null]);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $user->id]);

    // Finalised with a rate - counts.
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $worker->id, 'farm_job_id' => $job->id,
        'started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addHours(3),
        'status' => WorkSession::FINALISED,
    ]);

    // Approved with a rate - also counts (approved is further along than finalised, not a different track).
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $worker->id, 'farm_job_id' => $job->id,
        'started_at' => now()->subDays(4), 'ended_at' => now()->subDays(4)->addHours(1),
        'status' => WorkSession::APPROVED,
    ]);

    // Draft, even with an end time and a valid rate - excluded, hours aren't confirmed yet.
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $worker->id, 'farm_job_id' => $job->id,
        'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHours(2),
        'status' => WorkSession::DRAFT,
    ]);

    // Finalised, but the worker has no rate set (and the job has none either) - excluded, nothing to value it at.
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $unpaidWorker->id, 'farm_job_id' => $job->id,
        'started_at' => now()->subDays(3), 'ended_at' => now()->subDays(3)->addHours(2),
        'status' => WorkSession::FINALISED,
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
            ->where('labourTotal', 240)
            ->where('labourEntries.0.user_name', 'Doug Dolittle')
            ->missing('labourEntries.0.status')
            ->where('labourEntries.1.user_name', 'Doug Dolittle'));
});

test('a job with no confirmed, valued time logged shows a zero labour total and no entries', function () {
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
