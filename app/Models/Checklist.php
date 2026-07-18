<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    const INCOMPLETE = 'incomplete';
    const COMPLETE = 'complete';

    const STATUSES = [self::INCOMPLETE, self::COMPLETE];

    protected $fillable = [
        'farm_job_id',
        'checklist_template_id',
        'created_by',
        'name',
        'type',
        'status',
    ];

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class, 'farm_job_id');
    }

    public function checklistTemplate()
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('id');
    }

    /**
     * Attach a template to a job - snapshotting the template's name/type and
     * each item's name/evidence-prompt/photo_required onto a new instance, so
     * the checklist stays meaningful even if the template later changes.
     */
    public static function attach(ChecklistTemplate $template, FarmJob $job, User $user): self
    {
        $checklist = self::create([
            'farm_job_id' => $job->id,
            'checklist_template_id' => $template->id,
            'created_by' => $user->id,
            'name' => $template->name,
            'type' => $template->type,
            'status' => self::INCOMPLETE,
        ]);

        foreach ($template->items as $templateItem) {
            $checklist->items()->create([
                'name' => $templateItem->name,
                'evidence_prompt' => $templateItem->evidence,
                'photo_required' => $templateItem->photo_required,
            ]);
        }

        return $checklist;
    }

    /**
     * Recompute this checklist's status from its items - complete once every
     * item is checked, incomplete otherwise. Called after any item update.
     */
    public function refreshStatus(): void
    {
        $status = $this->items()->where('is_checked', false)->doesntExist()
            ? self::COMPLETE
            : self::INCOMPLETE;

        $this->update(['status' => $status]);
    }
}
