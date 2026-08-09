<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('finalising a session from its own page redirects back there, preserving the from=manage marker', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $admin->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::DRAFT,
    ]);

    $showUrl = route('work-sessions.show', ['work_session' => $session->id, 'from' => 'manage']);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->from($showUrl)
        ->post(route('work-sessions.finalise', $session->id))
        ->assertRedirect($showUrl);
});

test('stopping a session redirects back to wherever it was posted from', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $admin->id,
        'started_at' => now()->subHour(),
        'status' => WorkSession::DRAFT,
    ]);

    $showUrl = route('work-sessions.show', ['work_session' => $session->id, 'from' => 'manage']);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->from($showUrl)
        ->post(route('work-sessions.stop', $session->id))
        ->assertRedirect($showUrl);
});

test('reverting to draft from the Manage review list navigates to the session with the manage marker preserved', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $admin->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::FINALISED,
    ]);

    // Posted from the Manage list itself, not the session's own page.
    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->from(route('manage.work-sessions'))
        ->post(route('work-sessions.revert-to-draft', ['workSession' => $session->id, 'from' => 'manage']))
        ->assertRedirect(route('work-sessions.show', ['work_session' => $session->id, 'from' => 'manage']));
});

test('reverting to draft from the session\'s own page (no from marker) still lands back on that page', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $admin->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::FINALISED,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('work-sessions.revert-to-draft', $session->id))
        ->assertRedirect(route('work-sessions.show', $session->id));
});

test('visiting a session directly from the Work tab (no from marker) reports no manage origin', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $admin->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.show', $session->id))
        ->assertInertia(fn ($page) => $page->component('WorkSessions/Show')->where('from', null));
});

test('deleting a session reached via ?from=manage redirects to the Manage review list', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $admin->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->delete(route('work-sessions.destroy', ['work_session' => $session->id, 'from' => 'manage']))
        ->assertRedirect(route('manage.work-sessions'));

    expect(WorkSession::find($session->id))->toBeNull();
});

test('deleting a session with no from marker still redirects to the Work tab list', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $admin->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->delete(route('work-sessions.destroy', $session->id))
        ->assertRedirect(route('work-sessions.index'));
});

test('visiting a session via ?from=manage reports the manage origin', function () {
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $admin->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'status' => WorkSession::DRAFT,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.show', ['work_session' => $session->id, 'from' => 'manage']))
        ->assertInertia(fn ($page) => $page->component('WorkSessions/Show')->where('from', 'manage'));
});
