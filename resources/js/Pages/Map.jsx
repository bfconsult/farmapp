import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export default function Map({ jobs, shape, currentRole }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
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
        });

        return () => {
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
    }, []);

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
                <div
                    ref={mapRef}
                    style={{ height: 'calc(100dvh - 8.5rem)' }}
                />
            </div>
        </AuthenticatedLayout>
    );
}