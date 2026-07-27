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
        return $this->hasMany(MaintenanceItem::class)->orderBy('next_due_date');
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
     * Work sessions logged directly against this asset - ad-hoc work with
     * no job at all. A session reached via farmJob->asset (job linked to
     * this asset) already counts toward its time; see workSessionsQuery()
     * for the combined total that also covers this direct link.
     */
    public function workSessions()
    {
        return $this->hasMany(WorkSession::class);
    }

    /**
     * Every work session that should count toward this asset's time: either
     * directly logged against it (ad-hoc, no job) or via a job linked to it
     * (see jobs()). A single query (not two summed queries) so a session
     * that somehow has both set isn't double-counted.
     */
    public function workSessionsQuery()
    {
        $jobIds = $this->jobs()->pluck('farm_jobs.id');

        // Grouped into a single where() so callers can safely chain further
        // ->where(...)/->whereIn(...) filters (e.g. by status) as an AND
        // across the whole OR pair, instead of AND binding to only the
        // last branch.
        return WorkSession::where(function ($query) use ($jobIds) {
            $query->where('asset_id', $this->id)->orWhereIn('farm_job_id', $jobIds);
        });
    }
}
