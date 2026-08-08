import { useEffect, useRef } from 'react';

const PROPERTY_BOUNDARY_COLOR = '#ca8a04';
const PROPERTY_BOUNDARY_FILL = '#fef08a';

function loadLeaflet() {
    return Promise.all([
        import('leaflet'),
        import('leaflet/dist/leaflet.css'),
    ]).then(([L]) => {
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        });
        return L;
    });
}

/**
 * A Leaflet map showing a pin (if latitude/longitude are set) in the context
 * of a property's boundary. When `editable`, the pin can be dragged and
 * reports its new position via `onDragEnd`. `interactive={false}` disables
 * the map's own pan/zoom (but not marker dragging) for use as a static-
 * looking thumbnail preview. Renders nothing itself if there's neither a
 * pin position nor a boundary to show - the caller should show its own
 * placeholder for that case rather than mounting an empty map.
 */
export default function LocationMap({
    latitude,
    longitude,
    propertyBoundary,
    editable = false,
    onDragEnd,
    height = '300px',
    interactive = true,
    boundaryColor = PROPERTY_BOUNDARY_COLOR,
    boundaryFillColor = PROPERTY_BOUNDARY_FILL,
}) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const hasPin = latitude != null && longitude != null;

    useEffect(() => {
        if (!hasPin && !propertyBoundary) return undefined;

        let cancelled = false;

        loadLeaflet().then((L) => {
            if (cancelled || mapInstance.current) return;

            const map = L.map(mapRef.current, {
                zoomControl: interactive,
                dragging: interactive,
                touchZoom: interactive,
                scrollWheelZoom: interactive,
                doubleClickZoom: interactive,
                boxZoom: interactive,
                keyboard: interactive,
            });
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            const bounds = L.latLngBounds([]);

            if (propertyBoundary) {
                const boundary = L.polygon(propertyBoundary, {
                    color: boundaryColor,
                    weight: 2,
                    dashArray: '6, 6',
                    fillColor: boundaryFillColor,
                    fillOpacity: 0.15,
                    interactive: false,
                }).addTo(map);
                bounds.extend(boundary.getBounds());
            }

            if (hasPin) {
                const marker = L.marker([latitude, longitude], {
                    draggable: Boolean(editable),
                    icon: L.icon({
                        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                    }),
                }).addTo(map);
                bounds.extend([latitude, longitude]);

                if (editable) {
                    marker.on('dragend', () => {
                        const { lat, lng } = marker.getLatLng();
                        onDragEnd?.(lat, lng);
                    });
                }
            }

            map.invalidateSize();
            map.fitBounds(bounds, { padding: [40, 40] });
        });

        return () => {
            cancelled = true;
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (!hasPin && !propertyBoundary) return null;

    return <div ref={mapRef} style={{ height }} />;
}
