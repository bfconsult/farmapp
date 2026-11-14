<?php

namespace App\Services\Lorawan\Decoders;

/**
 * Milesight CT101 current sensor (CT103/CT105 share the format). Ported
 * from Milesight's own decoder (github.com/Milesight-IoT/SensorDecoders,
 * ct-series/ct101-v2, which covers both firmware versions).
 *
 * The payload is a run of channels, each a 1-byte id, 1-byte type and a
 * value of a length fixed by the pair, multi-byte values little-endian:
 *   03 97  4  total current, Ah (uint32 / 100)
 *   04 98  2  current, A (uint16 / 100; FFFF = read failed)
 *   09 67  2  temperature, °C (int16 / 10; FFFD/FFFF = sensor fault)
 *   10 63  4  energy, kWh (uint32 / 100) - firmware v2
 *   13 75  1  low battery alarm - firmware v2
 *   84 98  7  current alarm: max, min, current (uint16 / 100 each) + alarm bits
 *   89 67  3  temperature alarm: temperature (int16 / 10) + 0/1 alarm
 *   FF xx     device information (versions, serial number, ...) - skipped
 */
class MilesightCt101Decoder implements SensorDecoder
{
    // Lengths of the device information channels (id FF), skipped.
    private const INFO_LENGTHS = [
        0x01 => 1, // IPSO version
        0x09 => 2, // hardware version
        0x0A => 2, // firmware version
        0xFF => 2, // TSL version
        0x16 => 8, // serial number
        0x0F => 1, // LoRaWAN class
        0xFE => 1, // reset event
        0x0B => 1, // device status
    ];

    public function decode(string $bytes, int $fPort): array
    {
        $b = array_values(unpack('C*', $bytes) ?: []);
        $values = [];

        for ($i = 0; $i < count($b);) {
            $this->expectBytes($b, $i, 2);
            $id = $b[$i++];
            $type = $b[$i++];

            switch (true) {
                case $id === 0x03 && $type === 0x97:
                    $this->expectBytes($b, $i, 4);
                    $values['total_current'] = $this->uint32($b, $i) / 100;
                    $i += 4;
                    break;

                case $id === 0x04 && $type === 0x98:
                    $this->expectBytes($b, $i, 2);
                    $raw = $this->uint16($b, $i);
                    if ($raw !== 0xFFFF) {
                        $values['current'] = $raw / 100;
                    }
                    $i += 2;
                    break;

                case $id === 0x09 && $type === 0x67:
                    $this->expectBytes($b, $i, 2);
                    $raw = $this->uint16($b, $i);
                    if ($raw !== 0xFFFD && $raw !== 0xFFFF) {
                        $values['temperature'] = $this->int16($b, $i) / 10;
                    }
                    $i += 2;
                    break;

                case $id === 0x10 && $type === 0x63:
                    $this->expectBytes($b, $i, 4);
                    $values['energy'] = $this->uint32($b, $i) / 100;
                    $i += 4;
                    break;

                case $id === 0x13 && $type === 0x75:
                    $this->expectBytes($b, $i, 1);
                    $values['low_battery'] = $b[$i] === 1 ? 1 : 0;
                    $i += 1;
                    break;

                case $id === 0x84 && $type === 0x98:
                    $this->expectBytes($b, $i, 7);
                    $values['current_max'] = $this->uint16($b, $i) / 100;
                    $values['current_min'] = $this->uint16($b, $i + 2) / 100;
                    $values['current'] = $this->uint16($b, $i + 4) / 100;
                    $values['current_alarm'] = $b[$i + 6];
                    $i += 7;
                    break;

                case $id === 0x89 && $type === 0x67:
                    $this->expectBytes($b, $i, 3);
                    $values['temperature'] = $this->int16($b, $i) / 10;
                    $values['temperature_alarm'] = $b[$i + 2] === 1 ? 1 : 0;
                    $i += 3;
                    break;

                case $id === 0xFF && isset(self::INFO_LENGTHS[$type]):
                    $i += self::INFO_LENGTHS[$type];
                    break;

                // Anything else has a length we don't know, so the rest
                // can't be read - Milesight's decoder stops here too.
                default:
                    return $values;
            }
        }

        return $values;
    }

    private function uint16(array $b, int $offset): int
    {
        return $b[$offset] | ($b[$offset + 1] << 8);
    }

    private function int16(array $b, int $offset): int
    {
        $value = $this->uint16($b, $offset);

        return $value > 0x7FFF ? $value - 0x10000 : $value;
    }

    private function uint32(array $b, int $offset): int
    {
        return $b[$offset] | ($b[$offset + 1] << 8) | ($b[$offset + 2] << 16) | ($b[$offset + 3] << 24);
    }

    private function expectBytes(array $b, int $offset, int $length): void
    {
        if ($offset + $length > count($b)) {
            throw new InvalidPayload('Payload ends part way through a channel');
        }
    }
}
