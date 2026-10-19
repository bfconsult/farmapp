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
    protected $description = 'Turn each auto-generate maintenance item into a job once it is due by date or hours of use, unless it already has an open job';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Usage is per asset, so it's computed once per asset rather than
        // once per item.
        $usageByAsset = [];

        MaintenanceItem::where('auto_generate', true)
            ->with('asset')
            ->get()
            ->filter(function (MaintenanceItem $item) use (&$usageByAsset) {
                $usage = $usageByAsset[$item->asset_id] ??= $item->asset->usageHours();

                return $item->isDue($usage) && !$item->openJob();
            })
            ->each(function (MaintenanceItem $item) {
                $job = $item->convertToJob();
                $this->info("Created \"{$job->name}\" (maintenance item #{$item->id}).");
            });
    }
}
