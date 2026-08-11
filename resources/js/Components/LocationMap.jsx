import { useEffect, useRef } from 'react';

const PROPERTY_BOUNDARY_COLOR = '#ca8a04';
const PROPERTY_BOUNDARY_FILL = '#fef08a';
const ZONE_COLOR = '#7c3aed';

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
 * of a property's boundary, and optionally shaded `zones` (violet, same
 * styling as the Map/Shape/Asset pages) - typically a job's related zones.
 * When `editable`, the pin can be dragged and reports its new position via
 * `onDragEnd`. `interactive={false}` disables the map's own pan/zoom (but
 * not marker dragging) for use as a static-looking thumbnail preview.
 * Renders nothing itself if there's no pin, boundary, or zone to show - the
 * caller should show its own placeholder for that case rather than mounting
 * an empty map.
 */
export default function LocationMap({
    latitude,
    longitude,
    propertyBoundary,
    zones,
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
    const hasZones = zones && zones.length > 0;

    useEffect(() => {
        if (!hasPin && !propertyBoundary && !hasZones) return undefined;

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
                // Its tile fade-in relies on requestAnimationFrame, which stalls if the
                // tab loses focus/visibility mid-fade, leaving tiles stuck at opacity 0.
                fadeAnimation: false,
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

            if (hasZones) {
                zones.forEach((zone) => {
                    const polygon = L.polygon(zone.coordinates, {
                        color: ZONE_COLOR,
                        weight: 2,
                        fillColor: ZONE_COLOR,
                        fillOpacity: 0.15,
                        interactive: false,
                    }).bindTooltip(zone.name, { permanent: true, direction: 'center' }).addTo(map);
                    bounds.extend(polygon.getBounds());
                });
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

                    // Tapping/clicking anywhere on the map moves the pin
                    // straight there, rather than requiring the user to
                    // first grab it from wherever it currently sits and
                    // drag it - dragging still works too, for fine
                    // adjustment once it's roughly in place.
                    map.on('click', (e) => {
                        marker.setLatLng(e.latlng);
                        onDragEnd?.(e.latlng.lat, e.latlng.lng);
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

    if (!hasPin && !propertyBoundary && !hasZones) return null;

    return <div ref={mapRef} style={{ height }} />;
}
