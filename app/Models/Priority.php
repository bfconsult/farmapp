<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Priority extends Model
{
    protected $fillable = ['name', 'color', 'order'];

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }
}
