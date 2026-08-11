<?php

use App\Models\FarmJob;
use App\Models\Property;
use App\Models\RecurringJob;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

function createPropertyAdminAndZones(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $zoneA = Zone::create(['property_id' => $property->id, 'name' => 'North Paddock', 'coordinates' => [[1, 1], [1, 2], [2, 2]]]);
    $zoneB = Zone::create(['property_id' => $property->id, 'name' => 'South Paddock', 'coordinates' => [[3, 3], [3, 4], [4, 4]]]);

    return [$admin, $property, $zoneA, $zoneB];
}

test('creating a job with multiple zone ids attaches all of them', function () {
    [$admin, $property, $zoneA, $zoneB] = createPropertyAdminAndZones();

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('jobs.store'), [
            'name' => 'Fence the paddocks',
            'zone_ids' => [$zoneA->id, $zoneB->id],
            'intent' => 'plan',
        ])
        ->assertSessionHasNoErrors();

    $job = FarmJob::firstOrFail();
    expect($job->zones->pluck('id')->sort()->values()->all())->toBe([$zoneA->id, $zoneB->id]);
});

test('editing a job to a different zone set replaces the old one', function () {
    [$admin, $property, $zoneA, $zoneB] = createPropertyAdminAndZones();
    $job = FarmJob::create(['name' => 'Fence the paddocks', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $job->zones()->sync([$zoneA->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('jobs.update', $job), [
            'name' => 'Fence the paddocks',
            'zone_ids' => [$zoneB->id],
        ])
        ->assertSessionHasNoErrors();

    expect($job->fresh()->zones->pluck('id')->all())->toBe([$zoneB->id]);
});

test('a zone belonging to a different property is rejected', function () {
    [$admin, $property] = createPropertyAdminAndZones();
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    $otherZone = Zone::create(['property_id' => $otherProperty->id, 'name' => 'Someone Else\'s Paddock', 'coordinates' => [[1, 1], [1, 2], [2, 2]]]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('jobs.store'), [
            'name' => 'Fence the paddocks',
            'zone_ids' => [$otherZone->id],
            'intent' => 'plan',
        ])
        ->assertSessionHasErrors('zone_ids.0');

    expect(FarmJob::count())->toBe(0);
});

test('promoting a job to recurring copies its zones onto the template and every generated instance', function () {
    [$admin, $property, $zoneA, $zoneB] = createPropertyAdminAndZones();

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('jobs.store'), [
            'name' => 'Mow the paddocks',
            'zone_ids' => [$zoneA->id, $zoneB->id],
            'repeats' => true,
            'interval' => RecurringJob::WEEKLY,
            'starts_on' => '2026-08-10',
            'intent' => 'plan',
        ])
        ->assertSessionHasNoErrors();

    $recurringJob = RecurringJob::firstOrFail();
    expect($recurringJob->zones->pluck('id')->sort()->values()->all())->toBe([$zoneA->id, $zoneB->id]);

    $instance = $recurringJob->instances()->firstOrFail();
    expect($instance->zones->pluck('id')->sort()->values()->all())->toBe([$zoneA->id, $zoneB->id]);

    $secondInstance = $recurringJob->createInstance(\Carbon\Carbon::parse('2026-08-17'));
    expect($secondInstance->zones->pluck('id')->sort()->values()->all())->toBe([$zoneA->id, $zoneB->id]);
});

test('deleting a zone removes it from any jobs without deleting the jobs', function () {
    [$admin, $property, $zoneA, $zoneB] = createPropertyAdminAndZones();
    $job = FarmJob::create(['name' => 'Fence the paddocks', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $job->zones()->sync([$zoneA->id, $zoneB->id]);

    $zoneA->delete();

    $job->refresh();
    expect($job->zones->pluck('id')->all())->toBe([$zoneB->id]);
});

test('deleting a job removes its zone links', function () {
    [$admin, $property, $zoneA] = createPropertyAdminAndZones();
    $job = FarmJob::create(['name' => 'Fence the paddocks', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $job->zones()->sync([$zoneA->id]);

    $job->delete();

    expect(DB::table('farm_job_zone')->where('zone_id', $zoneA->id)->exists())->toBeFalse();
});
