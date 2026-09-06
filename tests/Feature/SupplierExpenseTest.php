<?php

use App\Models\Expense;
use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createAcceptedQuote(bool $invoiceRequested = true): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd', 'email' => 'farm@example.test']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);
    $quote = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $admin->id,
        'requires_quote' => true, 'status' => Quote::ACCEPTED, 'amount' => 450, 'invited_at' => now(), 'decided_at' => now(),
        'invoice_requested_at' => $invoiceRequested ? now() : null,
    ]);

    return [$quote, $job, $supplier];
}

test('a supplier can submit an invoice image against the job via their share link', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$quote, $job, $supplier] = createAcceptedQuote();

    $this->post(route('quotes.share.expense', $quote->share_token), [
        'name' => 'Fencing materials and labour',
        'description' => 'Posts, wire, and two days labour.',
        'amount' => 890.50,
        'gst_inclusive' => true,
        'invoice' => UploadedFile::fake()->image('invoice.jpg'),
    ])->assertSessionHasNoErrors();

    $expense = Expense::first();
    expect($expense)->not->toBeNull();
    expect($expense->farm_job_id)->toBe($job->id);
    expect($expense->supplier_id)->toBe($supplier->id);
    expect($expense->quote_id)->toBe($quote->id);
    expect($expense->created_by)->toBeNull();
    expect((float) $expense->amount)->toBe(890.5);
    expect($expense->reimburse)->toBeFalse();
    expect($expense->invoice_file)->not->toBeNull();
    expect($expense->invoice_original_name)->toBe('invoice.jpg');
    expect($expense->status)->toBe(Expense::COMPLETE);
    Storage::disk('public')->assertExists($expense->invoice_file);
});

test('a supplier who only attaches the file, with no name or amount, creates a needs_review expense', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$quote, $job, $supplier] = createAcceptedQuote();

    $this->post(route('quotes.share.expense', $quote->share_token), [
        'invoice' => UploadedFile::fake()->create('invoice.pdf', 100),
    ])->assertSessionHasNoErrors();

    $expense = Expense::first();
    expect($expense)->not->toBeNull();
    expect($expense->name)->toBe($job->name);
    expect($expense->amount)->toBeNull();
    expect($expense->supplier_id)->toBe($supplier->id);
    expect($expense->status)->toBe(Expense::NEEDS_REVIEW);
});

test('a blank amount field (empty string) is treated the same as omitting it entirely', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$quote] = createAcceptedQuote();

    $this->post(route('quotes.share.expense', $quote->share_token), [
        'name' => '',
        'amount' => '',
        'invoice' => UploadedFile::fake()->image('invoice.jpg'),
    ])->assertSessionHasNoErrors();

    $expense = Expense::first();
    expect($expense->amount)->toBeNull();
    expect($expense->status)->toBe(Expense::NEEDS_REVIEW);
});

test('a supplier can submit a PDF invoice', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$quote] = createAcceptedQuote();

    $this->post(route('quotes.share.expense', $quote->share_token), [
        'name' => 'Fencing materials and labour',
        'amount' => 890.50,
        'invoice' => UploadedFile::fake()->create('invoice.pdf', 100),
    ])->assertSessionHasNoErrors();

    $expense = Expense::first();
    expect($expense->invoice_original_name)->toBe('invoice.pdf');
});

test('submitting without a quote that requested an invoice 404s', function () {
    Storage::fake('public');
    [$quote] = createAcceptedQuote(invoiceRequested: false);

    $this->post(route('quotes.share.expense', $quote->share_token), [
        'name' => 'Fencing materials and labour',
        'amount' => 890.50,
        'invoice' => UploadedFile::fake()->image('invoice.jpg'),
    ])->assertNotFound();

    expect(Expense::count())->toBe(0);
});

test('submitting against a non-accepted quote 404s even if invoice_requested_at is somehow set', function () {
    Storage::fake('public');
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd', 'email' => 'farm@example.test']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);
    $quote = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $admin->id,
        'requires_quote' => true, 'status' => Quote::DECLINED, 'invited_at' => now(), 'decided_at' => now(),
        'invoice_requested_at' => now(),
    ]);

    $this->post(route('quotes.share.expense', $quote->share_token), [
        'name' => 'Fencing materials and labour',
        'amount' => 890.50,
        'invoice' => UploadedFile::fake()->image('invoice.jpg'),
    ])->assertNotFound();

    expect(Expense::count())->toBe(0);
});

test('an unknown share token 404s', function () {
    $this->post(route('quotes.share.expense', 'not-a-real-token'), [
        'name' => 'Fencing materials and labour',
        'amount' => 890.50,
        'invoice' => UploadedFile::fake()->image('invoice.jpg'),
    ])->assertNotFound();
});

test('submitting without a file is rejected', function () {
    [$quote] = createAcceptedQuote();

    $this->post(route('quotes.share.expense', $quote->share_token), [
        'name' => 'Fencing materials and labour',
        'amount' => 890.50,
    ])->assertSessionHasErrors('invoice');

    expect(Expense::count())->toBe(0);
});

test('a disallowed file type is rejected', function () {
    [$quote] = createAcceptedQuote();

    $this->post(route('quotes.share.expense', $quote->share_token), [
        'name' => 'Fencing materials and labour',
        'amount' => 890.50,
        'invoice' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('invoice');

    expect(Expense::count())->toBe(0);
});

test('a second invoice can be submitted on the same link without a lock-out', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$quote] = createAcceptedQuote();

    $submit = fn () => $this->post(route('quotes.share.expense', $quote->share_token), [
        'name' => 'Fencing materials and labour',
        'amount' => 890.50,
        'invoice' => UploadedFile::fake()->image('invoice.jpg'),
    ]);

    $submit()->assertSessionHasNoErrors();
    $submit()->assertSessionHasNoErrors();

    expect(Expense::count())->toBe(2);
});
