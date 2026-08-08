<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;

test('saving a boundary redirects back to wherever the request came from', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->from(route('properties.edit', $property))
        ->put(route('shape.update', $property), [
            'coordinates' => [[1, 2], [1, 3], [2, 3], [2, 2]],
        ]);

    $response->assertRedirect(route('properties.edit', $property));
    expect($property->fresh()->shape->coordinates)->toBe([[1, 2], [1, 3], [2, 3], [2, 2]]);
});

test('saving a boundary from the boundary editor itself still redirects there', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->from(route('shape.edit', $property))
        ->put(route('shape.update', $property), [
            'coordinates' => [[1, 2], [1, 3], [2, 3], [2, 2]],
        ]);

    $response->assertRedirect(route('shape.edit', $property));
});
