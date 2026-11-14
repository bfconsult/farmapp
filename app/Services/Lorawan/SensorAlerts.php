<?php

namespace App\Services\Lorawan;

use App\Mail\PumpRunningLong;
use App\Models\Device;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Warnings emailed to a property's admins and managers, checked each time
 * one of its sensors reports (UplinkProcessor) rather than on a schedule,
 * so they go out as soon as the reading that shows the problem arrives.
 */
class SensorAlerts
{
    // A backlog decoded late can't raise a warning about the past.
    private const FRESH_MINUTES = 15;

    public function check(Device $device): void
    {
        if (! $device->last_seen_at || $device->last_seen_at->lt(now()->subMinutes(self::FRESH_MINUTES))) {
            return;
        }

        try {
            if ($device->kind === 'pump') {
                $this->pumpRunningLong($device);
            }
        } catch (\Throwable $e) {
            // A warning that can't be sent mustn't lose the reading.
            Log::error('Sensor alert failed', ['device_id' => $device->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Once per run, when a pump has run longer than its 'warn_after_minutes'
     * setting (0 turns it off).
     */
    private function pumpRunningLong(Device $device): void
    {
        $limit = (float) SensorKinds::setting($device, 'warn_after_minutes');
        $status = (new PumpActivity($device, 'UTC'))->status();
        if ($limit <= 0 || ! $status || ! $status['running'] || ! $status['since']) {
            return;
        }

        // As of the reading that just came in.
        $since = Carbon::parse($status['since']);
        $minutes = (int) $since->diffInMinutes($device->last_seen_at);
        if ($minutes < $limit || ($device->config['alerted']['pump_running_long'] ?? null) === $status['since']) {
            return;
        }

        $config = $device->config ?? [];
        $device->update(['config' => [...$config, 'alerted' => [...($config['alerted'] ?? []), 'pump_running_long' => $status['since']]]]);

        foreach ($this->recipients($device) as $user) {
            Mail::to($user->email)->send(new PumpRunningLong($device, $since, $minutes));
        }
    }

    private function recipients(Device $device)
    {
        return User::contactable()
            ->whereHas('roles', fn ($query) => $query
                ->where('property_id', $device->property_id)
                ->whereIn('type', [Role::ADMIN, Role::MANAGER]))
            ->get();
    }
}
