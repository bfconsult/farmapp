<?php

use App\Models\Asset;
use App\Models\FarmJob;
use App\Models\Property;
use App\Models\RecurringJob;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

function createPropertyWithEquipment(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $tractor = Asset::create(['property_id' => $property->id, 'created_by' => $admin->id, 'name' => 'Tractor']);
    $mower = Asset::create(['property_id' => $property->id, 'created_by' => $admin->id, 'name' => 'Flail Mower']);

    return [$admin, $property, $tractor, $mower];
}

function equipmentSession(Property $property, User $user, array $attributes = []): WorkSession
{
    return WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'started_at' => '2026-10-01 08:00:00',
        'ended_at' => '2026-10-01 10:00:00',
        'status' => WorkSession::DRAFT,
        ...$attributes,
    ]);
}

test('a session can be logged with several pieces of equipment', function () {
    [$admin, $property, $tractor, $mower] = createPropertyWithEquipment();

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('work-sessions.store'), [
            'started_at' => '2026-10-01 08:00:00',
            'ended_at' => '2026-10-01 10:00:00',
            'asset_ids' => [$tractor->id, $mower->id],
        ])
        ->assertSessionHasNoErrors();

    expect(WorkSession::latest('id')->first()->assets->pluck('id')->sort()->values()->all())
        ->toBe(collect([$tractor->id, $mower->id])->sort()->values()->all());
});

test('editing a session syncs its equipment', function () {
    [$admin, $property, $tractor, $mower] = createPropertyWithEquipment();
    $session = equipmentSession($property, $admin);
    $session->assets()->attach($tractor);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('work-sessions.update', $session), [
            'started_at' => '2026-10-01 08:00:00',
            'ended_at' => '2026-10-01 10:00:00',
            'asset_ids' => [$mower->id],
        ])
        ->assertSessionHasNoErrors();

    expect($session->fresh()->assets->pluck('id')->all())->toBe([$mower->id]);
});

test('equipment from another property is rejected', function () {
    [$admin, $property] = createPropertyWithEquipment();
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    $otherAsset = Asset::create(['property_id' => $otherProperty->id, 'created_by' => $admin->id, 'name' => 'Someone Else\'s Tractor']);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('work-sessions.store'), [
            'started_at' => '2026-10-01 08:00:00',
            'ended_at' => '2026-10-01 10:00:00',
            'asset_ids' => [$otherAsset->id],
        ])
        ->assertSessionHasErrors('asset_ids.0');
});

test('the create form pre-fills equipment from the latest session on the same job', function () {
    [$admin, $property, $tractor, $mower] = createPropertyWithEquipment();
    $job = FarmJob::create(['name' => 'Mow the house paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $job->assignees()->attach($admin->id);

    equipmentSession($property, $admin, ['farm_job_id' => $job->id, 'started_at' => '2026-09-01 08:00:00', 'ended_at' => '2026-09-01 09:00:00'])
        ->assets()->attach($tractor);
    equipmentSession($property, $admin, ['farm_job_id' => $job->id])
        ->assets()->attach([$tractor->id, $mower->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.create'))
        ->assertInertia(fn ($page) => $page
            ->component('WorkSessions/Create')
            ->where("jobEquipment.{$job->id}", fn ($ids) => collect($ids)->sort()->values()->all() === collect([$tractor->id, $mower->id])->sort()->values()->all())
        );
});

test('a new recurring instance pre-fills equipment from a previous instance', function () {
    [$admin, $property, $tractor, $mower] = createPropertyWithEquipment();
    $recurring = RecurringJob::create([
        'property_id' => $property->id, 'created_by' => $admin->id, 'name' => 'Mow the lawn',
        'interval' => RecurringJob::WEEKLY, 'starts_on' => '2026-09-01', 'is_active' => true,
    ]);
    $lastWeek = FarmJob::create(['name' => 'Mow the lawn (last week)', 'property_id' => $property->id, 'user_id' => $admin->id, 'recurring_job_id' => $recurring->id]);
    $thisWeek = FarmJob::create(['name' => 'Mow the lawn (this week)', 'property_id' => $property->id, 'user_id' => $admin->id, 'recurring_job_id' => $recurring->id]);
    $thisWeek->assignees()->attach($admin->id);

    equipmentSession($property, $admin, ['farm_job_id' => $lastWeek->id])->assets()->attach($mower);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.create'))
        ->assertInertia(fn ($page) => $page->where("jobEquipment.{$thisWeek->id}", [$mower->id]));
});

test('the asset page splits usage hours from maintenance hours', function () {
    [$admin, $property, $tractor] = createPropertyWithEquipment();

    // Used for 2h mowing...
    $mowing = FarmJob::create(['name' => 'Mow the house paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    equipmentSession($property, $admin, ['farm_job_id' => $mowing->id, 'status' => WorkSession::FINALISED])
        ->assets()->attach($tractor);

    // ...and 1h on a job maintaining it.
    $service = FarmJob::create(['name' => 'Change the tractor oil', 'property_id' => $property->id, 'user_id' => $admin->id, 'asset_id' => $tractor->id]);
    equipmentSession($property, $admin, [
        'farm_job_id' => $service->id, 'status' => WorkSession::FINALISED,
        'started_at' => '2026-10-02 08:00:00', 'ended_at' => '2026-10-02 09:00:00',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('assets.show', $tractor))
        ->assertInertia(fn ($page) => $page
            ->component('Assets/Show')
            ->where('usageHours', 2)
            ->where('maintenanceHours', 1)
            ->has('workSessions', 2)
        );
});
