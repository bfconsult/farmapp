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

test('a new property defaults its email to the creating user\'s own email', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    $this->actingAs($user)->post(route('properties.store'));

    expect($user->properties()->first()->email)->toBe('me@example.com');
});

test('completing onboarding by saving a new property redirects to the Map page with a success flash', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'New Property', 'address' => '']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($user)
        ->patch(route('properties.update', $property), [
            'name' => 'Valle Pacis',
            'address' => '1 Test Rd',
        ])
        ->assertRedirect(route('map'))
        ->assertSessionHas('success');
});

test('editing an already-established property still redirects to the property page', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($user)
        ->patch(route('properties.update', $property), [
            'name' => 'Valle Pacis Updated',
            'address' => '1 Test Rd',
        ])
        ->assertRedirect(route('properties.show', $property))
        ->assertSessionMissing('success');
});

test('saving a property update with coordinates also saves the boundary in the same request', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'New Property', 'address' => '']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $coordinates = [[-27.11, 152.91], [-27.11, 153.01], [-27.21, 153.01], [-27.21, 152.91]];

    $this->actingAs($user)
        ->patch(route('properties.update', $property), [
            'name' => 'Valle Pacis',
            'address' => '1 Test Rd',
            'coordinates' => $coordinates,
        ])
        ->assertRedirect(route('map'));

    expect($property->fresh()->name)->toBe('Valle Pacis');
    expect($property->fresh()->shape->coordinates)->toBe($coordinates);
});
