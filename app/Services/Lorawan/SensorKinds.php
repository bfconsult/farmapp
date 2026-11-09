<?php

namespace App\Services\Lorawan;

use App\Models\Device;

/**
 * What a sensor is used for, as opposed to the hardware it is
 * (DeviceModels). The kind decides its page, its map icon and the states
 * it can be in. A kind is offered for any model that reports the readings
 * it needs, so a new model of an existing kind needs only a decoder.
 *
 * Adding a kind means an entry here, its page (Manage/Sensors/<page>) and
 * its icons in resources/js/Utils/sensorIcons.jsx.
 */
class SensorKinds
{
    public const KINDS = [
        'gate' => [
            'label' => 'Gate',
            'page' => 'Gate',
            'requires' => ['door_open'],
            // The state is the latest value of this reading.
            'state_metric' => 'door_open',
            'states' => [
                0 => ['key' => 'closed', 'label' => 'Closed'],
                1 => ['key' => 'open', 'label' => 'Open'],
            ],
        ],
    ];

    public static function label(?string $kind): ?string
    {
        return self::KINDS[$kind]['label'] ?? null;
    }

    public static function page(?string $kind): ?string
    {
        return self::KINDS[$kind]['page'] ?? null;
    }

    /**
     * The kinds a model can be used as.
     */
    public static function forModel(string $model): array
    {
        $metrics = array_keys(DeviceModels::metrics($model));

        return array_keys(array_filter(
            self::KINDS,
            fn ($kind) => array_diff($kind['requires'], $metrics) === [],
        ));
    }

    /**
     * For the frontend: [{value, label, models: [model keys]}].
     */
    public static function options(): array
    {
        return collect(self::KINDS)
            ->map(fn ($kind, $key) => [
                'value' => $key,
                'label' => $kind['label'],
                'models' => array_values(array_filter(
                    array_keys(DeviceModels::MODELS),
                    fn ($model) => in_array($key, self::forModel($model), true),
                )),
            ])
            ->values()
            ->all();
    }

    /**
     * Its current state as {key, label}, or null for a sensor with no kind
     * or nothing reported yet.
     */
    public static function state(Device $device): ?array
    {
        $kind = self::KINDS[$device->kind] ?? null;
        if (! $kind) {
            return null;
        }

        $reading = $device->readings()
            ->where('metric', $kind['state_metric'])
            ->latest('recorded_at')
            ->latest('id')
            ->first();

        return $reading ? ($kind['states'][(int) $reading->value] ?? null) : null;
    }
}
