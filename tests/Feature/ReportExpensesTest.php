<?php

use App\Models\Expense;
use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;

function createAdminWithProperty(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    return [$admin, $property];
}

test('expenses in the date range are grouped by job with a subtotal', function () {
    [$admin, $property] = createAdminWithProperty();
    $jobA = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $jobB = FarmJob::create(['name' => 'Service the tractor', 'property_id' => $property->id, 'user_id' => $admin->id]);

    Expense::create(['farm_job_id' => $jobA->id, 'name' => 'Posts', 'date' => '2026-06-10', 'amount' => 100, 'gst_inclusive' => true, 'status' => Expense::COMPLETE]);
    Expense::create(['farm_job_id' => $jobA->id, 'name' => 'Wire', 'date' => '2026-06-15', 'amount' => 50, 'gst_inclusive' => true, 'status' => Expense::COMPLETE]);
    Expense::create(['farm_job_id' => $jobB->id, 'name' => 'Oil filter', 'date' => '2026-06-12', 'amount' => 30, 'gst_inclusive' => true, 'status' => Expense::COMPLETE]);

    $response = $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('reports.index', ['date_from' => '2026-06-01', 'date_to' => '2026-06-30']));

    $response->assertInertia(fn ($page) => $page
        ->component('Reports/Index')
        ->has('expensesByJob', 2)
        ->where('expensesByJob.0.farmJob.name', 'Fence the north paddock')
        ->where('expensesByJob.0.totalAmount', 150)
        ->where('expensesByJob.1.farmJob.name', 'Service the tractor')
        ->where('expensesByJob.1.totalAmount', 30)
        ->where('grandTotal.expenses', 180));
});

test('expenses outside the date range are excluded', function () {
    [$admin, $property] = createAdminWithProperty();
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);

    Expense::create(['farm_job_id' => $job->id, 'name' => 'In range', 'date' => '2026-06-15', 'amount' => 100, 'gst_inclusive' => true, 'status' => Expense::COMPLETE]);
    Expense::create(['farm_job_id' => $job->id, 'name' => 'Out of range', 'date' => '2026-05-15', 'amount' => 200, 'gst_inclusive' => true, 'status' => Expense::COMPLETE]);

    $response = $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('reports.index', ['date_from' => '2026-06-01', 'date_to' => '2026-06-30']));

    $response->assertInertia(fn ($page) => $page
        ->has('expensesByJob', 1)
        ->where('expensesByJob.0.totalAmount', 100)
        ->where('grandTotal.expenses', 100));
});

test('a needs_review expense with no amount is listed but excluded from totals', function () {
    [$admin, $property] = createAdminWithProperty();
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);

    Expense::create(['farm_job_id' => $job->id, 'name' => 'Complete one', 'date' => '2026-06-10', 'amount' => 100, 'gst_inclusive' => true, 'status' => Expense::COMPLETE]);
    Expense::create(['farm_job_id' => $job->id, 'name' => 'Pending one', 'date' => '2026-06-11', 'amount' => null, 'gst_inclusive' => true, 'status' => Expense::NEEDS_REVIEW]);

    $response = $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('reports.index', ['date_from' => '2026-06-01', 'date_to' => '2026-06-30']));

    $response->assertInertia(fn ($page) => $page
        ->has('expensesByJob.0.expenses', 2)
        ->where('expensesByJob.0.totalAmount', 100)
        ->where('grandTotal.expenses', 100));
});

test('an approver does not see expensesByJob at all - they get the read-only diary view instead', function () {
    [$admin, $property] = createAdminWithProperty();
    $approver = User::factory()->create();
    Role::create(['user_id' => $approver->id, 'property_id' => $property->id, 'type' => Role::APPROVER]);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    Expense::create(['farm_job_id' => $job->id, 'name' => 'Posts', 'date' => '2026-06-10', 'amount' => 100, 'gst_inclusive' => true, 'status' => Expense::COMPLETE]);

    $response = $this->actingAs($approver)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('reports.index', ['date_from' => '2026-06-01', 'date_to' => '2026-06-30']));

    $response->assertInertia(fn ($page) => $page
        ->component('Reports/Diary')
        ->missing('expensesByJob'));
});

test('an expense from another property never appears, even with a matching date', function () {
    [$admin, $property] = createAdminWithProperty();
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    $otherJob = FarmJob::create(['name' => 'Other job', 'property_id' => $otherProperty->id, 'user_id' => $admin->id]);
    Expense::create(['farm_job_id' => $otherJob->id, 'name' => 'Not mine', 'date' => '2026-06-10', 'amount' => 999, 'gst_inclusive' => true, 'status' => Expense::COMPLETE]);

    $response = $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('reports.index', ['date_from' => '2026-06-01', 'date_to' => '2026-06-30']));

    $response->assertInertia(fn ($page) => $page
        ->has('expensesByJob', 0)
        ->where('grandTotal.expenses', 0));
});
