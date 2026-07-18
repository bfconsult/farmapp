<?php

namespace App\Console\Commands;

use App\Models\MaintenanceItem;
use Illuminate\Console\Command;

class GenerateMaintenanceJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintenance:generate-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Turn each auto-generate maintenance item into a job once its next due date arrives';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        MaintenanceItem::where('auto_generate', true)
            ->where('next_due_date', '<=', now()->toDateString())
            ->get()
            ->each(function (MaintenanceItem $item) {
                $job = $item->convertToJob();
                $this->info("Created \"{$job->name}\" (maintenance item #{$item->id}), next due {$item->next_due_date->toDateString()}.");
            });
    }
}
