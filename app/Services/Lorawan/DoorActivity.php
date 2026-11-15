<?php

namespace App\Services\Lorawan;

use App\Models\Device;
use App\Models\SensorReading;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * A door/gate sensor's month: how many times it opened each day, and each
 * opening with when it closed. Built from its door_open/open_count
 * readings. The open count is the sensor's own running total, so it still
 * counts an opening whose uplink the gateway never received.
 */
class DoorActivity
{
    private const METRICS = ['door_open', 'open_count', 'last_open_minutes'];

    /**
     * A gate swinging shut bounces the magnet past the reed switch, so one
     * use often arrives as open/close/open/close a few seconds apart. A
     * re-open within this long of the close is the same opening.
     */
    public const DEBOUNCE_SECONDS = 15;

    public function __construct(private Device $device, private string $timezone) {}

    /**
     * @return array{days: array<string, int>, openings: array, firstDate: ?string}
     */
    public function month(Carbon $monthStart): array
    {
        $from = $monthStart->copy()->setTimezone($this->timezone)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $days = [];
        $openings = [];
        $current = null;
        $previous = null;

        foreach ($this->uplinks($from->copy()->utc(), $to->copy()->utc()) as $uplink) {
            $inMonth = $uplink['at']->gte($from);
            $opened = $this->openingsSince($previous, $uplink);

            if ($uplink['door_open'] && $opened > 0) {
                // A second opening with no close between means the close
                // uplink was lost - that opening's close stays unknown.
                $current = null;
                if ($inMonth) {
                    $openings[] = [
                        'date' => $this->date($uplink['at']),
                        'opened_at' => $uplink['at'],
                        'closed_at' => null,
                        'seconds' => null,
                        'missed' => $opened - 1,
                    ];
                    $current = array_key_last($openings);
                }
            } else {
                if (! $uplink['door_open'] && $current !== null) {
                    $openings[$current] = $this->close($openings[$current], $uplink);
                    $current = null;
                }
                if ($inMonth && $opened > 0) {
                    // Opened and closed again between uplinks the gateway
                    // received: known only from the count.
                    $openings[] = [
                        'date' => $this->date($uplink['at']),
                        'opened_at' => null,
                        'closed_at' => null,
                        'seconds' => null,
                        'missed' => $opened,
                        // When it was seen, for debouncing - not sent.
                        'at' => $uplink['at'],
                    ];
                }
            }

            if ($inMonth) {
                // Days it reported on show 0, not "no data".
                $days[$this->date($uplink['at'])] ??= 0;
            }
            $previous = $uplink;
        }

        $openings = $this->debounce($openings);
        foreach ($openings as $opening) {
            $days[$opening['date']] += ($opening['opened_at'] ? 1 : 0) + $opening['missed'];
        }

        $first = $this->device->readings()->where('metric', 'door_open')->min('recorded_at');

        return [
            'days' => $days,
            'openings' => array_map(fn ($opening) => [
                ...collect($opening)->except('at')->all(),
                'opened_at' => $opening['opened_at']?->toIso8601String(),
                'closed_at' => $opening['closed_at']?->toIso8601String(),
            ], $openings),
            'firstDate' => $first ? $this->date(Carbon::parse($first, 'UTC')) : null,
        ];
    }

    /**
     * Folds each opening that starts within DEBOUNCE_SECONDS of the one
     * before closing into that one, which now closes when it does. Openings
     * counted from the total in the same window are bounces too, so their
     * count is dropped.
     */
    private function debounce(array $openings): array
    {
        $kept = [];
        foreach ($openings as $opening) {
            $last = array_key_last($kept);
            $startedAt = $opening['opened_at'] ?? $opening['at'] ?? null;

            $gap = $last !== null && $kept[$last]['closed_at'] && $startedAt
                ? $kept[$last]['closed_at']->diffInSeconds($startedAt)
                : null;

            if ($gap !== null && $gap >= 0 && $gap <= self::DEBOUNCE_SECONDS) {
                // A count-only one was open and closed again by the uplink
                // that reported it.
                $closedAt = $opening['opened_at'] ? $opening['closed_at'] : $opening['at'];
                $kept[$last]['closed_at'] = $closedAt;
                $kept[$last]['seconds'] = $closedAt ? (int) $kept[$last]['opened_at']->diffInSeconds($closedAt) : null;

                continue;
            }

            $kept[] = $opening;
        }

        return $kept;
    }

