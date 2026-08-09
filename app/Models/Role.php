<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['user_id', 'property_id', 'supplier_id', 'type'];

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

    /**
     * The supplier this person bills through, if they're effectively a
     * contractor rather than billed as an individual - carries company
     * name/ABN/invoice details from the Supplier record rather than
     * duplicating those fields here. Optional; most roles have none.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}