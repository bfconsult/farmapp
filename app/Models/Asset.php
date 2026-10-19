<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'property_id',
        'asset_type_id',
        'created_by',
        'name',
        'description',
        'value',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
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
