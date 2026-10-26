<?php

namespace App\Services\Lorawan\Decoders;

/**
 * Dragino LDS02 door sensor. Ported from Dragino's own decoder
 * (github.com/dragino/dragino-end-node-decoder, LDS02_Decoder_1.8.2) and
 * checked against the samples in docs/lorawan-ingest-spec.md.
 *
 * fPort 10 (10 bytes):
 *   0-1  bit 15 door open, bit 14 water leak, bits 13-0 battery mV
 *   2    mode: 1 = door, 2 = water leak, 3 = both (no counters)
 *   3-5  open (or leak) count
 *   6-8  last open (or leak) duration, minutes
 *   9    bit 0 alarm (open longer than the device's own timeout)
 * fPort 7 (EDC mode, 5 bytes): bytes 0-1 as above, 2-4 open count.
 */
class DraginoLds02Decoder implements SensorDecoder
{
    public function decode(string $bytes, int $fPort): array
    {
        $b = array_values(unpack('C*', $bytes) ?: []);

        if ($fPort === 7) {
            $this->expectLength($b, 5);

            return [
                ...$this->statusWord($b),
                'open_count' => $this->uint24($b, 2),
            ];
        }

        if ($fPort !== 10) {
            return [];
        }

        $this->expectLength($b, 10);

        $values = [
            ...$this->statusWord($b),
            'alarm' => $b[9] & 0x01,
        ];

        return match ($b[2]) {
            1 => [...$values, 'open_count' => $this->uint24($b, 3), 'last_open_minutes' => $this->uint24($b, 6)],
            2 => [...$values, 'water_leak' => ($b[0] & 0x40) ? 1 : 0, 'leak_count' => $this->uint24($b, 3), 'last_leak_minutes' => $this->uint24($b, 6)],
            3 => [...$values, 'water_leak' => ($b[0] & 0x40) ? 1 : 0],
            default => throw new InvalidPayload("Unknown LDS02 mode {$b[2]}"),
        };
    }

    private function statusWord(array $b): array
    {
        return [
            'door_open' => ($b[0] & 0x80) ? 1 : 0,
            'battery' => ((($b[0] << 8) | $b[1]) & 0x3FFF) / 1000,
        ];
    }

    private function uint24(array $b, int $offset): int
    {
        return ($b[$offset] << 16) | ($b[$offset + 1] << 8) | $b[$offset + 2];
    }

    private function expectLength(array $b, int $length): void
    {
        if (count($b) !== $length) {
            throw new InvalidPayload('Expected '.$length.' bytes, got '.count($b));
        }
    }
}
