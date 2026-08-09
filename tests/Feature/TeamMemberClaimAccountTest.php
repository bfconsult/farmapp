<?php

use App\Models\Invitation;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Facades\Mail;

function addUnclaimedMember(User $admin, Property $property, string $name = 'Casey Contractor'): User
{
    test()->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('invitations.store-member'), ['name' => $name, 'role' => 'worker'])
        ->assertSessionHasNoErrors();

    return User::where('name', $name)->firstOrFail();
}

test('inviting an already-added member persists their email and links the invitation to their user', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $member = addUnclaimedMember($admin, $property);
    $role = Role::where('user_id', $member->id)->firstOrFail();

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('invitations.invite-member', $role->id), [
            'email' => 'casey@example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->email)->toBe('casey@example.com');

    $invitation = Invitation::where('property_id', $property->id)->whereNull('accepted_at')->firstOrFail();
    expect($invitation->user_id)->toBe($member->id);
    expect($invitation->email)->toBe('casey@example.com');
});

test('the accept page renders the Claim form for an unclaimed member, not the normal Accept form', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $member = addUnclaimedMember($admin, $property);

    $invitation = Invitation::create([
        'property_id' => $property->id,
        'invited_by' => $admin->id,
        'user_id' => $member->id,
        'email' => 'casey@example.com',
        'role' => Role::WORKER,
    ]);

    $this->get(route('invitations.accept', $invitation->token))
        ->assertInertia(fn ($page) => $page->component('Invitations/Claim'));

    // A normal stranger-by-email invitation still gets the plain Accept page.
    $strangerInvitation = Invitation::create([
        'property_id' => $property->id,
        'invited_by' => $admin->id,
        'email' => 'stranger@example.com',
        'role' => Role::WORKER,
    ]);

    $this->get(route('invitations.accept', $strangerInvitation->token))
        ->assertInertia(fn ($page) => $page->component('Invitations/Accept'));
});

test('claiming sets a password and claimed_at, logs the user in, and reuses the existing role', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $member = addUnclaimedMember($admin, $property);
    $originalRole = Role::where('user_id', $member->id)->firstOrFail();

    // Time logged against the member before they ever claim their account.
    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $member->id,
        'created_by' => $admin->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::DRAFT,
    ]);

    $invitation = Invitation::create([
        'property_id' => $property->id,
        'invited_by' => $admin->id,
        'user_id' => $member->id,
        'email' => 'casey@example.com',
        'role' => Role::WORKER,
    ]);

    $this->post(route('invitations.claim', $invitation->token), [
        'name' => 'Casey Contractor',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    $member->refresh();
    expect($member->email)->toBe('casey@example.com');
    expect($member->claimed_at)->not->toBeNull();
    expect($member->isClaimed())->toBeTrue();
    $this->assertAuthenticatedAs($member);

    expect(Role::where('user_id', $member->id)->where('property_id', $property->id)->count())->toBe(1);
    expect(Role::find($originalRole->id))->not->toBeNull();

    expect(WorkSession::find($session->id))->not->toBeNull();
    expect($session->fresh()->user_id)->toBe($member->id);

    expect(Invitation::find($invitation->id)->fresh()->accepted_at)->not->toBeNull();
});

test('a logged-out invitee whose account already exists is sent to login, not register', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $existing = User::factory()->create(['email' => 'already-here@example.com']);

    $invitation = Invitation::create([
        'property_id' => $property->id,
        'invited_by' => $admin->id,
        'email' => 'already-here@example.com',
        'role' => Role::WORKER,
    ]);

    $this->post(route('invitations.process', $invitation->token))
        ->assertRedirect(route('login'));
});
