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
 * Draw mode is armed automatically as soon as the map loads, rather than
 * behind a toolbar button the user has to find first (real user testing
 * showed the button - Geoman's stock circle-tool icon - wasn't recognised
 * as a starting point at all). A translucent pin follows the cursor before
 * the first click as a hint of what clicking does; on touch devices, where
 * there's no hover to show it, tapping still works immediately since draw
 * mode is already active.
 *
 * Once a boundary exists (from here or from Shape.jsx), this renders a
 * static preview instead of the draw tool, so it can never accidentally
 * clobber a boundary someone has since carefully refined in the full editor.
 */
export default function PropertyBoundaryPicker({ property, onSave }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const squareLayer = useRef(null);
    const hoverMarker = useRef(null);
    const startDrawingRef = useRef(null);
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

            // fadeAnimation off - its tile fade-in relies on requestAnimationFrame,
            // which stalls if the tab loses focus/visibility mid-fade, leaving tiles
            // (and everything else) permanently stuck at opacity 0.
            const map = L.map(mapRef.current, { fadeAnimation: false });
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            // Geolocation is async and can take up to its 10s timeout to
            // resolve either way - the map needs *a* view synchronously
            // before that, both so it isn't blank the whole time and
            // because Geoman's enableDraw() below calls getCenter()
            // internally and throws ("Set map center and zoom first") on a
            // map with no view yet. Geolocation, if it resolves, refines
            // this immediately after.
            map.setView(FALLBACK_CENTER, FALLBACK_ZOOM);

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        if (cancelled) return;
                        const { latitude, longitude } = position.coords;
                        map.fitBounds(L.latLng(latitude, longitude).toBounds(20000));
                    },
                    () => {},
                    { timeout: 10000, enableHighAccuracy: true }
                );
            }

            // A translucent pin that tracks the cursor - shows what clicking
            // will do before the user commits to it. Desktop-only (no hover
            // on touch), which is fine: draw mode is already armed below, so
            // a tap places the boundary immediately either way.
            map.on('mousemove', (e) => {
                if (squareLayer.current) return;
                if (hoverMarker.current) {
                    hoverMarker.current.setLatLng(e.latlng);
                } else {
                    hoverMarker.current = L.marker(e.latlng, {
                        opacity: 0.6,
                        interactive: false,
                        keyboard: false,
                    }).addTo(map);
                }
            });
            map.on('mouseout', () => {
                hoverMarker.current?.remove();
                hoverMarker.current = null;
            });

            const startDrawing = () => {
                // cursorMarker: false - Geoman shows its own small dot at the
                // cursor during an active draw mode by default, which would
                // otherwise sit right on top of (and visually compete with)
                // our own pin below.
                map.pm.enableDraw('Circle', { continueDrawing: false, tooltips: false, cursorMarker: false });
            };
            startDrawingRef.current = startDrawing;

            map.on('pm:drawstart', () => {
                hoverMarker.current?.remove();
                hoverMarker.current = null;
            });

            map.on('pm:create', (e) => {
                if (e.shape !== 'Circle') return;

                const center = e.layer.getLatLng();
                const radius = e.layer.getRadius();
                e.layer.remove();
                map.pm.disableDraw();

                const coordinates = squareFromCircle(center.lat, center.lng, radius);

                // Removing the old square (if the user started over) also
                // tears down its rotate handle - Geoman disables rotate mode
                // automatically on layer removal.
                if (squareLayer.current) squareLayer.current.remove();
                squareLayer.current = L.polygon(coordinates, {
                    color: BOUNDARY_COLOR,
                    fillColor: BOUNDARY_COLOR,
                    fillOpacity: 0.15,
                }).addTo(map);
                squareLayer.current.pm.enableRotate();

                setHasDrawn(true);
            });

            startDrawing();
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
        onSave(coordinates, () => setSaving(false));
    };

    const startOver = () => {
        if (squareLayer.current) {
            squareLayer.current.remove();
            squareLayer.current = null;
        }
        setHasDrawn(false);
        startDrawingRef.current?.();
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
                    <span className="text-gray-500">Boundary is set - you can refine it later on the Map page</span>
                </div>
            </div>
        );
    }

    return (
        <div className="mb-6">
            <h2 className="text-base font-semibold text-gray-900">Locate Your Property</h2>
            <p className="text-sm text-gray-700 mt-0.5 mb-2">
                {hasDrawn
                    ? (saving ? 'Saving…' : 'Drag the handle to rotate the square, then save.')
                    : 'Tap where your property is, then drag outward to size the boundary.'}
            </p>
            <div className="rounded-lg border border-gray-200 overflow-hidden">
                <div ref={mapRef} style={{ height: '280px' }} />
            </div>
            <div className="mt-1 flex items-center justify-between gap-3">
                <p className="text-xs text-gray-500">
                    You can refine the exact shape anytime from the Map page.
                </p>
                {hasDrawn && (
                    <div className="flex items-center gap-3 shrink-0">
                        <button
                            type="button"
                            onClick={startOver}
                            className="text-sm text-gray-500 hover:text-gray-700"
                        >
                            Start over
                        </button>
                        <button
                            type="button"
                            onClick={saveBoundary}
                            disabled={saving}
                            className="px-3 py-1 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                        >
                            Save boundary
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
