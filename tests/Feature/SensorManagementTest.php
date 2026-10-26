<?php

use App\Models\Device;
use App\Models\LorawanEvent;
use App\Models\Property;
use App\Models\Role;
use App\Models\SensorReading;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
});

// The first real uplink from the farm's door sensor (docs/lorawan-ingest-spec.md).
function capturedUplink(string $devEui = 'a84041210862a0ce'): LorawanEvent
{
    return LorawanEvent::create([
        'event' => 'uplink',
        'dev_eui' => $devEui,
        'body' => [
            'data' => '8C960100000800000000',
            'fCnt' => 9,
            'fPort' => 10,
            'devEUI' => $devEui,
            'rxInfo' => [['rssi' => -57, 'loRaSNR' => 12]],
            'timestamp' => 1792998613,
            'deviceName' => 'door1',
            'data_encode' => 'hexstring',
        ],
    ])->fresh();
}

function asAdmin($test)
{
    return $test->actingAs($test->user)->withSession(['current_property_id' => $test->property->id]);
}

test('adding a sensor decodes what the gateway already captured from it', function () {
    capturedUplink();

    asAdmin($this)->post(route('manage.sensors.store'), [
        'name' => 'Shed door',
        'model' => 'dragino_lds02',
        'dev_eui' => 'A8:40:41:21:08:62:A0:CE',
    ])->assertSessionHasErrors('dev_eui');

    $response = asAdmin($this)->post(route('manage.sensors.store'), [
        'name' => 'Shed door',
        'model' => 'dragino_lds02',
        'dev_eui' => 'A84041210862A0CE',
    ]);

    $device = Device::sole();
    $response->assertRedirect(route('manage.sensors.show', $device));
    expect($device->dev_eui)->toBe('a84041210862a0ce')
        ->and($device->property_id)->toBe($this->property->id)
        ->and(SensorReading::where('device_id', $device->id)->count())->toBe(5);
});

test('a DevEUI can only be registered once', function () {
    $other = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Device::create(['property_id' => $other->id, 'name' => 'Theirs', 'model' => 'dragino_lds02', 'dev_eui' => 'a84041210862a0ce']);

    asAdmin($this)->post(route('manage.sensors.store'), [
        'name' => 'Mine',
        'model' => 'dragino_lds02',
        'dev_eui' => 'A84041210862A0CE',
    ])->assertSessionHasErrors('dev_eui');
});

test('the inspector shows each uplink raw alongside what it decoded to', function () {
    capturedUplink();
    asAdmin($this)->post(route('manage.sensors.store'), ['name' => 'Shed door', 'model' => 'dragino_lds02', 'dev_eui' => 'a84041210862a0ce']);

    asAdmin($this)->get(route('manage.sensors.show', Device::sole()))
        ->assertInertia(fn ($page) => $page
            ->component('Manage/Sensors/Show')
            ->where('device.battery', 3.222)
            ->where('device.shown_metrics', ['door_open', 'battery'])
            ->where('events.0.data', '8C960100000800000000')
            ->where('events.0.values.door_open', 1)
            ->where('events.0.values.open_count', 8)
            ->where('metrics.0.recorded_at', fn ($value) => $value !== null)
        );
});

test('the fields shown can be chosen per sensor', function () {
    $device = Device::create(['property_id' => $this->property->id, 'name' => 'Shed door', 'model' => 'dragino_lds02', 'dev_eui' => 'a84041210862a0ce']);

    asAdmin($this)->patch(route('manage.sensors.shown-metrics', $device), ['shown_metrics' => ['door_open', 'open_count']])
        ->assertRedirect();
    expect($device->fresh()->shownMetrics())->toBe(['door_open', 'open_count']);

    asAdmin($this)->patch(route('manage.sensors.shown-metrics', $device), ['shown_metrics' => ['not_a_field']])
        ->assertSessionHasErrors('shown_metrics.0');
});

test('removing a sensor leaves its uplinks to be decoded again if re-added', function () {
    $event = capturedUplink();
    asAdmin($this)->post(route('manage.sensors.store'), ['name' => 'Shed door', 'model' => 'dragino_lds02', 'dev_eui' => 'a84041210862a0ce']);

    asAdmin($this)->delete(route('manage.sensors.destroy', Device::sole()))
        ->assertRedirect(route('manage.sensors.index'));

    expect(Device::count())->toBe(0)
        ->and(SensorReading::count())->toBe(0)
        ->and($event->fresh()->processed_at)->toBeNull();
});

test('another property\'s sensor is not visible or editable', function () {
    $other = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    $device = Device::create(['property_id' => $other->id, 'name' => 'Theirs', 'model' => 'dragino_lds02', 'dev_eui' => 'a84041210862a0ce']);

    asAdmin($this)->get(route('manage.sensors.show', $device))->assertNotFound();
    asAdmin($this)->patch(route('manage.sensors.update', $device), ['name' => 'Hijacked'])->assertNotFound();
    asAdmin($this)->delete(route('manage.sensors.destroy', $device))->assertNotFound();
    expect($device->fresh()->name)->toBe('Theirs');
});

test('unrecognised devices are listed for app admins only', function () {
    capturedUplink('a840410000000001');

    asAdmin($this)->get(route('manage.sensors.index'))
        ->assertInertia(fn ($page) => $page->where('unrecognised', []));

    $this->user->forceFill(['app_admin' => true])->save();

    asAdmin($this)->get(route('manage.sensors.index'))
        ->assertInertia(fn ($page) => $page
            ->where('unrecognised.0.dev_eui', 'a840410000000001')
            ->where('unrecognised.0.device_name', 'door1')
            ->where('unrecognised.0.uplinks', 1)
        );
});

test('workers cannot manage sensors', function () {
    $worker = User::factory()->create();
    Role::create(['user_id' => $worker->id, 'property_id' => $this->property->id, 'type' => Role::WORKER]);

    $this->actingAs($worker)->withSession(['current_property_id' => $this->property->id])
        ->get(route('manage.sensors.index'))
        ->assertForbidden();
});
