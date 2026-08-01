<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    protected $fillable = ['property_id', 'name', 'color', 'order', 'can_book_time', 'is_in_progress_default', 'is_recurring_closed_default', 'is_finished_default'];

    protected $casts = [
        'can_book_time' => 'boolean',
        'is_default' => 'boolean',
        'is_protected' => 'boolean',
        'is_in_progress_default' => 'boolean',
        'is_recurring_closed_default' => 'boolean',
        'is_finished_default' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }

    /**
     * The default status set for a brand-new property - not called by the
     * scope_job_statuses_to_property migration, which instead copies each
     * existing property's actual live global rows verbatim (preserving
     * whatever names/colors/flags an existing account already has, even if
     * they've since renamed "In Progress" to something else). This is only
     * for properties created after that migration, which have no existing
     * data to preserve - so it uses clean canonical names instead.
     */
    public static function seedDefaultsForProperty(int $propertyId): void
    {
        static::create([
            'property_id' => $propertyId,
            'name' => 'Backlog',
            'order' => 0,
            'can_book_time' => true,
        ])->forceFill(['is_default' => true, 'is_protected' => true])->save();

        static::create([
            'property_id' => $propertyId,
            'name' => 'In Progress',
            'color' => 'blue',
            'order' => 1,
            'can_book_time' => true,
            'is_in_progress_default' => true,
        ]);

        static::create([
            'property_id' => $propertyId,
            'name' => 'Completed',
            'color' => 'green',
            'order' => 2,
            'can_book_time' => false,
            'is_recurring_closed_default' => true,
            'is_finished_default' => true,
        ]);
    }
}
