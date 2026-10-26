<?php

namespace App\Console\Commands;

use App\Models\LorawanEvent;
use Illuminate\Console\Command;

class PruneLorawanEvents extends Command
{
    protected $signature = 'lorawan:prune {--days=30}';

    protected $description = 'Delete raw gateway events older than the retention window, keeping uplinks not yet decoded';

    /**
     * Decoded uplinks live on as SensorReadings, and join/ack/status events
     * are only kept for troubleshooting. An undecoded uplink (unregistered
     * device, or no decoder yet) is kept, since its raw body is the only
     * copy of that reading.
     */
    public function handle(): void
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $deleted = LorawanEvent::where('received_at', '<', $cutoff)
            ->where(fn ($q) => $q->where('event', '!=', 'uplink')
                ->orWhere(fn ($q) => $q->whereNotNull('processed_at')->whereNull('error')))
            ->delete();

        $this->info("Deleted {$deleted} event(s).");
    }
}
