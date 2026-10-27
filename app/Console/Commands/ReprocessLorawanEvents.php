<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\LorawanEvent;
use App\Services\Lorawan\UplinkProcessor;
use Illuminate\Console\Command;

class ReprocessLorawanEvents extends Command
{
    protected $signature = 'lorawan:reprocess {--device= : Only this device id}';

    protected $description = "Decode stored uplinks that haven't been decoded yet, or that failed to decode - run after adding or fixing a decoder";

    public function handle(UplinkProcessor $processor): void
    {
        $devices = Device::query()
            ->when($this->option('device'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($devices as $device) {
            // Failed ones get another go - the decoder may have been fixed.
            LorawanEvent::where('property_id', $device->property_id)
                ->where('dev_eui', $device->dev_eui)
                ->whereNotNull('error')
                ->update(['processed_at' => null, 'error' => null]);

            $count = $processor->processBacklog($device);
            $this->info("{$device->name}: {$count} uplink(s) processed.");
        }
    }
}
