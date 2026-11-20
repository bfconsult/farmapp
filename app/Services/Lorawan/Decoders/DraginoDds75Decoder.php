<?php

namespace App\Services\Lorawan\Decoders;

/**
 * Dragino DDS75-LB / DDS75-LS (solar) ultrasonic distance sensor. The layout
 * is from Dragino's user manual (wiki.dragino.com, DDS75-LB "Uplink
 * Payload") and its decoder (github.com/dragino/dragino-end-node-decoder,
 * DDS75-LB), checked against the farm's first uplink.
 *
 * fPort 2 (8 bytes, big-endian):
 *   0-1  battery mV (bits 13-0)
 *   2-3  distance to the surface, mm. 0 = no ultrasonic sensor detected.
 *        The flat-object range is 280-7500 mm, so closer than 280 mm is
 *        in its blind zone.
 *   4    1 = sent because of the interrupt pin, 0 = periodic
 *   5-6  optional DS18B20 probe, °C x 10 signed. 0x0CCC (327.6) = no probe.
 *   7    1 = ultrasonic sensor detected
 * fPort 5 is device status (model, firmware, band, battery).
 *
 * Dragino's decoder takes bit 4 of byte 0 to mean a longer multi-reading
 * payload, but a charged solar battery (4096 mV and up) sets that bit too,
 * so the length is used instead and only the 8-byte payload is decoded.
 */
class DraginoDds75Decoder implements SensorDecoder
{
    private const NO_PROBE = 0x0CCC;

    public function decode(string $bytes, int $fPort): array
    {
        $b = array_values(unpack('C*', $bytes) ?: []);

        if ($fPort === 5) {
            $this->expectLength($b, 7);

            return ['battery' => $this->uint16($b, 5) / 1000];
        }

        if ($fPort !== 2) {
            return [];
        }

        $this->expectLength($b, 8);

        $distance = $this->uint16($b, 2);
        $temperature = $this->uint16($b, 5);
        $sensorDetected = $b[7] & 0x01;

        return array_filter([
            'battery' => ($this->uint16($b, 0) & 0x3FFF) / 1000,
            // Not stored with no sensor, rather than as a 0 mm reading.
            'distance' => $sensorDetected && $distance > 0 ? $distance : null,
            'interrupt' => $b[4] & 0x01,
            'temperature' => $temperature === self::NO_PROBE ? null : ($temperature >= 0x8000 ? $temperature - 0x10000 : $temperature) / 10,
            'sensor_detected' => $sensorDetected,
        ], fn ($value) => $value !== null);
    }

    private function uint16(array $b, int $offset): int
    {
        return ($b[$offset] << 8) | $b[$offset + 1];
    }

    private function expectLength(array $b, int $length): void
    {
        if (count($b) !== $length) {
            throw new InvalidPayload('Expected '.$length.' bytes, got '.count($b));
        }
    }
}