    /**
     * Open or closed now, and since when.
     */
    public function status(): ?array
    {
        $doorReadings = fn () => $this->device->readings()->where('metric', 'door_open');

        $latest = $doorReadings()->latest('recorded_at')->first();
        if (! $latest) {
            return null;
        }

        $lastOther = $doorReadings()->where('value', '!=', $latest->value)->where('recorded_at', '<', $latest->recorded_at)->max('recorded_at');
        $since = $doorReadings()->where('value', $latest->value)
            ->when($lastOther, fn ($query) => $query->where('recorded_at', '>', $lastOther))
            ->min('recorded_at');

        return [
            'open' => (bool) $latest->value,
            // Unknown when this state is all it has ever reported.
            'since' => $lastOther ? Carbon::parse($since, 'UTC')->toIso8601String() : null,
        ];
    }

    /**
     * The month's uplinks, plus the one before it so the first can be
     * compared, as [{at, door_open, open_count, last_open_minutes}] in order.
     */
    private function uplinks(Carbon $from, Carbon $to): Collection
    {
        $before = $this->device->readings()->where('metric', 'door_open')->where('recorded_at', '<', $from)->max('recorded_at');

        return $this->device->readings()
            ->whereIn('metric', self::METRICS)
            ->where('recorded_at', '>=', $before ?? $from)
            ->where('recorded_at', '<=', $to)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            // One uplink's readings share its frame counter and timestamp.
            ->groupBy(fn (SensorReading $reading) => $reading->recorded_at->getTimestamp().'|'.$reading->f_cnt)
            ->map(fn (Collection $readings) => [
                'at' => $readings->first()->recorded_at,
                ...collect(self::METRICS)->mapWithKeys(fn ($metric) => [
                    $metric => ($value = $readings->firstWhere('metric', $metric)?->value) !== null ? (int) $value : null,
                ]),
            ])
            ->filter(fn ($uplink) => $uplink['door_open'] !== null)
            ->values();
    }

    /**
     * How many times it opened since the previous uplink. The count drops
     * back to 0 when the sensor rejoins, so a lower count is all new.
     */
    private function openingsSince(?array $previous, array $uplink): int
    {
        if ($previous === null) {
            return $uplink['door_open'];
        }
        if ($previous['open_count'] === null || $uplink['open_count'] === null) {
            return $uplink['door_open'] && ! $previous['door_open'] ? 1 : 0;
        }

        return $uplink['open_count'] >= $previous['open_count']
            ? $uplink['open_count'] - $previous['open_count']
            : $uplink['open_count'];
    }

    /**
     * Closed by this uplink. If it's a periodic one that arrived well after
     * a lost close uplink, the sensor's own last-open duration is used
     * instead of the gap.
     */
    private function close(array $opening, array $uplink): array
    {
        $seconds = $opening['opened_at']->diffInSeconds($uplink['at']);
        $closedAt = $uplink['at'];

        if ($uplink['last_open_minutes'] !== null && abs($seconds / 60 - $uplink['last_open_minutes']) > 2) {
            $seconds = $uplink['last_open_minutes'] * 60;
            $closedAt = $opening['opened_at']->copy()->addSeconds($seconds);
        }

        return [...$opening, 'closed_at' => $closedAt, 'seconds' => (int) $seconds];
    }

    private function date(Carbon $at): string
    {
        return $at->copy()->setTimezone($this->timezone)->toDateString();
    }
}
