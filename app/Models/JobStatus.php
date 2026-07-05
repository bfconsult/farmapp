<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    protected $fillable = ['name', 'order', 'can_book_time', 'is_in_progress_default'];

    protected $casts = [
        'can_book_time' => 'boolean',
        'is_default' => 'boolean',
        'is_protected' => 'boolean',
        'is_in_progress_default' => 'boolean',
    ];

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }
}
