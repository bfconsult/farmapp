<?php

use App\Models\Device;
use App\Models\Gateway;
use App\Models\LorawanEvent;
use App\Models\Property;
use App\Models\SensorReading;
use App\Services\Lorawan\UplinkProcessor;

beforeEach(function () {
    config(['services.lorawan.key' => 'test-secret']);
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $this->gatewayKey = (new Gateway(['property_id' => $this->property->id, 'name' => 'Shed', 'gateway_eui' => 'ac1f09fffe291787']))->issueKey();
});

// The first real uplink from the farm's door sensor (docs/lorawan-ingest-spec.md).
function doorUplink(array $overrides = []): array
{
    return array_merge([
        'adr' => true,
        'data' => '8C960100000800000000',
        'fCnt' => 9,
        'fPort' => 10,
        'devEUI' => 'a84041210862a0ce',
        'rxInfo' => [[
            'rssi' => -57,
            'loRaSNR' => 12,
            'location' => ['altitude' => 0, 'latitude' => 0, 'longitude' => 0],
            'gatewayID' => 'ac1f09fffe291787',
        ]],
        'txInfo' => ['dr' => 5, 'frequency' => 917200000],
        'timestamp' => 1792998613,
        'deviceName' => 'door1',
        'data_encode' => 'hexstring',
        'applicationID' => '1',
        'applicationName' => 'Farm_Sensors',
    ], $overrides);
}

function postUplink($test, array $body)
{
    return $test->postJson('/api/lorawan/uplink', $body, ['X-Fieldwerkz-Ingest-Key' => $test->gatewayKey]);
}

function doorDevice(Property $property): Device
{
    return Device::create([
        'property_id' => $property->id,
        'name' => 'Shed door',
        'model' => 'dragino_lds02',
        'dev_eui' => 'a84041210862a0ce',
    ]);
}

test('an uplink from a registered device is decoded into one reading per field', function () {
    $device = doorDevice($this->property);

    postUplink($this, doorUplink())->assertNoContent();

    $readings = SensorReading::where('device_id', $device->id)->pluck('value', 'metric');
    expect($readings->all())->toEqual([
        'door_open' => 1.0,
        'battery' => 3.222,
        'alarm' => 0.0,
        'open_count' => 8.0,
        'last_open_minutes' => 0.0,
    ]);

    $reading = SensorReading::where('metric', 'door_open')->sole();
    expect($reading->recorded_at->timestamp)->toBe(1792998613)
        ->and($reading->rssi)->toBe(-57)
        ->and($reading->snr)->toBe(12.0)
        ->and($reading->f_cnt)->toBe(9);

    $device->refresh();
    expect($device->last_seen_at->timestamp)->toBe(1792998613)
        ->and((float) $device->battery)->toBe(3.222)
        ->and($device->last_rssi)->toBe(-57);

    expect(LorawanEvent::sole()->processed_at)->not->toBeNull();
});

test('an uplink from an unregistered device is stored but left undecoded', function () {
    postUplink($this, doorUplink())->assertNoContent();

    $event = LorawanEvent::sole();
    expect($event->dev_eui)->toBe('a84041210862a0ce')
        ->and($event->processed_at)->toBeNull()
        ->and(SensorReading::count())->toBe(0);
});

test('registering a device later decodes its backlog', function () {
    postUplink($this, doorUplink());
    postUplink($this, doorUplink(['data' => '0C9C0100000800000000', 'fCnt' => 10, 'timestamp' => 1792998625]));

    $device = doorDevice($this->property);
    expect(app(UplinkProcessor::class)->processBacklog($device))->toBe(2);

    expect(SensorReading::where('metric', 'door_open')->orderBy('recorded_at')->pluck('value')->all())->toEqual([1.0, 0.0])
        ->and($device->fresh()->last_seen_at->timestamp)->toBe(1792998625)
        ->and($device->latestReadings()['door_open']->value)->toEqual(0.0);
});

test('a retried uplink is not stored twice', function () {
    doorDevice($this->property);

    postUplink($this, doorUplink());
    postUplink($this, doorUplink());

    expect(LorawanEvent::count())->toBe(2)
        ->and(SensorReading::where('metric', 'door_open')->count())->toBe(1);
});

test('the same frame counter after a rejoin is still stored', function () {
    doorDevice($this->property);

    postUplink($this, doorUplink());
    postUplink($this, doorUplink(['timestamp' => 1792999613]));

    expect(SensorReading::where('metric', 'door_open')->count())->toBe(2);
});

test('an older uplink decoded late does not overwrite newer device status', function () {
    $device = doorDevice($this->property);

    postUplink($this, doorUplink(['data' => '0C9C0100000800000000', 'timestamp' => 1792998625]));
    postUplink($this, doorUplink(['fCnt' => 8, 'timestamp' => 1792998000]));

    expect((float) $device->fresh()->battery)->toBe(3.228);
});

test('an undecodable payload is marked with its error and the raw event kept', function () {
    doorDevice($this->property);

    postUplink($this, doorUplink(['data' => '8C96']))->assertNoContent();

    $event = LorawanEvent::sole();
    expect($event->processed_at)->not->toBeNull()
        ->and($event->error)->toContain('Expected 10 bytes')
        ->and(SensorReading::count())->toBe(0);
});

test('reprocessing retries uplinks that failed to decode', function () {
    $device = doorDevice($this->property);
    postUplink($this, doorUplink());
    LorawanEvent::query()->update(['error' => 'old decoder bug']);
    SensorReading::query()->delete();

    $this->artisan('lorawan:reprocess', ['--device' => $device->id])->assertSuccessful();

    expect(LorawanEvent::sole()->error)->toBeNull()
        ->and(SensorReading::count())->toBe(5);
});

test('a sensor registered on another property is not decoded from this gateway', function () {
    $other = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    doorDevice($other);

    postUplink($this, doorUplink());

    expect(SensorReading::count())->toBe(0)
        ->and(LorawanEvent::sole()->property_id)->toBe($this->property->id);
});

test('a gateway timestamp from before clock sync falls back to when it was received', function () {
    doorDevice($this->property);

    postUplink($this, doorUplink(['timestamp' => 12]));

    expect(SensorReading::first()->recorded_at->year)->toBe(now()->year);
});

test('prune keeps undecoded uplinks but drops old decoded ones and status events', function () {
    $device = doorDevice($this->property);
    postUplink($this, doorUplink());
    postUplink($this, doorUplink(['devEUI' => 'a840410000000001']));
    $this->postJson('/api/lorawan/status', ['ok' => true], ['X-Fieldwerkz-Ingest-Key' => 'test-secret']);
    LorawanEvent::query()->update(['received_at' => now()->subDays(31)]);

    $this->artisan('lorawan:prune')->assertSuccessful();

    expect(LorawanEvent::pluck('dev_eui')->all())->toBe(['a840410000000001'])
        ->and(SensorReading::where('device_id', $device->id)->count())->toBe(5);
});
