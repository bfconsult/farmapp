<?php

use App\Models\Expense;
use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

function createPropertyWithFinishedStatus(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $finishedStatus = JobStatus::create(['property_id' => $property->id, 'name' => 'Finished', 'order' => 1, 'is_finished_default' => true]);

    return [$admin, $property, $finishedStatus];
}

test('finishing a job via the dedicated action stamps completed_at', function () {
    [$admin, $property, $finishedStatus] = createPropertyWithFinishedStatus();
    $job = FarmJob::create(['name' => 'Fence the paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('jobs.finish', $job));

    expect($job->fresh()->completed_at)->not->toBeNull();
});

test('marking a job finished via a plain edit also stamps completed_at, and reverting it clears the stamp', function () {
    [$admin, $property, $finishedStatus] = createPropertyWithFinishedStatus();
    $openStatus = JobStatus::create(['property_id' => $property->id, 'name' => 'Open', 'order' => 2]);
    $job = FarmJob::create(['name' => 'Fence the paddock', 'property_id' => $property->id, 'user_id' => $admin->id, 'job_status_id' => $openStatus->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('jobs.update', $job), ['name' => 'Fence the paddock', 'job_status_id' => $finishedStatus->id])
        ->assertSessionHasNoErrors();

    expect($job->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('jobs.update', $job), ['name' => 'Fence the paddock', 'job_status_id' => $openStatus->id])
        ->assertSessionHasNoErrors();

    expect($job->fresh()->completed_at)->toBeNull();
});

test('completedDuringPeriod only includes jobs finished within the range, with all-time totals', function () {
    [$admin, $property, $finishedStatus] = createPropertyWithFinishedStatus();

    $inRange = FarmJob::create(['name' => 'Fence the paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $admin->id, 'farm_job_id' => $inRange->id,
        'started_at' => '2026-01-01 08:00:00', 'ended_at' => '2026-01-01 10:00:00', 'status' => WorkSession::FINALISED,
    ]);
    Expense::create(['farm_job_id' => $inRange->id, 'created_by' => $admin->id, 'name' => 'Materials', 'date' => '2026-01-01', 'amount' => 100, 'status' => Expense::COMPLETE]);
    $inRange->update(['job_status_id' => $finishedStatus->id]);
    $inRange->forceFill(['completed_at' => '2026-06-15'])->save();

    $outsideRange = FarmJob::create(['name' => 'Mow the lawn', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $outsideRange->update(['job_status_id' => $finishedStatus->id]);
    $outsideRange->forceFill(['completed_at' => '2026-08-01'])->save();

    $result = FarmJob::completedDuringPeriod($property->id, '2026-06-01', '2026-06-30');

    expect($result->pluck('id')->all())->toBe([$inRange->id]);
    expect($result->first()->total_hours)->toBe(2.0);
    expect($result->first()->total_expenses)->toBe(100.0);
});

test('openAsOf excludes finished jobs and caps totals at the period end', function () {
    [$admin, $property, $finishedStatus] = createPropertyWithFinishedStatus();

    $open = FarmJob::create(['name' => 'Fence the paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $open->forceFill(['created_at' => '2026-06-01 09:00:00'])->save();
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $admin->id, 'farm_job_id' => $open->id,
        'started_at' => '2026-06-10 08:00:00', 'ended_at' => '2026-06-10 10:00:00', 'status' => WorkSession::FINALISED,
    ]);
    // Booked after the report's end date - excluded from the capped total.
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $admin->id, 'farm_job_id' => $open->id,
        'started_at' => '2026-07-10 08:00:00', 'ended_at' => '2026-07-10 12:00:00', 'status' => WorkSession::FINALISED,
    ]);

    $finished = FarmJob::create(['name' => 'Mow the lawn', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $finished->update(['job_status_id' => $finishedStatus->id]);

    $result = FarmJob::openAsOf($property->id, '2026-06-30 23:59:59');

    expect($result->pluck('id')->all())->toBe([$open->id]);
    expect($result->first()->total_hours)->toBe(2.0);
});

test('openAsOf excludes jobs created after the period end', function () {
    [$admin, $property] = createPropertyWithFinishedStatus();

    $lastDay = FarmJob::create(['name' => 'Fence the paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $lastDay->forceFill(['created_at' => '2026-06-30 17:00:00'])->save();

    $afterPeriod = FarmJob::create(['name' => 'Mow the lawn', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $afterPeriod->forceFill(['created_at' => '2026-07-01 08:00:00'])->save();

    $result = FarmJob::openAsOf($property->id, \Carbon\Carbon::parse('2026-06-30')->endOfDay());

    expect($result->pluck('id')->all())->toBe([$lastDay->id]);
});

test('openAsOf includes jobs finished after the period end, but not those finished within it', function () {
    [$admin, $property, $finishedStatus] = createPropertyWithFinishedStatus();

    $finishedLater = FarmJob::create(['name' => 'Fence the paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $finishedLater->update(['job_status_id' => $finishedStatus->id]);
    $finishedLater->forceFill(['created_at' => '2026-06-01 09:00:00', 'completed_at' => '2026-07-05 12:00:00'])->save();

    $finishedWithin = FarmJob::create(['name' => 'Mow the lawn', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $finishedWithin->update(['job_status_id' => $finishedStatus->id]);
    $finishedWithin->forceFill(['created_at' => '2026-06-01 09:00:00', 'completed_at' => '2026-06-20 12:00:00'])->save();

    $result = FarmJob::openAsOf($property->id, \Carbon\Carbon::parse('2026-06-30')->endOfDay());

    expect($result->pluck('id')->all())->toBe([$finishedLater->id]);
});

test('the diary PDF renders without error when Completed and Open Jobs sections are present', function () {
    [$admin, $property, $finishedStatus] = createPropertyWithFinishedStatus();

    $completed = FarmJob::create(['name' => 'Fence the paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $completed->update(['job_status_id' => $finishedStatus->id]);
    FarmJob::create(['name' => 'Mow the lawn', 'property_id' => $property->id, 'user_id' => $admin->id]);

    $response = $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('reports.diary-preview.pdf', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]));

    $response->assertOk();
});
