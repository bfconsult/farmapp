<?php

namespace App\Services\Geo;

use App\Models\PropertyTerrain;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ground heights for a property, from the free AWS Terrain Tiles (Mapzen's
 * "Terrarium" PNGs: about 30 m detail in rural Australia, from SRTM). The
 * tiles covering the land are fetched once, sampled onto a regular grid and
 * saved; the farm view draws its hills and contours from that grid.
 */
class Terrain
{
    public const SOURCE = 'aws-terrain-tiles';

    private const TILE_URL = 'https://s3.amazonaws.com/elevation-tiles-prod/terrarium/%d/%d/%d.png';

    private const TILE_PX = 256;

    // The data is about 30 m, so zoom 13 (about 15 m a pixel here) is
    // plenty; a big property drops a zoom level rather than fetch more.
    private const MAX_ZOOM = 13;

    private const MAX_TILES = 16;

    // Grid spacing: about this many points across the longer side, but no
    // finer than the data really is.
    private const POINTS_ACROSS = 100;

    private const MIN_SPACING_METRES = 10;

    /** @var array<string, \GdImage> */
    private array $tiles = [];

    /** The pixels the tiles cover: [left, top, right, bottom]. */
    private array $pixels = [0, 0, 0, 0];

    /**
     * Fetch and save the heights under these [lat, lng] points (the
     * boundary and paddocks), with a margin around them.
     */
    public function fetchFor(int $propertyId, array $points): PropertyTerrain
    {
        if (count($points) === 0) {
            throw new RuntimeException('Draw the property\'s boundary or paddocks first.');
        }
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('The server can\'t read the height data (PHP\'s GD extension is missing).');
        }

        $lats = array_column($points, 0);
        $lngs = array_column($points, 1);
        $midLat = (max($lats) + min($lats)) / 2;
        $metresPerLat = 110540;
        $metresPerLng = 111320 * cos(deg2rad($midLat));

        $height = (max($lats) - min($lats)) * $metresPerLat;
        $width = (max($lngs) - min($lngs)) * $metresPerLng;
        $spacing = max(self::MIN_SPACING_METRES, max($width, $height) / self::POINTS_ACROSS);

        // Two points of margin all round, so the edges have hills too.
        $margin = 2 * $spacing;
        $north = max($lats) + $margin / $metresPerLat;
        $south = min($lats) - $margin / $metresPerLat;
        $west = min($lngs) - $margin / $metresPerLng;
        $east = max($lngs) + $margin / $metresPerLng;

        $columns = (int) ceil(($east - $west) * $metresPerLng / $spacing) + 1;
        $rows = (int) ceil(($north - $south) * $metresPerLat / $spacing) + 1;

        $zoom = $this->zoomFor($north, $south, $west, $east);
        $this->loadTiles($zoom, $north, $south, $west, $east);

        $heights = [];
        for ($r = 0; $r < $rows; $r++) {
            $lat = $north - ($north - $south) * $r / ($rows - 1);
            for ($c = 0; $c < $columns; $c++) {
                $lng = $west + ($east - $west) * $c / ($columns - 1);
                $heights[] = round($this->heightAt($zoom, $lat, $lng), 1);
            }
        }

