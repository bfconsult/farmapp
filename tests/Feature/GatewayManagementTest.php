<?php

use App\Models\Device;
use App\Models\Gateway;
use App\Models\LorawanEvent;
use App\Models\Property;
use App\Models\Role;
use App\Models\SensorReading;
use App\Models\User;

beforeEach(function () {
    config(['services.lorawan.key' => 'shared-secret']);
    $this->user = User::factory()->create();
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
});

function asGatewayAdmin($test)
{
    return $test->actingAs($test->user)->withSession(['current_property_id' => $test->property->id]);
}

function addGateway($test): array
{
    $response = asGatewayAdmin($test)->post(route('manage.gateways.store'), [
        'name' => 'Shed gateway',
        'gateway_eui' => 'AC1F09FFFE291787',
    ]);
    $gateway = Gateway::sole();
    $response->assertRedirect(route('manage.gateways.show', $gateway))->assertSessionHas('gatewayKey');

    return [$gateway, session('gatewayKey')];
}

test('adding a gateway issues a key that is shown once and works for ingest', function () {
    [$gateway, $key] = addGateway($this);

    expect($gateway->gateway_eui)->toBe('ac1f09fffe291787')
        ->and($gateway->property_id)->toBe($this->property->id)
        ->and($gateway->key_hash)->toBe(hash('sha256', $key))
        ->and($gateway->toArray())->not->toHaveKey('key_hash');

    // The redirect after adding shows it; revisiting doesn't - only its
    // hash is stored.
    asGatewayAdmin($this)->get(route('manage.gateways.show', $gateway))
        ->assertInertia(fn ($page) => $page->where('issuedKey', $key));
    asGatewayAdmin($this)->get(route('manage.gateways.show', $gateway))
        ->assertInertia(fn ($page) => $page
            ->component('Manage/Sensors/Gateway')
            ->where('issuedKey', null)
            ->where('eventUrls.uplink', route('lorawan.ingest', 'uplink'))
        );

    $this->postJson('/api/lorawan/status', ['ok' => true], ['X-Fieldwerkz-Ingest-Key' => $key])->assertNoContent();
    expect(LorawanEvent::sole()->property_id)->toBe($this->property->id);
});

test('issuing a new key stops the old one working', function () {
    [$gateway, $oldKey] = addGateway($this);

    asGatewayAdmin($this)->post(route('manage.gateways.regenerate-key', $gateway))->assertSessionHas('gatewayKey');
    $newKey = session('gatewayKey');

    $this->postJson('/api/lorawan/status', ['ok' => true], ['X-Fieldwerkz-Ingest-Key' => $oldKey])->assertUnauthorized();
    $this->postJson('/api/lorawan/status', ['ok' => true], ['X-Fieldwerkz-Ingest-Key' => $newKey])->assertNoContent();
});

test('adding a gateway claims and decodes what it sent with the shared key before', function () {
    Device::create(['property_id' => $this->property->id, 'name' => 'Shed door', 'model' => 'dragino_lds02', 'dev_eui' => 'a84041210862a0ce']);
    $this->postJson('/api/lorawan/uplink', [
        'data' => '8C960100000800000000',
        'fCnt' => 9,
        'fPort' => 10,
        'devEUI' => 'a84041210862a0ce',
        'rxInfo' => [['rssi' => -57, 'loRaSNR' => 12, 'gatewayID' => 'ac1f09fffe291787']],
        'timestamp' => 1792998613,
        'data_encode' => 'hexstring',
    ], ['X-Fieldwerkz-Ingest-Key' => 'shared-secret'])->assertNoContent();
    expect(LorawanEvent::sole()->property_id)->toBeNull();

    [$gateway] = addGateway($this);

    expect(LorawanEvent::sole()->property_id)->toBe($this->property->id)
        ->and(LorawanEvent::sole()->gateway_id)->toBe($gateway->id)
        ->and(SensorReading::count())->toBe(5);
});

test('a gateway EUI can only be added to one property', function () {
    $other = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    (new Gateway(['property_id' => $other->id, 'name' => 'Theirs', 'gateway_eui' => 'ac1f09fffe291787']))->issueKey();

    asGatewayAdmin($this)->post(route('manage.gateways.store'), ['name' => 'Mine', 'gateway_eui' => 'AC1F09FFFE291787'])
        ->assertSessionHasErrors('gateway_eui');
});

test('another property\'s gateway cannot be seen, rekeyed or removed', function () {
    $other = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    $gateway = new Gateway(['property_id' => $other->id, 'name' => 'Theirs', 'gateway_eui' => 'ac1f09fffe291787']);
    $key = $gateway->issueKey();

    asGatewayAdmin($this)->get(route('manage.gateways.show', $gateway))->assertNotFound();
    asGatewayAdmin($this)->post(route('manage.gateways.regenerate-key', $gateway))->assertNotFound();
    asGatewayAdmin($this)->delete(route('manage.gateways.destroy', $gateway))->assertNotFound();

    expect($gateway->fresh()->key_hash)->toBe(hash('sha256', $key));
});

test('removing a gateway stops its key working but keeps its events', function () {
    [$gateway, $key] = addGateway($this);
    $this->postJson('/api/lorawan/status', ['ok' => true], ['X-Fieldwerkz-Ingest-Key' => $key])->assertNoContent();

    asGatewayAdmin($this)->delete(route('manage.gateways.destroy', $gateway))->assertRedirect(route('manage.sensors.index'));

    $this->postJson('/api/lorawan/status', ['ok' => true], ['X-Fieldwerkz-Ingest-Key' => $key])->assertUnauthorized();
    expect(LorawanEvent::sole()->property_id)->toBe($this->property->id);
});

test('workers cannot manage gateways', function () {
    $worker = User::factory()->create();
    Role::create(['user_id' => $worker->id, 'property_id' => $this->property->id, 'type' => Role::WORKER]);

    $this->actingAs($worker)->withSession(['current_property_id' => $this->property->id])
        ->post(route('manage.gateways.store'), ['name' => 'Sneaky', 'gateway_eui' => 'ac1f09fffe291787'])
        ->assertForbidden();
});
