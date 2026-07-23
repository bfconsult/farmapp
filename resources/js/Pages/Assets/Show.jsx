import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { formatDate } from '@/dateInput';
import { compressImageFiles } from '@/imageCompression';
import { pillBadgeClass } from '@/Utils/pillColors';

const ASSET_COLOR = '#2563eb';
const PROPERTY_BOUNDARY_COLOR = '#9ca3af';
const ZONE_COLOR = '#7c3aed';

/** Standard ray-casting point-in-polygon test - `coordinates` is a plain
 * array of [lat, lng] pairs, the same shape Zone/Asset shapes already use
 * everywhere else in this app (not GeoJSON). */
function isPointInPolygon(lat, lng, coordinates) {
    let inside = false;
    for (let i = 0, j = coordinates.length - 1; i < coordinates.length; j = i++) {
        const [latI, lngI] = coordinates[i];
        const [latJ, lngJ] = coordinates[j];
        const intersects = (lngI > lng) !== (lngJ > lng) &&
            lat < (latJ - latI) * (lng - lngI) / (lngJ - lngI) + latI;
        if (intersects) inside = !inside;
    }
    return inside;
}

function findZoneNameForPoint(lat, lng, zones) {
    return (zones ?? []).find((zone) => isPointInPolygon(lat, lng, zone.coordinates))?.name;
}

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

