<?php

use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('converting an auto-tracked visit to a job promotes the job out of Backlog', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    JobStatus::seedDefaultsForProperty($property->id);
    $backlog = JobStatus::where('property_id', $property->id)->where('is_default', true)->firstOrFail();
    $inProgress = JobStatus::where('property_id', $property->id)->where('is_in_progress_default', true)->firstOrFail();

    $job = FarmJob::create([
        'name' => 'Fence the north paddock',
        'property_id' => $property->id,
        'job_status_id' => $backlog->id,
        'user_id' => $user->id,
    ]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'source' => 'auto_tracked',
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->put(route('work-sessions.update', $session), [
            'farm_job_id' => $job->id,
            'started_at' => $session->started_at->toIso8601String(),
            'ended_at' => $session->ended_at->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    expect($job->fresh()->job_status_id)->toBe($inProgress->id);
});

test('editing a draft session that already has a job attached does not re-promote it', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    JobStatus::seedDefaultsForProperty($property->id);
    $completed = JobStatus::where('property_id', $property->id)->where('is_finished_default', true)->firstOrFail();

    // A job someone has already moved past "in progress" manually.
    $job = FarmJob::create([
        'name' => 'Fence the north paddock',
        'property_id' => $property->id,
        'job_status_id' => $completed->id,
        'user_id' => $user->id,
    ]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'farm_job_id' => $job->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->put(route('work-sessions.update', $session), [
            'farm_job_id' => $job->id,
            'description' => 'Updated note',
            'started_at' => $session->started_at->toIso8601String(),
            'ended_at' => $session->ended_at->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    expect($job->fresh()->job_status_id)->toBe($completed->id);
});
