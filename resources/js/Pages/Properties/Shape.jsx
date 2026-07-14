import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import HelpTip from '@/Components/HelpTip';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function Shape({ property, shape }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const polygonLayer = useRef(null);
    const circleLayer = useRef(null);
    const [hasShape, setHasShape] = useState(!!shape);
    const [hasZone, setHasZone] = useState(
        !!property.non_working_zone_center_lat,
    );
    const [savingShape, setSavingShape] = useState(false);
    const [savingZone, setSavingZone] = useState(false);

    useEffect(() => {
        if (mapInstance.current) return;

        Promise.all([
            import('leaflet'),
            import('@geoman-io/leaflet-geoman-free'),
            import('leaflet/dist/leaflet.css'),
            import('@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css'),
        ]).then(([L]) => {
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl:
                    'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                iconUrl:
                    'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl:
                    'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            });

            const map = L.map(mapRef.current).setView([-37.8136, 144.9631], 15);
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            // Load existing boundary
            if (shape?.coordinates) {
                const polygon = L.polygon(shape.coordinates, {
                    color: '#16a34a',
                    fillColor: '#16a34a',
                    fillOpacity: 0.15,
                }).addTo(map);
                polygonLayer.current = polygon;
            }

            // Load existing non-working zone
            if (property.non_working_zone_center_lat) {
                const circle = L.circle(
                    [
                        property.non_working_zone_center_lat,
                        property.non_working_zone_center_lng,
                    ],
                    {
                        radius: property.non_working_zone_radius_meters,
                        color: '#f59e0b',
                        fillColor: '#f59e0b',
                        fillOpacity: 0.2,
                    },
                ).addTo(map);
                circleLayer.current = circle;
            }

            const existingBounds = [polygonLayer.current, circleLayer.current]
                .filter(Boolean)
                .map(
                    (layer) =>
                        layer.getBounds?.() ??
                        layer.getLatLng().toBounds(layer.getRadius() * 2),
                );
            if (existingBounds.length > 0) {
                const combined = existingBounds.reduce(
                    (acc, b) =>
                        acc
                            ? acc.extend(b)
                            : L.latLngBounds(
                                  b.getSouthWest(),
                                  b.getNorthEast(),
                              ),
                    null,
                );
                map.fitBounds(combined, { padding: [40, 40] });
            } else {
                map.locate({ setView: true, maxZoom: 16 });
            }

            // Enable geoman draw controls — polygon (boundary) and circle
            // (non-working zone), nothing else.
            map.pm.addControls({
                position: 'topleft',
                drawMarker: false,
                drawCircleMarker: false,
                drawPolyline: false,
                drawRectangle: false,
                drawText: false,
                editMode: true,
                dragMode: false,
                cutPolygon: false,
                removalMode: false,
            });

            map.on('pm:create', (e) => {
                if (e.shape === 'Circle') {
                    if (circleLayer.current) circleLayer.current.remove();
                    circleLayer.current = e.layer;
                    setHasZone(true);
                } else {
                    if (polygonLayer.current) polygonLayer.current.remove();
                    polygonLayer.current = e.layer;
                    setHasShape(true);
                }
                map.pm.disableDraw();
            });
        });

        return () => {
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
    }, []);

    const saveShape = () => {
        if (!polygonLayer.current) return;

        const latlngs = polygonLayer.current
            .getLatLngs()[0]
            .map((ll) => [ll.lat, ll.lng]);
        setSavingShape(true);

        router.put(
            route('shape.update', property.id),
            { coordinates: latlngs },
            {
                preserveScroll: true,
                onFinish: () => setSavingShape(false),
            },
        );
    };

    const removeZone = () => {
        if (!confirm('Remove the non-working zone for this property?')) return;

        router.delete(route('non-working-zone.destroy', property.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (circleLayer.current) {
                    circleLayer.current.remove();
                    circleLayer.current = null;
                }
                setHasZone(false);
            },
        });
    };

    const saveZone = () => {
        if (!circleLayer.current) return;

        const center = circleLayer.current.getLatLng();
        const radius = circleLayer.current.getRadius();
        setSavingZone(true);

        router.put(
            route('non-working-zone.update', property.id),
            {
                center_lat: center.lat,
                center_lng: center.lng,
                radius_meters: Math.round(radius),
            },
            { preserveScroll: true, onFinish: () => setSavingZone(false) },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title={`${property.name} — Boundary`} />

            {/* Colour the Geoman draw-tool icons to match the boundary (green)
                and non-working zone (amber) colours used everywhere else on
                this page, so the link between tool and purpose is obvious
                without relying on the hover tooltip. */}
            <style>{`
                .leaflet-buttons-control-button:has(.leaflet-pm-icon-polygon) {
                    background-color: #16a34a;
                }
                .leaflet-buttons-control-button:has(.leaflet-pm-icon-polygon) .leaflet-pm-icon-polygon {
                    filter: brightness(0) invert(1);
                }
                .leaflet-buttons-control-button:has(.leaflet-pm-icon-circle) {
                    background-color: #f59e0b;
                }
                .leaflet-buttons-control-button:has(.leaflet-pm-icon-circle) .leaflet-pm-icon-circle {
                    filter: brightness(0) invert(1);
                }
            `}</style>

            <div className="-mx-4">
                {/* Toolbar */}
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 bg-white px-4 py-2">
                    <div className="flex min-w-0 flex-1 items-center gap-1">
                        <span className="truncate text-sm font-medium text-gray-700">
                            {property.name} — boundary &amp; non-working zone
                        </span>
                        <HelpTip messageKey="properties.shape" />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            onClick={() =>
                                router.visit(
                                    route('properties.show', property.id),
                                )
                            }
                            className="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900"
                        >
                            Done
                        </button>
                        {hasZone && (
                            <button
                                onClick={removeZone}
                                className="px-3 py-1.5 text-sm text-red-600 hover:text-red-800"
                            >
                                Remove zone
                            </button>
                        )}
                        <button
                            onClick={saveZone}
                            disabled={!hasZone || savingZone}
                            className="rounded-md bg-amber-500 px-3 py-1.5 text-sm text-white hover:bg-amber-600 disabled:opacity-50"
                        >
                            {savingZone ? 'Saving…' : 'Save zone'}
                        </button>
                        <button
                            onClick={saveShape}
                            disabled={!hasShape || savingShape}
                            className="rounded-md bg-green-600 px-3 py-1.5 text-sm text-white hover:bg-green-700 disabled:opacity-50"
                        >
                            {savingShape ? 'Saving…' : 'Save boundary'}
                        </button>
                    </div>
                </div>

                <div ref={mapRef} style={{ height: 'calc(100dvh - 7rem)' }} />
            </div>
        </AuthenticatedLayout>
    );
}
