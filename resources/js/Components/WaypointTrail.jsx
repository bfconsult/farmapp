import { useEffect, useRef } from 'react';

/**
 * Read-only trail of the periodic location samples taken while a work
 * session was auto-tracked - helps a user recall/allocate where the time
 * actually went. Renders nothing if there are no waypoints (manually-logged
 * sessions never have any).
 */
export default function WaypointTrail({ waypoints }) {
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
    }, [waypoints]);

    if (!waypoints || waypoints.length === 0) return null;

    return (
        <div className="bg-white rounded-lg shadow p-4 mb-4">
            <p className="text-sm font-medium text-gray-700 mb-2">Location trail while tracked</p>
            <div ref={mapRef} style={{ height: '16rem' }} className="rounded-lg overflow-hidden" />
        </div>
    );
}