        return PropertyTerrain::updateOrCreate(['property_id' => $propertyId], [
            'north' => $north,
            'south' => $south,
            'west' => $west,
            'east' => $east,
            'columns' => $columns,
            'rows' => $rows,
            'heights' => $heights,
            'source' => self::SOURCE,
        ]);
    }

    private function zoomFor(float $north, float $south, float $west, float $east): int
    {
        for ($zoom = self::MAX_ZOOM; $zoom > 6; $zoom--) {
            [$x0, $y0] = $this->tileOf($zoom, $north, $west);
            [$x1, $y1] = $this->tileOf($zoom, $south, $east);
            if (($x1 - $x0 + 1) * ($y1 - $y0 + 1) <= self::MAX_TILES) {
                return $zoom;
            }
        }

        return 6;
    }

    private function loadTiles(int $zoom, float $north, float $south, float $west, float $east): void
    {
        // A pixel more all round, for blending at the edges.
        [$left, $top] = $this->pixelOf($zoom, $north, $west);
        [$right, $bottom] = $this->pixelOf($zoom, $south, $east);
        $this->pixels = [(int) floor($left) - 1, (int) floor($top) - 1, (int) floor($right) + 1, (int) floor($bottom) + 1];
        [$x0, $y0, $x1, $y1] = array_map(fn ($p) => intdiv($p, self::TILE_PX), $this->pixels);
        $wanted = [];
        for ($x = $x0; $x <= $x1; $x++) {
            for ($y = $y0; $y <= $y1; $y++) {
                $wanted["{$x}/{$y}"] = sprintf(self::TILE_URL, $zoom, $x, $y);
            }
        }

        $responses = Http::pool(fn (Pool $pool) => collect($wanted)
            ->map(fn ($url, $key) => $pool->as($key)->timeout(20)->get($url))
            ->all());

        foreach ($wanted as $key => $url) {
            $response = $responses[$key] ?? null;
            if (! $response instanceof \Illuminate\Http\Client\Response || ! $response->successful()) {
                throw new RuntimeException('Couldn\'t fetch the height data - try again in a minute.');
            }
            $image = @imagecreatefromstring($response->body());
            if ($image === false) {
                throw new RuntimeException('The height data came back unreadable.');
            }
            $this->tiles[$key] = $image;
        }
    }

    /** The tile a point is in, at this zoom (Web Mercator, as map tiles are). */
    private function tileOf(int $zoom, float $lat, float $lng): array
    {
        [$px, $py] = $this->pixelOf($zoom, $lat, $lng);

        return [intdiv((int) floor($px), self::TILE_PX), intdiv((int) floor($py), self::TILE_PX)];
    }

    private function pixelOf(int $zoom, float $lat, float $lng): array
    {
        $size = self::TILE_PX * (2 ** $zoom);
        $x = ($lng + 180) / 360 * $size;
        $sin = sin(deg2rad($lat));
        $y = (0.5 - log((1 + $sin) / (1 - $sin)) / (4 * M_PI)) * $size;

        return [$x, $y];
    }

    /** Height at a point, blended from the four pixels around it. */
    private function heightAt(int $zoom, float $lat, float $lng): float
    {
        [$px, $py] = $this->pixelOf($zoom, $lat, $lng);
        $px -= 0.5;
        $py -= 0.5;
        $x = (int) floor($px);
        $y = (int) floor($py);
        $fx = $px - $x;
        $fy = $py - $y;

        $top = $this->pixel($x, $y) * (1 - $fx) + $this->pixel($x + 1, $y) * $fx;
        $bottom = $this->pixel($x, $y + 1) * (1 - $fx) + $this->pixel($x + 1, $y + 1) * $fx;

        return $top * (1 - $fy) + $bottom * $fy;
    }

    /** One pixel's height: Terrarium packs metres as R*256 + G + B/256 - 32768. */
    private function pixel(int $x, int $y): float
    {
        $x = max($this->pixels[0], min($this->pixels[2], $x));
        $y = max($this->pixels[1], min($this->pixels[3], $y));
        $tile = $this->tiles[intdiv($x, self::TILE_PX).'/'.intdiv($y, self::TILE_PX)];
        $rgb = imagecolorat($tile, $x % self::TILE_PX, $y % self::TILE_PX);
        if (! imageistruecolor($tile)) {
            $c = imagecolorsforindex($tile, $rgb);
            [$r, $g, $b] = [$c['red'], $c['green'], $c['blue']];
        } else {
            [$r, $g, $b] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
        }

        return $r * 256 + $g + $b / 256 - 32768;
    }
}
