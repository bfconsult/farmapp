<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'property_id',
        'name',
        'description',
        'street_address',
        'phone',
        'email',
        'billing_company_name',
        'billing_abn',
        'billing_address',
        'billing_phone',
        'billing_contact_name',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Team members who bill through this supplier rather than as
     * individuals - see Role::supplier().
     */
    public function roles()
    {
        return $this->hasMany(Role::class);
    }
}
