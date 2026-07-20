<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checklist_template_recurring_job', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Backfill: infer each existing recurring job's checklist templates
        // from whichever ones ended up attached to its first-ever instance -
        // the only one that ever got them, due to the bug this table fixes
        // (createInstance() itself had no checklist logic; only the
        // controller's one-off call for the very first instance did) - so
        // already-repeating jobs don't need re-configuring to keep
        // generating checklists on their future instances.
        \App\Models\RecurringJob::all()->each(function (\App\Models\RecurringJob $recurringJob) {
            $firstInstance = $recurringJob->instances()->oldest('id')->first();
            if (!$firstInstance) {
                return;
            }

            $templateIds = $firstInstance->checklists()->pluck('checklist_template_id')->unique();
            if ($templateIds->isEmpty()) {
                return;
            }

            $recurringJob->checklistTemplates()->syncWithoutDetaching($templateIds);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_template_recurring_job');
    }
};
