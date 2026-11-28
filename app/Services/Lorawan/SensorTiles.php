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
 * Every tile: {id, kind, name, state, reporting, last_reading_at, battery,
 * calendar} plus its kind's own:
 *   tank: {percent, litres, capacity, low, configured, warn_below, change_per_hour, levels}
 *   gate: {open, since, openings_today, open_periods_today, openings_by_day}
 *   pump: {running, since, current, seconds_today, runs_today, seconds_by_day}
 *
 * `calendar` is a value per day, [{date, value}], from the Monday four
 * weeks before this week's to today - for a tall tile's calendar: a tank's
 * lowest level that day (%), a gate's openings, a pump's seconds running.
 */
class SensorTiles
{
    /** The kinds that have a tile, for now. */
    public const KINDS = ['tank', 'gate', 'pump'];

    /** Days of history a large tile's chart shows. */
    private const DAYS = 7;

    /** Weeks before this one a tall tile's calendar shows. */
    private const CALENDAR_WEEKS = 4;

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

        $history = $this->history($activity);
        // A tank's day is its lowest level; the others' are a count or a total.
        $days = $device->kind === 'tank'
            ? array_map(fn ($day) => $day['min'], $history['levels'] ?? [])
            : ($history['days'] ?? []);

        return [
            'id' => $device->id,
            'kind' => $device->kind,
            'name' => $device->displayName(),
            'state' => SensorKinds::state($device),
            'battery' => $battery ? round($battery->value, 2) : null,
            'calendar' => $this->calendar($days),
            ...match ($device->kind) {
                'tank' => $this->tank($device, $status),
                'gate' => $this->gate($device, $history, $status),
                'pump' => $this->pump($history, $status),
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
            'warn_below' => (float) SensorKinds::setting($device, 'warn_below_percent') ?: null,
            'reporting' => $status['reporting'] ?? false,
            'last_reading_at' => $status['at'] ?? null,
            'change_per_hour' => $change,
            'levels' => $levels->all(),
        ];
    }

    private function gate(Device $device, array $week, ?array $status): array
    {
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

    private function pump(array $week, ?array $status): array
    {
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

    /** The first day a tall tile's calendar shows: a Monday. */
    private function calendarStart(): Carbon
    {
        return Carbon::now($this->timezone)->startOfWeek(Carbon::MONDAY)->subWeeks(self::CALENDAR_WEEKS);
    }

    /**
     * The activity's month() for each month back to the calendar's start,
     * merged: days (and a tank's levels) by date, openings and runs in order.
     */
    private function history(?object $activity): array
    {
        if (! $activity) {
            return [];
        }
        $merged = ['days' => [], 'levels' => [], 'openings' => [], 'runs' => []];
        $month = $this->calendarStart()->startOfMonth();
        $now = Carbon::now($this->timezone);
        while ($month->lte($now)) {
            $data = $activity->month($month->copy());
            $merged['days'] = ($data['days'] ?? []) + $merged['days'];
            $merged['levels'] = ($data['levels'] ?? []) + $merged['levels'];
            $merged['openings'] = [...$merged['openings'], ...($data['openings'] ?? [])];
            $merged['runs'] = [...$merged['runs'], ...($data['runs'] ?? [])];
            $month->addMonth();
        }

        return $merged;
    }

    /** Each day from the calendar's start to today, as [{date, value}] - null for a day with none. */
    private function calendar(array $days): array
    {
        $today = Carbon::now($this->timezone)->toDateString();
        $dates = [];
        for ($day = $this->calendarStart(); $day->toDateString() <= $today; $day->addDay()) {
            $date = $day->toDateString();
            $dates[] = ['date' => $date, 'value' => isset($days[$date]) ? round((float) $days[$date], 1) : null];
        }

        return $dates;
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
