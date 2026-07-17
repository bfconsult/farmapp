<?php

namespace App\Console\Commands;

use App\Models\Metric;
use Illuminate\Console\Command;

class GenerateMetricMeasurements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:generate-measurements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Open each active metric's next measurement once its current period has ended";

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Metric::where('is_active', true)->get()->each(function (Metric $metric) {
            $latest = $metric->measurements()->latest('period_start')->first();

            if (!$latest) {
                // Normally the first measurement is created immediately
                // alongside the metric (see MetricController::store()) —
                // this only fires as a fallback if a metric ever exists
                // without one.
                $measurement = $metric->createMeasurement(now()->startOfDay());
                $this->info("Created \"{$measurement->name}\" for {$measurement->period_start->toDateString()} to {$measurement->period_end->toDateString()} (metric #{$metric->id}).");

                return;
            }

            // Deliberately no "closing" of an overdue incomplete measurement
            // — unlike recurring jobs, it just stays incomplete as a visible
            // "this one was missed" indicator while the next period opens.
            if (now()->toDateString() > $latest->period_end->toDateString()) {
                $measurement = $metric->createMeasurement($latest->period_end->copy()->addDay());
                $this->info("Created \"{$measurement->name}\" for {$measurement->period_start->toDateString()} to {$measurement->period_end->toDateString()} (metric #{$metric->id}).");
            }
        });
    }
}
