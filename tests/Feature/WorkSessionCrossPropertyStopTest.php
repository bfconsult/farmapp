<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('a user can stop their active session even while a different property is selected', function () {
    $user = User::factory()->create();
    $propertyA = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $propertyB = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $propertyA->id, 'type' => Role::WORKER]);
    Role::create(['user_id' => $user->id, 'property_id' => $propertyB->id, 'type' => Role::WORKER]);

    $session = WorkSession::create([
        'property_id' => $propertyA->id,
        'user_id' => $user->id,
        'started_at' => now()->subHour(),
        'status' => WorkSession::DRAFT,
    ]);

    // Switched to property B after starting the session on property A.
    $this->actingAs($user)
        ->withSession(['current_property_id' => $propertyB->id])
        ->post(route('work-sessions.stop', $session->id))
        ->assertRedirect();

    expect($session->fresh()->ended_at)->not->toBeNull();
});

test('a user with no role at all on the session\'s property cannot stop it', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $strangersProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $owner->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    Role::create(['user_id' => $stranger->id, 'property_id' => $strangersProperty->id, 'type' => Role::WORKER]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $owner->id,
        'started_at' => now()->subHour(),
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($stranger)
        ->withSession(['current_property_id' => $strangersProperty->id])
        ->post(route('work-sessions.stop', $session->id))
        ->assertNotFound();

    expect($session->fresh()->ended_at)->toBeNull();
});

test('the Work index page surfaces an active session even when it belongs to a different property', function () {
    $user = User::factory()->create();
    $propertyA = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $propertyB = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $propertyA->id, 'type' => Role::WORKER]);
    Role::create(['user_id' => $user->id, 'property_id' => $propertyB->id, 'type' => Role::WORKER]);

    $session = WorkSession::create([
        'property_id' => $propertyA->id,
        'user_id' => $user->id,
        'started_at' => now()->subHour(),
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $propertyB->id])
        ->get(route('work-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('activeSession.id', $session->id)
            ->where('activeSession.property.id', $propertyA->id));
});
