<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;

test('a user with no properties visiting Jobs is redirected to create their first property', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('jobs.index'))
        ->assertRedirect(route('properties.create'));
});

test('the get-started page renders for a user with no properties', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('properties.create'))
        ->assertInertia(fn ($page) => $page->component('Properties/GetStarted'));
});

test('a user who already has a property is sent past the get-started page', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($user)
        ->get(route('properties.create'))
        ->assertRedirect(route('jobs.index'));
});

test('creating a property from the get-started flow works the same as the nav Add Property action', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('properties.store'))
        ->assertRedirect();

    expect($user->properties()->count())->toBe(1);
    expect($user->fresh()->roleOn($user->properties()->first()))->toBe(Role::ADMIN);
});
