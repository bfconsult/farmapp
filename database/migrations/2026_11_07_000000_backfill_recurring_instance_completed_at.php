<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recurring instances auto-closed by jobs:generate-recurring used to get
     * completed_at = the day the command ran (after the period ended), so
     * e.g. September's instance showed as completed in October's Diary.
     * Pull those back to the end of their own period. Only touches instances
     * sitting on the property's recurring-closed status, so a job someone
     * moved on to some other status by hand is left alone.
     */
    public function up(): void
    {
        DB::table('farm_jobs')
            ->join('job_statuses', 'job_statuses.id', '=', 'farm_jobs.job_status_id')
            ->where('job_statuses.is_recurring_closed_default', true)
            ->whereNotNull('farm_jobs.recurring_job_id')
            ->whereNotNull('farm_jobs.period_end')
            ->whereNotNull('farm_jobs.completed_at')
            ->select('farm_jobs.id', 'farm_jobs.period_end', 'farm_jobs.completed_at')
            ->get()
            ->each(function ($job) {
                $periodEnd = Carbon::parse($job->period_end)->endOfDay();

                if (Carbon::parse($job->completed_at)->gt($periodEnd)) {
                    DB::table('farm_jobs')->where('id', $job->id)->update(['completed_at' => $periodEnd]);
                }
            });
    }

    public function down(): void
    {
        // Not reversible - the original run-day timestamps aren't kept.
    }
};
