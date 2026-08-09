<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;

test('an admin can create a supplier with billing details', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('manage.suppliers.store'), [
            'name' => 'Acme Fencing',
            'phone' => '0400 000 000',
            'billing_company_name' => 'Acme Fencing Pty Ltd',
            'billing_abn' => '12 345 678 901',
        ]);

    $response->assertRedirect(route('manage.suppliers.index'));

    $supplier = Supplier::where('name', 'Acme Fencing')->firstOrFail();
    expect($supplier->property_id)->toBe($property->id);
    expect($supplier->billing_company_name)->toBe('Acme Fencing Pty Ltd');
    expect($supplier->billing_abn)->toBe('12 345 678 901');
});

test('a supplier belonging to a different property cannot be edited or deleted', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $foreignSupplier = Supplier::create(['property_id' => $otherProperty->id, 'name' => 'Foreign Supplier']);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('manage.suppliers.edit', $foreignSupplier->id))
        ->assertNotFound();

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('manage.suppliers.update', $foreignSupplier->id), ['name' => 'Hijacked'])
        ->assertNotFound();

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->delete(route('manage.suppliers.destroy', $foreignSupplier->id))
        ->assertNotFound();

    expect($foreignSupplier->fresh()->name)->toBe('Foreign Supplier');
});

test('a worker cannot reach the suppliers pages', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('manage.suppliers.index'))
        ->assertForbidden();
});
