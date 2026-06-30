<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    protected $fillable = ['name', 'order', 'can_book_time'];

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }
}
