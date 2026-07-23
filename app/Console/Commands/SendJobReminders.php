<?php

namespace App\Console\Commands;

use App\Mail\JobOverdueReminder;
use App\Models\FarmJob;
use App\Models\JobReminder;
use App\Models\JobStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendJobReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for jobs needing a reminder email and send them to their assigned users';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->sendOverdueNoHoursReminders();
    }

    /**
     * A scheduled job whose date has passed with no time logged against it
     * yet, reminding every assigned user. Sent once per job - job_reminders
     * records that it's already gone out so this never repeats daily.
     */
    private function sendOverdueNoHoursReminders(): void
    {
        $activeStatusIds = JobStatus::where('can_book_time', true)->pluck('id');

        FarmJob::query()
            ->whereIn('job_status_id', $activeStatusIds)
            ->whereNotNull('scheduled_date')
            ->where('scheduled_date', '<', now()->toDateString())
            ->whereDoesntHave('workSessions')
            ->whereDoesntHave('reminders', fn ($query) => $query->where('type', JobReminder::TYPE_OVERDUE_NO_HOURS))
            ->with(['assignees', 'property'])
            ->get()
            ->each(function (FarmJob $job) {
                foreach ($job->assignees as $user) {
                    Mail::to($user->email)->send(new JobOverdueReminder($job));
                }

                $job->reminders()->create([
                    'type' => JobReminder::TYPE_OVERDUE_NO_HOURS,
                    'sent_at' => now(),
                ]);

                $this->info("Sent overdue reminder for \"{$job->name}\" (job #{$job->id}) to {$job->assignees->count()} assignee(s).");
            });
    }
}
