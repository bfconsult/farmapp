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
        'repeat_usage_hours',
        'usage_baseline_hours',
        'auto_generate',
    ];

    protected $casts = [
        'start_date' => 'date',
        'next_due_date' => 'date',
        'repeat_usage_hours' => 'float',
        'usage_baseline_hours' => 'float',
        'auto_generate' => 'boolean',
    ];

    protected $appends = ['next_due_usage_hours'];

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
     * The asset's usage hours at which this item is next due, or null if it
     * has no usage trigger.
     */
    public function getNextDueUsageHoursAttribute(): ?float
    {
        if ($this->repeat_usage_hours === null) {
            return null;
        }

        return round(($this->usage_baseline_hours ?? 0) + $this->repeat_usage_hours, 2);
    }

    /**
     * Due by whichever trigger comes first: the date has arrived, or the
     * asset has been used for the interval since the baseline. $usageHours
     * is the asset's current usage (Asset::usageHours()) - passed in so a
     * list of items on one asset only computes it once.
     */
    public function isDue(float $usageHours): bool
    {
        $dueByDate = $this->next_due_date && $this->next_due_date->lte(today());
        $dueByUsage = $this->next_due_usage_hours !== null && $usageHours >= $this->next_due_usage_hours;

        return $dueByDate || $dueByUsage;
    }

    /**
     * The job created from this item that hasn't been finished yet, if any.
     * While one exists the item isn't turned into another - the next
     * interval only starts once it's completed (see resetAfterCompletion()).
     * Checks the status as well as completed_at, since jobs finished before
     * completed_at existed were never backfilled.
     */
    public function openJob(): ?FarmJob
    {
        return $this->jobs()
            ->with('jobStatus:id,name,color')
            ->whereNull('completed_at')
            ->whereDoesntHave('jobStatus', fn ($query) => $query->where('is_finished_default', true))
            ->latest('id')
            ->first();
    }

    /**
     * Start the next interval from when this item's job was completed: the
     * date trigger counts on from the completion date, the usage trigger
     * from the asset's usage hours at that point.
     */
    public function resetAfterCompletion(\Carbon\CarbonInterface $completedAt): void
    {
        $this->update([
            'next_due_date' => $this->repeat_period_days
                ? $completedAt->copy()->startOfDay()->addDays($this->repeat_period_days)
                : $this->next_due_date,
            'usage_baseline_hours' => $this->repeat_usage_hours !== null
                ? $this->asset->usageHours()
                : $this->usage_baseline_hours,
        ]);
    }

    /**
     * Create a job from this maintenance item - mirrors
     * RecurringJob::createInstance(). Doesn't move the due point on - that
     * happens when the job is completed (see resetAfterCompletion()).
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
            'job_status_id' => JobStatus::where('property_id', $this->asset->property_id)->where('is_default', true)->value('id'),
            'user_id' => $user->id ?? $this->created_by,
            'property_id' => $this->asset->property_id,
            'maintenance_item_id' => $this->id,
        ]);

        $teamUserIds = Role::where('property_id', $job->property_id)->pluck('user_id');
        $job->assignees()->attach($teamUserIds);

        return $job;
    }
}
