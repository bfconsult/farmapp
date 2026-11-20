<?php

use App\Services\Lorawan\Decoders\DraginoDds75Decoder;
use App\Services\Lorawan\Decoders\InvalidPayload;

// The farm's DDS75-LS on the house water tank, 2026-11-20 - "Captured
// samples" in docs/lorawan-ingest-spec.md.
test('decodes a real uplink, with no temperature probe fitted', function () {
    expect((new DraginoDds75Decoder)->decode(hex2bin('0EC40279000CCC01'), 2))->toBe([
        'battery' => 3.78,
        'distance' => 633,
        'interrupt' => 0,
        'sensor_detected' => 1,
    ]);
});

test('decodes a temperature probe, including below zero', function () {
    // The manual's examples: 0x0105 = 26.1 °C, 0xFF3F = -19.3 °C.
    expect((new DraginoDds75Decoder)->decode(hex2bin('0EC40B0501010501'), 2))
        ->toMatchArray(['distance' => 2821, 'interrupt' => 1, 'temperature' => 26.1]);
    expect((new DraginoDds75Decoder)->decode(hex2bin('0EC40B0500FF3F01'), 2)['temperature'])->toBe(-19.3);
});

test('stores no distance when the ultrasonic sensor isn\'t detected', function () {
    expect((new DraginoDds75Decoder)->decode(hex2bin('0EC40000000CCC00'), 2))->toBe([
        'battery' => 3.78,
        'interrupt' => 0,
        'sensor_detected' => 0,
    ]);
});

test('reads a charged solar battery over 4.096 V as a battery, not a different payload', function () {
    // 0x1068 = 4200 mV sets bit 4 of byte 0.
    expect((new DraginoDds75Decoder)->decode(hex2bin('10680279000CCC01'), 2))
        ->toMatchArray(['battery' => 4.2, 'distance' => 633]);
});

test('reads the battery from a device status uplink', function () {
    // Model 0x27, firmware 1.0.0, AU915 sub-band 2, 3780 mV.
    expect((new DraginoDds75Decoder)->decode(hex2bin('27010004020EC4'), 5))->toBe(['battery' => 3.78]);
});

test('ignores ports with nothing to store, and rejects a wrong-length reading', function () {
    // The farm's sensor sent a one-byte fPort 4 uplink on joining.
    expect((new DraginoDds75Decoder)->decode(hex2bin('00'), 4))->toBe([]);
    (new DraginoDds75Decoder)->decode(hex2bin('0EC40279000CCC'), 2);
})->throws(InvalidPayload::class);
