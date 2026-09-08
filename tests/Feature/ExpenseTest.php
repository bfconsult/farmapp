<?php

use App\Models\Expense;
use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createJobWithAdmin(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);

    return [$admin, $property, $job];
}

test('a needs_review expense can be edited without providing an amount', function () {
    [$admin, $property, $job] = createJobWithAdmin();
    $expense = Expense::create([
        'farm_job_id' => $job->id, 'name' => 'Fence the north paddock', 'date' => '2026-06-15', 'amount' => null,
        'gst_inclusive' => true, 'status' => Expense::NEEDS_REVIEW,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('expenses.update', $expense->id), [
            'name' => 'Fence the north paddock',
            'date' => '2026-06-15',
            'description' => 'Saw the invoice, materials only so far.',
        ])
        ->assertSessionHasNoErrors();

    $expense->refresh();
    expect($expense->amount)->toBeNull();
    expect($expense->description)->toBe('Saw the invoice, materials only so far.');
    expect($expense->status)->toBe(Expense::NEEDS_REVIEW);
});

test('marking an expense reviewed is rejected while the amount is still missing', function () {
    [$admin, $property, $job] = createJobWithAdmin();
    $expense = Expense::create([
        'farm_job_id' => $job->id, 'name' => 'Fence the north paddock', 'date' => '2026-06-15', 'amount' => null,
        'gst_inclusive' => true, 'status' => Expense::NEEDS_REVIEW,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('expenses.mark-reviewed', $expense->id))
        ->assertSessionHasErrors('amount');

    expect($expense->refresh()->status)->toBe(Expense::NEEDS_REVIEW);
});

test('an admin can mark an expense reviewed once an amount has been entered', function () {
    [$admin, $property, $job] = createJobWithAdmin();
    $expense = Expense::create([
        'farm_job_id' => $job->id, 'name' => 'Fence the north paddock', 'date' => '2026-06-15', 'amount' => 420,
        'gst_inclusive' => true, 'status' => Expense::NEEDS_REVIEW,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('expenses.mark-reviewed', $expense->id))
        ->assertSessionHasNoErrors();

    expect($expense->refresh()->status)->toBe(Expense::COMPLETE);
});

test('saving an amount does not automatically clear needs_review - marking reviewed is a separate step', function () {
    [$admin, $property, $job] = createJobWithAdmin();
    $expense = Expense::create([
        'farm_job_id' => $job->id, 'name' => 'Fence the north paddock', 'date' => '2026-06-15', 'amount' => null,
        'gst_inclusive' => true, 'status' => Expense::NEEDS_REVIEW,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('expenses.update', $expense->id), [
            'name' => 'Fence the north paddock',
            'date' => '2026-06-15',
            'amount' => 420,
        ])
        ->assertSessionHasNoErrors();

    expect($expense->refresh()->amount)->not->toBeNull();
    expect($expense->status)->toBe(Expense::NEEDS_REVIEW);
});

test('a worker cannot mark an expense reviewed', function () {
    [$admin, $property, $job] = createJobWithAdmin();
    $worker = User::factory()->create();
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    $expense = Expense::create([
        'farm_job_id' => $job->id, 'name' => 'Fence the north paddock', 'date' => '2026-06-15', 'amount' => 420,
        'gst_inclusive' => true, 'status' => Expense::NEEDS_REVIEW,
    ]);

    $this->actingAs($worker)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('expenses.mark-reviewed', $expense->id))
        ->assertForbidden();

    expect($expense->refresh()->status)->toBe(Expense::NEEDS_REVIEW);
});

test('a needs_review expense with no amount is excluded from the job list total_expenses figure', function () {
    [$admin, $property, $job] = createJobWithAdmin();
    JobStatus::seedDefaultsForProperty($property->id);
    $job->update(['job_status_id' => JobStatus::where('property_id', $property->id)->where('can_book_time', true)->firstOrFail()->id]);
    $job->assignees()->attach($admin->id);
    Expense::create(['farm_job_id' => $job->id, 'name' => 'Complete one', 'date' => '2026-06-15', 'amount' => 100, 'gst_inclusive' => true, 'status' => Expense::COMPLETE]);
    Expense::create(['farm_job_id' => $job->id, 'name' => 'Pending one', 'date' => '2026-06-15', 'amount' => null, 'gst_inclusive' => true, 'status' => Expense::NEEDS_REVIEW]);

    $response = $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('jobs.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('jobs.0.total_expenses', 100));
});

test('an admin can attach an invoice file when manually creating an expense', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$admin, $property, $job] = createJobWithAdmin();

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('expenses.store', $job->id), [
            'name' => 'Fencing materials and labour',
            'date' => '2026-06-15',
            'amount' => 890.50,
            'gst_inclusive' => true,
            'invoice' => UploadedFile::fake()->create('invoice.pdf', 100),
        ])
        ->assertSessionHasNoErrors();

    $expense = Expense::first();
    expect($expense->invoice_file)->not->toBeNull();
    expect($expense->invoice_original_name)->toBe('invoice.pdf');
    Storage::disk('public')->assertExists($expense->invoice_file);
});

test('an admin can attach an invoice file to an existing expense that had none', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$admin, $property, $job] = createJobWithAdmin();
    $expense = Expense::create([
        'farm_job_id' => $job->id, 'name' => 'Fencing materials and labour', 'date' => '2026-06-15',
        'amount' => 890.50, 'gst_inclusive' => true, 'status' => Expense::COMPLETE,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('expenses.update', $expense->id), [
            'name' => 'Fencing materials and labour',
            'date' => '2026-06-15',
            'amount' => 890.50,
            'invoice' => UploadedFile::fake()->image('invoice.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $expense->refresh();
    expect($expense->invoice_file)->not->toBeNull();
    expect($expense->invoice_original_name)->toBe('invoice.jpg');
    Storage::disk('public')->assertExists($expense->invoice_file);
});

test('replacing an expense\'s invoice file deletes the old one', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$admin, $property, $job] = createJobWithAdmin();
    $oldPath = Storage::disk('public')->putFile('invoices', UploadedFile::fake()->image('old.jpg'));
    $expense = Expense::create([
        'farm_job_id' => $job->id, 'name' => 'Fencing materials and labour', 'date' => '2026-06-15',
        'amount' => 890.50, 'gst_inclusive' => true, 'status' => Expense::COMPLETE,
        'invoice_file' => $oldPath, 'invoice_original_name' => 'old.jpg',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('expenses.update', $expense->id), [
            'name' => 'Fencing materials and labour',
            'date' => '2026-06-15',
            'amount' => 890.50,
            'invoice' => UploadedFile::fake()->image('new.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $expense->refresh();
    expect($expense->invoice_original_name)->toBe('new.jpg');
    Storage::disk('public')->assertExists($expense->invoice_file);
    Storage::disk('public')->assertMissing($oldPath);
});

test('editing an expense without choosing a new file leaves its existing invoice untouched', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$admin, $property, $job] = createJobWithAdmin();
    $path = Storage::disk('public')->putFile('invoices', UploadedFile::fake()->image('invoice.jpg'));
    $expense = Expense::create([
        'farm_job_id' => $job->id, 'name' => 'Fencing materials and labour', 'date' => '2026-06-15',
        'amount' => 890.50, 'gst_inclusive' => true, 'status' => Expense::COMPLETE,
        'invoice_file' => $path, 'invoice_original_name' => 'invoice.jpg',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('expenses.update', $expense->id), [
            'name' => 'Fencing materials and labour (updated)',
            'date' => '2026-06-15',
            'amount' => 890.50,
        ])
        ->assertSessionHasNoErrors();

    $expense->refresh();
    expect($expense->invoice_file)->toBe($path);
    expect($expense->invoice_original_name)->toBe('invoice.jpg');
    Storage::disk('public')->assertExists($path);
});
