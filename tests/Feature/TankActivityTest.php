<?php

use App\Mail\TankLow;
use App\Models\Device;
use App\Models\LorawanEvent;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\Lorawan\SensorKinds;
use App\Services\Lorawan\UplinkProcessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Australia/Adelaide', 'claimed_at' => now()]);
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
    // 2000 mm down to the floor when empty, 400 mm when full, 10,000 L:
    // each 16 mm is 1%.
    $this->device = Device::create([
        'property_id' => $this->property->id,
        'name' => 'House tank',
        'model' => 'dragino_dds75',
        'kind' => 'tank',
        'dev_eui' => 'a840418f4362f80e',
        'latitude' => -34.9,
        'longitude' => 138.6,
        'config' => ['settings' => ['empty_distance' => 2000, 'full_distance' => 400, 'capacity' => 10000, 'warn_below_percent' => 20]],
    ]);
    $this->fCnt = 0;

    // In UTC like the server - see GateActivityTest.
    Carbon::setTestNow(Carbon::parse('2026-11-20 12:00', 'Australia/Adelaide')->utc());
});

afterEach(fn () => Carbon::setTestNow());

// A DDS75 uplink with this distance, sent at a local Adelaide time, processed
// as the gateway's would be.
function tankUplink($test, string $at, int $distance): void
{
    $event = LorawanEvent::create([
        'property_id' => $test->property->id,
        'event' => 'uplink',
        'dev_eui' => 'a840418f4362f80e',
        'body' => [
            'data' => '0EC4'.sprintf('%04X', $distance).'000CCC01',
            'fCnt' => ++$test->fCnt,
            'fPort' => 2,
            'devEUI' => 'a840418f4362f80e',
            'rxInfo' => [['rssi' => -51, 'loRaSNR' => 12]],
            'timestamp' => Carbon::parse($at, 'Australia/Adelaide')->getTimestamp(),
            'data_encode' => 'hexstring',
        ],
    ]);
    app(UplinkProcessor::class)->process($event);
}

function tankPage($test, array $query = [])
{
    return $test->actingAs($test->user)
        ->withSession(['current_property_id' => $test->property->id])
        ->get(route('manage.sensors.show', [$test->device, ...$query]));
}

test('a DDS75 can be a water tank', function () {
    expect(SensorKinds::forModel('dragino_dds75'))->toBe(['tank']);
});

test('the level is worked out from the distance and the tank\'s size', function () {
    tankUplink($this, '2026-11-19 08:00', 720);   // 80%
    tankUplink($this, '2026-11-19 08:20', 1040);  // 60%
    tankUplink($this, '2026-11-19 21:00', 880);   // 70%
    tankUplink($this, '2026-11-20 11:50', 1200);  // 50%

    tankPage($this)->assertInertia(fn ($page) => $page
        ->component('Manage/Sensors/Tank')
        ->where('status.distance', 1200)
        ->where('status.percent', 50)
        ->where('status.litres', 5000)
        ->where('status.reporting', true)
        ->where('status.low', false)
        ->where('days', ['2026-11-19' => 70, '2026-11-20' => 50])
        ->where('levels.2026-11-19.min', 60)
        ->where('levels.2026-11-19.max', 80)
        ->where('levels.2026-11-19.last', 70)
        ->where('levels.2026-11-19.last_litres', 7000)
        // One point an hour: 08:00 and 08:20 share one.
        ->has('levels.2026-11-19.points', 2)
        ->where('firstDate', '2026-11-19')
    );
});

test('readings past full or empty stay within 0-100%', function () {
    tankUplink($this, '2026-11-20 11:00', 300);
    tankPage($this)->assertInertia(fn ($page) => $page->where('status.percent', 100));

    tankUplink($this, '2026-11-20 11:20', 2100);
    tankPage($this)->assertInertia(fn ($page) => $page->where('status.percent', 0));
});

test('until its size is set it shows only the distance', function () {
    $this->device->update(['config' => []]);
    tankUplink($this, '2026-11-20 11:50', 633);

    tankPage($this)->assertInertia(fn ($page) => $page
        ->where('status.configured', false)
        ->where('status.distance', 633)
        ->where('status.percent', null)
        ->where('days', [])
    );

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->get(route('map'))
        ->assertInertia(fn ($page) => $page->where('sensors.0.state', ['key' => 'unknown', 'label' => 'Tank size not set']));
});

