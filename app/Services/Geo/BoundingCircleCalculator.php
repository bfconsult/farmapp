<?php

namespace App\Services\Geo;

/**
 * Neither Android's nor iOS's native geofencing API supports polygons -
 * only circles. This computes a circle that fully contains a property's
 * hand-drawn boundary polygon, for the mobile app to register as its
 * OS-level geofence; the app then does precise point-in-polygon math only
 * once it's woken up by that circle, using the polygon coordinates also
 * returned alongside it.
 */
class BoundingCircleCalculator
{
    private const EARTH_RADIUS_METERS = 6371000;

    private const DEFAULT_BUFFER_METERS = 75.0;

    /**
     * Centroid-of-vertices as center, max distance to any vertex (plus a
     * buffer) as radius - not a true min-enclosing-circle, but within a few
     * percent for farm-paddock-sized polygons, which is well inside the
     * buffer anyway. A pathologically long/thin property producing an
     * oversized radius is the signal to revisit this with something like
     * Welzl's algorithm - isolated here so that wouldn't touch callers.
     *
     * @param array<int, array{0: float, 1: float}> $coordinates [lat, lng] pairs
     * @return array{lat: float, lng: float, radius_meters: float}
     */
    public static function forPolygon(array $coordinates, float $bufferMeters = self::DEFAULT_BUFFER_METERS): array
    {
        if (empty($coordinates)) {
            return ['lat' => 0.0, 'lng' => 0.0, 'radius_meters' => $bufferMeters];
        }

        $centerLat = array_sum(array_column($coordinates, 0)) / count($coordinates);
        $centerLng = array_sum(array_column($coordinates, 1)) / count($coordinates);

        $maxDistance = 0.0;
        foreach ($coordinates as [$lat, $lng]) {
            $maxDistance = max($maxDistance, self::haversine($centerLat, $centerLng, $lat, $lng));
        }

        return [
            'lat' => $centerLat,
            'lng' => $centerLng,
            'radius_meters' => round($maxDistance + $bufferMeters, 1),
        ];
    }

    private static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
