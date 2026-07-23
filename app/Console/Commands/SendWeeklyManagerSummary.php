<?php

namespace App\Console\Commands;

use App\Mail\WeeklyManagerSummary;
use App\Models\JobStatus;
use App\Models\MetricMeasurement;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyManagerSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send-weekly-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email each manager/admin a weekly summary of incomplete scheduled jobs and incomplete metrics, grouped by property';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $activeStatusIds = JobStatus::where('can_book_time', true)->pluck('id');

        $managers = User::whereHas(
            'roles',
            fn ($query) => $query->whereIn('type', [Role::ADMIN, Role::MANAGER])
        )->get();

        foreach ($managers as $manager) {
            $propertyIds = $manager->roles()
                ->whereIn('type', [Role::ADMIN, Role::MANAGER])
                ->pluck('property_id');

            $properties = Property::whereIn('id', $propertyIds)->get();

            $summary = $properties
                ->map(fn (Property $property) => [
                    'property' => $property,
                    'jobs' => $this->incompleteJobsFor($property, $activeStatusIds),
                    'measurements' => $this->incompleteMeasurementsFor($property),
                ])
                ->filter(fn ($entry) => $entry['jobs']->isNotEmpty() || $entry['measurements']->isNotEmpty())
                ->values();

            if ($summary->isEmpty()) {
                continue;
            }

            Mail::to($manager->email)->send(new WeeklyManagerSummary($summary));

            $this->info("Sent weekly summary to {$manager->email} covering {$summary->count()} propert" . ($summary->count() === 1 ? 'y' : 'ies') . '.');
        }
    }

    private function incompleteJobsFor(Property $property, $activeStatusIds)
    {
        return $property->farmJobs()
            ->whereIn('job_status_id', $activeStatusIds)
            ->whereNotNull('scheduled_date')
            ->where('scheduled_date', '<=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->get();
    }

    private function incompleteMeasurementsFor(Property $property)
    {
        return MetricMeasurement::whereIn('metric_id', $property->metrics()->pluck('id'))
            ->where('status', MetricMeasurement::INCOMPLETE)
            ->with('metric')
            ->orderBy('period_end')
            ->get();
    }
}
