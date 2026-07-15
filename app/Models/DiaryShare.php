<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DiaryShare extends Model
{
    protected $fillable = ['property_id', 'created_by', 'date_from', 'date_to'];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function (DiaryShare $share) {
            $share->token = $share->token ?? Str::random(40);
        });
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
