<?php

use App\Models\Gateway;
use App\Models\LorawanEvent;
use App\Models\Property;

beforeEach(function () {
    config(['services.lorawan.key' => 'test-secret']);
});

test('a request without the key is rejected', function () {
    $this->postJson('/api/lorawan/uplink', ['test' => true])->assertUnauthorized();

    expect(LorawanEvent::count())->toBe(0);
});

test('a request with the wrong key is rejected', function () {
    $this->postJson('/api/lorawan/uplink', ['test' => true], ['X-Fieldwerkz-Ingest-Key' => 'wrong'])
        ->assertUnauthorized();

    expect(LorawanEvent::count())->toBe(0);
});

test('every request is rejected when no key is configured', function () {
    config(['services.lorawan.key' => null]);

    $this->postJson('/api/lorawan/uplink', ['test' => true], ['X-Fieldwerkz-Ingest-Key' => ''])
        ->assertUnauthorized();
});

test('a request with the right key is stored as received', function () {
    $body = ['devEUI' => 'a840411234567890', 'data' => '0b45', 'rxInfo' => [['rssi' => -87, 'loRaSNR' => 9.5]]];

    $this->postJson('/api/lorawan/uplink', $body, ['X-Fieldwerkz-Ingest-Key' => 'test-secret'])
        ->assertNoContent();

    $event = LorawanEvent::sole();
    expect($event->event)->toBe('uplink')
        ->and($event->body)->toBe($body)
        ->and($event->received_at)->not->toBeNull();
});

test('each gateway event type is accepted', function (string $event) {
    $this->postJson("/api/lorawan/{$event}", ['test' => true], ['X-Fieldwerkz-Ingest-Key' => 'test-secret'])
        ->assertNoContent();

    expect(LorawanEvent::where('event', $event)->exists())->toBeTrue();
})->with(['uplink', 'join', 'ack', 'status']);

test('an unknown event name is a 404', function () {
    $this->postJson('/api/lorawan/reboot', ['test' => true], ['X-Fieldwerkz-Ingest-Key' => 'test-secret'])
        ->assertNotFound();

    expect(LorawanEvent::count())->toBe(0);
});

test('the gateway gets its own 120/min limit, not the api group\'s 60/min', function () {
    for ($i = 0; $i < 61; $i++) {
        $this->postJson('/api/lorawan/status', ['i' => $i], ['X-Fieldwerkz-Ingest-Key' => 'test-secret'])
            ->assertNoContent();
    }
});

test('the header name is matched regardless of case', function () {
    $this->postJson('/api/lorawan/uplink', ['test' => true], ['x-fieldwerkz-ingest-key' => 'test-secret'])
        ->assertNoContent();
});

test('a gateway\'s own key files its events under its property', function () {
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $gateway = new Gateway(['property_id' => $property->id, 'name' => 'Shed', 'gateway_eui' => 'ac1f09fffe291787']);
    $key = $gateway->issueKey();

    $this->postJson('/api/lorawan/uplink', ['devEUI' => 'a84041210862a0ce', 'rxInfo' => [['gatewayID' => 'AC1F09FFFE291787']]], ['X-Fieldwerkz-Ingest-Key' => $key])
        ->assertNoContent();

    $event = LorawanEvent::sole();
    expect($event->property_id)->toBe($property->id)
        ->and($event->gateway_id)->toBe($gateway->id)
        ->and($event->gateway_eui)->toBe('ac1f09fffe291787')
        ->and($gateway->fresh()->last_seen_at)->not->toBeNull();
});

test('a gateway key is rejected when the body names a different gateway', function () {
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $key = (new Gateway(['property_id' => $property->id, 'name' => 'Shed', 'gateway_eui' => 'ac1f09fffe291787']))->issueKey();

    $this->postJson('/api/lorawan/uplink', ['rxInfo' => [['gatewayID' => '0000000000000001']]], ['X-Fieldwerkz-Ingest-Key' => $key])
        ->assertForbidden();

    expect(LorawanEvent::count())->toBe(0);
});

test('a gateway key is accepted on events that don\'t name a gateway', function () {
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $key = (new Gateway(['property_id' => $property->id, 'name' => 'Shed', 'gateway_eui' => 'ac1f09fffe291787']))->issueKey();

    $this->postJson('/api/lorawan/status', ['ok' => true], ['X-Fieldwerkz-Ingest-Key' => $key])->assertNoContent();

    expect(LorawanEvent::sole()->property_id)->toBe($property->id);
});

test('the shared key files events under the property that registered the gateway EUI', function () {
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    (new Gateway(['property_id' => $property->id, 'name' => 'Shed', 'gateway_eui' => 'ac1f09fffe291787']))->issueKey();

    $this->postJson('/api/lorawan/uplink', ['rxInfo' => [['gatewayID' => 'ac1f09fffe291787']]], ['X-Fieldwerkz-Ingest-Key' => 'test-secret'])->assertNoContent();
    $this->postJson('/api/lorawan/uplink', ['rxInfo' => [['gatewayID' => '0000000000000001']]], ['X-Fieldwerkz-Ingest-Key' => 'test-secret'])->assertNoContent();

    expect(LorawanEvent::orderBy('id')->pluck('property_id')->all())->toBe([$property->id, null])
        ->and(LorawanEvent::orderBy('id')->pluck('gateway_eui')->all())->toBe(['ac1f09fffe291787', '0000000000000001']);
});
