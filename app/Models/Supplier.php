<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['property_id', 'name', 'description', 'street_address', 'phone', 'email'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
