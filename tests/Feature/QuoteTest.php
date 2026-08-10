<?php

use App\Mail\SupplierJobLet;
use App\Mail\SupplierJobRequest;
use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function createJobAndAdmin(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fence the north paddock', 'property_id' => $property->id, 'user_id' => $admin->id]);

    return [$admin, $property, $job];
}

test('inviting a supplier creates an invited quote and emails them', function () {
    Mail::fake();
    [$admin, $property, $job] = createJobAndAdmin();
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('quotes.store', $job->id), [
            'supplier_id' => $supplier->id,
            'requires_quote' => true,
            'message' => 'Please have a look at the north paddock fence.',
        ])
        ->assertSessionHasNoErrors();

    $quote = Quote::first();
    expect($quote->status)->toBe(Quote::INVITED);
    expect($quote->supplier_id)->toBe($supplier->id);
    expect($quote->requires_quote)->toBeTrue();
    expect($quote->invited_at)->not->toBeNull();

    Mail::assertSent(SupplierJobRequest::class, fn ($mail) => $mail->hasTo($supplier->email) && $mail->quote->id === $quote->id);
});

test('inviting a supplier with no email on file is rejected', function () {
    Mail::fake();
    [$admin, $property, $job] = createJobAndAdmin();
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'No Email Co']);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('quotes.store', $job->id), [
            'supplier_id' => $supplier->id,
            'requires_quote' => true,
        ])
        ->assertSessionHasErrors('supplier_id');

    expect(Quote::count())->toBe(0);
    Mail::assertNothingSent();
});

test('a supplier belonging to a different property cannot be invited', function () {
    [$admin, $property, $job] = createJobAndAdmin();
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    $otherSupplier = Supplier::create(['property_id' => $otherProperty->id, 'name' => 'Cross-Property Co', 'email' => 'x@example.test']);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('quotes.store', $job->id), [
            'supplier_id' => $otherSupplier->id,
            'requires_quote' => true,
        ])
        ->assertSessionHasErrors('supplier_id');
});

test('a worker cannot invite a supplier', function () {
    [$admin, $property, $job] = createJobAndAdmin();
    $worker = User::factory()->create();
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);

    $this->actingAs($worker)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('quotes.store', $job->id), [
            'supplier_id' => $supplier->id,
            'requires_quote' => true,
        ])
        ->assertForbidden();
});

test('accepting a quote that requires one needs an amount', function () {
    [$admin, $property, $job] = createJobAndAdmin();
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);
    $quote = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $admin->id,
        'requires_quote' => true, 'status' => Quote::INVITED, 'invited_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('quotes.accept', $quote->id), [])
        ->assertSessionHasErrors('amount');

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('quotes.accept', $quote->id), ['amount' => 450])
        ->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->status)->toBe(Quote::ACCEPTED);
    expect((float) $quote->amount)->toBe(450.0);
    expect($quote->decided_at)->not->toBeNull();
});

test('accepting a do-and-charge quote does not require an amount', function () {
    [$admin, $property, $job] = createJobAndAdmin();
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);
    $quote = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $admin->id,
        'requires_quote' => false, 'status' => Quote::INVITED, 'invited_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('quotes.accept', $quote->id), [])
        ->assertSessionHasNoErrors();

    expect($quote->refresh()->status)->toBe(Quote::ACCEPTED);
});

test('declining a quote records the outcome', function () {
    [$admin, $property, $job] = createJobAndAdmin();
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);
    $quote = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $admin->id,
        'requires_quote' => true, 'status' => Quote::INVITED, 'invited_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('quotes.decline', $quote->id))
        ->assertRedirect();

    $quote->refresh();
    expect($quote->status)->toBe(Quote::DECLINED);
    expect($quote->decided_at)->not->toBeNull();
});

test('notifying other suppliers declines every still-invited quote and emails them, leaving the accepted one alone', function () {
    Mail::fake();
    [$admin, $property, $job] = createJobAndAdmin();
    $winner = Supplier::create(['property_id' => $property->id, 'name' => 'Winning Co', 'email' => 'winner@example.test']);
    $loserA = Supplier::create(['property_id' => $property->id, 'name' => 'Loser A', 'email' => 'a@example.test']);
    $loserB = Supplier::create(['property_id' => $property->id, 'name' => 'Loser B', 'email' => 'b@example.test']);

    $winningQuote = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $winner->id, 'created_by' => $admin->id,
        'requires_quote' => true, 'status' => Quote::ACCEPTED, 'amount' => 500, 'invited_at' => now(), 'decided_at' => now(),
    ]);
    $quoteA = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $loserA->id, 'created_by' => $admin->id,
        'requires_quote' => true, 'status' => Quote::INVITED, 'invited_at' => now(),
    ]);
    $quoteB = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $loserB->id, 'created_by' => $admin->id,
        'requires_quote' => false, 'status' => Quote::INVITED, 'invited_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('quotes.notify-others', $job->id))
        ->assertSessionHasNoErrors();

    expect($quoteA->refresh()->status)->toBe(Quote::DECLINED);
    expect($quoteB->refresh()->status)->toBe(Quote::DECLINED);
    expect($winningQuote->refresh()->status)->toBe(Quote::ACCEPTED);

    Mail::assertSent(SupplierJobLet::class, 2);
    Mail::assertSent(SupplierJobLet::class, fn ($mail) => $mail->hasTo($loserA->email));
    Mail::assertSent(SupplierJobLet::class, fn ($mail) => $mail->hasTo($loserB->email));
});

test('a quote can be removed while still invited', function () {
    [$admin, $property, $job] = createJobAndAdmin();
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);
    $quote = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $admin->id,
        'requires_quote' => true, 'status' => Quote::INVITED, 'invited_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->delete(route('quotes.destroy', $quote->id));

    expect(Quote::find($quote->id))->toBeNull();
});

test('deleting a job cascades to its quotes', function () {
    [$admin, $property, $job] = createJobAndAdmin();
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);
    $quote = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $admin->id,
        'requires_quote' => true, 'status' => Quote::INVITED, 'invited_at' => now(),
    ]);

    $job->delete();

    expect(Quote::find($quote->id))->toBeNull();
});

test('deleting a supplier preserves the historical quote record with a null supplier', function () {
    [$admin, $property, $job] = createJobAndAdmin();
    $supplier = Supplier::create(['property_id' => $property->id, 'name' => 'Acme Fencing', 'email' => 'quotes@acmefencing.test']);
    $quote = Quote::create([
        'farm_job_id' => $job->id, 'supplier_id' => $supplier->id, 'created_by' => $admin->id,
        'requires_quote' => true, 'status' => Quote::ACCEPTED, 'amount' => 450, 'invited_at' => now(), 'decided_at' => now(),
    ]);

    $supplier->delete();

    $quote->refresh();
    expect($quote)->not->toBeNull();
    expect($quote->supplier_id)->toBeNull();
    expect((float) $quote->amount)->toBe(450.0);
});
