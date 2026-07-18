<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTemplate extends Model
{
    const BEFORE_START = 'before_start';
    const AT_COMPLETION = 'at_completion';

    const TYPES = [self::BEFORE_START, self::AT_COMPLETION];

    protected $fillable = [
        'property_id',
        'created_by',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(ChecklistTemplateItem::class)->orderBy('id');
    }

    public function checklists()
    {
        return $this->hasMany(Checklist::class);
    }
}
