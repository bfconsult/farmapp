<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('a few stray seconds past a clean block multiple do not push the duration up an extra block', function () {
    $user = User::factory()->create(['billing_block_minutes' => 15]);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    // 30 minutes and 7 seconds - mirrors stop() stamping ended_at with a
    // live now() while started_at sits on a clean block boundary. Carbon 3's
    // diffInMinutes() returns 30.1166..., which used to get ceil()'d to the
    // next 15-minute block (45m) instead of staying at 30m.
    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'started_at' => '2026-09-05 17:00:00',
        'ended_at' => '2026-09-05 17:30:07',
        'status' => WorkSession::DRAFT,
    ]);

    expect($session->duration_in_hours)->toBe(0.5);
});

test('an exact clean-minute session is unaffected by the truncation fix', function () {
    $user = User::factory()->create(['billing_block_minutes' => 15]);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'started_at' => '2026-09-06 10:45:00',
        'ended_at' => '2026-09-06 13:00:00',
        'status' => WorkSession::DRAFT,
    ]);

    expect($session->duration_in_hours)->toBe(2.25);
});

test('stray seconds still round up correctly when they push past the next block boundary', function () {
    $user = User::factory()->create(['billing_block_minutes' => 15]);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    // 31 whole minutes (not just stray seconds past 30) genuinely belongs
    // in the next 15-minute block.
    $session = WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'started_at' => '2026-09-05 17:00:00',
        'ended_at' => '2026-09-05 17:31:00',
        'status' => WorkSession::DRAFT,
    ]);

    expect($session->duration_in_hours)->toBe(0.75);
});
