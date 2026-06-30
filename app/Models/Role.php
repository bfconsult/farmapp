<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['user_id', 'property_id', 'type'];

    const TYPES = ['admin', 'manager', 'worker', 'approver'];

    const ADMIN = 'admin';
    const MANAGER = 'manager';
    const WORKER = 'worker';
    const APPROVER = 'approver';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}