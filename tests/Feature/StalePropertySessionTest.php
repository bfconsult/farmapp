<?php

use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;

test('a session pointing at a deleted property is repaired instead of crashing Jobs', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    // A property this session still points at, but which no longer exists -
    // e.g. it was deleted after being selected as current.
    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => 999999])
        ->get(route('jobs.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Jobs/Index'));

    // The session should have been repaired to the user's one real property,
    // not left pointing at the deleted one.
    $this->assertSame($property->id, session('current_property_id'));
});

test('a session pointing at a property the user no longer belongs to is repaired', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    // otherProperty exists, but this user was never given a role on it.

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $otherProperty->id])
        ->get(route('jobs.index'));

    $response->assertOk();
    $this->assertSame($property->id, session('current_property_id'));
});

test('a valid current_property_id in session is left untouched', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $user->id, 'property_id' => $otherProperty->id, 'type' => Role::ADMIN]);
    FarmJob::create(['name' => 'A job', 'property_id' => $property->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('jobs.index'))
        ->assertOk();

    $this->assertSame($property->id, session('current_property_id'));
});

test('an approver whose stale session property was deleted does not crash Jobs either', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::APPROVER]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => 999999])
        ->get(route('jobs.index'))
        ->assertOk();
});
