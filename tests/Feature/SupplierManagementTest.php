<?php

use App\Models\Expense;
use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkSession;

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

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('manage.suppliers.show', $foreignSupplier->id))
        ->assertNotFound();

    expect($foreignSupplier->fresh()->name)->toBe('Foreign Supplier');
});

test('the supplier summary page defaults to the past 3 months and totals in-range transactions', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing']);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $user->id]);

    $inRange = Expense::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id,
        'name' => 'Fence posts', 'amount' => 150.00,
    ]);
    $inRange->created_at = now()->subMonth();
    $inRange->save();

    $outOfRange = Expense::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id,
        'name' => 'Old wire', 'amount' => 40.00,
    ]);
    $outOfRange->created_at = now()->subMonths(6);
    $outOfRange->save();

    // Belongs to a different supplier entirely - must never appear here.
    $otherSupplier = Supplier::create(['property_id' => $property->id, 'name' => 'Other Supplier']);
    Expense::create([
        'farm_job_id' => $job->id, 'supplier_id' => $otherSupplier->id, 'created_by' => $user->id,
        'name' => 'Unrelated purchase', 'amount' => 999.00,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('manage.suppliers.show', $supplier->id))
        ->assertInertia(fn ($page) => $page
            ->component('Manage/Suppliers/Show')
            ->has('transactions', 1)
            ->where('transactions.0.name', 'Fence posts')
            ->where('total', 150));
});

test('a custom date range on the supplier summary page includes transactions outside the default window', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing']);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $user->id]);

    $old = Expense::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id,
        'name' => 'Old wire', 'amount' => 40.00,
    ]);
    $old->created_at = now()->subMonths(6);
    $old->save();

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('manage.suppliers.show', [
            'supplier' => $supplier->id,
            'date_from' => now()->subMonths(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('Manage/Suppliers/Show')
            ->has('transactions', 1)
            ->where('transactions.0.name', 'Old wire'));
});

test('the supplier summary page includes work sessions from workers linked to it as labour transactions', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create(['hourly_rate' => 40]);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'BFC Labour']);
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER, 'supplier_id' => $supplier->id]);

    // Linked worker's session in range - counts.
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $worker->id,
        'started_at' => now()->subDays(3), 'ended_at' => now()->subDays(3)->addHours(2),
        'status' => WorkSession::FINALISED,
    ]);

    // Out of range - excluded.
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $worker->id,
        'started_at' => now()->subMonths(6), 'ended_at' => now()->subMonths(6)->addHours(2),
        'status' => WorkSession::FINALISED,
    ]);

    // An unrelated worker with no supplier link - never counts.
    $otherWorker = User::factory()->create(['hourly_rate' => 40]);
    Role::create(['user_id' => $otherWorker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $otherWorker->id,
        'started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addHours(2),
        'status' => WorkSession::FINALISED,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('manage.suppliers.show', $supplier->id))
        ->assertInertia(fn ($page) => $page
            ->component('Manage/Suppliers/Show')
            ->has('transactions', 1)
            ->where('transactions.0.type', 'labour')
            ->where('transactions.0.name', $worker->name)
            ->where('transactions.0.amount', 80)
            ->where('total', 80));
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
