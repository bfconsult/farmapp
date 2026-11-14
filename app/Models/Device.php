<?php

namespace App\Models;

use App\Services\Lorawan\DeviceModels;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A LoRaWAN sensor registered to a property by its DevEUI. Its uplinks
 * arrive as LorawanEvents and are decoded into SensorReadings - see
 * App\Services\Lorawan\UplinkProcessor.
 */
class Device extends Model
{
    protected $fillable = [
        'property_id',
        'name',
        'model',
        'kind',
        'dev_eui',
        'config',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'config' => 'array',
        'last_seen_at' => 'datetime',
        'battery' => 'decimal:3',
        'last_snr' => 'decimal:2',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function readings()
    {
        return $this->hasMany(SensorReading::class);
    }

    /**
     * Raw events from this DevEUI that reached its own property's gateway.
     */
    public function events()
    {
        return $this->hasMany(LorawanEvent::class, 'dev_eui', 'dev_eui')->where('property_id', $this->property_id);
    }

    /**
     * The fields chosen to show for this device, falling back to its
     * model's defaults until someone picks.
     */
    public function shownMetrics(): array
    {
        return $this->config['shown_metrics'] ?? DeviceModels::defaultMetrics($this->model);
    }

    /**
     * Its latest low-battery alarm as {reported_at, advice}, while the
     * alarm is still repeating - for models that send one (DeviceModels
     * 'low_battery_alarm').
     */
    public function lowBattery(): ?array
    {
        $alarm = DeviceModels::MODELS[$this->model]['low_battery_alarm'] ?? null;
        if (! $alarm) {
            return null;
        }

        $reportedAt = $this->readings()
            ->where('metric', $alarm['metric'])
            ->where('value', 1)
            ->where('recorded_at', '>=', now()->subMinutes($alarm['repeats_within_minutes']))
            ->max('recorded_at');

        return $reportedAt ? [
            'reported_at' => Carbon::parse($reportedAt, 'UTC')->toIso8601String(),
            'advice' => $alarm['advice'],
        ] : null;
    }

    /**
     * The newest value of each metric, keyed by metric name. One query per
     * metric (a device has a handful) - ordered by recorded_at, not id,
     * since a backlog decoded late gets newer ids than it should.
     */
    public function latestReadings(): array
    {
        return $this->readings()->distinct()->pluck('metric')
            ->mapWithKeys(fn ($metric) => [
                $metric => $this->readings()->where('metric', $metric)->latest('recorded_at')->latest('id')->first(),
            ])
            ->all();
    }
}
