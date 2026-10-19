<?php

use App\Models\LorawanEvent;

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
