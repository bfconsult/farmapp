<?php

use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\Property;
use App\Models\RecurringJob;
use App\Models\User;
use Carbon\Carbon;

afterEach(fn () => Carbon::setTestNow());

test('auto-closing a recurring instance stamps completed_at at its period end, not the day the command runs', function () {
    Carbon::setTestNow('2026-09-01 08:00:00');
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    JobStatus::create(['property_id' => $property->id, 'name' => 'Closed', 'order' => 1, 'is_finished_default' => true, 'is_recurring_closed_default' => true]);

    $template = RecurringJob::create([
        'property_id' => $property->id,
        'created_by' => $user->id,
        'name' => 'Stock Management',
        'interval' => RecurringJob::MONTHLY,
        'starts_on' => '2026-09-01',
        'is_active' => true,
    ]);
    $september = $template->createInstance(Carbon::parse('2026-09-01'));

    Carbon::setTestNow('2026-10-02 03:00:00');
    $this->artisan('jobs:generate-recurring')->assertSuccessful();

    $september->refresh();
    expect($september->completed_at)->toBe('2026-09-30 23:59:59');

    $completedInOctober = FarmJob::completedDuringPeriod($property->id, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
    expect($completedInOctober->pluck('id')->all())->not->toContain($september->id);

    $openAtEndOfSeptember = FarmJob::openAsOf($property->id, Carbon::parse('2026-09-30')->endOfDay());
    expect($openAtEndOfSeptember->pluck('id')->all())->not->toContain($september->id);
});
