<?php

namespace App\Services\Lorawan;

use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What each sensor's tile on the sensor dashboard shows, at any size - the
 * tile picks what fits (Manage/SensorDashboard.jsx). Built from the same
 * activity classes as each kind's own page, so the two always agree.
 *
 * Every tile: {id, kind, name, state, reporting, last_reading_at, battery}
 * plus its kind's own:
 *   tank: {percent, litres, capacity, low, configured, change_per_hour, levels}
 *   gate: {open, since, openings_today, open_periods_today, openings_by_day}
 *   pump: {running, since, current, seconds_today, runs_today, seconds_by_day}
 */
class SensorTiles
{
    /** The kinds that have a tile, for now. */
    public const KINDS = ['tank', 'gate', 'pump'];

    /** Days of history a large tile shows. */
    private const DAYS = 7;

    public function __construct(private string $timezone) {}

    /** @return array<int, array> */
    public function forProperty(?int $propertyId): array
    {
        return Device::where('property_id', $propertyId)
            ->whereIn('kind', self::KINDS)
            ->with('asset')
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => $this->tile($device))
            ->values()
            ->all();
    }

    public function tile(Device $device): array
    {
        $activity = SensorKinds::activity($device, $this->timezone);
        $status = $activity?->status();
        $battery = $device->readings()->where('metric', 'battery')->latest('recorded_at')->latest('id')->first();

        return [
            'id' => $device->id,
            'kind' => $device->kind,
            'name' => $device->displayName(),
            'state' => SensorKinds::state($device),
            'battery' => $battery ? round($battery->value, 2) : null,
            ...match ($device->kind) {
                'tank' => $this->tank($device, $status),
                'gate' => $this->gate($device, $activity, $status),
                'pump' => $this->pump($activity, $status),
                default => [],
            },
        ];
    }

    private function tank(Device $device, ?array $status): array
    {
        // Its level every couple of hours over the last week: the last
        // reading in each two-hour slot.
        $levels = $device->readings()
            ->where('metric', 'distance')
            ->where('recorded_at', '>=', Carbon::now()->subDays(self::DAYS))
            ->orderBy('recorded_at')
            ->get(['value', 'recorded_at'])
            ->groupBy(fn ($reading) => intdiv($reading->recorded_at->getTimestamp(), 7200))
            ->map(function (Collection $slot) use ($device) {
                $reading = $slot->last();
                $level = TankActivity::level($device, $reading->value);

                return $level ? ['at' => $reading->recorded_at->toIso8601String(), 'percent' => $level['percent']] : null;
            })
            ->filter()
            ->values();

        // How fast it's rising or falling: over the last six hours of it.
        $recent = $levels->filter(fn ($point) => Carbon::parse($point['at'])->gte(Carbon::now()->subHours(6)));
        $change = null;
        if ($recent->count() >= 2) {
            $hours = Carbon::parse($recent->first()['at'])->diffInMinutes(Carbon::parse($recent->last()['at'])) / 60;
            $change = $hours >= 1 ? round(($recent->last()['percent'] - $recent->first()['percent']) / $hours, 2) : null;
        }

        return [
            'percent' => $status['percent'] ?? null,
            'litres' => $status['litres'] ?? null,
            'capacity' => TankActivity::capacity($device),
            'low' => $status['low'] ?? false,
            'configured' => $status['configured'] ?? false,
            'reporting' => $status['reporting'] ?? false,
            'last_reading_at' => $status['at'] ?? null,
            'change_per_hour' => $change,
            'levels' => $levels->all(),
        ];
    }

    private function gate(Device $device, ?object $activity, ?array $status): array
    {
        $week = $this->lastWeek($activity);
        $today = Carbon::now($this->timezone)->toDateString();
        $openings = collect($week['openings'] ?? []);
        $latest = $device->readings()->where('metric', 'door_open')->latest('recorded_at')->value('recorded_at');

        return [
            'open' => $status['open'] ?? null,
            'since' => $status['since'] ?? null,
            // A gate sensor reports on change and every few hours, so it's
            // "not reporting" after a day of silence.
            'reporting' => $latest ? Carbon::parse($latest, 'UTC')->gte(Carbon::now()->subDay()) : false,
            'last_reading_at' => $latest ? Carbon::parse($latest, 'UTC')->toIso8601String() : null,
            'openings_today' => (int) ($week['days'][$today] ?? 0),
            'open_periods_today' => $openings
                ->filter(fn ($opening) => ($opening['date'] ?? null) === $today && $opening['opened_at'])
                ->map(fn ($opening) => ['from' => $opening['opened_at'], 'to' => $opening['closed_at']])
                ->values()
                ->all(),
            'openings_by_day' => $this->byDay($week['days'] ?? []),
        ];
    }

    private function pump(?object $activity, ?array $status): array
    {
        $week = $this->lastWeek($activity);
        $today = Carbon::now($this->timezone)->toDateString();

        return [
            'running' => $status['running'] ?? null,
            'since' => $status['since'] ?? null,
            'current' => $status['current'] ?? null,
            'reporting' => $status['reporting'] ?? false,
            'last_reading_at' => $status['last_reading_at'] ?? null,
            'seconds_today' => (int) ($week['days'][$today] ?? 0),
            'runs_today' => collect($week['runs'] ?? [])
                ->filter(fn ($run) => in_array($today, $run['dates'], true))
                ->map(fn ($run) => ['from' => $run['started_at'], 'to' => $run['ended_at']])
                ->values()
                ->all(),
            'seconds_by_day' => $this->byDay($week['days'] ?? []),
        ];
    }

    /**
     * The activity's month() for this month, with last month's merged in
     * while the last week reaches back into it.
     */
    private function lastWeek(?object $activity): array
    {
        if (! $activity) {
            return [];
        }
        $now = Carbon::now($this->timezone);
        $month = $activity->month($now->copy()->startOfMonth());
        if ($now->copy()->subDays(self::DAYS - 1)->month !== $now->month) {
            $previous = $activity->month($now->copy()->startOfMonth()->subMonth());
            foreach (['openings', 'runs'] as $list) {
                if (isset($month[$list])) {
                    $month[$list] = [...($previous[$list] ?? []), ...$month[$list]];
                }
            }
            $month['days'] = ($previous['days'] ?? []) + ($month['days'] ?? []);
        }

        return $month;
    }

    /** The last week's days, oldest first, as [{date, value}] - 0 for a day with none. */
    private function byDay(array $days): array
    {
        $today = Carbon::now($this->timezone)->startOfDay();

        return collect(range(self::DAYS - 1, 0))
            ->map(fn ($back) => $today->copy()->subDays($back)->toDateString())
            ->map(fn ($date) => ['date' => $date, 'value' => (int) ($days[$date] ?? 0)])
            ->all();
    }
}
