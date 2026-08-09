<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;

test('an admin can link and unlink a worker role to a supplier', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $workerRole = Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing']);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('invitations.update-member-supplier', $workerRole), ['supplier_id' => $supplier->id])
        ->assertSessionHasNoErrors();

    expect($workerRole->fresh()->supplier_id)->toBe($supplier->id);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('invitations.update-member-supplier', $workerRole), ['supplier_id' => null])
        ->assertSessionHasNoErrors();

    expect($workerRole->fresh()->supplier_id)->toBeNull();
});

test('a manager can link a worker or their own row, but not another manager or an admin', function () {
    $admin = User::factory()->create();
    $manager = User::factory()->create();
    $otherManager = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $managerRole = Role::create(['user_id' => $manager->id, 'property_id' => $property->id, 'type' => Role::MANAGER]);
    $otherManagerRole = Role::create(['user_id' => $otherManager->id, 'property_id' => $property->id, 'type' => Role::MANAGER]);
    $workerRole = Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    $adminRole = Role::where('user_id', $admin->id)->firstOrFail();
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing']);

    $this->actingAs($manager)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('invitations.update-member-supplier', $workerRole), ['supplier_id' => $supplier->id])
        ->assertSessionHasNoErrors();
    expect($workerRole->fresh()->supplier_id)->toBe($supplier->id);

    $this->actingAs($manager)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('invitations.update-member-supplier', $managerRole), ['supplier_id' => $supplier->id])
        ->assertSessionHasNoErrors();
    expect($managerRole->fresh()->supplier_id)->toBe($supplier->id);

    $this->actingAs($manager)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('invitations.update-member-supplier', $otherManagerRole), ['supplier_id' => $supplier->id])
        ->assertForbidden();
    expect($otherManagerRole->fresh()->supplier_id)->toBeNull();

    $this->actingAs($manager)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('invitations.update-member-supplier', $adminRole), ['supplier_id' => $supplier->id])
        ->assertForbidden();
    expect($adminRole->fresh()->supplier_id)->toBeNull();
});

test('a supplier from a different property is rejected', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $workerRole = Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    $foreignSupplier = Supplier::create(['property_id' => $otherProperty->id, 'name' => 'Foreign Supplier']);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('invitations.update-member-supplier', $workerRole), ['supplier_id' => $foreignSupplier->id])
        ->assertSessionHasErrors('supplier_id');

    expect($workerRole->fresh()->supplier_id)->toBeNull();
});

test('team index exposes the current property suppliers and each role supplier link', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing']);
    Supplier::create(['property_id' => $otherProperty->id, 'name' => 'Foreign Supplier']);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER, 'supplier_id' => $supplier->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('invitations.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Invitations/Index')
            ->has('suppliers', 1)
            ->where('suppliers.0.name', 'Acme Fencing')
            ->where('roles.1.supplier.id', $supplier->id));
});

test('deleting a linked supplier unlinks the role instead of failing', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing']);
    $workerRole = Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER, 'supplier_id' => $supplier->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->delete(route('manage.suppliers.destroy', $supplier->id))
        ->assertRedirect(route('manage.suppliers.index'));

    expect(Supplier::find($supplier->id))->toBeNull();
    expect($workerRole->fresh())->not->toBeNull();
    expect($workerRole->fresh()->supplier_id)->toBeNull();
});
