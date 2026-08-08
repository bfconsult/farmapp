<?php

use App\Models\Property;
use App\Models\RecurringJob;
use App\Models\User;
use Carbon\Carbon;

function makeRecurringJob(string $interval): RecurringJob
{
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);

    return RecurringJob::create([
        'property_id' => $property->id,
        'created_by' => $user->id,
        'name' => 'Stock Management',
        'interval' => $interval,
        'starts_on' => '2026-07-01',
        'is_active' => true,
    ]);
}

test('a monthly instance title gets the month name appended', function () {
    $job = makeRecurringJob(RecurringJob::MONTHLY)->createInstance(Carbon::parse('2026-07-01'));
    expect($job->name)->toBe('Stock Management - July');
});

test('a yearly instance title gets the year appended', function () {
    $job = makeRecurringJob(RecurringJob::YEARLY)->createInstance(Carbon::parse('2026-01-01'));
    expect($job->name)->toBe('Stock Management - 2026');
});

test('a daily instance title gets the day date and month appended', function () {
    $job = makeRecurringJob(RecurringJob::DAILY)->createInstance(Carbon::parse('2026-07-06')); // a Monday
    expect($job->name)->toBe('Stock Management - Mon 06 Jul');
});

test('a weekly instance title uses the first day of the week', function () {
    $job = makeRecurringJob(RecurringJob::WEEKLY)->createInstance(Carbon::parse('2026-07-06')); // week starting Monday
    expect($job->name)->toBe('Stock Management - Mon 06 Jul');
});
