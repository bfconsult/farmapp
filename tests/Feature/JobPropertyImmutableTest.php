<?php

use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;

test('editing a job cannot move it to a different property', function () {
    $user = User::factory()->create();
    $propertyA = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $propertyB = Property::create(['name' => 'Play Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $propertyA->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $user->id, 'property_id' => $propertyB->id, 'type' => Role::ADMIN]);

    $job = FarmJob::create([
        'name' => 'Fence the north paddock',
        'property_id' => $propertyA->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $propertyA->id])
        ->patch(route('jobs.update', $job), [
            'name' => 'Fence the north paddock',
            'property_id' => $propertyB->id,
        ])
        ->assertSessionHasNoErrors();

    expect($job->fresh()->property_id)->toBe($propertyA->id);
});
