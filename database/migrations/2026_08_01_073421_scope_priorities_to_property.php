<?php

use App\Models\FarmJob;
use App\Models\Priority;
use App\Models\Property;
use App\Models\RecurringJob;
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
        // Guarded/idempotent throughout - see scope_job_statuses_to_property.php
        // for why (a deploy getting killed mid-migration has happened more
        // than once on this project's Vapor setup). Safe to re-run.
        if (!Schema::hasColumn('priorities', 'property_id')) {
            Schema::table('priorities', function (Blueprint $table) {
                $table->foreignId('property_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        // priorities was a single global list shared by every property - give
        // each existing property its own copy of the current global rows
        // (verbatim, so nothing visibly changes today), then repoint every
        // FarmJob/RecurringJob at its own property's copy instead of the old
        // shared row.
        $globalPriorities = Priority::whereNull('property_id')->get();
        $oldIdToName = $globalPriorities->pluck('name', 'id');

        Property::all()->each(function (Property $property) use ($globalPriorities, $oldIdToName) {
            $newIdByName = Priority::where('property_id', $property->id)->pluck('id', 'name')->all();

            if (empty($newIdByName)) {
                foreach ($globalPriorities as $old) {
                    $copy = Priority::create([
                        'property_id' => $property->id,
                        'name' => $old->name,
                        'color' => $old->color,
                        'order' => $old->order,
                    ]);
                    $newIdByName[$old->name] = $copy->id;
                }
            }

            $remap = function ($job) use ($newIdByName, $oldIdToName) {
                $name = $oldIdToName[$job->priority_id] ?? null;
                if ($name && isset($newIdByName[$name]) && $job->priority_id !== $newIdByName[$name]) {
                    $job->update(['priority_id' => $newIdByName[$name]]);
                }
            };

            FarmJob::where('property_id', $property->id)->whereNotNull('priority_id')->get()->each($remap);
            RecurringJob::where('property_id', $property->id)->whereNotNull('priority_id')->get()->each($remap);
        });

        Priority::whereNull('property_id')->delete();

        if (Schema::hasColumn('priorities', 'property_id')) {
            Schema::table('priorities', function (Blueprint $table) {
                $table->foreignId('property_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not a meaningful reverse - see scope_job_statuses_to_property.php.
        Schema::table('priorities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
