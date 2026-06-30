import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export default function Map({ jobs }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const { currentProperty } = usePage().props;

    useEffect(() => {
        if (mapInstance.current) return;

        import('leaflet').then((L) => {
            import('leaflet/dist/leaflet.css');

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

            // Fit map to markers if we have multiple
            if (jobsWithLocation.length > 1) {
                const bounds = L.latLngBounds(
                    jobsWithLocation.map(j => [j.latitude, j.longitude])
                );
                map.fitBounds(bounds, { padding: [40, 40] });
            }

            // Try to get user's current location
            map.locate({ setView: jobsWithLocation.length === 0, maxZoom: 16 });
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

            <div className="-mx-4 -mt-4">
                {jobs.filter(j => j.latitude && j.longitude).length === 0 && (
                    <div className="bg-yellow-50 border-b border-yellow-200 px-4 py-2 text-sm text-yellow-800">
                        No jobs with location data yet. Add jobs in the field to see them on the map.
                    </div>
                )}
                <div
                    ref={mapRef}
                    style={{ height: 'calc(100vh - 8rem)' }}
                />
            </div>
        </AuthenticatedLayout>
    );
}