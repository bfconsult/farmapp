<?php

namespace App\Services\Lorawan;

use App\Models\Device;
use App\Models\Metric;
use App\Models\MetricMeasurement;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Fills in measurements of Metrics linked to a sensor, the way someone would
 * by hand: once a period is over, with the sensor's last reading in it. A
 * daily metric therefore has a value for the end of any report's dates.
 *
 * Only measurements nobody has touched are filled (source null, still
 * incomplete); a person's change marks one 'manual' and it's left alone.
 * A period with no readings stays incomplete to be filled in by hand.
 */
class MetricsFromSensors
{
    /**
     * The property's sensors a metric can be linked to: those whose kind
     * can record one (SensorKinds 'metric').
     */
    public static function linkable(?int $propertyId): Collection
    {
        return Device::where('property_id', $propertyId)
            ->whereIn('kind', array_keys(array_filter(SensorKinds::KINDS, fn ($kind) => isset($kind['metric']))))
            ->orderBy('name')
            ->get(['id', 'name', 'kind'])
            ->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'records' => SensorKinds::KINDS[$device->kind]['metric']['label'],
            ]);
    }

    /**
     * Fill every finished, untouched measurement of every linked metric.
     * Returns how many were filled.
     */
    public function fillAll(): int
    {
        $filled = 0;

        Metric::whereNotNull('device_id')->with('device')->get()->each(function (Metric $metric) use (&$filled) {
            if (! $metric->device) {
                return;
            }
            $timezone = self::timezone($metric->property_id);

            $metric->measurements()
                ->where('status', MetricMeasurement::INCOMPLETE)
                ->whereNull('source')
                ->get()
                ->each(function (MetricMeasurement $measurement) use ($metric, $timezone, &$filled) {
                    $filled += (int) $this->fill($metric, $measurement, $timezone);
                });
        });

        return $filled;
    }

    /**
     * Fill one measurement once its period is over where the property is.
     */
    public function fill(Metric $metric, MetricMeasurement $measurement, string $timezone): bool
    {
        $kind = SensorKinds::KINDS[$metric->device->kind] ?? null;
        if (! isset($kind['metric'])) {
            return false;
        }

        $from = Carbon::parse($measurement->period_start->toDateString(), $timezone)->startOfDay();
        $to = Carbon::parse($measurement->period_end->toDateString(), $timezone)->endOfDay();
        if ($to->isFuture()) {
            return false;
        }

        $reading = $metric->device->readings()
            ->where('metric', $kind['state_metric'])
            ->whereBetween('recorded_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->latest('recorded_at')
            ->latest('id')
            ->first();
        $value = $reading ? call_user_func($kind['metric']['value'], $metric->device, $reading->value) : null;
        if ($value === null) {
            return false;
        }

        $measurement->update([
            'value_number' => $value,
            'status' => MetricMeasurement::COMPLETE,
            'source' => MetricMeasurement::SENSOR,
            'completed_by' => null,
            // When it was measured, as for one recorded by hand.
            'completed_at' => $reading->recorded_at,
        ]);

        return true;
    }

    /**
     * Where the property is, for when its days end: its first admin's
     * timezone (properties don't have their own).
     */
    private static function timezone(int $propertyId): string
    {
        $admin = Role::where('property_id', $propertyId)
            ->where('type', Role::ADMIN)
            ->orderBy('id')
            ->with('user:id,timezone')
            ->first();

        return $admin?->user?->timezone ?? config('app.timezone');
    }
}
