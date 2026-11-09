<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

function logPastSetup(array $userAttributes = []): array
{
    $user = User::factory()->create($userAttributes);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    return [$user, $property];
}

function existingSession($user, $property, string $start, ?string $end): WorkSession
{
    return WorkSession::create(['property_id' => $property->id, 'user_id' => $user->id, 'started_at' => $start, 'ended_at' => $end, 'status' => WorkSession::DRAFT]);
}

function logPastSession($user, $property, bool $fit = true): WorkSession
{
    test()->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('work-sessions.store'), [
            'started_at' => '2026-06-10 06:00:00',
            'ended_at' => '2026-06-10 15:00:00',
            'fit_to_existing' => $fit,
        ])
        ->assertRedirect();

    return WorkSession::where('user_id', $user->id)->latest('id')->first();
}

function sessionTimes(WorkSession $session): array
{
    return [$session->started_at->format('H:i'), $session->ended_at->format('H:i')];
}

test('with nothing else logged that day, the placeholder stays 6am to 3pm', function () {
    [$user, $property] = logPastSetup();

    expect(sessionTimes(logPastSession($user, $property)))->toBe(['06:00', '15:00']);
});

test('the start moves to the end of a session that started at or before 6am', function () {
    [$user, $property] = logPastSetup();
    existingSession($user, $property, '2026-06-10 05:00:00', '2026-06-10 08:00:00');

    expect(sessionTimes(logPastSession($user, $property)))->toBe(['08:00', '15:00']);
});

test('the start chains through back-to-back sessions', function () {
    [$user, $property] = logPastSetup();
    existingSession($user, $property, '2026-06-10 05:00:00', '2026-06-10 08:00:00');
    existingSession($user, $property, '2026-06-10 08:00:00', '2026-06-10 09:30:00');

    expect(sessionTimes(logPastSession($user, $property)))->toBe(['09:30', '15:00']);
});

test('the end moves back to the start of the next session, if that is before 3pm', function () {
    [$user, $property] = logPastSetup();
    existingSession($user, $property, '2026-06-10 11:00:00', '2026-06-10 12:00:00');

    expect(sessionTimes(logPastSession($user, $property)))->toBe(['06:00', '11:00']);
});

test('both ends round inward to the billing block', function () {
    [$user, $property] = logPastSetup(['billing_block_minutes' => 15]);
    existingSession($user, $property, '2026-06-10 05:00:00', '2026-06-10 07:23:00');
    existingSession($user, $property, '2026-06-10 12:10:00', '2026-06-10 13:00:00');

    expect(sessionTimes(logPastSession($user, $property)))->toBe(['07:30', '12:00']);
});

test('if there is no gap at all, it falls back to 6am to 3pm', function () {
    [$user, $property] = logPastSetup();
    existingSession($user, $property, '2026-06-10 05:00:00', '2026-06-10 16:00:00');

    expect(sessionTimes(logPastSession($user, $property)))->toBe(['06:00', '15:00']);
});

test('another user\'s sessions are ignored', function () {
    [$user, $property] = logPastSetup();
    $other = User::factory()->create();
    existingSession($other, $property, '2026-06-10 05:00:00', '2026-06-10 08:00:00');

    expect(sessionTimes(logPastSession($user, $property)))->toBe(['06:00', '15:00']);
});

test('without fit_to_existing, the submitted times are kept as-is', function () {
    [$user, $property] = logPastSetup();
    existingSession($user, $property, '2026-06-10 05:00:00', '2026-06-10 08:00:00');

    expect(sessionTimes(logPastSession($user, $property, fit: false)))->toBe(['06:00', '15:00']);
});

test('billing block rounding happens in the user\'s own timezone, not UTC', function () {
    // Adelaide is UTC+9:30 in June - rounding an hour block in UTC would
    // land on :30 local time.
    [$user, $property] = logPastSetup(['billing_block_minutes' => 60, 'timezone' => 'Australia/Adelaide']);
    existingSession($user, $property, '2026-06-09 20:00:00', '2026-06-09 21:50:00'); // 05:30 - 07:20 local

    test()->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('work-sessions.store'), [
            'started_at' => '2026-06-09 20:30:00', // 06:00 local
            'ended_at' => '2026-06-10 05:30:00',   // 15:00 local
            'fit_to_existing' => true,
        ]);

    $session = WorkSession::where('user_id', $user->id)->latest('id')->first();
    expect($session->started_at->clone()->setTimezone('Australia/Adelaide')->format('H:i'))->toBe('08:00');
    expect($session->ended_at->clone()->setTimezone('Australia/Adelaide')->format('H:i'))->toBe('15:00');
});
