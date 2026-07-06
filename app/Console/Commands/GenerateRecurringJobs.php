<?php

namespace App\Console\Commands;

use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\RecurringJob;
use App\Models\Role;
use Carbon\Carbon;
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
                if (now()->toDateString() >= $template->starts_on->toDateString()) {
                    $this->createInstance($template, $template->starts_on);
                }

                return;
            }

            if (now()->toDateString() > $latest->period_end->toDateString()) {
                if ($closedStatusId) {
                    $latest->update(['job_status_id' => $closedStatusId]);
                }

                $this->createInstance($template, $latest->period_end->copy()->addDay());
            }
        });
    }

    private function createInstance(RecurringJob $template, Carbon $periodStart): void
    {
        $periodEnd = $template->periodEndFor($periodStart);

        $job = FarmJob::create([
            'name' => $template->name,
            'description' => $template->description,
            'estimated_hours' => $template->estimated_hours,
            'budget' => $template->budget,
            'hourly_rate' => $template->hourly_rate,
            'priority_id' => $template->priority_id,
            'job_type_id' => $template->job_type_id,
            'job_status_id' => JobStatus::where('is_default', true)->value('id'),
            'user_id' => $template->created_by,
            'property_id' => $template->property_id,
            'recurring_job_id' => $template->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $teamUserIds = Role::where('property_id', $job->property_id)->pluck('user_id');
        $job->assignees()->attach($teamUserIds);

        $this->info("Created \"{$job->name}\" for {$periodStart->toDateString()} to {$periodEnd->toDateString()} (recurring job #{$template->id}).");
    }
}
