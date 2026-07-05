import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function Shape({ property, shape }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const drawnLayer = useRef(null);
    const [hasShape, setHasShape] = useState(!!shape);
    const [saving, setSaving] = useState(false);

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
                iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            });

            const map = L.map(mapRef.current).setView([-37.8136, 144.9631], 15);
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            // Load existing shape
            if (shape?.coordinates) {
                const polygon = L.polygon(shape.coordinates, {
                    color: '#16a34a',
                    fillColor: '#16a34a',
                    fillOpacity: 0.15,
                }).addTo(map);
                drawnLayer.current = polygon;
                map.fitBounds(polygon.getBounds(), { padding: [40, 40] });
            } else {
                map.locate({ setView: true, maxZoom: 16 });
            }

            // Enable geoman draw controls — polygon only
            map.pm.addControls({
                position: 'topleft',
                drawMarker: false,
                drawCircleMarker: false,
                drawPolyline: false,
                drawRectangle: false,
                drawCircle: false,
                drawText: false,
                editMode: true,
                dragMode: false,
                cutPolygon: false,
                removalMode: false,
            });

            map.on('pm:create', (e) => {
                // Remove previous shape if one existed
                if (drawnLayer.current) {
                    drawnLayer.current.remove();
                }
                drawnLayer.current = e.layer;
                setHasShape(true);

                // Disable draw mode after first polygon
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

    const save = () => {
        if (!drawnLayer.current) return;

        const latlngs = drawnLayer.current.getLatLngs()[0].map((ll) => [ll.lat, ll.lng]);
        setSaving(true);

        router.put(route('shape.update', property.id), { coordinates: latlngs }, {
            onFinish: () => setSaving(false),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={`${property.name} — Boundary`} />

            <div className="-mx-4 -mt-4">
                {/* Toolbar */}
                <div className="flex items-center justify-between px-4 py-2 bg-white border-b border-gray-200">
                    <span className="text-sm font-medium text-gray-700">
                        {shape ? 'Edit boundary' : 'Draw boundary'} — {property.name}
                    </span>
                    <div className="flex gap-2">
                        <button
                            onClick={() => router.visit(route('properties.show', property.id))}
                            className="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={save}
                            disabled={!hasShape || saving}
                            className="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                        >
                            {saving ? 'Saving…' : 'Save boundary'}
                        </button>
                    </div>
                </div>

                <p className="px-4 py-2 text-xs text-gray-500 bg-gray-50 border-b border-gray-100">
                    Use the polygon tool on the left to draw your property boundary. Click each corner, then click the first point to close the shape.
                </p>

                <div ref={mapRef} style={{ height: 'calc(100dvh - 10rem)' }} />
            </div>
        </AuthenticatedLayout>
    );
}
