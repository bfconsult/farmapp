<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = ['property_id', 'invited_by', 'email', 'role', 'message', 'token', 'accepted_at'];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($invitation) {
            $invitation->token = $invitation->token ?? Str::random(40);
        });
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
