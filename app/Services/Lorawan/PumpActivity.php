<?php

namespace App\Services\Lorawan;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * A pump's month from its current sensor: how long it ran each day, and
 * each run with its average draw. It's running while the current is above
 * the sensor's threshold (SensorKinds 'running_above'). The sensor reports
 * on an interval, so a start or stop is only known to within that.
 */
class PumpActivity
{
    // A run with no reading for this long is taken to have stopped at its
    // last one, rather than running through a gap of lost uplinks.
    private const MAX_GAP_SECONDS = 2 * 60 * 60;

    private float $threshold;

    public function __construct(private Device $device, private string $timezone)
    {
        $this->threshold = (float) SensorKinds::setting($device, 'running_above');
    }

    /**
     * @return array{days: array<string, int>, runs: array, firstDate: ?string}
     */
    public function month(Carbon $monthStart): array
    {
        $from = $monthStart->copy()->setTimezone($this->timezone)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        // A run still going counts up to now, not the end of the month.
        $until = Carbon::now()->min($to);

        $runs = $this->runs($this->readings($from->copy()->utc(), $to->copy()->utc()), $until)
            ->filter(fn ($run) => $run['ended']->gt($from) && $run['started_at']->lt($to));

        $days = [];
        $runs = $runs->map(function ($run) use ($from, $to, &$days) {
            $dates = [];
            // Split across midnights so each day gets its share.
            $start = $run['started_at']->copy()->setTimezone($this->timezone)->max($from);
            $end = $run['ended']->copy()->setTimezone($this->timezone)->min($to);
            while ($start->lt($end)) {
                $dayEnd = $start->copy()->endOfDay()->min($end);
                $date = $start->toDateString();
                $days[$date] = ($days[$date] ?? 0) + (int) round($start->diffInSeconds($dayEnd));
                $dates[] = $date;
                $start = $start->copy()->addDay()->startOfDay();
            }
            // A run seen in a single reading has no length but did happen.
            if ($dates === []) {
                $dates[] = $start->toDateString();
                $days[$dates[0]] = $days[$dates[0]] ?? 0;
            }

            return [
                'dates' => $dates,
                'started_at' => $run['started_at']->toIso8601String(),
                'ended_at' => $run['ended_at']?->toIso8601String(),
                'seconds' => (int) round($run['started_at']->diffInSeconds($run['ended'])),
                'average_current' => round($run['total'] / $run['count'], 2),
            ];
        });

        $first = $this->device->readings()->where('metric', 'current')->min('recorded_at');

        return [
            'days' => $days,
            'runs' => $runs->values()->all(),
            'firstDate' => $first ? Carbon::parse($first, 'UTC')->setTimezone($this->timezone)->toDateString() : null,
            'threshold' => $this->threshold,
        ];
    }

    /**
     * Running or stopped now and since when, with the latest current.
     */
    public function status(): ?array
    {
        $latest = $this->device->readings()->where('metric', 'current')->latest('recorded_at')->latest('id')->first();
        if (! $latest) {
            return null;
        }

        $running = $latest->value > $this->threshold;
        $readings = fn () => $this->device->readings()->where('metric', 'current');
        $isRunning = fn ($query) => $query->where('value', '>', $this->threshold);
        $isStopped = fn ($query) => $query->where('value', '<=', $this->threshold);

        $lastOther = $readings()->where($running ? $isStopped : $isRunning)->where('recorded_at', '<', $latest->recorded_at)->max('recorded_at');
        $since = $lastOther
            ? $readings()->where($running ? $isRunning : $isStopped)->where('recorded_at', '>', $lastOther)->min('recorded_at')
            : null;

        return [
            'running' => $running,
            // Unknown when this state is all it has ever reported.
            'since' => $since ? Carbon::parse($since, 'UTC')->toIso8601String() : null,
            'current' => $latest->value,
        ];
    }

    /**
     * The month's current readings, plus the one before it in case a run
     * was already going, as [{at, value}] in order.
     */
    private function readings(Carbon $from, Carbon $to): Collection
    {
        $before = $this->device->readings()->where('metric', 'current')->where('recorded_at', '<', $from)->max('recorded_at');

        return $this->device->readings()
            ->where('metric', 'current')
            ->where('recorded_at', '>=', $before ?? $from)
            ->where('recorded_at', '<=', $to)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['recorded_at', 'value'])
            ->map(fn ($reading) => ['at' => $reading->recorded_at, 'value' => $reading->value]);
    }

    /**
     * Each run as {started_at, ended_at (null while running), ended (for
     * its length: the stop, or $until while running), total, count}. It
     * stops at the first reading at or below the threshold.
     */
    private function runs(Collection $readings, Carbon $until): Collection
    {
        $runs = collect();
        $run = null;

        foreach ($readings as $reading) {
            if ($run && $run['last_at']->diffInSeconds($reading['at']) > self::MAX_GAP_SECONDS) {
                $runs->push([...$run, 'ended_at' => $run['last_at'], 'ended' => $run['last_at']]);
                $run = null;
            }

            if ($reading['value'] > $this->threshold) {
                $run ??= ['started_at' => $reading['at'], 'total' => 0, 'count' => 0];
                $run['last_at'] = $reading['at'];
                $run['total'] += $reading['value'];
                $run['count']++;
            } elseif ($run) {
                $runs->push([...$run, 'ended_at' => $reading['at'], 'ended' => $reading['at']]);
                $run = null;
            }
        }

        if ($run) {
            // Still running, unless it has gone quiet for too long.
            $stale = $run['last_at']->diffInSeconds(Carbon::now()) > self::MAX_GAP_SECONDS;
            $runs->push([
                ...$run,
                'ended_at' => $stale ? $run['last_at'] : null,
                'ended' => $stale ? $run['last_at'] : $until->copy()->max($run['last_at']),
            ]);
        }

        return $runs;
    }
}
