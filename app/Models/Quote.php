<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    const INVITED = 'invited';
    const ACCEPTED = 'accepted';
    const DECLINED = 'declined';

    const STATUSES = [self::INVITED, self::ACCEPTED, self::DECLINED];

    protected $fillable = [
        'farm_job_id',
        'supplier_id',
        'created_by',
        'requires_quote',
        'status',
        'amount',
        'message',
        'invited_at',
        'decided_at',
    ];

    protected $casts = [
        'requires_quote' => 'boolean',
        'amount' => 'decimal:2',
        'invited_at' => 'datetime',
        'decided_at' => 'datetime',
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
}
