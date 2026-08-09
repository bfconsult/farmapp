<?php

use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('admin, manager, and approver can each log a complete session for a worker', function (string $roleType) {
    $actor = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $actor->id, 'property_id' => $property->id, 'type' => $roleType]);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $this->actingAs($actor)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $worker->id,
            'description' => 'Fixed the pump',
            'started_at' => '2026-06-15 08:00:00',
            'ended_at' => '2026-06-15 10:00:00',
        ])
        ->assertSessionHasNoErrors();

    $session = WorkSession::where('property_id', $property->id)->firstOrFail();
    expect($session->user_id)->toBe($worker->id);
    expect($session->created_by)->toBe($actor->id);
    expect($session->status)->toBe(WorkSession::DRAFT);
    expect($session->source)->toBe('manual_on_behalf');
})->with(['admin', 'manager', 'approver']);

test('a worker cannot log time on behalf of anyone', function () {
    $worker = User::factory()->create();
    $otherWorker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    Role::create(['user_id' => $otherWorker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $this->actingAs($worker)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $otherWorker->id,
            'started_at' => '2026-06-15 08:00:00',
            'ended_at' => '2026-06-15 10:00:00',
        ])
        ->assertForbidden();

    expect(WorkSession::count())->toBe(0);
});

test('a session that overlaps an existing one for that worker is rejected, but back-to-back entries are allowed', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $worker->id,
        'started_at' => '2026-06-15 09:00:00',
        'ended_at' => '2026-06-15 12:00:00',
        'status' => WorkSession::DRAFT,
    ]);

    // Overlaps the draft session above.
    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $worker->id,
            'started_at' => '2026-06-15 11:00:00',
            'ended_at' => '2026-06-15 13:00:00',
        ])
        ->assertSessionHasErrors('started_at');

    // Starts exactly when the other one ends - not an overlap.
    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $worker->id,
            'started_at' => '2026-06-15 12:00:00',
            'ended_at' => '2026-06-15 13:00:00',
        ])
        ->assertSessionHasNoErrors();

    expect(WorkSession::where('user_id', $worker->id)->count())->toBe(2);
});

test('an existing open-ended session blocks any later entry for that worker', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $worker->id,
        'started_at' => '2026-06-15 09:00:00',
        'ended_at' => null,
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $worker->id,
            'started_at' => '2026-06-15 14:00:00',
            'ended_at' => '2026-06-15 15:00:00',
        ])
        ->assertSessionHasErrors('started_at');
});

test('overlap is only checked against the same worker, not other workers', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create();
    $otherWorker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    Role::create(['user_id' => $otherWorker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $worker->id,
        'started_at' => '2026-06-15 09:00:00',
        'ended_at' => '2026-06-15 12:00:00',
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $otherWorker->id,
            'started_at' => '2026-06-15 10:00:00',
            'ended_at' => '2026-06-15 11:00:00',
        ])
        ->assertSessionHasNoErrors();
});

test('a worker or job from a different property is rejected', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $foreignWorker = User::factory()->create();
    Role::create(['user_id' => $foreignWorker->id, 'property_id' => $otherProperty->id, 'type' => Role::WORKER]);

    $localWorker = User::factory()->create();
    Role::create(['user_id' => $localWorker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $foreignJob = FarmJob::create([
        'name' => 'Foreign job',
        'property_id' => $otherProperty->id,
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $foreignWorker->id,
            'started_at' => '2026-06-15 08:00:00',
            'ended_at' => '2026-06-15 10:00:00',
        ])
        ->assertSessionHasErrors('user_id');

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $localWorker->id,
            'farm_job_id' => $foreignJob->id,
            'started_at' => '2026-06-15 08:00:00',
            'ended_at' => '2026-06-15 10:00:00',
        ])
        ->assertSessionHasErrors('farm_job_id');
});

test('a missing end time is rejected - on-behalf entries must be complete', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $worker->id,
            'started_at' => '2026-06-15 08:00:00',
        ])
        ->assertSessionHasErrors('ended_at');

    expect(WorkSession::count())->toBe(0);
});

test('duration is computed from the target worker\'s billing block, not the actor\'s', function () {
    $admin = User::factory()->create(['billing_block_minutes' => null]);
    $worker = User::factory()->create(['billing_block_minutes' => 30]);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $worker->id,
            'started_at' => '2026-06-15 08:00:00',
            'ended_at' => '2026-06-15 08:40:00',
        ])
        ->assertSessionHasNoErrors();

    $session = WorkSession::where('user_id', $worker->id)->firstOrFail();
    // 40 raw minutes, ceiled to the worker's 30-minute block = 60 minutes = 1h.
    expect($session->duration_in_hours)->toBe(1.0);
});

test('attaching a job on the on-behalf form still promotes it out of Backlog', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    JobStatus::seedDefaultsForProperty($property->id);
    $backlog = JobStatus::where('property_id', $property->id)->where('is_default', true)->firstOrFail();
    $inProgress = JobStatus::where('property_id', $property->id)->where('is_in_progress_default', true)->firstOrFail();

    $job = FarmJob::create([
        'name' => 'Fence the north paddock',
        'property_id' => $property->id,
        'job_status_id' => $backlog->id,
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.work-sessions.store'), [
            'user_id' => $worker->id,
            'farm_job_id' => $job->id,
            'started_at' => '2026-06-15 08:00:00',
            'ended_at' => '2026-06-15 10:00:00',
        ])
        ->assertSessionHasNoErrors();

    expect($job->fresh()->job_status_id)->toBe($inProgress->id);
});
