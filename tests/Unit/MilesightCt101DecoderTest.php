<?php

use App\Services\Lorawan\Decoders\InvalidPayload;
use App\Services\Lorawan\Decoders\MilesightCt101Decoder;

// The examples from Milesight's own decoder README
// (github.com/Milesight-IoT/SensorDecoders, ct-series/ct101-v2).
function ct101(string $hex): array
{
    return (new MilesightCt101Decoder)->decode(hex2bin($hex), 85);
}

test('decodes total current', function () {
    expect(ct101('039710270000'))->toBe(['total_current' => 100]);
});

test('decodes current, stopping at channels it has no length for', function () {
    expect(ct101('0498B80B00000000'))->toBe(['current' => 30]);
});

test('a failed current reading is left out', function () {
    expect(ct101('0498FFFF'))->toBe([]);
});

test('decodes a current alarm', function () {
    expect(ct101('8498B80BD007C40905'))->toBe([
        'current_max' => 30,
        'current_min' => 20,
        'current' => 25,
        'current_alarm' => 5,
    ]);
});

test('notes a power-on and skips other device information to reach the readings after it', function () {
    expect(ct101('FF0BFF'.'FF0101'.'FF166746D38802580000'.'FF090100'.'FF0A0101'.'FF0F00'.'04983200'))
        ->toBe(['powered_on' => 1, 'current' => 0.5]);
});

test('decodes several readings in one uplink, including below-zero temperatures', function () {
    // 1234.56 Ah, 2.5 A, -4.5 °C, 12.34 kWh.
    expect(ct101('039740E20100'.'0498FA00'.'0967D3FF'.'1063D2040000'))->toBe([
        'total_current' => 1234.56,
        'current' => 2.5,
        'temperature' => -4.5,
        'energy' => 12.34,
    ]);
});

test('a truncated channel is rejected', function () {
    ct101('039710');
})->throws(InvalidPayload::class);

// The first real uplink from the farm's CT101, before it was clamped on
// the pump (docs/lorawan-ingest-spec.md, "Captured samples").
test('decodes a real uplink, leaving out the failed temperature reading', function () {
    expect(ct101('039700000000049800000967FFFF'))->toBe(['total_current' => 0, 'current' => 0]);
});

test('decodes real uplinks from the pump running and the low battery alarm', function () {
    expect(ct101('03970A00000004981F010967FFFF'))->toBe(['total_current' => 0.1, 'current' => 2.87])
        ->and(ct101('137501'))->toBe(['low_battery' => 1]);
});
