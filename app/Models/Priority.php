<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Priority extends Model
{
    protected $fillable = ['name', 'order'];

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }
}
