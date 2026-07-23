<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobType extends Model
{
    protected $fillable = ['name', 'color'];

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }
}
