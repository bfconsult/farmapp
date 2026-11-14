<?php

use App\Models\Device;
use App\Models\LorawanEvent;
use App\Models\Property;
use App\Models\Role;
use App\Models\SensorReading;
use App\Models\User;
use App\Services\Lorawan\SensorKinds;
use App\Services\Lorawan\UplinkProcessor;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Australia/Adelaide']);
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
    $this->device = Device::create(['property_id' => $this->property->id, 'name' => 'Bore pump', 'model' => 'milesight_ct101', 'kind' => 'pump', 'dev_eui' => '24e124000000c101']);
    $this->fCnt = 0;

    // In UTC like the server - see GateActivityTest.
    Carbon::setTestNow(Carbon::parse('2026-11-20 12:00', 'Australia/Adelaide')->utc());
});

afterEach(fn () => Carbon::setTestNow());

// One current reading at a local Adelaide time.
function pumpReading($test, string $at, float $amps): void
{
    SensorReading::create([
        'device_id' => $test->device->id,
        'recorded_at' => Carbon::parse($at, 'Australia/Adelaide')->utc(),
        'metric' => 'current',
        'value' => $amps,
        'f_cnt' => ++$test->fCnt,
    ]);
}

function pumpPage($test, array $query = [])
{
    return $test->actingAs($test->user)
        ->withSession(['current_property_id' => $test->property->id])
        ->get(route('manage.sensors.show', [$test->device, ...$query]));
}

function adelaideIso(string $at): string
{
    return Carbon::parse($at, 'Australia/Adelaide')->utc()->toIso8601String();
}

test('runs are timed from when the current rises above the threshold to when it falls back', function () {
    pumpReading($this, '2026-11-03 06:00', 0.1);
    pumpReading($this, '2026-11-03 06:10', 4.0);
    pumpReading($this, '2026-11-03 06:20', 5.0);
    pumpReading($this, '2026-11-03 06:40', 0.2);
    pumpReading($this, '2026-11-03 15:00', 4.5);
    pumpReading($this, '2026-11-03 15:30', 0.0);

    pumpPage($this)->assertInertia(fn ($page) => $page
        ->component('Manage/Sensors/Pump')
        ->where('threshold', 0.5)
        ->where('firstDate', '2026-11-03')
        ->where('days', ['2026-11-03' => 60 * 60])
        ->has('runs', 2)
        ->where('runs.0.started_at', adelaideIso('2026-11-03 06:10'))
        ->where('runs.0.ended_at', adelaideIso('2026-11-03 06:40'))
        ->where('runs.0.seconds', 30 * 60)
        ->where('runs.0.average_current', 4.5)
        ->where('runs.1.dates', ['2026-11-03'])
        ->where('status.running', false)
        ->where('status.since', adelaideIso('2026-11-03 15:30'))
        ->where('status.current', 0)
    );
});

test('a run over midnight counts towards both days', function () {
    pumpReading($this, '2026-11-04 23:00', 3.0);
    pumpReading($this, '2026-11-05 01:00', 3.0);
    pumpReading($this, '2026-11-05 02:00', 0.0);

    pumpPage($this)->assertInertia(fn ($page) => $page
        ->where('days', ['2026-11-04' => 3600, '2026-11-05' => 2 * 3600])
        ->where('runs.0.dates', ['2026-11-04', '2026-11-05'])
        ->where('runs.0.seconds', 3 * 3600)
    );
});

test('a pump still running counts up to now', function () {
    pumpReading($this, '2026-11-20 10:00', 0.0);
    pumpReading($this, '2026-11-20 11:00', 6.0);
    pumpReading($this, '2026-11-20 11:50', 6.0);

    pumpPage($this)->assertInertia(fn ($page) => $page
        ->where('days.2026-11-20', 3600)
        ->where('runs.0.ended_at', null)
        ->where('status.running', true)
        ->where('status.since', adelaideIso('2026-11-20 11:00'))
    );
});

