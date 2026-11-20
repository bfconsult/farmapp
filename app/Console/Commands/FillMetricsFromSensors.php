<?php

namespace App\Console\Commands;

use App\Services\Lorawan\MetricsFromSensors;
use Illuminate\Console\Command;

class FillMetricsFromSensors extends Command
{
    protected $signature = 'metrics:fill-from-sensors';

    protected $description = "Fill in finished measurements of metrics linked to a sensor from the sensor's readings";

    public function handle(MetricsFromSensors $metrics): void
    {
        $this->info("Filled {$metrics->fillAll()} measurement(s) from sensors.");
    }
}
