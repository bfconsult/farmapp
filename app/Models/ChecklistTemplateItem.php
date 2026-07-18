<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTemplateItem extends Model
{
    protected $fillable = [
        'checklist_template_id',
        'name',
        'evidence',
        'photo_required',
    ];

    protected $casts = [
        'photo_required' => 'boolean',
    ];

    public function checklistTemplate()
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }
}
