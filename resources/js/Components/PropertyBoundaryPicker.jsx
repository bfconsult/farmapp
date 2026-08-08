import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import LocationMap from '@/Components/LocationMap';

const BOUNDARY_COLOR = '#16a34a';

// Fallback view (Australia-wide) when geolocation fails or is denied - lets
// the user pan/zoom to find their property manually instead of being stuck
// with no map at all.
const FALLBACK_CENTER = [-25.27, 133.77];
const FALLBACK_ZOOM = 4;

/** A rough square centered on (lat, lng), radiusMeters from center to each
 * edge - turns a "drop a pin, drag out a radius" gesture (Geoman's circle
 * draw tool) into a simple starting boundary, in the same flat [lat, lng]
 * pair format Shape.coordinates is already stored/consumed in. */
function squareFromCircle(lat, lng, radiusMeters) {
    const dLat = radiusMeters / 111320;
    const dLng = radiusMeters / (111320 * Math.cos((lat * Math.PI) / 180));
    return [
        [lat + dLat, lng - dLng],
        [lat + dLat, lng + dLng],
        [lat - dLat, lng + dLng],
        [lat - dLat, lng - dLng],
    ];
}

function loadLeafletAndGeoman() {
    return Promise.all([
        import('leaflet'),
        import('@geoman-io/leaflet-geoman-free'),
        import('leaflet/dist/leaflet.css'),
        import('@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css'),
    ]).then(([L]) => L);
}

/** Reads a polygon layer's current ring back out in the flat [lat, lng]
 * pair format Shape.coordinates expects - used once the user is done
 * rotating, not on every drag frame. */
function layerToCoordinates(layer) {
    return layer.getLatLngs()[0].map((latLng) => [latLng.lat, latLng.lng]);
}

/**
 * Quick, low-friction boundary picker shown on a freshly created property
 * (see Properties/Edit.jsx) - aimed at a user who doesn't know the full
 * Shape.jsx boundary editor yet. Offers only Geoman's circle draw tool
 * (click to place a center, drag to set a radius - the same interaction
 * Shape.jsx already uses for the non-working zone), converts the result to
 * a square, and lets the user drag Geoman's rotate handle to line it up
 * with the real property before committing - unlike Shape.jsx's zones,
 * which auto-save with no separate step, this mirrors the boundary tab's
 * own explicit save (a rotate step wouldn't work with an instant save,
 * since the square would vanish into the static preview before it could
 * be dragged straight).
 *
 * Once a boundary exists (from here or from Shape.jsx), this renders a
 * static preview instead of the draw tool, so it can never accidentally
 * clobber a boundary someone has since carefully refined in the full editor.
 */
export default function PropertyBoundaryPicker({ property, onRefineClick }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const squareLayer = useRef(null);
    const [hasDrawn, setHasDrawn] = useState(false);
    const [saving, setSaving] = useState(false);

    const hasShape = !!property.shape;

    useEffect(() => {
        if (hasShape || mapInstance.current) return undefined;

        let cancelled = false;

        loadLeafletAndGeoman().then((L) => {
            if (cancelled || mapInstance.current) return;

            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            });

            const map = L.map(mapRef.current);
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        if (cancelled) return;
                        const { latitude, longitude } = position.coords;
                        map.fitBounds(L.latLng(latitude, longitude).toBounds(20000));
                    },
                    () => {
                        if (!cancelled) map.setView(FALLBACK_CENTER, FALLBACK_ZOOM);
                    },
                    { timeout: 10000, enableHighAccuracy: true }
                );
            } else {
                map.setView(FALLBACK_CENTER, FALLBACK_ZOOM);
            }

            map.pm.addControls({
                position: 'topleft',
                drawMarker: false,
                drawCircleMarker: false,
                drawPolyline: false,
                drawPolygon: false,
                drawRectangle: false,
                drawCircle: true,
                drawText: false,
                editMode: false,
                dragMode: false,
                cutPolygon: false,
                removalMode: false,
            });

            map.on('pm:create', (e) => {
                if (e.shape !== 'Circle') return;

                const center = e.layer.getLatLng();
                const radius = e.layer.getRadius();
                e.layer.remove();
                map.pm.disableDraw();

                const coordinates = squareFromCircle(center.lat, center.lng, radius);

                // Removing the old square (if the user drew a second circle
                // to start over) also tears down its rotate handle - Geoman
                // disables rotate mode automatically on layer removal.
                if (squareLayer.current) squareLayer.current.remove();
                squareLayer.current = L.polygon(coordinates, {
                    color: BOUNDARY_COLOR,
                    fillColor: BOUNDARY_COLOR,
                    fillOpacity: 0.15,
                }).addTo(map);
                squareLayer.current.pm.enableRotate();

                setHasDrawn(true);
            });
        });

        return () => {
            cancelled = true;
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [hasShape]);

    const saveBoundary = () => {
        const coordinates = layerToCoordinates(squareLayer.current);
        setSaving(true);
        router.put(route('shape.update', property.id), { coordinates }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setSaving(false),
        });
    };

    if (hasShape) {
        return (
            <div className="mb-6 rounded-lg border border-gray-200 overflow-hidden">
                <LocationMap
                    propertyBoundary={property.shape.coordinates}
                    interactive={false}
                    height="160px"
                    boundaryColor={BOUNDARY_COLOR}
                    boundaryFillColor={BOUNDARY_COLOR}
                />
                <div className="px-3 py-2 bg-white border-t border-gray-200 text-sm">
                    <span className="text-gray-500">Boundary is set — </span>
                    {onRefineClick ? (
                        <button type="button" onClick={onRefineClick} className="text-green-600">
                            refine it on the Boundary page →
                        </button>
                    ) : (
                        <Link href={route('shape.edit', property.id)} className="text-green-600">
                            refine it on the Boundary page →
                        </Link>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-1">Property boundary</label>
            <div className="rounded-lg border border-gray-200 overflow-hidden">
                <div ref={mapRef} style={{ height: '280px' }} />
            </div>
            {hasDrawn ? (
                <div className="mt-1 flex items-center justify-between gap-3">
                    <p className="text-xs text-gray-500">
                        {saving
                            ? 'Saving…'
                            : 'Drag the handle to rotate the square, then save.'}
                    </p>
                    <button
                        type="button"
                        onClick={saveBoundary}
                        disabled={saving}
                        className="shrink-0 px-3 py-1 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                    >
                        Save boundary
                    </button>
                </div>
            ) : (
                <p className="mt-1 text-xs text-gray-500">
                    Drop a pin and drag out a rough square boundary for your property
                    — you can refine the exact shape anytime from the Boundary page.
                </p>
            )}
        </div>
    );
}
