import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import HelpTip from '@/Components/HelpTip';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const ZONE_COLOR = '#7c3aed';

export default function Shape({ property, shape, zones }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const leafletRef = useRef(null);
    const polygonLayer = useRef(null);
    const circleLayer = useRef(null);
    const zoneLayers = useRef({});
    const [hasShape, setHasShape] = useState(!!shape);
    const [hasZone, setHasZone] = useState(
        !!property.non_working_zone_center_lat,
    );
    const [savingShape, setSavingShape] = useState(false);
    const [savingZone, setSavingZone] = useState(false);
    const [activeTab, setActiveTab] = useState('boundary');
    const activeTabRef = useRef(activeTab);
    const zonesRef = useRef(zones);

    useEffect(() => {
        activeTabRef.current = activeTab;
    }, [activeTab]);

    useEffect(() => {
        zonesRef.current = zones;
    }, [zones]);

    // Persists a zone's reshaping the moment the user finishes dragging a
    // vertex (pm:edit fires once per completed drag, not continuously) -
    // matches the existing "no separate Save step" convention for zones
    // (see pm:create below). Reads the name from zonesRef rather than
    // closing over it, since this listener is attached once per layer and
    // must still see a later rename.
    const attachZoneEditSave = (polygon, zoneId) => {
        polygon.on('pm:edit', (e) => {
            const latlngs = e.layer.getLatLngs()[0].map((ll) => [ll.lat, ll.lng]);
            const currentZone = zonesRef.current.find((z) => z.id === zoneId);

            router.put(
                route('zones.update', [property.id, zoneId]),
                { name: currentZone?.name, coordinates: latlngs },
                { preserveScroll: true },
            );
        });
    };

    useEffect(() => {
        if (mapInstance.current) return;

        Promise.all([
            import('leaflet'),
            import('@geoman-io/leaflet-geoman-free'),
            import('leaflet/dist/leaflet.css'),
            import('@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css'),
        ]).then(([L]) => {
            leafletRef.current = L;

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

            // Load existing named zones (paddocks etc.) - created but not
            // attached to the map yet; only shown while the Zones tab is
            // active (see the visibility-sync effect below).
            (zones ?? []).forEach((zone) => {
                const polygon = L.polygon(zone.coordinates, {
                    color: ZONE_COLOR,
                    fillColor: ZONE_COLOR,
                    fillOpacity: 0.15,
                }).bindTooltip(zone.name, { permanent: true, direction: 'center' });
                attachZoneEditSave(polygon, zone.id);
                zoneLayers.current[zone.id] = polygon;
            });

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
                // On the Zones tab, any drawn polygon is a new named zone,
                // not the property boundary - prompt for a name and save it
                // immediately (no separate "Save" step, unlike boundary/NWZ).
                if (activeTabRef.current === 'zones') {
                    const name = window.prompt('Name this zone (e.g. "North Paddock")');
                    const latlngs = e.layer.getLatLngs()[0].map((ll) => [ll.lat, ll.lng]);
                    e.layer.remove();
                    map.pm.disableDraw();

                    if (!name) return;

                    router.post(
                        route('zones.store', property.id),
                        { name, coordinates: latlngs },
                        { preserveScroll: true },
                    );
                    return;
                }

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

    // Reconciles the map's zone layers with the `zones` prop whenever it
    // changes (after a create/rename/delete round-trip) - a no-op on first
    // mount, since the map isn't ready yet at that point (handled by the
    // initial load above instead).
    useEffect(() => {
        const map = mapInstance.current;
        const L = leafletRef.current;
        if (!map || !L) return;

        const currentIds = new Set((zones ?? []).map((z) => z.id));

        Object.keys(zoneLayers.current).forEach((id) => {
            if (!currentIds.has(Number(id))) {
                zoneLayers.current[id].remove();
                delete zoneLayers.current[id];
            }
        });

        (zones ?? []).forEach((zone) => {
            const existing = zoneLayers.current[zone.id];
            if (existing) {
                existing.setLatLngs(zone.coordinates);
                existing.setTooltipContent(zone.name);
                return;
            }
            const polygon = L.polygon(zone.coordinates, {
                color: ZONE_COLOR,
                fillColor: ZONE_COLOR,
                fillOpacity: 0.15,
            }).bindTooltip(zone.name, { permanent: true, direction: 'center' });
            attachZoneEditSave(polygon, zone.id);
            if (activeTabRef.current === 'zones') polygon.addTo(map);
            zoneLayers.current[zone.id] = polygon;
        });
    }, [zones]);

    // Swap which geoman draw tools are offered per tab - circle only makes
    // sense for the non-working zone (Boundary tab); Zones only ever draws
    // polygons. Edit Mode is available on both tabs (see attachZoneEditSave
    // for how a reshaped zone gets persisted). Zone shapes themselves are
    // also only shown on the map while the Zones tab is active, kept off
    // the Boundary view entirely.
    useEffect(() => {
        const map = mapInstance.current;
        if (!map) return;

        map.pm.removeControls();
        map.pm.addControls({
            position: 'topleft',
            drawMarker: false,
            drawCircleMarker: false,
            drawPolyline: false,
            drawRectangle: false,
            drawText: false,
            drawPolygon: true,
            drawCircle: activeTab === 'boundary',
            editMode: true,
            dragMode: false,
            cutPolygon: false,
            removalMode: false,
        });

        Object.values(zoneLayers.current).forEach((layer) => {
            if (activeTab === 'zones') {
                if (!map.hasLayer(layer)) layer.addTo(map);
            } else if (map.hasLayer(layer)) {
                layer.remove();
            }
        });

        // The map container's height changes between tabs (the Zones list
        // takes up extra vertical space) - Leaflet needs an explicit nudge
        // to recalculate its size after that CSS change, or tiles render
        // into the old (wrong) dimensions.
        setTimeout(() => map.invalidateSize(), 0);
    }, [activeTab]);

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

    const renameZoneItem = (zone) => {
        const name = window.prompt('Rename zone', zone.name);
        if (!name || name === zone.name) return;

        router.put(
            route('zones.update', [property.id, zone.id]),
            { name, coordinates: zone.coordinates },
            { preserveScroll: true },
        );
    };

    const deleteZoneItem = (zone) => {
        if (!confirm(`Delete the zone "${zone.name}"?`)) return;

        router.delete(route('zones.destroy', [property.id, zone.id]), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={`${property.name} — Boundary`} />

            {/* Colour the Geoman draw-tool icons to match the boundary
                (green), non-working zone (amber), and zone (violet, only
                while the Zones tab is active) colours used on this page, so
                the link between tool and purpose is obvious without relying
                on the hover tooltip. */}
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
                .zones-tab-active .leaflet-buttons-control-button:has(.leaflet-pm-icon-polygon) {
                    background-color: ${ZONE_COLOR};
                }
            `}</style>

            <div className="-mx-4">
                {/* Toolbar */}
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 bg-white px-4 py-2">
                    <div className="flex min-w-0 flex-1 items-center gap-1">
                        <span className="truncate text-sm font-medium text-gray-700">
                            {property.name} — boundary &amp; zones
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
                        {activeTab === 'boundary' && hasZone && (
                            <button
                                onClick={removeZone}
                                className="px-3 py-1.5 text-sm text-red-600 hover:text-red-800"
                            >
                                Remove zone
                            </button>
                        )}
                        {activeTab === 'boundary' && (
                            <>
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
                            </>
                        )}
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex items-center gap-2 border-b border-gray-200 bg-white px-4 py-2">
                    <button
                        onClick={() => setActiveTab('boundary')}
                        className={`rounded-full px-3 py-1 text-sm font-medium ${
                            activeTab === 'boundary'
                                ? 'bg-gray-900 text-white'
                                : 'bg-gray-100 text-gray-600'
                        }`}
                    >
                        Boundary
                    </button>
                    <button
                        onClick={() => setActiveTab('zones')}
                        className={`rounded-full px-3 py-1 text-sm font-medium ${
                            activeTab === 'zones'
                                ? 'bg-gray-900 text-white'
                                : 'bg-gray-100 text-gray-600'
                        }`}
                    >
                        Zones{zones && zones.length > 0 ? ` (${zones.length})` : ''}
                    </button>
                </div>

                {activeTab === 'zones' && (
                    <div className="border-b border-gray-200 bg-white px-4 py-3">
                        <p className="text-xs text-gray-500 mb-2">
                            Use the polygon tool to draw a paddock or other named
                            area - you'll be asked to name it as soon as you finish
                            drawing. Each zone saves immediately. To reshape an
                            existing zone, turn on Edit Mode (top-left) and drag
                            its points - changes save as soon as you drop a point.
                        </p>
                        {(!zones || zones.length === 0) ? (
                            <p className="text-sm text-gray-500">No zones yet.</p>
                        ) : (
                            <div className="max-h-40 space-y-2 overflow-y-auto">
                                {zones.map((zone) => (
                                    <div
                                        key={zone.id}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span className="text-sm text-gray-900 truncate">
                                            {zone.name}
                                        </span>
                                        <div className="flex flex-shrink-0 items-center gap-3">
                                            <button
                                                onClick={() => renameZoneItem(zone)}
                                                className="text-xs text-violet-600"
                                            >
                                                Rename
                                            </button>
                                            <button
                                                onClick={() => deleteZoneItem(zone)}
                                                className="text-xs text-red-600"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                <div className={activeTab === 'zones' ? 'zones-tab-active' : ''}>
                    <div
                        ref={mapRef}
                        style={{
                            height:
                                activeTab === 'zones'
                                    ? 'calc(100dvh - 14rem)'
                                    : 'calc(100dvh - 7rem)',
                        }}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
