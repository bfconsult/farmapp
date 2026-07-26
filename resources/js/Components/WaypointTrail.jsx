import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';

const ZONE_COLOR = '#7c3aed';

/**
 * Read-only trail of the periodic location samples taken while a work
 * session was auto-tracked - helps a user recall/allocate where the time
 * actually went. Renders nothing if there are no waypoints (manually-logged
 * sessions never have any). Zones are drawn underneath as context (which
 * paddock the trail passed through), matching the main Map page's styling.
 *
 * `workSessionId` also doubles as the "show the Delete Path button" flag -
 * only passed by the already-reviewed Show page, not Edit (where the trail
 * is still needed as reference while allocating the session).
 */
export default function WaypointTrail({ waypoints, zones, workSessionId }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);

    useEffect(() => {
        if (!waypoints || waypoints.length === 0 || mapInstance.current) return;

        import('leaflet').then(async (L) => {
            await import('leaflet/dist/leaflet.css');

            const map = L.map(mapRef.current);
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            (zones ?? []).forEach((zone) => {
                L.polygon(zone.coordinates, {
                    color: ZONE_COLOR,
                    weight: 2,
                    fillColor: ZONE_COLOR,
                    fillOpacity: 0.15,
                }).bindTooltip(zone.name, { permanent: true, direction: 'center' }).addTo(map);
            });

            const points = waypoints.map((w) => [w.latitude, w.longitude]);
            L.polyline(points, { color: '#16a34a', weight: 3 }).addTo(map);
            points.forEach((point, i) => {
                L.circleMarker(point, {
                    radius: 4,
                    color: '#16a34a',
                    fillColor: i === points.length - 1 ? '#16a34a' : '#fff',
                    fillOpacity: 1,
                }).addTo(map);
            });

            map.fitBounds(L.latLngBounds(points), { padding: [24, 24] });
        });

        return () => {
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
    }, [waypoints, zones]);

    if (!waypoints || waypoints.length === 0) return null;

    const deletePath = () => {
        if (!confirm('Delete this tracked path? This cannot be undone.')) return;
        router.delete(route('work-sessions.waypoints.destroy', workSessionId), { preserveScroll: true });
    };

    return (
        <div className="bg-white rounded-lg shadow p-4 mb-4">
            <div className="flex items-center justify-between mb-2">
                <p className="text-sm font-medium text-gray-700">Location trail while tracked</p>
                {workSessionId && (
                    <button onClick={deletePath} className="text-xs text-red-500 font-medium">
                        Delete Path
                    </button>
                )}
            </div>
            <div ref={mapRef} style={{ height: '16rem' }} className="rounded-lg overflow-hidden" />
        </div>
    );
}
