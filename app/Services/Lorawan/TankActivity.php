<?php

namespace App\Services\Lorawan;

use App\Models\Device;
use App\Models\SensorReading;
use Carbon\Carbon;

/**
 * A water tank's level from an ultrasonic sensor above the water, which
 * reports its distance down to the surface. For an upright tank with
 * straight sides the level goes up evenly with depth, so it's worked out
 * from the tank's settings: the distance when empty (sensor to the floor
 * or outlet), the distance when full, and optionally the capacity in
 * litres. Until both distances are set, only the raw distance is known.
 */
class TankActivity
{
    // The DDS75 reports every 20 minutes by default.
    public const STALE_AFTER_MINUTES = 90;

    // Once warned, it's warned again only after refilling this far above
    // the warning level, so a reading wobbling around it can't repeat it.
    public const REARM_ABOVE_PERCENT = 5;

    public function __construct(private Device $device, private string $timezone) {}

    /**
     * {percent, litres (null with no capacity set)}, or null until the
     * tank's distances are set.
     */
    public static function level(Device $device, float $distance): ?array
    {
        $empty = SensorKinds::setting($device, 'empty_distance');
        $full = SensorKinds::setting($device, 'full_distance');
        $capacity = (float) SensorKinds::setting($device, 'capacity');
        if ($empty === null || $full === null || $empty <= $full) {
            return null;
        }

        $percent = max(0, min(100, ($empty - $distance) / ($empty - $full) * 100));

        return [
            'percent' => round($percent, 1),
            'litres' => $capacity > 0 ? (int) round($percent / 100 * $capacity) : null,
        ];
    }

    /**
     * The litres a distance reading means, or null until the tank's size
     * and capacity are set - what a Metric linked to it records.
     */
    public static function litres(Device $device, float $distance): ?float
    {
        return self::level($device, $distance)['litres'] ?? null;
    }

    /**
     * Its "dropped by" warnings as [{percent, within_minutes}]: each warns
     * when the level falls by that much of a full tank within that time.
     */
    public static function dropWarnings(Device $device): array
    {
        return $device->config['drop_warnings'] ?? [];
    }

    /**
     * A warning's time in words: "1 hour", "2 days", "90 minutes".
     */
    public static function describeMinutes(int $minutes): string
    {
        [$count, $unit] = match (true) {
            $minutes % 1440 === 0 => [$minutes / 1440, 'day'],
            $minutes % 60 === 0 => [$minutes / 60, 'hour'],
            default => [$minutes, 'minute'],
        };

        return $count.' '.$unit.($count === 1 ? '' : 's');
    }

    /**
     * How far the level has fallen by $reading from its highest in the
     * $minutes before it: {drop, from_percent, from_at, percent, litres},
     * or null until the tank's size is set.
     */
    public static function dropWithin(Device $device, SensorReading $reading, int $minutes): ?array
    {
        $level = self::level($device, $reading->value);
        // The highest level is the shortest distance down to the water.
        $highest = $device->readings()
            ->where('metric', 'distance')
            ->whereBetween('recorded_at', [$reading->recorded_at->copy()->subMinutes($minutes), $reading->recorded_at])
            ->orderBy('value')
            ->orderBy('recorded_at')
            ->first();
        $from = $highest ? self::level($device, $highest->value) : null;
        if (! $level || ! $from) {
            return null;
        }

        return [
            'drop' => round(max(0, $from['percent'] - $level['percent']), 1),
            'from_percent' => $from['percent'],
            'from_at' => $highest->recorded_at,
            'percent' => $level['percent'],
            'litres' => $level['litres'],
            'from_litres' => $from['litres'],
        ];
    }

    public static function isLow(Device $device, ?array $level): bool
    {
        $warnBelow = (float) SensorKinds::setting($device, 'warn_below_percent');

        return $level !== null && $warnBelow > 0 && $level['percent'] < $warnBelow;
    }

    /**
     * Its map/list state from its latest distance reading (SensorKinds::state).
     */
    public static function state(Device $device, SensorReading $reading): array
    {
        $level = self::level($device, $reading->value);
        if (! $level) {
            return ['key' => 'unknown', 'label' => 'Tank size not set'];
        }

        return self::isLow($device, $level)
            ? ['key' => 'low', 'label' => 'Low - '.round($level['percent']).'% full']
            : ['key' => 'ok', 'label' => round($level['percent']).'% full'];
    }

    /**
     * Its latest reading: {distance, percent, litres, at, reporting, low,
     * configured}.
     */
    public function status(): ?array
    {
        $latest = $this->device->readings()->where('metric', 'distance')->latest('recorded_at')->latest('id')->first();
        if (! $latest) {
            return null;
        }

        $level = self::level($this->device, $latest->value);

        return [
            'distance' => (int) $latest->value,
            'percent' => $level['percent'] ?? null,
            'litres' => $level['litres'] ?? null,
            'at' => $latest->recorded_at->toIso8601String(),
            'reporting' => $latest->recorded_at->gte(Carbon::now()->subMinutes(self::STALE_AFTER_MINUTES)),
            'low' => self::isLow($this->device, $level),
            'configured' => $level !== null,
        ];
    }

    /**
     * The month's levels: `days` is each day's last level (%) for the
     * calendar, `levels` each day's lowest, highest and last with its
     * readings, one per hour, for that day's chart.
     *
     * @return array{days: array<string, float>, levels: array, firstDate: ?string, settings: array}
     */
    public function month(Carbon $monthStart): array
    {
        $from = $monthStart->copy()->setTimezone($this->timezone)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $days = [];
        $levels = [];

        $this->device->readings()
            ->where('metric', 'distance')
            ->whereBetween('recorded_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['recorded_at', 'value'])
            ->each(function (SensorReading $reading) use (&$days, &$levels) {
                $level = self::level($this->device, $reading->value);
                if (! $level) {
                    return;
                }

                $local = $reading->recorded_at->copy()->setTimezone($this->timezone);
                $date = $local->toDateString();
                $day = $levels[$date] ?? ['min' => $level['percent'], 'max' => $level['percent'], 'points' => []];

                $day['min'] = min($day['min'], $level['percent']);
                $day['max'] = max($day['max'], $level['percent']);
                $day['last'] = $level['percent'];
                $day['last_litres'] = $level['litres'];
                // The hour's latest reading.
                $day['points'][$local->hour] = [$reading->recorded_at->toIso8601String(), $level['percent']];

                $levels[$date] = $day;
                $days[$date] = $level['percent'];
            });

        $first = $this->device->readings()->where('metric', 'distance')->min('recorded_at');

        return [
            'days' => $days,
            'levels' => array_map(fn ($day) => [...$day, 'points' => array_values($day['points'])], $levels),
            'firstDate' => $first ? Carbon::parse($first, 'UTC')->setTimezone($this->timezone)->toDateString() : null,
            'settings' => [
                'empty_distance' => SensorKinds::setting($this->device, 'empty_distance'),
                'full_distance' => SensorKinds::setting($this->device, 'full_distance'),
                'capacity' => SensorKinds::setting($this->device, 'capacity'),
                'warn_below_percent' => (float) SensorKinds::setting($this->device, 'warn_below_percent'),
            ],
        ];
    }
}
