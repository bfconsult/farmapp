<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    protected $fillable = [
        'checklist_id',
        'name',
        'evidence_prompt',
        'photo_required',
        'is_checked',
        'evidence_value',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'photo_required' => 'boolean',
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }
}
