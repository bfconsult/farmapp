<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'farm_job_id', 'supplier_id', 'created_by',
        'name', 'description', 'amount', 'gst_inclusive', 'reimburse',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gst_inclusive' => 'boolean',
        'reimburse' => 'boolean',
    ];

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }
}
