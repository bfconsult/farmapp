import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const ZONE_COLOR = '#7c3aed';
const ASSET_COLOR = '#2563eb';
// Below this zoom level, zone/asset name labels are hidden (but the outlines
// stay visible) - at wider zooms the fixed-size permanent tooltips overlap
// each other and obscure the shapes they're meant to label.
const ZONE_LABEL_MIN_ZOOM = 16;

export default function Map({ jobs, shape, zones, assets, currentRole }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const zoneLayerGroup = useRef(null);
    const assetLayerGroup = useRef(null);
    const updateZoneLabels = useRef(null);
    const updateAssetLabels = useRef(null);
    const [showZones, setShowZones] = useState(false);
    const [showAssets, setShowAssets] = useState(false);
    const [mapReady, setMapReady] = useState(false);
    const { currentProperty } = usePage().props;
    const isAdminOrManager = currentRole === 'admin' || currentRole === 'manager';

    useEffect(() => {
        if (mapInstance.current) return;

        import('leaflet').then(async (L) => {
            await import('leaflet/dist/leaflet.css');
            // Fix default marker icons
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            });

            // Default center — will be overridden by job locations or user location
            let center = [-37.8136, 144.9631];
            let zoom = 13;

            // If we have jobs with locations, center on them
            const jobsWithLocation = jobs.filter(j => j.latitude && j.longitude);
            if (jobsWithLocation.length > 0) {
                center = [jobsWithLocation[0].latitude, jobsWithLocation[0].longitude];
            }

            const map = L.map(mapRef.current).setView(center, zoom);
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            // Add job markers
            jobsWithLocation.forEach((job) => {
                const marker = L.marker([job.latitude, job.longitude]).addTo(map);
                marker.bindPopup(`
                    <div style="min-width:150px">
                        <strong>${job.name}</strong><br/>
                        ${job.job_status ? `<span>${job.job_status.name}</span><br/>` : ''}
                        <a href="/jobs/${job.id}" style="color:#16a34a">View job →</a>
                    </div>
                `);
            });

            // Draw property boundary and non-working zone, and fit to
            // whichever of them exist.
            const boundsTargets = [];

            if (shape?.coordinates) {
                const boundary = L.polygon(shape.coordinates, {
                    color: '#ca8a04',
                    weight: 2,
                    dashArray: '6, 6',
                    fillColor: '#fef08a',
                    fillOpacity: 0.15,
                }).addTo(map);
                boundsTargets.push(boundary.getBounds());
            }

            if (currentProperty?.non_working_zone_center_lat) {
                const zone = L.circle(
                    [
                        currentProperty.non_working_zone_center_lat,
                        currentProperty.non_working_zone_center_lng,
                    ],
                    {
                        radius: Number(currentProperty.non_working_zone_radius_meters),
                        color: '#f59e0b',
                        weight: 2,
                        fillColor: '#f59e0b',
                        fillOpacity: 0.2,
                    },
                ).addTo(map);
                boundsTargets.push(zone.getBounds());
            }

            // Named zones (paddocks) - built up front but left off the map
            // until the "Show zones" toggle is switched on (see the
            // visibility-sync effect below), so they don't clutter the
            // default view or affect the initial fitBounds.
            zoneLayerGroup.current = L.layerGroup(
                (zones ?? []).map((zone) =>
                    L.polygon(zone.coordinates, {
                        color: ZONE_COLOR,
                        weight: 2,
                        fillColor: ZONE_COLOR,
                        fillOpacity: 0.15,
                    }).bindTooltip(zone.name, { permanent: true, direction: 'center' }),
                ),
            );

            updateZoneLabels.current = () => {
                const group = zoneLayerGroup.current;
                if (!group || !map.hasLayer(group)) return;
                const showLabels = map.getZoom() >= ZONE_LABEL_MIN_ZOOM;
                group.eachLayer((layer) => {
                    if (showLabels) layer.openTooltip();
                    else layer.closeTooltip();
                });
            };
            map.on('zoomend', updateZoneLabels.current);

            // Assets - point ones as markers, shape ones as polygons, same
            // built-up-front/toggle-visibility pattern as zones above. Assets
            // with neither set (location is optional) are left off entirely.
            assetLayerGroup.current = L.layerGroup(
                (assets ?? [])
                    .filter((asset) => asset.shape || (asset.latitude && asset.longitude))
                    .map((asset) => {
                    if (asset.shape) {
                        return L.polygon(asset.shape, {
                            color: ASSET_COLOR,
                            weight: 2,
                            fillColor: ASSET_COLOR,
                            fillOpacity: 0.15,
                        }).bindTooltip(asset.name, { permanent: true, direction: 'center' });
                    }
                    return L.marker([asset.latitude, asset.longitude]).bindPopup(`
                        <div style="min-width:150px">
                            <strong>${asset.name}</strong><br/>
                            ${asset.asset_type ? `<span>${asset.asset_type.name}</span><br/>` : ''}
                            <a href="/assets/${asset.id}" style="color:#2563eb">View asset →</a>
                        </div>
                    `);
                }),
            );

            updateAssetLabels.current = () => {
                const group = assetLayerGroup.current;
                if (!group || !map.hasLayer(group)) return;
                const showLabels = map.getZoom() >= ZONE_LABEL_MIN_ZOOM;
                group.eachLayer((layer) => {
                    if (layer.getTooltip?.()) {
                        if (showLabels) layer.openTooltip();
                        else layer.closeTooltip();
                    }
                });
            };
            map.on('zoomend', updateAssetLabels.current);

            if (boundsTargets.length > 0) {
                const combined = boundsTargets.reduce(
                    (acc, b) => (acc ? acc.extend(b) : L.latLngBounds(b.getSouthWest(), b.getNorthEast())),
                    null,
                );
                map.fitBounds(combined, { padding: [40, 40] });
            } else if (jobsWithLocation.length > 1) {
                // No boundary — fit to job markers
                map.fitBounds(
                    L.latLngBounds(jobsWithLocation.map(j => [j.latitude, j.longitude])),
                    { padding: [40, 40] }
                );
            } else {
                // No boundary, no multiple jobs — try geolocation
                map.locate({ setView: true, maxZoom: 16 });
            }

            setMapReady(true);
        });

        return () => {
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
    }, []);

    useEffect(() => {
        if (!mapReady) return;

        if (showZones) {
            zoneLayerGroup.current.addTo(mapInstance.current);
            updateZoneLabels.current?.();
        } else {
            zoneLayerGroup.current.remove();
        }
    }, [showZones, mapReady]);

    useEffect(() => {
        if (!mapReady) return;

        if (showAssets) {
            assetLayerGroup.current.addTo(mapInstance.current);
            updateAssetLabels.current?.();
        } else {
            assetLayerGroup.current.remove();
        }
    }, [showAssets, mapReady]);

    return (
        <AuthenticatedLayout>
            <Head title="Map" />

            <div className="-mx-4">
                {currentProperty && !shape && isAdminOrManager && (
                    <div className="bg-green-50 border-b border-green-200 px-4 py-2 text-sm text-green-800 flex items-center justify-between">
                        <span>No boundary set for this property.</span>
                        <Link
                            href={route('shape.edit', currentProperty.id)}
                            className="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700"
                        >
                            Create boundary
                        </Link>
                    </div>
                )}
                {jobs.filter(j => j.latitude && j.longitude).length === 0 && (
                    <div className="bg-yellow-50 border-b border-yellow-200 px-4 py-2 text-sm text-yellow-800">
                        No jobs with location data yet. Add jobs in the field to see them on the map.
                    </div>
                )}
                <div className="relative">
                    {((zones && zones.length > 0) || (assets && assets.length > 0)) && (
                        <div className="absolute right-4 top-4 z-[1000] flex flex-col gap-2">
                            {zones && zones.length > 0 && (
                                <div className="flex items-center gap-2 rounded-md bg-white px-3 py-2 shadow-md">
                                    <span className="text-sm font-medium text-gray-700">
                                        Zones
                                    </span>
                                    <button
                                        type="button"
                                        role="switch"
                                        aria-checked={showZones}
                                        onClick={() => setShowZones((v) => !v)}
                                        className={`relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors ${
                                            showZones ? 'bg-violet-600' : 'bg-gray-300'
                                        }`}
                                    >
                                        <span
                                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                showZones ? 'translate-x-6' : 'translate-x-1'
                                            }`}
                                        />
                                    </button>
                                </div>
                            )}
                            {assets && assets.length > 0 && (
                                <div className="flex items-center gap-2 rounded-md bg-white px-3 py-2 shadow-md">
                                    <span className="text-sm font-medium text-gray-700">
                                        Assets
                                    </span>
                                    <button
                                        type="button"
                                        role="switch"
                                        aria-checked={showAssets}
                                        onClick={() => setShowAssets((v) => !v)}
                                        className={`relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors ${
                                            showAssets ? 'bg-blue-600' : 'bg-gray-300'
                                        }`}
                                    >
                                        <span
                                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                showAssets ? 'translate-x-6' : 'translate-x-1'
                                            }`}
                                        />
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                    <div
                        ref={mapRef}
                        style={{ height: 'calc(100dvh - 8.5rem)' }}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}