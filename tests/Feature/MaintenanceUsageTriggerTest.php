<?php

use App\Models\Asset;
use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\MaintenanceItem;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Carbon;

function createMowerWithAdmin(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $mower = Asset::create(['property_id' => $property->id, 'created_by' => $admin->id, 'name' => 'Flail Mower']);

    return [$admin, $property, $mower];
}

/** Logs $hours of use of $asset, starting at $start. */
function useAsset(Asset $asset, User $user, float $hours, string $start = '2026-10-01 08:00:00', string $status = WorkSession::FINALISED): WorkSession
{
    $startedAt = Carbon::parse($start);
    $session = WorkSession::create([
        'property_id' => $asset->property_id,
        'user_id' => $user->id,
        'started_at' => $startedAt,
        'ended_at' => $startedAt->copy()->addMinutes((int) round($hours * 60)),
        'status' => $status,
    ]);
    $session->assets()->attach($asset);

    return $session;
}

function greaseItem(Asset $asset, User $user, array $attributes = []): MaintenanceItem
{
    return MaintenanceItem::create([
        'asset_id' => $asset->id,
        'created_by' => $user->id,
        'name' => 'Grease',
        'repeat_usage_hours' => 10,
        'usage_baseline_hours' => 0,
        'auto_generate' => true,
        ...$attributes,
    ]);
}

test('a usage-based item counts from the asset\'s usage when it is added', function () {
    [$admin, $property, $mower] = createMowerWithAdmin();
    useAsset($mower, $admin, 37);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('maintenance-items.store', $mower), [
            'name' => 'Grease',
            'repeat_usage_hours' => 10,
        ])
        ->assertSessionHasNoErrors();

    $item = $mower->maintenanceItems()->first();
    expect($item->next_due_usage_hours)->toBe(47.0)
        ->and($item->next_due_date)->toBeNull()
        ->and($item->isDue($mower->usageHours()))->toBeFalse();
});

test('an item needs a date trigger, a usage trigger, or both', function () {
    [$admin, $property, $mower] = createMowerWithAdmin();

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('maintenance-items.store', $mower), ['name' => 'Grease'])
        ->assertSessionHasErrors('repeat_period_days');
});

test('only finalised use counts toward a usage trigger', function () {
    [$admin, , $mower] = createMowerWithAdmin();
    $item = greaseItem($mower, $admin);

    useAsset($mower, $admin, 6);
    useAsset($mower, $admin, 6, '2026-10-02 08:00:00', WorkSession::DRAFT);
    expect($item->isDue($mower->usageHours()))->toBeFalse();

    useAsset($mower, $admin, 4, '2026-10-03 08:00:00');
    expect($item->isDue($mower->usageHours()))->toBeTrue();
});

test('an item with both triggers is due at whichever comes first', function () {
    [$admin, , $mower] = createMowerWithAdmin();
    $item = greaseItem($mower, $admin, ['repeat_period_days' => 90, 'start_date' => '2026-01-01', 'next_due_date' => today()->subDay()]);

    // Hardly used, but the date has passed.
    useAsset($mower, $admin, 1);
    expect($item->isDue($mower->usageHours()))->toBeTrue();
});

test('the scheduler creates one job when usage is reached, and no more while it is open', function () {
    [$admin, , $mower] = createMowerWithAdmin();
    $item = greaseItem($mower, $admin);
    useAsset($mower, $admin, 11);

    $this->artisan('maintenance:generate-jobs')->assertSuccessful();
    $this->artisan('maintenance:generate-jobs')->assertSuccessful();

    expect($item->jobs()->count())->toBe(1)
        ->and($item->fresh()->usage_baseline_hours)->toBe(0.0);
});

test('completing the job starts the next interval from that point', function () {
    [$admin, $property, $mower] = createMowerWithAdmin();
    $finished = JobStatus::create(['property_id' => $property->id, 'name' => 'Done', 'color' => 'green', 'order' => 9, 'is_finished_default' => true]);
    $item = greaseItem($mower, $admin, ['repeat_period_days' => 30, 'start_date' => '2026-09-01', 'next_due_date' => '2026-09-01']);
    useAsset($mower, $admin, 13);

    $job = $item->convertToJob($admin);
    Carbon::setTestNow('2026-10-10 12:00:00');
    $job->update(['job_status_id' => $finished->id]);
    Carbon::setTestNow();

    $item->refresh();
    expect($item->next_due_usage_hours)->toBe(23.0)
        ->and($item->next_due_date->toDateString())->toBe('2026-11-09')
        ->and($item->openJob())->toBeNull();
});

test('turning an item with an open job into a job goes to the open one instead', function () {
    [$admin, $property, $mower] = createMowerWithAdmin();
    $item = greaseItem($mower, $admin);
    $job = $item->convertToJob($admin);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('maintenance-items.convert', $item))
        ->assertRedirect(route('jobs.show', $job));

    expect($item->jobs()->count())->toBe(1);
});

test('the asset page flags an item that is due by usage and links its open job', function () {
    [$admin, $property, $mower] = createMowerWithAdmin();
    $item = greaseItem($mower, $admin);
    useAsset($mower, $admin, 12);
    $job = $item->convertToJob($admin);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('assets.show', $mower))
        ->assertInertia(fn ($page) => $page
            ->where('asset.maintenance_items.0.is_due', true)
            ->where('asset.maintenance_items.0.open_job.id', $job->id)
            ->where('asset.maintenance_items.0.next_due_usage_hours', 10)
        );
});

test('a maintenance job left on a non-finished status like Cancelled is flagged as open', function () {
    [$admin, $property, $mower] = createMowerWithAdmin();
    $cancelled = JobStatus::create(['property_id' => $property->id, 'name' => 'Cancelled', 'color' => 'gray', 'order' => 8]);
    $job = greaseItem($mower, $admin)->convertToJob($admin);
    $job->update(['job_status_id' => $cancelled->id]);

    $this->actingAs($admin)->withSession(['current_property_id' => $property->id]);

    $this->get(route('assets.show', $mower))
        ->assertInertia(fn ($page) => $page->where('asset.maintenance_items.0.open_job.status', 'Cancelled'));
    $this->get(route('manage.assets'))
        ->assertInertia(fn ($page) => $page->where('assets.0.maintenance_job_open', true));
    $this->get(route('manage.index'))
        ->assertInertia(fn ($page) => $page->where('assetsJobsOpen', 1));
});
