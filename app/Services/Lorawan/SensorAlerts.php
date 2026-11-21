<?php

namespace App\Services\Lorawan;

use App\Mail\PumpRunningLong;
use App\Mail\TankDropping;
use App\Mail\TankLow;
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
            if ($device->kind === 'tank') {
                $this->tankLow($device);
                $this->tankDropping($device->fresh());
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

    /**
     * Once when a tank drops below its 'warn_below_percent' setting (0 turns
     * it off), then not again until it has refilled a little above it.
     */
    private function tankLow(Device $device): void
    {
        $reading = $device->readings()->where('metric', 'distance')->latest('recorded_at')->latest('id')->first();
        $level = $reading ? TankActivity::level($device, $reading->value) : null;
        if (! $level) {
            return;
        }

        $warnBelow = (float) SensorKinds::setting($device, 'warn_below_percent');
        $alerted = $device->config['alerted']['tank_low'] ?? null;
        $config = $device->config ?? [];

        if ($alerted && $level['percent'] >= $warnBelow + TankActivity::REARM_ABOVE_PERCENT) {
            unset($config['alerted']['tank_low']);
            $device->update(['config' => $config]);

            return;
        }
        if ($alerted || ! TankActivity::isLow($device, $level)) {
            return;
        }

        $device->update(['config' => [...$config, 'alerted' => [...($config['alerted'] ?? []), 'tank_low' => $reading->recorded_at->toIso8601String()]]]);

        foreach ($this->recipients($device) as $user) {
            Mail::to($user->email)->send(new TankLow($device, $level));
        }
    }

    /**
     * Once for each of its drop warnings when the level falls by that much
     * within that time, then not again until the fall over that time is
     * back under half as much - so a steady leak is one email, not one per
     * reading.
     */
    private function tankDropping(Device $device): void
    {
        $warnings = TankActivity::dropWarnings($device);
        $reading = $device->readings()->where('metric', 'distance')->latest('recorded_at')->latest('id')->first();
        if ($warnings === [] || ! $reading) {
            return;
        }

        $config = $device->config ?? [];
        $alerted = $config['alerted']['tank_drop'] ?? [];
        $send = [];

        foreach ($warnings as $warning) {
            $key = "{$warning['percent']}/{$warning['within_minutes']}";
            $drop = TankActivity::dropWithin($device, $reading, (int) $warning['within_minutes']);
            if (! $drop) {
                continue;
            }

            if (isset($alerted[$key]) && $drop['drop'] < $warning['percent'] / 2) {
                unset($alerted[$key]);
            } elseif (! isset($alerted[$key]) && $drop['drop'] >= $warning['percent']) {
                $alerted[$key] = $reading->recorded_at->toIso8601String();
                $send[] = [$warning, $drop];
            }
        }

        // Only the keys of warnings it still has.
        $current = array_map(fn ($warning) => "{$warning['percent']}/{$warning['within_minutes']}", $warnings);
        $alerted = array_intersect_key($alerted, array_flip($current));
        if ($alerted !== ($config['alerted']['tank_drop'] ?? [])) {
            $device->update(['config' => [...$config, 'alerted' => [...($config['alerted'] ?? []), 'tank_drop' => $alerted]]]);
        }

        foreach ($send as [$warning, $drop]) {
            foreach ($this->recipients($device) as $user) {
                Mail::to($user->email)->send(new TankDropping($device, $warning, $drop));
            }
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
