<?php

use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;

function createPropertyAdminAndFinishedStatus(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $openStatus = JobStatus::create(['property_id' => $property->id, 'name' => 'Open', 'order' => 1, 'can_book_time' => true]);
    $finishedStatus = JobStatus::create(['property_id' => $property->id, 'name' => 'Finished', 'order' => 2, 'can_book_time' => true, 'is_finished_default' => true]);

    return [$admin, $property, $openStatus, $finishedStatus];
}

function visitJobsIndexFor($admin, $property, $dateFrom, $dateTo)
{
    $statusIds = JobStatus::where('property_id', $property->id)->pluck('id')->all();

    return test()->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('jobs.index', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'statuses' => $statusIds]));
}

test('an open job created long before the date range still shows - it is not finished yet', function () {
    [$admin, $property, $openStatus] = createPropertyAdminAndFinishedStatus();
    $job = FarmJob::create([
        'name' => 'Ute - Replace engine oil and clutch', 'property_id' => $property->id,
        'user_id' => $admin->id, 'job_status_id' => $openStatus->id,
    ]);
    $job->forceFill(['created_at' => '2026-07-19'])->save();
    $job->assignees()->attach($admin->id);

    $response = visitJobsIndexFor($admin, $property, '2026-09-01', '2026-09-30');

    $response->assertInertia(fn ($page) => $page->where('jobs.0.id', $job->id));
});

test('a job finished within the selected range shows', function () {
    [$admin, $property, $openStatus, $finishedStatus] = createPropertyAdminAndFinishedStatus();
    $job = FarmJob::create(['name' => 'Fence repair', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $job->forceFill(['created_at' => '2026-01-01'])->save();
    $job->update(['job_status_id' => $finishedStatus->id]); // stamps completed_at = now()
    $job->forceFill(['completed_at' => '2026-09-15'])->save();
    $job->assignees()->attach($admin->id);

    $response = visitJobsIndexFor($admin, $property, '2026-09-01', '2026-09-30');

    $response->assertInertia(fn ($page) => $page->where('jobs.0.id', $job->id));
});

test('a job finished outside the selected range is excluded', function () {
    [$admin, $property, $openStatus, $finishedStatus] = createPropertyAdminAndFinishedStatus();
    $job = FarmJob::create(['name' => 'Fence repair', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $job->forceFill(['created_at' => '2026-01-01'])->save();
    $job->update(['job_status_id' => $finishedStatus->id]);
    $job->forceFill(['completed_at' => '2026-05-15'])->save();
    $job->assignees()->attach($admin->id);

    $response = visitJobsIndexFor($admin, $property, '2026-09-01', '2026-09-30');

    $response->assertInertia(fn ($page) => $page->where('jobs', []));
});

test('a legacy finished job with no completed_at falls back to created_at', function () {
    [$admin, $property, $openStatus, $finishedStatus] = createPropertyAdminAndFinishedStatus();

    $inRange = FarmJob::create(['name' => 'Old job, finished before completed_at existed', 'property_id' => $property->id, 'user_id' => $admin->id, 'job_status_id' => $finishedStatus->id]);
    $inRange->assignees()->attach($admin->id);
    // Undo the saving-event stamp to simulate genuinely legacy data.
    $inRange->forceFill(['created_at' => '2026-09-10', 'completed_at' => null])->save();

    $outOfRange = FarmJob::create(['name' => 'Old job, created earlier', 'property_id' => $property->id, 'user_id' => $admin->id, 'job_status_id' => $finishedStatus->id]);
    $outOfRange->assignees()->attach($admin->id);
    $outOfRange->forceFill(['created_at' => '2026-01-01', 'completed_at' => null])->save();

    $response = visitJobsIndexFor($admin, $property, '2026-09-01', '2026-09-30');

    $response->assertInertia(fn ($page) => $page->where('jobs.0.id', $inRange->id)->where('jobs', fn ($jobs) => count($jobs) === 1));
});
