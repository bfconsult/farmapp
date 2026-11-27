<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    /** A fence's details.fence_type, with its label. */
    public const FENCE_TYPES = [
        'plain' => 'Plain wire',
        'barbed' => 'Barbed wire',
        'electric' => 'Electric',
        'netting' => 'Netting',
        'post_and_rail' => 'Post and rail',
        'other' => 'Other',
    ];

    /** Vegetation's details.vegetation_type, with its label. */
    public const VEGETATION_TYPES = [
        'tree' => 'Trees',
        'shrubs' => 'Shrubs',
        'undergrowth' => 'Undergrowth',
    ];

    /**
     * Standard stock trough sizes (details.trough_size): its shape, length
     * (or diameter) and width in metres, and about how many litres it holds
     * - the capacity a new one starts with, which can be changed. "other"
     * has no standard capacity. Matches TROUGH_SIZES in AssetDetailsFields.jsx.
     */
    public const TROUGH_SIZES = [
        'round_1200' => ['label' => 'Round, 1.2 m across', 'shape' => 'round', 'length' => 1.2, 'width' => 1.2, 'litres' => 450],
        'round_1500' => ['label' => 'Round, 1.5 m across', 'shape' => 'round', 'length' => 1.5, 'width' => 1.5, 'litres' => 700],
        'round_1800' => ['label' => 'Round, 1.8 m across', 'shape' => 'round', 'length' => 1.8, 'width' => 1.8, 'litres' => 1100],
        'round_2400' => ['label' => 'Round, 2.4 m across', 'shape' => 'round', 'length' => 2.4, 'width' => 2.4, 'litres' => 2000],
        'rect_1200' => ['label' => 'Rectangular, 1.2 m long', 'shape' => 'rect', 'length' => 1.2, 'width' => 0.5, 'litres' => 200],
        'rect_2400' => ['label' => 'Rectangular, 2.4 m long', 'shape' => 'rect', 'length' => 2.4, 'width' => 0.5, 'litres' => 400],
        'rect_3000' => ['label' => 'Rectangular, 3 m long', 'shape' => 'rect', 'length' => 3.0, 'width' => 0.5, 'litres' => 500],
        'rect_3600' => ['label' => 'Rectangular, 3.6 m long', 'shape' => 'rect', 'length' => 3.6, 'width' => 0.5, 'litres' => 600],
        'other' => ['label' => 'Other size', 'shape' => 'round', 'length' => 1.5, 'width' => 1.5, 'litres' => null],
    ];

    /** The details each kind of asset records (see AssetType kinds). */
    public const DETAILS = [
        AssetType::FENCE => ['fence_type', 'wires'],
        AssetType::TANK => ['capacity'],
        AssetType::TROUGH => ['trough_size', 'capacity'],
        AssetType::VEGETATION => ['vegetation_type'],
    ];

    protected $fillable = [
        'property_id',
        'asset_type_id',
        'details',
        'created_by',
        'name',
        'description',
        'value',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'details' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
    }

    /** Assets of the property's type of these kinds, whatever it's called. */
    public function scopeOfKind($query, string ...$kinds)
    {
        return $query->whereHas('assetType', fn ($type) => $type->whereIn('kind', $kinds));
    }

    public function scopeFences($query)
    {
        return $query->ofKind(AssetType::FENCE);
    }

    /** What the app treats it as (its type's kind), or null. */
    public function kind(): ?string
    {
        return $this->assetType?->kind;
    }

    /** Sensors fitted to it, e.g. a tank's level sensor. */
    public function devices()
    {
        return $this->hasMany(Device::class)->orderBy('name');
    }

    /**
     * Its details as kept for its kind: only that kind's fields, empty ones
     * left out, numbers as numbers - or null for a kind with none.
     */
    public static function detailsFor(?string $kind, ?array $details): ?array
    {
        $kept = collect($details ?? [])
            ->only(self::DETAILS[$kind] ?? [])
            ->reject(fn ($value) => $value === null || $value === '')
            ->map(fn ($value, $key) => match ($key) {
                'wires' => (int) $value,
                'capacity' => (float) $value == (int) $value ? (int) $value : (float) $value,
                default => $value,
            })
            ->all();

        return $kept ?: null;
    }

    /**
     * Its point on the map as [lat, lng]: its location's point, or the
     * middle of its shape or line - where a gate, tank or pump stands.
     */
    public function point(): ?array
    {
        $location = $this->currentLocation;
        if (! $location) {
            return null;
        }
        if ($location->latitude !== null && $location->longitude !== null) {
            return [(float) $location->latitude, (float) $location->longitude];
        }
        $points = $location->shape ?? $location->line;
        if (! $points) {
            return null;
        }

        return [
            collect($points)->avg(fn ($p) => (float) $p[0]),
            collect($points)->avg(fn ($p) => (float) $p[1]),
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function maintenanceItems()
    {
        return $this->hasMany(MaintenanceItem::class)->orderByRaw('next_due_date is null')->orderBy('next_due_date');
    }

    public function notes()
    {
        return $this->hasMany(Note::class)->latest();
    }

    public function locations()
    {
        return $this->hasMany(AssetLocation::class)->latest();
    }

    /**
     * The most recently recorded location - the single "current" location
     * everywhere the app used to read asset.latitude/longitude/shape
     * directly. Mirrors Metric::latestMeasurement().
     */
    public function currentLocation()
    {
        return $this->hasOne(AssetLocation::class)->latestOfMany();
    }

    /**
     * Every job related to this asset: either directly (ad-hoc work not
     * tied to a maintenance schedule, via FarmJob.asset_id) or via one of
     * its maintenance items (FarmJob.maintenance_item_id). Not a true
     * Eloquent relation - no single FK/join path covers both - just a
     * query builder, but it supports the same read-only chaining
     * (with/count/pluck/latest) callers already use on a relation.
     */
    public function jobs()
    {
        return FarmJob::where('asset_id', $this->id)
            ->orWhereHas('maintenanceItem', fn ($query) => $query->where('asset_id', $this->id));
    }

    /**
     * Work sessions that *used* this asset (equipment used, via the
     * asset_work_session pivot) - e.g. the tractor on a mowing session.
     */
    public function workSessions()
    {
        return $this->belongsToMany(WorkSession::class)->withTimestamps();
    }

    /**
     * Total hours this asset has been used - finalised/approved sessions
     * only, matching the "used" figure on the asset's page. Drives
     * usage-based maintenance (MaintenanceItem::isDue()).
     */
    public function usageHours(): float
    {
        return round($this->workSessions()
            ->whereIn('status', [WorkSession::FINALISED, WorkSession::APPROVED])
            ->with('user')
            ->get()
            ->sum('duration_in_hours'), 2);
    }

    /**
     * Work sessions spent *maintaining* this asset: every session on one of
     * its jobs (see jobs()). Distinct from workSessions() (usage) - they
     * measure different things, so a session counted in both (driving the
     * tractor to the shed for its own service) isn't a double count.
     */
    public function maintenanceSessionsQuery()
    {
        return WorkSession::whereIn('farm_job_id', $this->jobs()->pluck('farm_jobs.id'));
    }
}
