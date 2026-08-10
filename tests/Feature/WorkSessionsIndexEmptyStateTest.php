<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('a user with no sessions on this property reports hasAnySessions as false', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->component('WorkSessions/Index')
            ->where('hasAnySessions', false));
});

test('hasAnySessions is true even when the session falls outside the default date range', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    // Outside the default "this month" filter the index applies - hasAnySessions
    // is unscoped by date range, same as FarmJobController's hasAnyJobs.
    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'started_at' => now()->subYear(),
        'ended_at' => now()->subYear()->addHour(),
        'status' => WorkSession::FINALISED,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->component('WorkSessions/Index')
            ->where('hasAnySessions', true));
});

test('hasAnySessions is scoped to the current property, not any property the user has a role on', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $user->id, 'property_id' => $otherProperty->id, 'type' => Role::ADMIN]);

    WorkSession::create([
        'property_id' => $otherProperty->id,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'started_at' => now(),
        'ended_at' => now()->addHour(),
        'status' => WorkSession::FINALISED,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->component('WorkSessions/Index')
            ->where('hasAnySessions', false));
});

test('hasAnySessions is scoped to the acting user, not every session on the property', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $otherUser->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    // The Work Sessions index only ever lists the acting user's own sessions,
    // so hasAnySessions must match that scope rather than the whole property.
    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $otherUser->id,
        'created_by' => $otherUser->id,
        'started_at' => now(),
        'ended_at' => now()->addHour(),
        'status' => WorkSession::FINALISED,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->component('WorkSessions/Index')
            ->where('hasAnySessions', false));
});
