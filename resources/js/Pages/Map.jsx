import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { compressImageFiles } from '@/imageCompression';
import Modal from '@/Components/Modal';
import { formatDate } from '@/dateInput';

const ZONE_COLOR = '#7c3aed';
const ASSET_COLOR = '#2563eb';
// Below this zoom level, zone/asset name labels are hidden (but the outlines
// stay visible) - at wider zooms the fixed-size permanent tooltips overlap
// each other and obscure the shapes they're meant to label.
const ZONE_LABEL_MIN_ZOOM = 16;

/** Same pin shape/size everywhere (the default Leaflet marker), just
 * recolored per layer - a same-dimension swap of the stock icon, not a
 * different marker style. */
function pinIcon(L, color) {
    return L.icon({
        iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-${color}.png`,
        iconRetinaUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${color}.png`,
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41],
    });
}

export default function Map({
    jobs, shape, zones, assets, notes, currentRole,
    currentJobStatusActive, currentJobStatusCompleted, currentJobAge,
}) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const leafletRef = useRef(null);
    const jobLayerGroup = useRef(null);
    const zoneLayerGroup = useRef(null);
    const assetLayerGroup = useRef(null);
    const noteLayerGroup = useRef(null);
    const noteMarkersById = useRef({});
    const noteFileInput = useRef(null);
    const updateZoneLabels = useRef(null);
    const updateAssetLabels = useRef(null);
    // Jobs default to visible (this page's original, only content before the
    // other layers existed) - the rest default off since they're additions.
    const [showJobs, setShowJobs] = useState(true);
    const [showZones, setShowZones] = useState(false);
    const [showAssets, setShowAssets] = useState(false);
    const [showNotes, setShowNotes] = useState(false);
    const [showFiltersPanel, setShowFiltersPanel] = useState(false);
    const [jobStatusActive, setJobStatusActive] = useState(currentJobStatusActive);
    const [jobStatusCompleted, setJobStatusCompleted] = useState(currentJobStatusCompleted);
    const [jobAge, setJobAge] = useState(currentJobAge);
    const [mapReady, setMapReady] = useState(false);
    const [activeNote, setActiveNote] = useState(null);
    const [editingNoteBody, setEditingNoteBody] = useState(false);
    const [noteBodyDraft, setNoteBodyDraft] = useState('');
    const [uploadingNotePhoto, setUploadingNotePhoto] = useState(false);
    const [editingLocationNoteId, setEditingLocationNoteId] = useState(null);
    const [pendingNoteLatLng, setPendingNoteLatLng] = useState(null);
    const [creatingNote, setCreatingNote] = useState(false);
    const [draftNoteLatLng, setDraftNoteLatLng] = useState(null);
    const [draftNoteBody, setDraftNoteBody] = useState('');
    const { currentProperty } = usePage().props;
    const isAdminOrManager = currentRole === 'admin' || currentRole === 'manager';
    const canCreateNote = isAdminOrManager || currentRole === 'worker';
    const jobsWithLocation = jobs.filter(j => j.latitude && j.longitude);

    useEffect(() => {
        if (mapInstance.current) return;

        import('leaflet').then(async (L) => {
            await import('leaflet/dist/leaflet.css');
            leafletRef.current = L;
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
            if (jobsWithLocation.length > 0) {
                center = [jobsWithLocation[0].latitude, jobsWithLocation[0].longitude];
            }

            const map = L.map(mapRef.current).setView(center, zoom);
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

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
                    .filter((asset) => asset.current_location?.shape || (asset.current_location?.latitude && asset.current_location?.longitude))
                    .map((asset) => {
                    const location = asset.current_location;
                    if (location.shape) {
                        return L.polygon(location.shape, {
                            color: ASSET_COLOR,
                            weight: 2,
                            fillColor: ASSET_COLOR,
                            fillOpacity: 0.15,
                        }).bindTooltip(asset.name, { permanent: true, direction: 'center' });
                    }
                    return L.marker([location.latitude, location.longitude]).bindPopup(`
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

    // Job status/age are filtered server-side and re-fetched via the
    // Filters popout on this same page, so (like notes) this layer must
    // rebuild whenever the `jobs` prop changes, not just once on mount.
    useEffect(() => {
        if (!mapReady || !leafletRef.current) return;
        const L = leafletRef.current;

        jobLayerGroup.current?.remove();
        jobLayerGroup.current = L.layerGroup(
            jobsWithLocation.map((job) => {
                const marker = L.marker([job.latitude, job.longitude], { icon: pinIcon(L, 'red') });
                marker.bindPopup(`
                    <div style="min-width:150px">
                        <strong>${job.name}</strong><br/>
                        ${job.job_status ? `<span>${job.job_status.name}</span><br/>` : ''}
                        <a href="/jobs/${job.id}" style="color:#16a34a">View job →</a>
                    </div>
                `);
                return marker;
            }),
        );

        if (showJobs) jobLayerGroup.current.addTo(mapInstance.current);
    }, [jobs, mapReady]);

    useEffect(() => {
        if (!mapReady) return;

        if (showJobs) jobLayerGroup.current?.addTo(mapInstance.current);
        else jobLayerGroup.current?.remove();
    }, [showJobs, mapReady]);

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

    // Notes are created/edited/deleted from this same page - an Inertia
    // back() redirect re-renders this same mounted component rather than
    // remounting it, so (unlike zones/assets, which only ever change from a
    // different page) this layer must rebuild from scratch whenever the
    // `notes` prop changes, not just once on mount.
    useEffect(() => {
        if (!mapReady || !leafletRef.current) return;
        const L = leafletRef.current;

        noteLayerGroup.current?.remove();
        noteMarkersById.current = {};

        noteLayerGroup.current = L.layerGroup(
            (notes ?? []).map((note) => {
                const marker = L.marker([note.latitude, note.longitude], { icon: pinIcon(L, 'green') });
                // Opens a read-only view only - editing/dragging never starts
                // from tapping the map itself, only from an explicit button.
                marker.on('click', () => setActiveNote(note));
                noteMarkersById.current[note.id] = marker;
                return marker;
            }),
        );

        if (showNotes) noteLayerGroup.current.addTo(mapInstance.current);
    }, [notes, mapReady]);

    useEffect(() => {
        if (!mapReady) return;

        if (showNotes) noteLayerGroup.current?.addTo(mapInstance.current);
        else noteLayerGroup.current?.remove();
    }, [showNotes, mapReady]);

    // Keeps the open view modal in sync after a photo upload / body edit
    // triggers a partial reload of the `notes` prop.
    useEffect(() => {
        if (!activeNote) return;
        setActiveNote((notes ?? []).find((n) => n.id === activeNote.id) ?? null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [notes]);

    // "+ Add Note" draft marker - a single draggable pin shown while creating.
    useEffect(() => {
        if (!mapReady || !leafletRef.current || !creatingNote || !draftNoteLatLng) return;
        const L = leafletRef.current;

        const marker = L.marker([draftNoteLatLng.lat, draftNoteLatLng.lng], { draggable: true, icon: pinIcon(L, 'green') })
            .addTo(mapInstance.current);

        marker.on('dragend', () => {
            const { lat, lng } = marker.getLatLng();
            setDraftNoteLatLng({ lat, lng });
        });

        return () => marker.remove();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [creatingNote, mapReady]);

    // "Edit location" draft marker - swaps a note's static marker for a
    // single draggable overlay while repositioning it.
    useEffect(() => {
        if (!mapReady || !leafletRef.current || !editingLocationNoteId || !pendingNoteLatLng) return;
        const L = leafletRef.current;
        const group = noteLayerGroup.current;
        const staticMarker = noteMarkersById.current[editingLocationNoteId];

        if (staticMarker && group) group.removeLayer(staticMarker);

        const overlay = L.marker([pendingNoteLatLng.lat, pendingNoteLatLng.lng], { draggable: true, icon: pinIcon(L, 'green') })
            .addTo(mapInstance.current);

        overlay.on('dragend', () => {
            const { lat, lng } = overlay.getLatLng();
            setPendingNoteLatLng({ lat, lng });
        });

        return () => {
            overlay.remove();
            // If this edit was cancelled (not saved), put the static marker
            // back. A save instead refreshes `notes`, which rebuilds the
            // whole layer group fresh, superseding this.
            if (staticMarker && group) group.addLayer(staticMarker);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editingLocationNoteId, mapReady]);

    // Job status/age are filtered server-side (unlike the other layers,
    // which are just client-side show/hide over data already loaded) since
    // the whole point is to stop ever-growing old job pins from being sent
    // to the browser at all, not just hidden there.
    const updateJobFilters = (overrides = {}) => {
        router.get(route('map'), {
            job_status_active: overrides.jobStatusActive ?? jobStatusActive,
            job_status_completed: overrides.jobStatusCompleted ?? jobStatusCompleted,
            job_age: overrides.jobAge ?? jobAge,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['jobs', 'currentJobStatusActive', 'currentJobStatusCompleted', 'currentJobAge'],
        });
    };

    const toggleJobStatusActive = () => {
        const next = !jobStatusActive;
        setJobStatusActive(next);
        updateJobFilters({ jobStatusActive: next });
    };

    const toggleJobStatusCompleted = () => {
        const next = !jobStatusCompleted;
        setJobStatusCompleted(next);
        updateJobFilters({ jobStatusCompleted: next });
    };

    const changeJobAge = (value) => {
        setJobAge(value);
        updateJobFilters({ jobAge: value });
    };

    const hasNonDefaultFilters = !jobStatusActive || jobStatusCompleted || jobAge !== 'all';

    const startAddingNote = () => {
        const place = (lat, lng) => {
            setDraftNoteLatLng({ lat, lng });
            setCreatingNote(true);
        };

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => place(position.coords.latitude, position.coords.longitude),
                () => {
                    const center = mapInstance.current.getCenter();
                    place(center.lat, center.lng);
                },
                { timeout: 10000, enableHighAccuracy: true },
            );
        } else {
            const center = mapInstance.current.getCenter();
            place(center.lat, center.lng);
        }
    };

    const saveNewNote = () => {
        router.post(route('notes.store'), {
            body: draftNoteBody,
            latitude: draftNoteLatLng.lat,
            longitude: draftNoteLatLng.lng,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setCreatingNote(false);
                setDraftNoteLatLng(null);
                setDraftNoteBody('');
                setShowNotes(true);
            },
        });
    };

    const cancelNewNote = () => {
        setCreatingNote(false);
        setDraftNoteLatLng(null);
        setDraftNoteBody('');
    };

    const openNoteEdit = () => {
        setNoteBodyDraft(activeNote.body);
        setEditingNoteBody(true);
    };

    const saveNoteBody = () => {
        router.patch(route('notes.update', activeNote.id), { body: noteBodyDraft }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditingNoteBody(false),
        });
    };

    const deleteNote = () => {
        if (confirm('Delete this note?')) {
            router.delete(route('notes.destroy', activeNote.id), {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setActiveNote(null),
            });
        }
    };

    const startEditNoteLocation = () => {
        setPendingNoteLatLng({ lat: activeNote.latitude, lng: activeNote.longitude });
        setEditingLocationNoteId(activeNote.id);
        setActiveNote(null);
    };

    const saveNoteLocation = () => {
        router.put(route('notes.update-location', editingLocationNoteId), {
            latitude: pendingNoteLatLng.lat,
            longitude: pendingNoteLatLng.lng,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setEditingLocationNoteId(null);
                setPendingNoteLatLng(null);
            },
        });
    };

    const cancelNoteLocationEdit = () => {
        setEditingLocationNoteId(null);
        setPendingNoteLatLng(null);
    };

    const uploadNotePhotos = async (e) => {
        const files = e.target.files;
        if (!files.length) return;

        setUploadingNotePhoto(true);
        const compressed = await compressImageFiles(files);

        const formData = new FormData();
        compressed.forEach(file => formData.append('photos[]', file));

        router.post(route('photos.store-note', activeNote.id), formData, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setUploadingNotePhoto(false),
            onError: () => alert('Photo upload failed. Please try again with a smaller photo.'),
        });
    };

    const destroyNotePhoto = (photoId) => {
        if (confirm('Delete this photo?')) {
            router.delete(route('photos.destroy', photoId), { preserveScroll: true, preserveState: true });
        }
    };

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
                {jobsWithLocation.length === 0 && (
                    <div className="bg-yellow-50 border-b border-yellow-200 px-4 py-2 text-sm text-yellow-800">
                        No jobs with location data yet. Add jobs in the field to see them on the map.
                    </div>
                )}
                <div className="relative">
                    <div className="absolute right-4 top-4 z-[1000]">
                        <button
                            type="button"
                            onClick={() => setShowFiltersPanel((v) => !v)}
                            className="flex items-center gap-2 rounded-md bg-white px-3 py-2 shadow-md text-sm font-medium text-gray-700"
                        >
                            <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z" />
                            </svg>
                            Filters
                            {hasNonDefaultFilters && <span className="w-1.5 h-1.5 rounded-full bg-green-600" />}
                        </button>

                        {showFiltersPanel && (
                            <div className="mt-2 w-60 rounded-md bg-white shadow-md p-3 space-y-3">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-sm font-medium text-gray-700">Filters</h3>
                                    <button
                                        type="button"
                                        onClick={() => setShowFiltersPanel(false)}
                                        className="text-sm text-gray-500"
                                    >
                                        Close
                                    </button>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Layers</p>
                                    <div className="space-y-2">
                                        {jobsWithLocation.length > 0 && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Jobs</span>
                                                <button
                                                    type="button"
                                                    role="switch"
                                                    aria-checked={showJobs}
                                                    onClick={() => setShowJobs((v) => !v)}
                                                    className={`relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors ${
                                                        showJobs ? 'bg-red-600' : 'bg-gray-300'
                                                    }`}
                                                >
                                                    <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${showJobs ? 'translate-x-6' : 'translate-x-1'}`} />
                                                </button>
                                            </div>
                                        )}
                                        {zones && zones.length > 0 && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Zones</span>
                                                <button
                                                    type="button"
                                                    role="switch"
                                                    aria-checked={showZones}
                                                    onClick={() => setShowZones((v) => !v)}
                                                    className={`relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors ${
                                                        showZones ? 'bg-violet-600' : 'bg-gray-300'
                                                    }`}
                                                >
                                                    <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${showZones ? 'translate-x-6' : 'translate-x-1'}`} />
                                                </button>
                                            </div>
                                        )}
                                        {assets && assets.length > 0 && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Assets</span>
                                                <button
                                                    type="button"
                                                    role="switch"
                                                    aria-checked={showAssets}
                                                    onClick={() => setShowAssets((v) => !v)}
                                                    className={`relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors ${
                                                        showAssets ? 'bg-blue-600' : 'bg-gray-300'
                                                    }`}
                                                >
                                                    <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${showAssets ? 'translate-x-6' : 'translate-x-1'}`} />
                                                </button>
                                            </div>
                                        )}
                                        {notes && notes.length > 0 && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Notes</span>
                                                <button
                                                    type="button"
                                                    role="switch"
                                                    aria-checked={showNotes}
                                                    onClick={() => setShowNotes((v) => !v)}
                                                    className={`relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors ${
                                                        showNotes ? 'bg-teal-600' : 'bg-gray-300'
                                                    }`}
                                                >
                                                    <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${showNotes ? 'translate-x-6' : 'translate-x-1'}`} />
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 pt-3">
                                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Job status</p>
                                    <div className="space-y-1.5">
                                        <label className="flex items-center gap-2 text-sm text-gray-700">
                                            <input
                                                type="checkbox"
                                                checked={jobStatusActive}
                                                onChange={toggleJobStatusActive}
                                                className="rounded text-green-600 focus:ring-green-500"
                                            />
                                            Active jobs
                                        </label>
                                        <label className="flex items-center gap-2 text-sm text-gray-700">
                                            <input
                                                type="checkbox"
                                                checked={jobStatusCompleted}
                                                onChange={toggleJobStatusCompleted}
                                                className="rounded text-green-600 focus:ring-green-500"
                                            />
                                            Completed / Cancelled
                                        </label>
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 pt-3">
                                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Job age</p>
                                    <div className="space-y-1.5">
                                        {[
                                            { value: 'all', label: 'All time' },
                                            { value: '30', label: 'Last 30 days' },
                                            { value: '7', label: 'Last 7 days' },
                                        ].map((option) => (
                                            <label key={option.value} className="flex items-center gap-2 text-sm text-gray-700">
                                                <input
                                                    type="radio"
                                                    name="job_age"
                                                    checked={jobAge === option.value}
                                                    onChange={() => changeJobAge(option.value)}
                                                    className="text-green-600 focus:ring-green-500"
                                                />
                                                {option.label}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {canCreateNote && !creatingNote && !editingLocationNoteId && (
                        <button
                            type="button"
                            onClick={startAddingNote}
                            className="absolute left-4 bottom-4 z-[1000] px-4 py-2 bg-teal-600 text-white rounded-md shadow-md text-sm font-medium"
                        >
                            + Add Note
                        </button>
                    )}

                    {creatingNote && (
                        <div className="absolute inset-x-4 bottom-4 z-[1000] bg-white rounded-lg shadow-md p-3 space-y-2">
                            <p className="text-xs text-gray-500">Drag the pin to adjust the location.</p>
                            <textarea
                                value={draftNoteBody}
                                onChange={(e) => setDraftNoteBody(e.target.value)}
                                placeholder="Note..."
                                rows={2}
                                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                            />
                            <div className="flex gap-2">
                                <button
                                    onClick={saveNewNote}
                                    disabled={!draftNoteBody}
                                    className="flex-1 py-2 bg-teal-600 text-white rounded-lg text-sm disabled:opacity-50"
                                >
                                    Save
                                </button>
                                <button onClick={cancelNewNote} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}

                    {editingLocationNoteId && (
                        <div className="absolute inset-x-4 bottom-4 z-[1000] bg-white rounded-lg shadow-md p-3 space-y-2">
                            <p className="text-xs text-gray-500">Drag the pin to move it.</p>
                            <div className="flex gap-2">
                                <button onClick={saveNoteLocation} className="flex-1 py-2 bg-teal-600 text-white rounded-lg text-sm">
                                    Save
                                </button>
                                <button onClick={cancelNoteLocationEdit} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}

                    <div
                        ref={mapRef}
                        style={{ height: 'calc(100dvh - 8.5rem)' }}
                    />
                </div>
            </div>

            <Modal show={Boolean(activeNote)} onClose={() => { setActiveNote(null); setEditingNoteBody(false); }} maxWidth="lg">
                {activeNote && (
                    <div className="p-4 space-y-3">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-medium text-gray-700">Note</h3>
                            <button onClick={() => { setActiveNote(null); setEditingNoteBody(false); }} className="text-sm text-gray-500">
                                Close
                            </button>
                        </div>

                        {editingNoteBody ? (
                            <>
                                <textarea
                                    value={noteBodyDraft}
                                    onChange={(e) => setNoteBodyDraft(e.target.value)}
                                    rows={3}
                                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                                />
                                <div className="flex gap-2">
                                    <button onClick={saveNoteBody} className="flex-1 py-2 bg-teal-600 text-white rounded-lg text-sm">Save</button>
                                    <button onClick={() => setEditingNoteBody(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                                </div>
                            </>
                        ) : (
                            <>
                                <p className="text-sm text-gray-900 whitespace-pre-wrap">{activeNote.body}</p>
                                <p className="text-xs text-gray-400">
                                    {activeNote.created_by?.name} · {formatDate(activeNote.created_at)}
                                </p>
                            </>
                        )}

                        {activeNote.photos && activeNote.photos.length > 0 && (
                            <div className="grid grid-cols-3 gap-2">
                                {activeNote.photos.map((photo) => (
                                    <div key={photo.id} className="relative">
                                        <img src={photo.url} className="w-full h-20 object-cover rounded-lg" />
                                        {isAdminOrManager && (
                                            <button
                                                onClick={() => destroyNotePhoto(photo.id)}
                                                className="absolute top-1 right-1 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                            >
                                                ×
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}

                        {!editingNoteBody && (
                            <div className="flex flex-wrap gap-3 text-xs pt-1">
                                {canCreateNote && (
                                    <button onClick={() => noteFileInput.current.click()} disabled={uploadingNotePhoto} className="text-teal-600 font-medium disabled:opacity-50">
                                        {uploadingNotePhoto ? 'Uploading…' : '+ Add Photo'}
                                    </button>
                                )}
                                {isAdminOrManager && (
                                    <>
                                        <button onClick={openNoteEdit} className="text-teal-600 font-medium">Edit</button>
                                        <button onClick={startEditNoteLocation} className="text-teal-600 font-medium">Edit location</button>
                                        <button onClick={deleteNote} className="text-red-500 font-medium">Delete</button>
                                    </>
                                )}
                            </div>
                        )}
                        <input
                            ref={noteFileInput}
                            type="file"
                            accept="image/*"
                            multiple
                            onChange={uploadNotePhotos}
                            className="hidden"
                        />
                    </div>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}