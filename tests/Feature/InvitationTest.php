<?php

use App\Models\Invitation;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('re-inviting the same email supersedes the previous invitation token', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $oldInvitation = Invitation::create([
        'property_id' => $property->id,
        'invited_by' => $admin->id,
        'email' => 'wez1139@gmail.com',
        'role' => Role::WORKER,
    ]);
    $oldToken = $oldInvitation->token;

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('invitations.store'), [
            'email' => 'wez1139@gmail.com',
            'role' => 'worker',
        ])
        ->assertSessionHasNoErrors();

    expect(Invitation::where('property_id', $property->id)
        ->where('email', 'wez1139@gmail.com')
        ->whereNull('accepted_at')
        ->count())->toBe(1);

    // The old link a user might still have open must no longer resolve.
    $this->get(route('invitations.accept', $oldToken))->assertNotFound();
});

test('removing a team member deletes their pending invitation', function () {
    $admin = User::factory()->create();
    $worker = User::factory()->create(['email' => 'wez1139@gmail.com']);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $workerRole = Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $invitation = Invitation::create([
        'property_id' => $property->id,
        'invited_by' => $admin->id,
        'email' => 'wez1139@gmail.com',
        'role' => Role::WORKER,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->delete(route('invitations.destroy-role', $workerRole))
        ->assertSessionHasNoErrors();

    expect(Invitation::find($invitation->id))->toBeNull();
});
