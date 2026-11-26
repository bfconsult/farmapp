<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetType;
use Illuminate\Console\Command;

/**
 * Turns assets drawn as an "out and back" shape - a fence drawn as a
 * polygon whose return points sit on its outward ones, before assets could
 * be lines - into a line, and puts them in the property's fence type. Lists what it would do
 * unless run with --apply. The old shape stays in the asset's location
 * history; anything that isn't clearly out and back is left alone.
 */
class AssetShapesToLines extends Command
{
    protected $signature = 'assets:shapes-to-lines
        {--property= : Only this property\'s assets}
        {--apply : Make the changes (otherwise only list them)}
        {--tolerance=3 : How close, in metres, a return point must be to its outward one}';

    protected $description = 'Convert out-and-back asset shapes (fences drawn as thin polygons) into lines';

    public function handle(): int
    {
        $tolerance = (float) $this->option('tolerance');
        $assets = Asset::with('currentLocation')
            ->when($this->option('property'), fn ($query, $id) => $query->where('property_id', $id))
            ->orderBy('property_id')
            ->orderBy('name')
            ->get()
            ->filter(fn (Asset $asset) => $asset->currentLocation?->shape);

        $converted = 0;
        foreach ($assets as $asset) {
            $line = self::outAndBack($asset->currentLocation->shape, $tolerance);
            if (! $line) {
                $this->line("  left as a shape: #{$asset->id} {$asset->name} (property {$asset->property_id}) - not out and back");

                continue;
            }

            $metres = round(self::length($line));
            $this->info("  line: #{$asset->id} {$asset->name} (property {$asset->property_id}) - ".count($line)." points, {$metres} m");
            $converted++;

            if ($this->option('apply')) {
                $asset->locations()->create([
                    'line' => $line,
                    'created_by' => $asset->currentLocation->created_by,
                ]);
                $asset->update(['asset_type_id' => AssetType::fenceTypeFor($asset->property_id)->id]);
            }
        }

        $this->newLine();
        $this->line($this->option('apply')
            ? "Converted {$converted} to lines."
            : "{$converted} would be converted. Run again with --apply to convert them.");

        return self::SUCCESS;
    }

    /**
     * The outward half of a shape that goes out along a line and comes back
     * along it - or null if it doesn't. Points within the tolerance of each
     * other count as the same place; going round the shape then reads the
     * same forwards and backwards from one end of the line.
     *
     * @param  array<array{0: float, 1: float}>  $shape
     * @return array<array{0: float, 1: float}>|null
     */
    public static function outAndBack(array $shape, float $tolerance = 3): ?array
    {
        // Each point as a place: the first earlier place it's close to.
        $places = [];
        $sequence = [];
        foreach ($shape as $point) {
            $found = null;
            foreach ($places as $i => $place) {
                if (self::distance($place, $point) <= $tolerance) {
                    $found = $i;
                    break;
                }
            }
            if ($found === null) {
                $places[] = [(float) $point[0], (float) $point[1]];
                $found = count($places) - 1;
            }
            // The same place twice running (a doubled-up end) is one visit.
            if (end($sequence) !== $found) {
                $sequence[] = $found;
            }
        }
        if (count($sequence) > 1 && $sequence[0] === end($sequence)) {
            array_pop($sequence);
        }

        $count = count($sequence);
        if ($count < 2 || count($places) < 2 || $count % 2 !== 0) {
            return null;
        }

        // Start from one end: then the way back mirrors the way out.
        for ($start = 0; $start < $count; $start++) {
            $mirrors = true;
            for ($i = 1; $i < $count && $mirrors; $i++) {
                $mirrors = $sequence[($start + $i) % $count] === $sequence[($start - $i + $count) % $count];
            }
            if ($mirrors) {
                $line = [];
                for ($i = 0; $i <= $count / 2; $i++) {
                    $line[] = $places[$sequence[($start + $i) % $count]];
                }

                return $line;
            }
        }

        return null;
    }

    private static function distance(array $a, array $b): float
    {
        $dy = ($b[0] - $a[0]) * 110540;
        $dx = ($b[1] - $a[1]) * 111320 * cos(deg2rad(($a[0] + $b[0]) / 2));

        return sqrt($dx * $dx + $dy * $dy);
    }

    private static function length(array $line): float
    {
        $metres = 0;
        for ($i = 1; $i < count($line); $i++) {
            $metres += self::distance($line[$i - 1], $line[$i]);
        }

        return $metres;
    }
}
