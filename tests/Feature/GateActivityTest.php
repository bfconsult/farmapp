<?php

use App\Models\Device;
use App\Models\Property;
use App\Models\Role;
use App\Models\SensorReading;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Australia/Adelaide']);
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
    $this->device = Device::create(['property_id' => $this->property->id, 'name' => 'Main gate', 'model' => 'dragino_lds02', 'dev_eui' => 'a84041210862a0ce']);
    $this->fCnt = 0;

    // In UTC like the server - a test-now in another timezone makes Carbon
    // read the database's times in that timezone.
    Carbon::setTestNow(Carbon::parse('2026-11-20 12:00', 'Australia/Adelaide')->utc());
});

afterEach(fn () => Carbon::setTestNow());

// One LDS02 uplink at a local Adelaide time.
function gateUplink($test, string $at, int $open, int $count, ?int $lastOpenMinutes = 0): void
{
    $recordedAt = Carbon::parse($at, 'Australia/Adelaide')->utc();
    $fCnt = ++$test->fCnt;

    foreach (array_filter(['door_open' => $open, 'open_count' => $count, 'last_open_minutes' => $lastOpenMinutes], fn ($v) => $v !== null) as $metric => $value) {
        SensorReading::create(['device_id' => $test->device->id, 'recorded_at' => $recordedAt, 'metric' => $metric, 'value' => $value, 'f_cnt' => $fCnt]);
    }
}

function gatePage($test, array $query = [])
{
    return $test->actingAs($test->user)
        ->withSession(['current_property_id' => $test->property->id])
        ->get(route('manage.sensors.show', [$test->device, ...$query]));
}

test('openings are counted per day in the user\'s timezone, each with when it closed', function () {
    gateUplink($this, '2026-11-02 18:00', 0, 10);
    gateUplink($this, '2026-11-03 07:40', 1, 11);
    gateUplink($this, '2026-11-03 07:42', 0, 11, 2);
    gateUplink($this, '2026-11-03 16:05', 1, 12);
    gateUplink($this, '2026-11-03 16:05:40', 0, 12, 0);
    // 00:30 Adelaide on the 4th is still the 3rd in UTC.
    gateUplink($this, '2026-11-04 00:30', 1, 13);
    gateUplink($this, '2026-11-04 00:40', 0, 13, 10);

    gatePage($this)->assertInertia(fn ($page) => $page
        ->component('Manage/Sensors/Show')
        ->where('month', '2026-11')
        ->where('today', '2026-11-20')
        ->where('firstDate', '2026-11-02')
        ->where('days', ['2026-11-02' => 0, '2026-11-03' => 2, '2026-11-04' => 1])
        ->has('openings', 3)
        ->where('openings.0.date', '2026-11-03')
        ->where('openings.0.seconds', 120)
        ->where('openings.1.seconds', 40)
        ->where('openings.2.date', '2026-11-04')
        ->where('status.open', false)
        ->where('status.since', Carbon::parse('2026-11-04 00:40', 'Australia/Adelaide')->utc()->toIso8601String())
    );
});

test('openings the gateway missed are still counted from the sensor\'s running total', function () {
    gateUplink($this, '2026-11-05 08:00', 0, 20);
    // Three openings, only the last one's open uplink arrived.
    gateUplink($this, '2026-11-05 09:00', 1, 23);
    // Close lost; the next periodic uplink reports the 5 minutes it was open.
    gateUplink($this, '2026-11-05 11:00', 0, 23, 5);

    gatePage($this)->assertInertia(fn ($page) => $page
        ->where('days.2026-11-05', 3)
        ->has('openings', 1)
        ->where('openings.0.missed', 2)
        ->where('openings.0.seconds', 300)
        ->where('openings.0.closed_at', Carbon::parse('2026-11-05 09:05', 'Australia/Adelaide')->utc()->toIso8601String())
    );
});

test('a gate still open shows as open since it opened', function () {
    gateUplink($this, '2026-11-20 06:00', 0, 30);
    gateUplink($this, '2026-11-20 10:15', 1, 31);

    gatePage($this)->assertInertia(fn ($page) => $page
        ->where('status.open', true)
        ->where('status.since', Carbon::parse('2026-11-20 10:15', 'Australia/Adelaide')->utc()->toIso8601String())
        ->where('openings.0.closed_at', null)
    );
});

test('an earlier month is shown on its own, compared against the uplink before it', function () {
    gateUplink($this, '2026-10-30 08:00', 1, 40);
    gateUplink($this, '2026-10-30 08:01', 0, 40, 1);
    gateUplink($this, '2026-11-01 09:00', 1, 41);

    gatePage($this, ['month' => '2026-10'])->assertInertia(fn ($page) => $page
        ->where('month', '2026-10')
        ->where('days', ['2026-10-30' => 1])
        ->has('openings', 1)
    );

    gatePage($this)->assertInertia(fn ($page) => $page
        ->where('days', ['2026-11-01' => 1])
    );
});

test('the open count starting again after the sensor rejoins counts as new openings', function () {
    gateUplink($this, '2026-11-06 08:00', 0, 50);
    gateUplink($this, '2026-11-06 09:00', 1, 1);

    gatePage($this)->assertInertia(fn ($page) => $page->where('days.2026-11-06', 1));
});

test('a bad month is rejected', function () {
    gatePage($this, ['month' => 'november'])->assertSessionHasErrors('month');
});
