<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('farm_jobs')
            ->whereNotNull('zone_id')
            ->select('id', 'zone_id')
            ->orderBy('id')
            ->chunkById(500, function ($jobs) {
                $rows = $jobs->map(fn ($job) => ['farm_job_id' => $job->id, 'zone_id' => $job->zone_id])->all();
                DB::table('farm_job_zone')->insert($rows);
            });

        DB::table('recurring_jobs')
            ->whereNotNull('zone_id')
            ->select('id', 'zone_id')
            ->orderBy('id')
            ->chunkById(500, function ($jobs) {
                $rows = $jobs->map(fn ($job) => ['recurring_job_id' => $job->id, 'zone_id' => $job->zone_id])->all();
                DB::table('recurring_job_zone')->insert($rows);
            });

        Schema::table('farm_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
        });

        Schema::table('recurring_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
        });
    }

    /**
     * Best-effort only: a job now attached to several zones can only give
     * one of them back to a single-column zone_id, so this picks the
     * lowest zone_id per job. Fine for a dev rollback; not meant to survive
     * a real multi-zone dataset round-trip.
     */
    public function down(): void
    {
        Schema::table('farm_jobs', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('property_id')->constrained('zones')->onDelete('set null');
        });

        Schema::table('recurring_jobs', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('property_id')->constrained('zones')->onDelete('set null');
        });

        DB::table('farm_job_zone')
            ->select('farm_job_id', DB::raw('MIN(zone_id) as zone_id'))
            ->groupBy('farm_job_id')
            ->orderBy('farm_job_id')
            ->get()
            ->each(fn ($row) => DB::table('farm_jobs')->where('id', $row->farm_job_id)->update(['zone_id' => $row->zone_id]));

        DB::table('recurring_job_zone')
            ->select('recurring_job_id', DB::raw('MIN(zone_id) as zone_id'))
            ->groupBy('recurring_job_id')
            ->orderBy('recurring_job_id')
            ->get()
            ->each(fn ($row) => DB::table('recurring_jobs')->where('id', $row->recurring_job_id)->update(['zone_id' => $row->zone_id]));
    }
};
