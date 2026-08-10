<?php

use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;

test('a property with no jobs at all reports hasAnyJobs as false', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('jobs.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Jobs/Index')
            ->where('hasAnyJobs', false));
});

test('hasAnyJobs is true even when every job is filtered out of the current view', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    // A job that exists on the property but isn't assigned to this user -
    // hasAnyJobs is property-wide, not scoped to "jobs I can see in the list".
    FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('jobs.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Jobs/Index')
            ->where('hasAnyJobs', true));
});

test('hasAnyJobs is scoped to the current property, not any property the user has a role on', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $user->id, 'property_id' => $otherProperty->id, 'type' => Role::ADMIN]);
    FarmJob::create(['name' => 'Job on the other farm', 'property_id' => $otherProperty->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('jobs.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Jobs/Index')
            ->where('hasAnyJobs', false));
});
