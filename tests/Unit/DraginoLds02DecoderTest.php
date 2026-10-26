<?php

use App\Services\Lorawan\Decoders\DraginoLds02Decoder;
use App\Services\Lorawan\Decoders\InvalidPayload;

// Real uplinks from the farm's door sensor - "Captured samples" in
// docs/lorawan-ingest-spec.md.
test('decodes a real door-open uplink', function () {
    expect((new DraginoLds02Decoder)->decode(hex2bin('8C960100000800000000'), 10))->toBe([
        'door_open' => 1,
        'battery' => 3.222,
        'alarm' => 0,
        'open_count' => 8,
        'last_open_minutes' => 0,
    ]);
});

test('decodes a real door-closed uplink', function () {
    expect((new DraginoLds02Decoder)->decode(hex2bin('0C9C0100000800000000'), 10))->toBe([
        'door_open' => 0,
        'battery' => 3.228,
        'alarm' => 0,
        'open_count' => 8,
        'last_open_minutes' => 0,
    ]);
});

test('decodes multi-byte counters and the alarm bit', function () {
    // 0x00012C = 300 opens, 0x00005A = 90 minutes, alarm on.
    $values = (new DraginoLds02Decoder)->decode(hex2bin('8C9601'.'00012C'.'00005A'.'01'), 10);

    expect($values['open_count'])->toBe(300)
        ->and($values['last_open_minutes'])->toBe(90)
        ->and($values['alarm'])->toBe(1);
});

test('decodes water-leak mode', function () {
    $values = (new DraginoLds02Decoder)->decode(hex2bin('4C9C0200000300000500'), 10);

    expect($values)->toMatchArray(['door_open' => 0, 'water_leak' => 1, 'leak_count' => 3, 'last_leak_minutes' => 5])
        ->and($values)->not->toHaveKey('open_count');
});

test('decodes the 5-byte EDC uplink on port 7', function () {
    expect((new DraginoLds02Decoder)->decode(hex2bin('8C96000010'), 7))
        ->toBe(['door_open' => 1, 'battery' => 3.222, 'open_count' => 16]);
});

test('ignores other ports', function () {
    expect((new DraginoLds02Decoder)->decode(hex2bin('0102'), 5))->toBe([]);
});

test('rejects a payload of the wrong length', function () {
    (new DraginoLds02Decoder)->decode(hex2bin('8C9601'), 10);
})->throws(InvalidPayload::class);
