<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'share_token',
        'invoice_requested_at',
    ];

    protected $casts = [
        'requires_quote' => 'boolean',
        'amount' => 'decimal:2',
        'invited_at' => 'datetime',
        'decided_at' => 'datetime',
        'invoice_requested_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (Quote $quote) {
            $quote->share_token = $quote->share_token ?? Str::random(40);
        });
    }

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