test('a run that goes quiet for hours ends at its last reading', function () {
    pumpReading($this, '2026-11-06 08:00', 3.0);
    pumpReading($this, '2026-11-06 08:30', 3.0);
    // Uplinks lost until the evening.
    pumpReading($this, '2026-11-06 18:00', 0.0);

    pumpPage($this)->assertInertia(fn ($page) => $page
        ->where('days.2026-11-06', 30 * 60)
        ->where('runs.0.ended_at', adelaideIso('2026-11-06 08:30'))
    );
});

test('the running threshold is set per sensor', function () {
    pumpReading($this, '2026-11-07 08:00', 0.8);
    pumpReading($this, '2026-11-07 09:00', 0.0);

    pumpPage($this)->assertInertia(fn ($page) => $page->where('days.2026-11-07', 3600));

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->put(route('manage.sensors.update', $this->device), ['name' => 'Bore pump', 'kind' => 'pump', 'settings' => ['running_above' => 1.5]])
        ->assertSessionHasNoErrors();

    expect(SensorKinds::setting($this->device->fresh(), 'running_above'))->toBe(1.5);
    pumpPage($this)->assertInertia(fn ($page) => $page->where('threshold', 1.5)->where('days', []));

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->put(route('manage.sensors.update', $this->device), ['name' => 'Bore pump', 'kind' => 'pump', 'settings' => ['running_above' => -1]])
        ->assertSessionHasErrors('settings.running_above');
});

test('a CT101 can only be a pump, and a door sensor only a gate', function () {
    expect(SensorKinds::forModel('milesight_ct101'))->toBe(['pump'])
        ->and(SensorKinds::forModel('dragino_lds02'))->toBe(['gate']);
});

test('a CT101 uplink is stored and puts the pump on the map running', function () {
    $this->device->update(['latitude' => -34.9, 'longitude' => 138.6]);
    $event = LorawanEvent::create([
        'property_id' => $this->property->id,
        'event' => 'uplink',
        'dev_eui' => '24e124000000c101',
        'body' => [
            // Total 100 Ah, 5.5 A, 23.4 °C.
            'data' => '039710270000'.'04982602'.'0967EA00',
            'fCnt' => 3,
            'fPort' => 85,
            'devEUI' => '24e124000000c101',
            'rxInfo' => [['rssi' => -80, 'loRaSNR' => 7]],
            'timestamp' => Carbon::now()->subMinutes(5)->getTimestamp(),
            'data_encode' => 'hexstring',
        ],
    ]);

    app(UplinkProcessor::class)->process($event);

    expect($this->device->readings()->pluck('value', 'metric')->all())
        ->toEqual(['total_current' => 100, 'current' => 5.5, 'temperature' => 23.4]);

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->get(route('map'))
        ->assertInertia(fn ($page) => $page
            ->where('sensors.0.kind', 'pump')
            ->where('sensors.0.state', ['key' => 'running', 'label' => 'Running'])
        );
});

test('a low battery shows on the pump page while the sensor keeps reporting it', function () {
    pumpPage($this)->assertInertia(fn ($page) => $page->where('device.low_battery', null));

    // "Now" is 12:00; it repeats the alarm every couple of minutes.
    SensorReading::create(['device_id' => $this->device->id, 'recorded_at' => Carbon::parse('2026-11-20 11:20', 'Australia/Adelaide')->utc(), 'metric' => 'low_battery', 'value' => 1, 'f_cnt' => ++$this->fCnt]);
    SensorReading::create(['device_id' => $this->device->id, 'recorded_at' => Carbon::parse('2026-11-20 11:22', 'Australia/Adelaide')->utc(), 'metric' => 'low_battery', 'value' => 1, 'f_cnt' => ++$this->fCnt]);

    pumpPage($this)->assertInertia(fn ($page) => $page
        ->where('device.low_battery.reported_at', adelaideIso('2026-11-20 11:22'))
        ->where('device.low_battery.advice', fn ($advice) => str_contains($advice, 'charges itself'))
    );

    // An hour with no alarm: it has charged.
    Carbon::setTestNow(Carbon::parse('2026-11-20 12:30', 'Australia/Adelaide')->utc());
    pumpPage($this)->assertInertia(fn ($page) => $page->where('device.low_battery', null));
});
