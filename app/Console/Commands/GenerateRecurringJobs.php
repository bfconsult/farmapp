<?php

namespace App\Console\Commands;

use App\Models\JobStatus;
use App\Models\RecurringJob;
use Illuminate\Console\Command;

class GenerateRecurringJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:generate-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create each active recurring job's first/next instance, closing out the previous one once its period has ended";

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $closedStatusId = JobStatus::where('is_recurring_closed_default', true)->value('id');

        RecurringJob::where('is_active', true)->get()->each(function (RecurringJob $template) use ($closedStatusId) {
            $latest = $template->instances()->latest('period_start')->first();

            if (!$latest) {
                // Normally the first instance is created immediately alongside
                // the template (see FarmJobController::store()) — this only
                // fires as a fallback if a template ever exists without one.
                if (now()->toDateString() >= $template->starts_on->toDateString()) {
                    $job = $template->createInstance($template->starts_on);
                    $this->info("Created \"{$job->name}\" for {$job->period_start->toDateString()} to {$job->period_end->toDateString()} (recurring job #{$template->id}).");
                }

                return;
            }

            if (now()->toDateString() > $latest->period_end->toDateString()) {
                if ($closedStatusId) {
                    $latest->update(['job_status_id' => $closedStatusId]);
                }

                $job = $template->createInstance($latest->period_end->copy()->addDay());
                $this->info("Created \"{$job->name}\" for {$job->period_start->toDateString()} to {$job->period_end->toDateString()} (recurring job #{$template->id}).");
            }
        });
    }
}
