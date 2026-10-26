<?php

namespace App\Services\Lorawan\Decoders;

interface SensorDecoder
{
    /**
     * Turn one uplink's payload into named values, e.g.
     * ['door_open' => 1, 'battery' => 3.222]. Returns [] for a port that
     * carries nothing worth storing; throws InvalidPayload for bytes that
     * don't match the layout.
     *
     * @return array<string, int|float>
     */
    public function decode(string $bytes, int $fPort): array;
}
