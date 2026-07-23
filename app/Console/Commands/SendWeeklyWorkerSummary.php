<?php

namespace App\Console\Commands;

use App\Mail\WeeklyWorkerSummary;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyWorkerSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send-weekly-worker-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Email each worker a summary of the hours they've booked (draft and finalised) over the last week, grouped by property then job";

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $since = now()->subDays(7);

        $users = User::whereHas(
            'workSessions',
            fn ($query) => $query
                ->whereIn('status', [WorkSession::DRAFT, WorkSession::FINALISED])
                ->where('started_at', '>=', $since)
        )->get();

        foreach ($users as $user) {
            $sessions = $user->workSessions()
                ->whereIn('status', [WorkSession::DRAFT, WorkSession::FINALISED])
                ->where('started_at', '>=', $since)
                ->with(['property', 'farmJob'])
                ->orderBy('started_at')
                ->get();

            $summary = $sessions
                ->groupBy('property_id')
                ->map(fn ($propertySessions) => [
                    'property' => $propertySessions->first()->property,
                    'total_hours' => $propertySessions->sum(fn ($s) => $s->duration_in_hours ?? 0),
                    'jobs' => $propertySessions
                        ->groupBy(fn ($s) => $s->farm_job_id ?? 0)
                        ->map(fn ($jobSessions) => [
                            'label' => $jobSessions->first()->farmJob?->name ?? 'Ad-hoc work',
                            'sessions' => $jobSessions->values(),
                            'total_hours' => $jobSessions->sum(fn ($s) => $s->duration_in_hours ?? 0),
                        ])
                        ->values(),
                ])
                ->sortBy(fn ($entry) => $entry['property']->name)
                ->values();

            Mail::to($user->email)->send(new WeeklyWorkerSummary($summary));

            $this->info("Sent weekly hours summary to {$user->email} covering {$sessions->count()} session(s).");
        }
    }
}
