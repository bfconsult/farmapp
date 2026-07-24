<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'description', 'street_address', 'phone', 'email'];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