test('the map shows its level, flagged when low or not reporting', function () {
    $mapState = fn () => $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])->get(route('map'));

    tankUplink($this, '2026-11-20 11:50', 880);
    $mapState()->assertInertia(fn ($page) => $page->where('sensors.0.state', ['key' => 'ok', 'label' => '70% full']));

    tankUplink($this, '2026-11-20 11:55', 1760);
    $mapState()->assertInertia(fn ($page) => $page->where('sensors.0.state', ['key' => 'low', 'label' => 'Low - 15% full']));

    Carbon::setTestNow(Carbon::parse('2026-11-20 13:30', 'Australia/Adelaide')->utc());
    $mapState()->assertInertia(fn ($page) => $page->where('sensors.0.state.key', 'not_reporting'));
});

test('the sensors list shows the water in the tank rather than the distance', function () {
    $list = fn () => $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])->get(route('manage.sensors.index'));
    tankUplink($this, '2026-11-20 11:50', 880);

    $list()->assertInertia(fn ($page) => $page
        ->where('devices.0.latest.0.label', 'Level')
        ->where('devices.0.latest.0.display', '7,000 L (70%)'));

    // No capacity: just the percentage.
    $this->device->update(['config' => ['settings' => ['empty_distance' => 2000, 'full_distance' => 400]]]);
    $list()->assertInertia(fn ($page) => $page->where('devices.0.latest.0.display', '70%'));

    // No size: the raw distance.
    $this->device->update(['config' => []]);
    $list()->assertInertia(fn ($page) => $page
        ->where('devices.0.latest.0.value', 880)
        ->missing('devices.0.latest.0.display'));
});

test('admins and managers are emailed once when it drops below its warning level, and again after a refill', function () {
    Mail::fake();
    $worker = User::factory()->create(['claimed_at' => now()]);
    Role::create(['user_id' => $worker->id, 'property_id' => $this->property->id, 'type' => Role::WORKER]);

    // Alerts only look at fresh readings, so these are all in the last
    // 15 minutes.
    tankUplink($this, '2026-11-20 11:46', 1600);  // 25%
    Mail::assertNothingSent();

    tankUplink($this, '2026-11-20 11:48', 1700);  // 18.75%
    Mail::assertSent(TankLow::class, 1);
    Mail::assertSent(TankLow::class, fn ($mail) => $mail->hasTo($this->user->email) && $mail->level['percent'] == 18.8);
    Mail::assertNotSent(TankLow::class, fn ($mail) => $mail->hasTo($worker->email));

    // Wobbling around the line, and lower still: no repeat.
    tankUplink($this, '2026-11-20 11:50', 1670);  // 20.6%
    tankUplink($this, '2026-11-20 11:52', 1800);  // 12.5%
    Mail::assertSent(TankLow::class, 1);

    // Refilled past 25%, then down again.
    tankUplink($this, '2026-11-20 11:54', 1000);
    tankUplink($this, '2026-11-20 11:56', 1750);
    Mail::assertSent(TankLow::class, 2);
});

test('no low-level email when it is turned off', function () {
    Mail::fake();
    $this->device->update(['config' => ['settings' => [...$this->device->config['settings'], 'warn_below_percent' => 0]]]);

    tankUplink($this, '2026-11-20 11:58', 1900);
    Mail::assertNothingSent();
});

test('the tank\'s size is saved from the Edit form', function () {
    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->patch(route('manage.sensors.update', $this->device), [
            'name' => 'House tank',
            'kind' => 'tank',
            'settings' => ['empty_distance' => 2200, 'full_distance' => 350, 'capacity' => 22500, 'warn_below_percent' => 25],
        ])
        ->assertRedirect(route('manage.sensors.show', $this->device));

    expect($this->device->fresh()->config['settings'])
        ->toEqual(['empty_distance' => 2200, 'full_distance' => 350, 'capacity' => 22500, 'warn_below_percent' => 25]);
});
