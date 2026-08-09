<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('an admin can add a team member with no email', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('invitations.store-member'), [
            'name' => 'Casey Contractor',
            'role' => 'worker',
            'hourly_rate' => '32.50',
        ])
        ->assertSessionHasNoErrors();

    $member = User::where('name', 'Casey Contractor')->firstOrFail();
    expect($member->email)->toBeNull();
    expect($member->claimed_at)->toBeNull();
    expect($member->isClaimed())->toBeFalse();
    expect((float) $member->hourly_rate)->toBe(32.50);

    $role = Role::where('user_id', $member->id)->where('property_id', $property->id)->firstOrFail();
    expect($role->type)->toBe(Role::WORKER);
});

test('a manager can only add a worker, not a manager', function () {
    $manager = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $manager->id, 'property_id' => $property->id, 'type' => Role::MANAGER]);

    $this->actingAs($manager)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('invitations.store-member'), [
            'name' => 'Casey Contractor',
            'role' => 'manager',
        ])
        ->assertForbidden();

    expect(User::where('name', 'Casey Contractor')->exists())->toBeFalse();

    $this->actingAs($manager)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('invitations.store-member'), [
            'name' => 'Casey Contractor',
            'role' => 'worker',
        ])
        ->assertSessionHasNoErrors();

    expect(User::where('name', 'Casey Contractor')->exists())->toBeTrue();
});

test('a worker is forbidden from adding a team member', function () {
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $this->actingAs($worker)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('invitations.store-member'), [
            'name' => 'Casey Contractor',
            'role' => 'worker',
        ])
        ->assertForbidden();
});

test('adding a member with an email already in use is rejected', function () {
    $admin = User::factory()->create();
    $existing = User::factory()->create(['email' => 'taken@example.com']);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('invitations.store-member'), [
            'name' => 'Casey Contractor',
            'email' => 'taken@example.com',
            'role' => 'worker',
        ])
        ->assertSessionHasErrors('email');

    expect(User::where('name', 'Casey Contractor')->exists())->toBeFalse();
});

test('an unclaimed member cannot log in with any password', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('invitations.store-member'), [
            'name' => 'Casey Contractor',
            'email' => 'casey@example.com',
            'role' => 'worker',
        ])
        ->assertSessionHasNoErrors();

    // actingAs() sets the resolved auth user directly and outlives the
    // request - it doesn't get cleared just because the login attempt
    // below fails, so the admin session has to be torn down explicitly
    // or assertGuest() would see the still-logged-in admin, not casey.
    Auth::logout();

    $this->post('/login', [
        'email' => 'casey@example.com',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('adding a team member only creates a role on the current property', function () {
    $admin = User::factory()->create();
    $propertyA = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $propertyB = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $propertyA->id, 'type' => Role::ADMIN]);
    Role::create(['user_id' => $admin->id, 'property_id' => $propertyB->id, 'type' => Role::ADMIN]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $propertyA->id])
        ->post(route('invitations.store-member'), [
            'name' => 'Casey Contractor',
            'role' => 'worker',
        ])
        ->assertSessionHasNoErrors();

    $member = User::where('name', 'Casey Contractor')->firstOrFail();
    expect(Role::where('user_id', $member->id)->pluck('property_id')->all())->toBe([$propertyA->id]);
});
