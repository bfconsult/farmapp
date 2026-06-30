<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = ['name', 'address'];

    public function shape()
    {
        return $this->hasOne(Shape::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'roles')->withPivot('type')->withTimestamps();
    }

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }
    
    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
}