/** Small non-interactive preview: the asset's pin/shape in the context of its property's boundary. */
function LocationThumbnail({ asset, propertyBoundary }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const location = asset.current_location;

    useEffect(() => {
        let cancelled = false;

        loadLeaflet().then((L) => {
            if (cancelled || mapInstance.current) return;

            const map = L.map(mapRef.current, {
                zoomControl: false,
                attributionControl: false,
                dragging: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false,
                touchZoom: false,
            });
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

            const bounds = L.latLngBounds([]);

            if (propertyBoundary) {
                const boundary = L.polygon(propertyBoundary, {
                    color: PROPERTY_BOUNDARY_COLOR,
                    weight: 1,
                    fillOpacity: 0.05,
                    interactive: false,
                }).addTo(map);
                bounds.extend(boundary.getBounds());
            }

            if (location.shape) {
                const shape = L.polygon(location.shape, {
                    color: ASSET_COLOR,
                    fillColor: ASSET_COLOR,
                    fillOpacity: 0.2,
                    interactive: false,
                }).addTo(map);
                bounds.extend(shape.getBounds());
            } else if (location.latitude && location.longitude) {
                L.marker([location.latitude, location.longitude], { interactive: false }).addTo(map);
                bounds.extend([location.latitude, location.longitude]);
            }

            map.fitBounds(bounds, { padding: [16, 16] });
        });

        return () => {
            cancelled = true;
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
    }, []);

    return (
        <div
            ref={mapRef}
            style={{ height: '140px', position: 'relative', zIndex: 0 }}
            className="rounded-lg pointer-events-none"
        />
    );
}

/** Full interactive map + Geoman drawing controls, shown inside the Location modal. */
function LocationEditorMap({ asset, propertyBoundary, zones, canManage, onSaved }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const originalLayer = useRef(null);
    const pendingLayer = useRef(null);
    const zoneLayerGroup = useRef(null);
    const [showZones, setShowZones] = useState(false);
    const [zonesReady, setZonesReady] = useState(false);
    const [pendingPayload, setPendingPayload] = useState(null);

    useEffect(() => {
        Promise.all([
            loadLeaflet(),
            import('@geoman-io/leaflet-geoman-free'),
            import('@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css'),
        ]).then(([L]) => {
            if (mapInstance.current) return;

            const map = L.map(mapRef.current).setView([-37.8136, 144.9631], 15);
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            zoneLayerGroup.current = L.layerGroup(
                (zones ?? []).map((zone) =>
                    L.polygon(zone.coordinates, {
                        color: ZONE_COLOR,
                        weight: 2,
                        fillColor: ZONE_COLOR,
                        fillOpacity: 0.15,
                        interactive: false,
                    }).bindTooltip(zone.name, { permanent: true, direction: 'center' }),
                ),
            );
            setZonesReady(true);

            let boundaryBounds = null;
            if (propertyBoundary) {
                const boundary = L.polygon(propertyBoundary, {
                    color: PROPERTY_BOUNDARY_COLOR,
                    weight: 1,
                    fillOpacity: 0.05,
                    interactive: false,
                }).addTo(map);
                boundaryBounds = boundary.getBounds();
            }

            const location = asset.current_location;
            if (location?.shape) {
                originalLayer.current = L.polygon(location.shape, {
                    color: ASSET_COLOR,
                    fillColor: ASSET_COLOR,
                    fillOpacity: 0.15,
                }).addTo(map);
            } else if (location?.latitude && location?.longitude) {
                originalLayer.current = L.marker([location.latitude, location.longitude]).addTo(map);
            }

            // Always open on the whole property's extents (plus the asset's
            // own location, if set) rather than zooming in tight on just the
            // pin - gives context for where the asset sits on the property,
            // and room to place a new pin when there isn't one yet.
            if (boundaryBounds) {
                if (originalLayer.current) boundaryBounds.extend(originalLayer.current.getBounds?.() ?? originalLayer.current.getLatLng());
                map.fitBounds(boundaryBounds, { padding: [40, 40] });
            } else if (originalLayer.current) {
                map.fitBounds(originalLayer.current.getBounds?.() ?? L.latLngBounds([originalLayer.current.getLatLng()]), { padding: [40, 40] });
            } else {
                map.locate({ setView: true, maxZoom: 16 });
            }

            if (canManage) {
                map.pm.addControls({
                    position: 'topleft',
                    drawMarker: true,
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

                // Drawing a new pin/shape no longer saves immediately - it
                // becomes an adjustable draft (draggable marker, or an
                // editable shape via Geoman's own edit mode) until the user
                // explicitly confirms with the "Done" button, so a single
                // tap-to-place doesn't lock in an imprecise location.
                map.on('pm:create', (e) => {
                    (pendingLayer.current ?? originalLayer.current)?.remove();
                    pendingLayer.current = e.layer;
                    map.pm.disableDraw();

                    const updatePending = () => {
                        setPendingPayload(
                            e.shape === 'Marker'
                                ? { latitude: e.layer.getLatLng().lat, longitude: e.layer.getLatLng().lng }
                                : { shape: e.layer.getLatLngs()[0].map((ll) => [ll.lat, ll.lng]) }
                        );
                    };

                    if (e.shape === 'Marker') {
                        e.layer.dragging.enable();
                        e.layer.on('dragend', updatePending);
                    } else {
                        e.layer.on('pm:edit', updatePending);
                    }

                    updatePending();
                });
            }
        });

        return () => {
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
    }, []);

    useEffect(() => {
        if (!zonesReady) return;

        if (showZones) zoneLayerGroup.current.addTo(mapInstance.current);
        else zoneLayerGroup.current.remove();
    }, [showZones, zonesReady]);

    const saveDraft = () => {
        router.put(route('assets.update-location', asset.id), pendingPayload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onSaved(),
        });
    };

    const cancelDraft = () => {
        pendingLayer.current?.remove();
        pendingLayer.current = null;
        originalLayer.current?.addTo(mapInstance.current);
        setPendingPayload(null);
    };

    return (
        <div>
            {zones && zones.length > 0 && (
                <div className="flex items-center justify-between mb-2 px-2 py-1.5 rounded-lg bg-gray-50 border border-gray-200">
                    <span className="flex items-center gap-1.5 text-xs font-medium text-gray-600">
                        <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        Zones
                    </span>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={showZones}
                        onClick={() => setShowZones((v) => !v)}
                        className={`relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors ${
                            showZones ? 'bg-violet-600' : 'bg-gray-300'
                        }`}
                    >
                        <span
                            className={`inline-block h-3 w-3 transform rounded-full bg-white transition-transform ${
                                showZones ? 'translate-x-5' : 'translate-x-1'
                            }`}
                        />
                    </button>
                </div>
            )}
            <div ref={mapRef} style={{ height: '350px' }} />
            {pendingPayload && (
                <div className="flex gap-2 mt-3">
                    <button onClick={saveDraft} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm font-medium">Done</button>
                    <button onClick={cancelDraft} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                </div>
            )}
        </div>
    );
}

function AssetFields({ values, setValues }) {
    return (
        <div className="space-y-3">
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Name"
            />
            <textarea
                value={values.description}
                onChange={(e) => setValues({ ...values, description: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Description (optional)"
                rows={2}
            />
            <input
                type="number"
                step="0.01"
                value={values.value}
                onChange={(e) => setValues({ ...values, value: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Value ($, optional)"
            />
        </div>
    );
}

function MaintenanceItemRow({ item, canManage, canConvert }) {
    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({
        name: item.name,
        description: item.description ?? '',
        start_date: item.start_date.slice(0, 10),
        repeat_period_days: item.repeat_period_days,
        auto_generate: item.auto_generate,
    });

    const save = () => {
        router.patch(route('maintenance-items.update', item.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm(`Delete maintenance item "${item.name}"?`)) {
            router.delete(route('maintenance-items.destroy', item.id), { preserveScroll: true, preserveState: true });
        }
    };

    const convert = () => {
        if (confirm(`Turn "${item.name}" into a job now?`)) {
            router.post(route('maintenance-items.convert', item.id));
        }
    };

    const isOverdue = item.next_due_date.slice(0, 10) < new Date().toISOString().slice(0, 10);

    if (editing) {
        return (
            <div className="p-3 bg-green-50 space-y-2">
                <input
                    type="text"
                    value={values.name}
                    onChange={(e) => setValues({ ...values, name: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Item name"
                />
                <textarea
                    value={values.description}
                    onChange={(e) => setValues({ ...values, description: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Description (optional)"
                    rows={2}
                />
                <div className="grid grid-cols-2 gap-2">
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Start date</label>
                        <input
                            type="date"
                            value={values.start_date}
                            onChange={(e) => setValues({ ...values, start_date: e.target.value })}
                            className="w-full border-gray-300 rounded-lg p-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Repeat (days)</label>
                        <input
                            type="number"
                            min="1"
                            value={values.repeat_period_days}
                            onChange={(e) => setValues({ ...values, repeat_period_days: e.target.value })}
                            className="w-full border-gray-300 rounded-lg p-2 text-sm"
                        />
                    </div>
                </div>
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={values.auto_generate}
                        onChange={(e) => setValues({ ...values, auto_generate: e.target.checked })}
                        className="rounded text-green-600 focus:ring-green-500"
                    />
                    Auto-generate the job when due
                </label>
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-1.5 bg-green-600 text-white rounded-lg text-xs">Save</button>
                    <button onClick={() => setEditing(false)} className="flex-1 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between gap-2 px-3 py-3">
            <div className="min-w-0 flex-1">
                <p className="text-sm text-gray-900">{item.name}</p>
                {item.description && (
                    <p className="text-xs text-gray-500 mt-0.5">{item.description}</p>
                )}
                <div className="flex items-center gap-2 mt-1">
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${isOverdue ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'}`}>
                        {isOverdue ? 'Overdue' : 'Due'} {formatDate(item.next_due_date.slice(0, 10))}
                    </span>
                    <span className="text-xs text-gray-400">every {item.repeat_period_days}d</span>
                    {item.auto_generate && (
                        <span className="text-xs px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-700">
                            Auto
                        </span>
                    )}
                </div>
                <div className="flex gap-3 mt-1 text-xs">
                    {canConvert && (
                        <button onClick={convert} className="text-green-600 font-medium">Turn into Job</button>
                    )}
                    {canManage && (
                        <>
                            <button onClick={() => setEditing(true)} className="text-green-600">Edit</button>
                            <button onClick={destroy} className="text-red-500">Delete</button>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

function AddMaintenanceItemForm({ asset, onClose }) {
    const todayIso = () => new Date().toISOString().slice(0, 10);
    const [values, setValues] = useState({ name: '', description: '', start_date: todayIso(), repeat_period_days: 90, auto_generate: false });

    const create = () => {
        router.post(route('maintenance-items.store', asset.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                onClose();
                setValues({ name: '', description: '', start_date: todayIso(), repeat_period_days: 90, auto_generate: false });
            },
        });
    };

    return (
        <div className="p-4 space-y-3 border-t border-gray-100">
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Name (e.g. Service pump)"
            />
            <textarea
                value={values.description}
                onChange={(e) => setValues({ ...values, description: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Description (optional)"
                rows={2}
            />
            <div className="grid grid-cols-2 gap-2">
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Start date</label>
                    <input
                        type="date"
                        value={values.start_date}
                        onChange={(e) => setValues({ ...values, start_date: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                </div>
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Repeat (days)</label>
                    <input
                        type="number"
                        min="1"
                        value={values.repeat_period_days}
                        onChange={(e) => setValues({ ...values, repeat_period_days: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                </div>
            </div>
            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={values.auto_generate}
                    onChange={(e) => setValues({ ...values, auto_generate: e.target.checked })}
                    className="rounded text-green-600 focus:ring-green-500"
                />
                Auto-generate the job when due
            </label>
            <div className="flex gap-2">
                <button onClick={create} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Item</button>
                <button onClick={onClose} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
            </div>
        </div>
    );
}

function NoteRow({ note, canManage, canCreate }) {
    const fileInput = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [editing, setEditing] = useState(false);
    const [body, setBody] = useState(note.body);

    const save = () => {
        router.patch(route('notes.update', note.id), { body }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm('Delete this note?')) {
            router.delete(route('notes.destroy', note.id), { preserveScroll: true, preserveState: true });
        }
    };

    const destroyPhoto = (photoId) => {
        if (confirm('Delete this photo?')) {
            router.delete(route('photos.destroy', photoId), { preserveScroll: true, preserveState: true });
        }
    };

    const uploadPhotos = async (e) => {
        const files = e.target.files;
        if (!files.length) return;

        setUploading(true);
        const compressed = await compressImageFiles(files);

        const formData = new FormData();
        compressed.forEach(file => formData.append('photos[]', file));

        router.post(route('photos.store-note', note.id), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
            onError: () => alert('Photo upload failed. Please try again with a smaller photo.'),
        });
    };

    if (editing) {
        return (
            <div className="p-3 bg-green-50 space-y-2">
                <textarea
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    rows={3}
                />
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-1.5 bg-green-600 text-white rounded-lg text-xs">Save</button>
                    <button onClick={() => { setEditing(false); setBody(note.body); }} className="flex-1 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div className="px-3 py-3">
            <p className="text-sm text-gray-900 whitespace-pre-wrap">{note.body}</p>
            <p className="text-xs text-gray-400 mt-1">
                {note.created_by?.name} · {formatDate(note.created_at)}
            </p>

            {note.photos && note.photos.length > 0 && (
                <div className="grid grid-cols-3 gap-2 mt-2">
                    {note.photos.map((photo) => (
                        <div key={photo.id} className="relative">
                            <img src={photo.url} className="w-full h-20 object-cover rounded-lg" />
                            {canManage && (
                                <button
                                    onClick={() => destroyPhoto(photo.id)}
                                    className="absolute top-1 right-1 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                >
                                    ×
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            <div className="flex gap-3 mt-2 text-xs">
                {canCreate && (
                    <button onClick={() => fileInput.current.click()} disabled={uploading} className="text-green-600 font-medium disabled:opacity-50">
                        {uploading ? 'Uploading…' : '+ Add Photo'}
                    </button>
                )}
                {canManage && (
                    <>
                        <button onClick={() => setEditing(true)} className="text-green-600">Edit</button>
                        <button onClick={destroy} className="text-red-500">Delete</button>
                    </>
                )}
            </div>
            <input
                ref={fileInput}
                type="file"
                accept="image/*"
                multiple
                onChange={uploadPhotos}
                className="hidden"
            />
        </div>
    );
}

function AddNoteForm({ asset, onClose }) {
    const [body, setBody] = useState('');

    const create = () => {
        router.post(route('notes.store'), { asset_id: asset.id, body }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                onClose();
                setBody('');
            },
        });
    };

    return (
        <div className="p-4 space-y-3 border-t border-gray-100">
            <textarea
                value={body}
                onChange={(e) => setBody(e.target.value)}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Note..."
                rows={3}
            />
            <div className="flex gap-2">
                <button onClick={create} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Note</button>
                <button onClick={onClose} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
            </div>
        </div>
    );
}

export default function Show({ asset, recentJobs, jobsCount, bookedHours }) {
    const { currentUserRole } = usePage().props;
    const canManage = currentUserRole === 'admin' || currentUserRole === 'manager';
    const canConvert = canManage || currentUserRole === 'worker';
    const canCreateNote = canManage || currentUserRole === 'worker';

    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({
        name: asset.name,
        description: asset.description ?? '',
        value: asset.value ?? '',
    });

    const [showLocationModal, setShowLocationModal] = useState(false);
    const hasLocation = Boolean(
        asset.current_location?.shape ||
        (asset.current_location?.latitude && asset.current_location?.longitude)
    );

    const [addingMaintenanceItem, setAddingMaintenanceItem] = useState(false);
    const [addingNote, setAddingNote] = useState(false);
    const [showHistory, setShowHistory] = useState(false);
    const pastLocations = (asset.locations ?? []).slice(1); // [0] is current_location itself

    const describeLocation = (location) => {
        if (location.shape) return `Shape (${location.shape.length} points)`;
        if (location.latitude && location.longitude) {
            const lat = Number(location.latitude);
            const lng = Number(location.longitude);
            const zoneName = findZoneNameForPoint(lat, lng, asset.property?.zones);
            const pointText = `Point (${lat.toFixed(5)}, ${lng.toFixed(5)})`;
            return zoneName ? `${zoneName} — ${pointText}` : pointText;
        }
        return 'Location cleared';
    };

    const save = () => {
        router.patch(route('assets.update', asset.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const clearLocation = () => {
        if (!confirm('Clear this asset\'s location?')) return;
        router.put(route('assets.update-location', asset.id), {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title={asset.name} />

            <div className="max-w-lg mx-auto space-y-4">
                <div className="flex items-center justify-between">
                    <Link href={route('manage.index')} className="text-green-600 text-sm">
                        ← Manage
                    </Link>
                </div>

                <div className="bg-white rounded-lg shadow p-4">
                    {editing ? (
                        <div className="space-y-3">
                            <AssetFields values={values} setValues={setValues} />
                            <div className="flex gap-2">
                                <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                                <button onClick={() => setEditing(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="flex items-start justify-between mb-2">
                                <h1 className="text-lg font-semibold text-gray-900">{asset.name}</h1>
                                {asset.asset_type && (
                                    <span className="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600 flex-shrink-0">
                                        {asset.asset_type.name}
                                    </span>
                                )}
                            </div>
                            {asset.description && (
                                <p className="text-sm text-gray-500 mb-2">{asset.description}</p>
                            )}
                            {asset.value && (
                                <p className="text-sm text-gray-900">${asset.value}</p>
                            )}
                            {canManage && (
                                <button onClick={() => setEditing(true)} className="text-xs text-green-600 mt-2">Edit</button>
                            )}
                        </>
                    )}
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Location</h2>
                        {canManage && hasLocation && (
                            <button onClick={clearLocation} className="text-xs text-red-500">Clear location</button>
                        )}
                    </div>
                    <div className="p-3">
                        {hasLocation ? (
                            <button onClick={() => setShowLocationModal(true)} className="block w-full">
                                {/* Keyed on the location record's own id so the thumbnail's
                                    internal Leaflet map (built once per mount) remounts fresh
                                    whenever a new location is saved, instead of continuing to
                                    show whatever it first rendered. */}
                                <LocationThumbnail key={asset.current_location.id} asset={asset} propertyBoundary={asset.property?.shape?.coordinates} />
                                <p className="text-xs text-gray-400 mt-1">{canManage ? 'Tap to edit' : 'Tap to view'}</p>
                            </button>
                        ) : canManage ? (
                            <button onClick={() => setShowLocationModal(true)} className="text-sm text-green-600">
                                + Set location
                            </button>
                        ) : (
                            <p className="text-sm text-gray-400">No location set.</p>
                        )}
                    </div>
                </div>

                {pastLocations.length > 0 && (
                    <div className="bg-white rounded-lg shadow overflow-hidden">
                        <button
                            onClick={() => setShowHistory((v) => !v)}
                            className="w-full flex items-center justify-between px-4 py-2 border-b border-gray-100"
                        >
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">
                                {showHistory ? '▾' : '▸'} History ({pastLocations.length})
                            </h2>
                        </button>
                        {showHistory && (
                            <div className="divide-y divide-gray-100">
                                {pastLocations.map((location) => (
                                    <div key={location.id} className="px-4 py-2 text-sm text-gray-700 flex justify-between">
                                        <span>{describeLocation(location)}</span>
                                        <span className="text-xs text-gray-400">{formatDate(location.created_at)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                <Modal show={showLocationModal} onClose={() => setShowLocationModal(false)} maxWidth="lg">
                    <div className="p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-sm font-medium text-gray-700">Location</h3>
                            <button onClick={() => setShowLocationModal(false)} className="text-sm text-gray-500">Close</button>
                        </div>
                        {showLocationModal && (
                            <LocationEditorMap
                                asset={asset}
                                propertyBoundary={asset.property?.shape?.coordinates}
                                zones={asset.property?.zones}
                                canManage={canManage}
                                onSaved={() => setShowLocationModal(false)}
                            />
                        )}
                    </div>
                </Modal>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Maintenance Items</h2>
                        {canManage && !addingMaintenanceItem && (
                            <button onClick={() => setAddingMaintenanceItem(true)} className="text-xs text-green-600 font-medium">
                                + Add
                            </button>
                        )}
                    </div>
                    {asset.maintenance_items.length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No maintenance items yet.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {asset.maintenance_items.map((item) => (
                                <MaintenanceItemRow key={item.id} item={item} canManage={canManage} canConvert={canConvert} />
                            ))}
                        </div>
                    )}
                    {addingMaintenanceItem && (
                        <AddMaintenanceItemForm asset={asset} onClose={() => setAddingMaintenanceItem(false)} />
                    )}
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Notes</h2>
                        {canCreateNote && !addingNote && (
                            <button onClick={() => setAddingNote(true)} className="text-xs text-green-600 font-medium">
                                + Add
                            </button>
                        )}
                    </div>
                    {asset.notes.length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No notes yet.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {asset.notes.map((note) => (
                                <NoteRow key={note.id} note={note} canManage={canManage} canCreate={canCreateNote} />
                            ))}
                        </div>
                    )}
                    {addingNote && (
                        <AddNoteForm asset={asset} onClose={() => setAddingNote(false)} />
                    )}
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Jobs</h2>
                        <div className="flex items-center gap-3">
                            {bookedHours > 0 && (
                                <span className="text-xs text-gray-500">{bookedHours}h booked</span>
                            )}
                            {canConvert && (
                                <Link href={route('jobs.create', { asset_id: asset.id })} className="text-xs text-green-600 font-medium">
                                    + New Job
                                </Link>
                            )}
                        </div>
                    </div>
                    {recentJobs.length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No jobs related to this asset yet.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {recentJobs.map((job) => (
                                <Link key={job.id} href={route('jobs.show', job.id)} className="block px-4 py-3">
                                    <p className="text-sm text-gray-900">{job.name}</p>
                                    <div className="flex items-center gap-2 mt-1">
                                        <span className="text-xs text-gray-400">{formatDate(job.created_at.slice(0, 10))}</span>
                                        {job.job_status && (
                                            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${pillBadgeClass(job.job_status.color)}`}>
                                                {job.job_status.name}
                                            </span>
                                        )}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                    {jobsCount > 0 && (
                        <Link
                            href={route('assets.jobs', asset.id)}
                            className="block text-center py-2 text-sm text-green-600 border-t border-gray-100"
                        >
                            View all {jobsCount} job{jobsCount === 1 ? '' : 's'} →
                        </Link>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
