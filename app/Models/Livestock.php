<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Livestock extends Model
{
    // "livestock" is already plural/uncountable - Eloquent's naming
    // convention would otherwise guess "livestocks" for the table name.
    protected $table = 'livestock';

    protected $fillable = [
        'property_id',
        'livestock_type_id',
        'mob_id',
        'created_by',
        'tag_number',
        'name',
        'sex',
        'date_of_birth',
        'purchase_date',
        'birth_weight',
        'purchase_weight',
        'purchase_price_type',
        'purchase_price',
        'sale_weight',
        'sale_price_type',
        'sale_price',
        'status',
        'sire_id',
        'dam_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'purchase_date' => 'date',
        'birth_weight' => 'decimal:2',
        'purchase_weight' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'sale_weight' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function livestockType()
    {
        return $this->belongsTo(LivestockType::class);
    }

    public function mob()
    {
        return $this->belongsTo(Mob::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sire()
    {
        return $this->belongsTo(self::class, 'sire_id');
    }

    public function dam()
    {
        return $this->belongsTo(self::class, 'dam_id');
    }

    /**
     * Children where this animal is the sire. Eloquent can't OR across two
     * FK columns in a single relation, so parentage is split into this and
     * offspringAsDam() - see offspring() for the combined read-only view.
     */
    public function offspringAsSire()
    {
        return $this->hasMany(self::class, 'sire_id');
    }

    public function offspringAsDam()
    {
        return $this->hasMany(self::class, 'dam_id');
    }

    /**
     * Every child of this animal, as either sire or dam. Not a true
     * Eloquent relation - no single FK covers both - just a query builder,
     * same pattern as Asset::jobs().
     */
    public function offspring()
    {
        return self::where('sire_id', $this->id)->orWhere('dam_id', $this->id);
    }

    public function notes()
    {
        return $this->hasMany(Note::class)->latest();
    }
}
