<?php

use App\Models\FarmJob;
use App\Models\JobType;
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
        if (!Schema::hasColumn('job_types', 'property_id')) {
            Schema::table('job_types', function (Blueprint $table) {
                $table->foreignId('property_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        // job_types was a single global list shared by every property - give
        // each existing property its own copy of the current global rows
        // (verbatim), then repoint every FarmJob/RecurringJob at its own
        // property's copy instead of the old shared row.
        $globalJobTypes = JobType::whereNull('property_id')->get();
        $oldIdToName = $globalJobTypes->pluck('name', 'id');

        Property::all()->each(function (Property $property) use ($globalJobTypes, $oldIdToName) {
            $newIdByName = JobType::where('property_id', $property->id)->pluck('id', 'name')->all();

            if (empty($newIdByName)) {
                foreach ($globalJobTypes as $old) {
                    $copy = JobType::create([
                        'property_id' => $property->id,
                        'name' => $old->name,
                        'color' => $old->color,
                    ]);
                    $newIdByName[$old->name] = $copy->id;
                }
            }

            $remap = function ($job) use ($newIdByName, $oldIdToName) {
                $name = $oldIdToName[$job->job_type_id] ?? null;
                if ($name && isset($newIdByName[$name]) && $job->job_type_id !== $newIdByName[$name]) {
                    $job->update(['job_type_id' => $newIdByName[$name]]);
                }
            };

            FarmJob::where('property_id', $property->id)->whereNotNull('job_type_id')->get()->each($remap);
            RecurringJob::where('property_id', $property->id)->whereNotNull('job_type_id')->get()->each($remap);
        });

        JobType::whereNull('property_id')->delete();

        if (Schema::hasColumn('job_types', 'property_id')) {
            Schema::table('job_types', function (Blueprint $table) {
                $table->foreignId('property_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
