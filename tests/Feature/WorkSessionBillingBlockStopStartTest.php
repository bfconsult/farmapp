<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['billing_block_minutes' => 15, 'timezone' => 'Australia/Melbourne']);
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::WORKER]);
    $this->as = fn () => $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id]);
});

afterEach(fn () => Carbon::setTestNow());

test('stopping a session saves its end rounded up to the billing block', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-28 10:07:23', 'Australia/Melbourne')->utc());
    $session = WorkSession::create([
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'started_at' => Carbon::parse('2026-11-28 09:00', 'Australia/Melbourne')->utc(),
        'status' => WorkSession::DRAFT,
    ]);

    ($this->as)()->post(route('work-sessions.stop', $session))->assertRedirect();

    expect($session->fresh()->ended_at->setTimezone('Australia/Melbourne')->format('H:i:s'))->toBe('10:15:00')
        ->and($session->fresh()->duration_in_hours)->toEqual(1.25);
});

test('a session started straight after one was stopped begins where that one ended, not before', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-28 10:07:23', 'Australia/Melbourne')->utc());
    $first = WorkSession::create([
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'started_at' => Carbon::parse('2026-11-28 09:00', 'Australia/Melbourne')->utc(),
        'status' => WorkSession::DRAFT,
    ]);
    ($this->as)()->post(route('work-sessions.stop', $first));

    Carbon::setTestNow(Carbon::parse('2026-11-28 10:10:05', 'Australia/Melbourne')->utc());
    ($this->as)()->get(route('work-sessions.create'))->assertInertia(fn ($page) => $page
        ->where('startsAt', fn ($value) => Carbon::parse($value)->eq($first->fresh()->ended_at)));
    ($this->as)()->post(route('work-sessions.store'), []);

    $second = WorkSession::where('user_id', $this->user->id)->whereNull('ended_at')->sole();
    // Starts at 10:15, where the first ended - not 10:00, inside it.
    expect($second->started_at->eq($first->fresh()->ended_at))->toBeTrue()
        ->and($second->started_at->setTimezone('Australia/Melbourne')->format('H:i'))->toBe('10:15');
});

test('a session started later still begins at now rounded down', function () {
    WorkSession::create([
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'started_at' => Carbon::parse('2026-11-28 08:00', 'Australia/Melbourne')->utc(),
        'ended_at' => Carbon::parse('2026-11-28 09:00', 'Australia/Melbourne')->utc(),
        'status' => WorkSession::DRAFT,
    ]);
    Carbon::setTestNow(Carbon::parse('2026-11-28 10:10:05', 'Australia/Melbourne')->utc());

    ($this->as)()->post(route('work-sessions.store'), []);

    $session = WorkSession::where('user_id', $this->user->id)->whereNull('ended_at')->sole();
    expect($session->started_at->setTimezone('Australia/Melbourne')->format('H:i:s'))->toBe('10:00:00');
});

test('blocks line up with the hour in the user\'s timezone, even a half-hour one', function () {
    $user = User::factory()->create(['billing_block_minutes' => 60, 'timezone' => 'Australia/Adelaide']);
    $time = Carbon::parse('2026-11-28 10:20', 'Australia/Adelaide')->setTimezone('UTC');

    expect($user->floorToBillingBlock($time)->setTimezone('Australia/Adelaide')->format('H:i'))->toBe('10:00')
        ->and($user->ceilToBillingBlock($time)->setTimezone('Australia/Adelaide')->format('H:i'))->toBe('11:00');
});
