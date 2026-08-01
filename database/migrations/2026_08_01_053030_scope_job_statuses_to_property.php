<?php

use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\Property;
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
        // Guarded/idempotent throughout - this touches real data, not just
        // schema, and a deploy getting killed mid-migration has happened
        // more than once on this project's Vapor setup. Safe to re-run.
        if (!Schema::hasColumn('job_statuses', 'property_id')) {
            Schema::table('job_statuses', function (Blueprint $table) {
                // Nullable for now - backfilled below, then tightened to
                // NOT NULL at the end of this same migration.
                $table->foreignId('property_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        // job_statuses was a single global list shared by every property -
        // give each existing property its own copy of the current global
        // rows (name/color/order/flags all preserved verbatim, so nothing
        // visibly changes for an existing property today), then repoint
        // every FarmJob at its own property's copy instead of the old
        // shared row.
        $globalStatuses = JobStatus::whereNull('property_id')->get();
        $oldIdToName = $globalStatuses->pluck('name', 'id');

        Property::all()->each(function (Property $property) use ($globalStatuses, $oldIdToName) {
            // Already backfilled (e.g. a previous run got this far before
            // failing/being interrupted) - skip straight to the FarmJob
            // remap in case that part didn't finish either.
            $newIdByName = JobStatus::where('property_id', $property->id)->pluck('id', 'name')->all();

            if (empty($newIdByName)) {
                foreach ($globalStatuses as $old) {
                    $copy = JobStatus::create([
                        'property_id' => $property->id,
                        'name' => $old->name,
                        'color' => $old->color,
                        'order' => $old->order,
                        'can_book_time' => $old->can_book_time,
                        'is_in_progress_default' => $old->is_in_progress_default,
                        'is_recurring_closed_default' => $old->is_recurring_closed_default,
                        'is_finished_default' => $old->is_finished_default,
                    ]);
                    // is_default/is_protected aren't mass-assignable
                    // (deliberately - only ever set directly, mirroring the
                    // original migration that introduced them).
                    $copy->forceFill([
                        'is_default' => $old->is_default,
                        'is_protected' => $old->is_protected,
                    ])->save();

                    $newIdByName[$old->name] = $copy->id;
                }
            }

            FarmJob::where('property_id', $property->id)
                ->whereNotNull('job_status_id')
                ->get()
                ->each(function (FarmJob $job) use ($newIdByName, $oldIdToName) {
                    $name = $oldIdToName[$job->job_status_id] ?? null;
                    if ($name && isset($newIdByName[$name]) && $job->job_status_id !== $newIdByName[$name]) {
                        $job->update(['job_status_id' => $newIdByName[$name]]);
                    }
                });
        });

        // Every FarmJob now points at a property-scoped copy - the original
        // global rows are unreferenced and can go.
        JobStatus::whereNull('property_id')->delete();

        if (Schema::hasColumn('job_statuses', 'property_id')) {
            Schema::table('job_statuses', function (Blueprint $table) {
                $table->foreignId('property_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not a meaningful reverse - collapsing N independent per-property
        // status lists back into one shared global list would require
        // arbitrarily picking a "winner" per status name and remapping
        // every FarmJob again. Just drops the column; restore from a
        // database backup if this migration needs to be undone.
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
