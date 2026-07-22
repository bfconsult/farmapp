<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceItem extends Model
{
    protected $fillable = [
        'asset_id',
        'created_by',
        'name',
        'description',
        'start_date',
        'repeat_period_days',
        'next_due_date',
        'auto_generate',
    ];

    protected $casts = [
        'start_date' => 'date',
        'next_due_date' => 'date',
        'auto_generate' => 'boolean',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jobs()
    {
        return $this->hasMany(FarmJob::class);
    }

    /**
     * Create a job from this maintenance item - mirrors
     * RecurringJob::createInstance(). Advances next_due_date by
     * repeat_period_days from its current value (not from today), so a late
     * conversion doesn't skip ahead further than the repeat period itself.
     * $user is the person clicking "Turn into Job" - omitted when called from
     * the auto-generate scheduler, which falls back to created_by instead
     * (mirroring how RecurringJob::createInstance() has no acting user either).
     */
    public function convertToJob(?User $user = null): FarmJob
    {
        $job = FarmJob::create([
            'name' => "{$this->asset->name} - {$this->name}",
            'description' => $this->description,
            'latitude' => $this->asset->currentLocation?->latitude,
            'longitude' => $this->asset->currentLocation?->longitude,
            'job_status_id' => JobStatus::where('is_default', true)->value('id'),
            'user_id' => $user->id ?? $this->created_by,
            'property_id' => $this->asset->property_id,
            'maintenance_item_id' => $this->id,
        ]);

        $teamUserIds = Role::where('property_id', $job->property_id)->pluck('user_id');
        $job->assignees()->attach($teamUserIds);

        $this->update(['next_due_date' => $this->next_due_date->copy()->addDays($this->repeat_period_days)]);

        return $job;
    }
}
