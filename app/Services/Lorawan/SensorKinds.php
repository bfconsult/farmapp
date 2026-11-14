<?php

namespace App\Services\Lorawan;

use App\Models\Device;

/**
 * What a sensor is used for, as opposed to the hardware it is
 * (DeviceModels). The kind decides its page, its map icon and the states
 * it can be in. A kind is offered for any model that reports the readings
 * it needs, so a new model of an existing kind needs only a decoder.
 *
 * Adding a kind means an entry here, an activity class for its page
 * (month() and status(), see DoorActivity), the page itself
 * (Manage/Sensors/<page>) and its icons in resources/js/Utils/sensorIcons.jsx.
 */
class SensorKinds
{
    public const KINDS = [
        'gate' => [
            'label' => 'Gate',
            'page' => 'Gate',
            'activity' => DoorActivity::class,
            'requires' => ['door_open'],
            // The state is the latest value of this reading...
            'state_metric' => 'door_open',
            'states' => [
                0 => ['key' => 'closed', 'label' => 'Closed'],
                1 => ['key' => 'open', 'label' => 'Open'],
            ],
            'settings' => [],
        ],
        'pump' => [
            'label' => 'Pump',
            'page' => 'Pump',
            'activity' => PumpActivity::class,
            'requires' => ['current'],
            'state_metric' => 'current',
            // ...or, with a threshold, whether it's above that setting. A
            // stopped pump's circuit doesn't always read zero.
            'state_threshold' => 'running_above',
            'states' => [
                0 => ['key' => 'stopped', 'label' => 'Stopped'],
                1 => ['key' => 'running', 'label' => 'Running'],
            ],
            'settings' => [
                'running_above' => [
                    'label' => 'Running above',
                    'unit' => 'A',
                    'default' => 0.5,
                    'min' => 0,
                    'max' => 1000,
                    'help' => 'The pump counts as running while it draws more current than this.',
                ],
                'warn_after_minutes' => [
                    'label' => 'Warn if running longer than',
                    'unit' => 'min',
                    'default' => 30,
                    'min' => 0,
                    'max' => 1440,
                    'help' => 'Admins and managers are emailed once per run. 0 turns it off.',
                ],
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

    public static function activity(Device $device, string $timezone): ?object
    {
        $class = self::KINDS[$device->kind]['activity'] ?? null;

        return $class ? new $class($device, $timezone) : null;
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
     * For the frontend: [{value, label, models: [model keys], settings}].
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
                'settings' => $kind['settings'],
            ])
            ->values()
            ->all();
    }

    /**
     * Validation rules for a kind's settings, as `settings.<name>`.
     */
    public static function settingRules(?string $kind): array
    {
        return collect(self::KINDS[$kind]['settings'] ?? [])
            ->mapWithKeys(fn ($setting, $name) => [
                "settings.{$name}" => ['nullable', 'numeric', "min:{$setting['min']}", "max:{$setting['max']}"],
            ])
            ->all();
    }

    /**
     * A device's setting, or its kind's default until one is saved.
     */
    public static function setting(Device $device, string $name): mixed
    {
        return $device->config['settings'][$name]
            ?? self::KINDS[$device->kind]['settings'][$name]['default']
            ?? null;
    }

    /**
     * The state a reading of the kind's state metric puts the device in, as
     * the index into its states.
     */
    public static function stateIndex(Device $device, float $value): int
    {
        $threshold = self::KINDS[$device->kind]['state_threshold'] ?? null;

        return $threshold ? (int) ($value > self::setting($device, $threshold)) : (int) $value;
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

        return $reading ? ($kind['states'][self::stateIndex($device, $reading->value)] ?? null) : null;
    }
}
