<?php

namespace App\Services\Lorawan;

use App\Services\Lorawan\Decoders\DraginoLds02Decoder;
use App\Services\Lorawan\Decoders\MilesightCt101Decoder;
use App\Services\Lorawan\Decoders\SensorDecoder;

/**
 * The sensor models the app can decode. Adding a new kind of sensor means
 * a decoder class (built from a captured payload, see
 * docs/lorawan-ingest-spec.md) plus an entry here; `lorawan:reprocess`
 * then decodes any uplinks it sent before that.
 *
 * Every field a decoder returns is stored. `metrics` only labels them and
 * picks the ones shown by default - each device can choose its own.
 */
class DeviceModels
{
    public const MODELS = [
        'dragino_lds02' => [
            'label' => 'Dragino LDS02 door/gate sensor',
            'decoder' => DraginoLds02Decoder::class,
            'metrics' => [
                'door_open' => ['label' => 'Door', 'unit' => null, 'states' => ['Closed', 'Open'], 'default' => true],
                'battery' => ['label' => 'Battery', 'unit' => 'V', 'default' => true],
                'open_count' => ['label' => 'Times opened', 'unit' => null, 'default' => false],
                'last_open_minutes' => ['label' => 'Last open for', 'unit' => 'min', 'default' => false],
                'alarm' => ['label' => 'Open-too-long alarm', 'unit' => null, 'states' => ['Off', 'On'], 'default' => false],
                'water_leak' => ['label' => 'Water leak', 'unit' => null, 'states' => ['Dry', 'Leak'], 'default' => false],
                'leak_count' => ['label' => 'Leak count', 'unit' => null, 'default' => false],
                'last_leak_minutes' => ['label' => 'Last leak for', 'unit' => 'min', 'default' => false],
            ],
        ],
        'milesight_ct101' => [
            'label' => 'Milesight CT101 current sensor',
            'decoder' => MilesightCt101Decoder::class,
            'metrics' => [
                'current' => ['label' => 'Current', 'unit' => 'A', 'default' => true],
                'temperature' => ['label' => 'Temperature', 'unit' => '°C', 'default' => false],
                'total_current' => ['label' => 'Total current', 'unit' => 'Ah', 'default' => false],
                'energy' => ['label' => 'Energy', 'unit' => 'kWh', 'default' => false],
                'current_alarm' => ['label' => 'Current alarm', 'unit' => null, 'states' => [1 => 'Threshold alarm', 2 => 'Threshold released', 4 => 'Over range', 8 => 'Over range released'], 'default' => false],
                'current_max' => ['label' => 'Max current (alarm)', 'unit' => 'A', 'default' => false],
                'current_min' => ['label' => 'Min current (alarm)', 'unit' => 'A', 'default' => false],
                'temperature_alarm' => ['label' => 'Temperature alarm', 'unit' => null, 'states' => ['Released', 'Alarm'], 'default' => false],
                'low_battery' => ['label' => 'Low battery', 'unit' => null, 'states' => ['No', 'Yes'], 'default' => false],
                'powered_on' => ['label' => 'Powered on', 'unit' => null, 'states' => [1 => 'Yes'], 'default' => false],
            ],
            // It sends an alarm when its battery runs low but never an
            // all-clear; it restarts, and says so, when the battery is changed.
            'low_battery_alarm' => ['metric' => 'low_battery', 'cleared_by' => 'powered_on'],
        ],
    ];

    public static function exists(string $model): bool
    {
        return isset(self::MODELS[$model]);
    }

    public static function decoder(string $model): SensorDecoder
    {
        return app(self::MODELS[$model]['decoder']);
    }

    public static function metrics(string $model): array
    {
        return self::MODELS[$model]['metrics'] ?? [];
    }

    public static function defaultMetrics(string $model): array
    {
        return array_keys(array_filter(self::metrics($model), fn ($metric) => $metric['default']));
    }

    /**
     * For the frontend: [{value, label, metrics: {name: {label, unit, states}}}].
     */
    public static function options(): array
    {
        return collect(self::MODELS)
            ->map(fn ($model, $key) => ['value' => $key, 'label' => $model['label'], 'metrics' => $model['metrics']])
            ->values()
            ->all();
    }
}
