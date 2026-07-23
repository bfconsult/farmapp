import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { compressImageFiles } from '@/imageCompression';
import { formatDate } from '@/dateInput';
import { pillBadgeClass } from '@/Utils/pillColors';

const PROPERTY_BOUNDARY_COLOR = '#ca8a04';
const PROPERTY_BOUNDARY_FILL = '#fef08a';

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

/** Map shown in the Location popover: the job's pin in the context of the property's boundary. When `editable`, the pin can be dragged and reports its new position via `onDragEnd`. */
function JobLocationMap({ job, propertyBoundary, editable, onDragEnd }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);

    useEffect(() => {
        let cancelled = false;

        loadLeaflet().then((L) => {
            if (cancelled || mapInstance.current) return;

            const map = L.map(mapRef.current);
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            const bounds = L.latLngBounds([]);

            if (propertyBoundary) {
                const boundary = L.polygon(propertyBoundary, {
                    color: PROPERTY_BOUNDARY_COLOR,
                    weight: 2,
                    dashArray: '6, 6',
                    fillColor: PROPERTY_BOUNDARY_FILL,
                    fillOpacity: 0.15,
                    interactive: false,
                }).addTo(map);
                bounds.extend(boundary.getBounds());
            }

            const marker = L.marker([job.latitude, job.longitude], {
                draggable: Boolean(editable),
                icon: L.icon({
                    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                }),
            }).addTo(map);
            bounds.extend([job.latitude, job.longitude]);

            if (editable) {
                marker.on('dragend', () => {
                    const { lat, lng } = marker.getLatLng();
                    onDragEnd?.(lat, lng);
                });
            }

            map.invalidateSize();
            map.fitBounds(bounds, { padding: [40, 40] });
        });

        return () => {
            cancelled = true;
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
    }, []);

    return <div ref={mapRef} style={{ height: '300px' }} />;
}

const CHECKLIST_TYPE_LABELS = {
    before_start: 'Before Start',
    at_completion: 'At Completion',
};

function Spinner({ className = 'h-4 w-4' }) {
    return (
        <svg className={`animate-spin ${className}`} viewBox="0 0 24 24" fill="none">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
    );
}

function formatViewedAt(datetime) {
    const time = new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return `${formatDate(datetime, { year: false })}, ${time}`;
}

export default function Show({ job, seenBy, checklistTemplates }) {
    const fileInput = useRef(null);
    const { flash } = usePage().props;
    const [uploading, setUploading] = useState(false);
    const [showDeleteOptions, setShowDeleteOptions] = useState(false);
    const [showShare, setShowShare] = useState(false);
    const [showChecklistPicker, setShowChecklistPicker] = useState(false);
    const [showLocationModal, setShowLocationModal] = useState(false);
    const [editingLocation, setEditingLocation] = useState(false);
    const [pendingLocation, setPendingLocation] = useState(null);
    const [copied, setCopied] = useState(false);

    const hasIncompleteChecklists = (job.checklists ?? []).some((c) => c.status === 'incomplete');

    useEffect(() => {
        if (flash?.addPhoto && fileInput.current) {
            fileInput.current.click();
        }
    }, [flash]);

    const finishJob = () => {
        if (confirm('Mark this job as finished?')) {
            router.post(route('jobs.finish', job.id));
        }
    };

    const destroy = () => {
        if (job.recurring_job_id) {
            setShowDeleteOptions(true);
            return;
        }

        if (confirm('Are you sure you want to delete this job?')) {
            router.delete(route('jobs.destroy', job.id));
        }
    };

    const destroyWithChoice = (deleteRecurring) => {
        router.delete(route('jobs.destroy', job.id), {
            data: { delete_recurring: deleteRecurring },
        });
    };

    const copyShareLink = async () => {
        const link = route('jobs.share', job.share_token);
        try {
            await navigator.clipboard.writeText(link);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard API unavailable; the link is still visible to copy manually.
        }
    };

    const closeLocationModal = () => {
        setShowLocationModal(false);
        setEditingLocation(false);
        setPendingLocation(null);
    };

    const cancelEditLocation = () => {
        setEditingLocation(false);
        setPendingLocation(null);
    };

    const saveLocation = () => {
        const coords = pendingLocation ?? { lat: job.latitude, lng: job.longitude };
        router.put(route('jobs.update-location', job.id), {
            latitude: coords.lat,
            longitude: coords.lng,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeLocationModal,
        });
    };

    const attachedChecklistFor = (templateId) =>
        (job.checklists ?? []).find((c) => c.checklist_template_id === templateId);

    const toggleChecklistTemplate = (template) => {
        const existing = attachedChecklistFor(template.id);
        if (existing) {
            router.delete(route('checklists.destroy', existing.id), { preserveScroll: true, preserveState: true });
        } else {
            router.post(route('checklists.store'), {
                farm_job_id: job.id,
                checklist_template_id: template.id,
            }, { preserveScroll: true, preserveState: true });
        }
    };

    const destroyPhoto = (photoId) => {
        if (confirm('Delete this photo?')) {
            router.delete(route('photos.destroy', photoId));
        }
    };

    const uploadPhotos = async (e) => {
        const files = e.target.files;
        if (!files.length) return;

        setUploading(true);
        const compressed = await compressImageFiles(files);

        const formData = new FormData();
        compressed.forEach(file => formData.append('photos[]', file));

        router.post(route('photos.store', job.id), formData, {
            forceFormData: true,
            onFinish: () => setUploading(false),
            onError: () => alert('Photo upload failed. Please try again with a smaller photo.'),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={job.name} />

            {uploading && (
                <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center">
                    <div className="bg-white rounded-lg shadow-lg px-6 py-5 flex flex-col items-center gap-3">
                        <Spinner className="h-10 w-10 text-green-600" />
                        <p className="text-sm text-gray-700">Uploading photo…</p>
                    </div>
                </div>
            )}

            <div className="max-w-lg mx-auto space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <Link href={route('jobs.index')} className="text-green-600 text-sm">
                        ← Jobs
                    </Link>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setShowShare((v) => !v)}
                            aria-label="Share"
                            className="p-2 border border-gray-300 text-gray-700 rounded-lg"
                        >
                            <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
                                <circle cx="18" cy="5" r="3" />
                                <circle cx="6" cy="12" r="3" />
                                <circle cx="18" cy="19" r="3" />
                                <line x1="8.59" y1="10.51" x2="15.42" y2="6.49" />
                                <line x1="8.59" y1="13.49" x2="15.42" y2="17.51" />
                            </svg>
                        </button>
                        <Link
                            href={route('jobs.edit', job.id)}
                            className="text-sm px-3 py-1 border border-green-600 text-green-600 rounded-lg"
                        >
                            Edit
                        </Link>
                    </div>
                </div>

                {showShare && (
                    <div className="bg-white rounded-lg shadow p-4">
                        <p className="text-xs text-gray-500 mb-2">
                            Anyone with this link can view this job, even without an account.
                        </p>
                        <div className="flex items-center gap-2">
                            <p className="flex-1 min-w-0 text-xs text-gray-500 bg-gray-50 rounded-lg px-2 py-1.5 truncate">
                                {route('jobs.share', job.share_token)}
                            </p>
                            <button
                                type="button"
                                onClick={copyShareLink}
                                className="text-xs text-green-600 whitespace-nowrap flex-shrink-0"
                            >
                                {copied ? 'Copied!' : 'Copy link'}
                            </button>
                        </div>
                    </div>
                )}

                {/* Job name & badges */}
                <div className="bg-white rounded-lg shadow p-4">
                    <h1 className="text-xl font-semibold text-gray-900 mb-3">{job.name}</h1>
                    <div className="flex flex-wrap gap-2">
                        {job.job_status && (
                            <span className={`text-xs px-2 py-1 rounded-full font-medium ${pillBadgeClass(job.job_status.color)}`}>
                                {job.job_status.name}
                            </span>
                        )}
                        {job.priority && (
                            <span className={`text-xs px-2 py-1 rounded-full font-medium ${pillBadgeClass(job.priority.color)}`}>
                                {job.priority.name}
                            </span>
                        )}
                        {job.job_type && (
                            <span className={`text-xs px-2 py-1 rounded-full font-medium ${pillBadgeClass(job.job_type.color)}`}>
                                {job.job_type.name}
                            </span>
                        )}
                        {hasIncompleteChecklists && (
                            <span className="text-xs px-2 py-1 rounded-full font-medium bg-red-100 text-red-700">
                                Checklist incomplete
                            </span>
                        )}
                    </div>
                </div>

                {/* Details */}
                <div className="bg-white rounded-lg shadow p-4">
                    <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Details</h2>
                    <div className="space-y-3">
                        {job.maintenance_item && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Maintenance</span>
                                <span className="text-sm text-gray-900">
                                    {job.maintenance_item.asset.name} → {job.maintenance_item.name}
                                </span>
                            </div>
                        )}
                        {job.asset && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Asset</span>
                                <Link href={route('assets.show', job.asset.id)} className="text-sm text-green-600">
                                    {job.asset.name}
                                </Link>
                            </div>
                        )}
                        {job.scheduled_date && (
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-gray-500">Scheduled</span>
                                <span className="flex items-center gap-2">
                                    <span className="text-sm text-gray-900">
                                        {formatDate(job.scheduled_date.slice(0, 10), { weekday: 'short', year: false })}
                                    </span>
                                    <a
                                        href={route('jobs.calendar', job.id)}
                                        className="text-xs text-green-600 border border-green-600 rounded-full px-2 py-0.5"
                                    >
                                        Add to Calendar
                                    </a>
                                </span>
                            </div>
                        )}
                        {job.estimated_hours && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Estimated Hours</span>
                                <span className="text-sm text-gray-900">{job.estimated_hours}h</span>
                            </div>
                        )}
                        {job.budget && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Budget</span>
                                <span className="text-sm text-gray-900">${job.budget}</span>
                            </div>
                        )}
                        {job.latitude && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Location</span>
                                <span className="text-sm text-gray-900">
                                    {job.zone && `${job.zone.name} — `}
                                    <button
                                        type="button"
                                        onClick={() => setShowLocationModal(true)}
                                        className="text-green-600"
                                    >
                                        View on map
                                    </button>
                                </span>
                            </div>
                        )}
                        {job.description && (
                            <div>
                                <p className="text-sm text-gray-500 mb-1">Description</p>
                                <p className="text-sm text-gray-900">{job.description}</p>
                            </div>
                        )}
                    </div>
                </div>

                {job.latitude && (
                    <Modal show={showLocationModal} onClose={closeLocationModal} maxWidth="lg">
                        <div className="p-4">
                            <div className="flex items-center justify-between mb-3">
                                <h3 className="text-sm font-medium text-gray-700">Location</h3>
                                <div className="flex items-center gap-3">
                                    {editingLocation ? (
                                        <>
                                            <button onClick={saveLocation} className="text-sm text-green-600 font-medium">Save</button>
                                            <button onClick={cancelEditLocation} className="text-sm text-gray-500">Cancel</button>
                                        </>
                                    ) : (
                                        <>
                                            <button onClick={() => setEditingLocation(true)} className="text-sm text-green-600">Edit</button>
                                            <button onClick={closeLocationModal} className="text-sm text-gray-500">Close</button>
                                        </>
                                    )}
                                </div>
                            </div>
                            {showLocationModal && (
                                <JobLocationMap
                                    key={editingLocation ? 'edit' : 'view'}
                                    job={job}
                                    propertyBoundary={job.property?.shape?.coordinates}
                                    editable={editingLocation}
                                    onDragEnd={(lat, lng) => setPendingLocation({ lat, lng })}
                                />
                            )}
                            {editingLocation && (
                                <p className="text-xs text-gray-400 mt-2">Drag the pin to move it.</p>
                            )}
                        </div>
                    </Modal>
                )}

                {/* Photos */}
                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Photos</h2>
                        <button
                            onClick={() => fileInput.current.click()}
                            disabled={uploading}
                            className="text-sm px-3 py-1 bg-green-600 text-white rounded-lg disabled:opacity-50"
                        >
                            + Add Photo
                        </button>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/*"
                            multiple
                            onChange={uploadPhotos}
                            className="hidden"
                        />

                    </div>

                    {job.photos && job.photos.length > 0 && (
                        <div className="grid grid-cols-3 gap-2">
                            {job.photos.map((photo) => (
                                <div key={photo.id} className="relative">
                                    <img
                                        src={photo.url}
                                        className="w-full h-24 object-cover rounded-lg"
                                    />
                                    <button
                                        onClick={() => destroyPhoto(photo.id)}
                                        className="absolute top-1 right-1 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                    >
                                        ×
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Checklists */}
                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Checklists</h2>
                        <button
                            onClick={() => setShowChecklistPicker(true)}
                            className="text-sm px-3 py-1 bg-green-600 text-white rounded-lg"
                        >
                            + Add Checklist
                        </button>
                    </div>

                    {job.checklists && job.checklists.length > 0 && (
                        <div className="space-y-2">
                            {job.checklists.map((checklist) => {
                                const checkedCount = checklist.items.filter((item) => item.is_checked).length;
                                return (
                                    <Link
                                        key={checklist.id}
                                        href={route('checklists.show', checklist.id)}
                                        className="flex items-center justify-between gap-2 border border-gray-200 rounded-lg p-3 hover:bg-gray-50"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm text-gray-900">{checklist.name}</p>
                                            <p className="text-xs text-gray-500 mt-0.5">{CHECKLIST_TYPE_LABELS[checklist.type]}</p>
                                        </div>
                                        <span className={`text-xs px-2 py-1 rounded-full font-medium flex-shrink-0 ${
                                            checklist.status === 'complete' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                        }`}>
                                            {checkedCount}/{checklist.items.length} checked
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </div>

                <Modal show={showChecklistPicker} onClose={() => setShowChecklistPicker(false)} maxWidth="lg">
                    <div className="p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-sm font-medium text-gray-700">Checklists</h3>
                            <button onClick={() => setShowChecklistPicker(false)} className="text-sm text-gray-500">Close</button>
                        </div>
                        {checklistTemplates && checklistTemplates.length > 0 ? (
                            <div className="max-h-96 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                                {checklistTemplates.map((template) => {
                                    const attached = attachedChecklistFor(template.id);
                                    return (
                                        <label key={template.id} className="flex items-center gap-3 px-3 py-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={Boolean(attached)}
                                                onChange={() => toggleChecklistTemplate(template)}
                                                className="rounded text-green-600 focus:ring-green-500"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm text-gray-900">{template.name}</p>
                                                <p className="text-xs text-gray-500">{CHECKLIST_TYPE_LABELS[template.type]}</p>
                                            </div>
                                        </label>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-400">
                                No checklist templates yet. Add one from{' '}
                                <Link href={route('manage.index')} className="text-green-600">Manage</Link>.
                            </p>
                        )}
                    </div>
                </Modal>

                {/* Finish job */}
                {!job.job_status?.is_finished_default && (
                    <button
                        onClick={finishJob}
                        className="w-full py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700"
                    >
                        Finish Job
                    </button>
                )}

                {/* Delete job */}
                <button
                    onClick={destroy}
                    className="w-full py-3 text-red-600 border border-red-300 rounded-lg text-sm"
                >
                    Delete Job
                </button>

                {/* Activity: created by + seen by */}
                {(job.user || (seenBy && seenBy.length > 0)) && (
                    <div className="bg-white rounded-lg shadow p-4">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Activity</h2>
                        <div className="space-y-2">
                            {job.user && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Created by</span>
                                    <span className="text-sm text-gray-900">{job.user.name}</span>
                                </div>
                            )}
                            {seenBy && seenBy.length > 0 && (
                                <>
                                    <p className="text-xs text-gray-400 pt-1">Viewed by</p>
                                    {seenBy.map((view) => (
                                        <div key={view.user_id} className="flex justify-between">
                                            <span className="text-sm text-gray-900">{view.user_name}</span>
                                            <span className="text-xs text-gray-500">{formatViewedAt(view.viewed_at)}</span>
                                        </div>
                                    ))}
                                </>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {showDeleteOptions && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    onClick={() => setShowDeleteOptions(false)}
                >
                    <div
                        className="bg-white rounded-lg shadow-lg max-w-sm w-full p-5"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h3 className="text-base font-semibold text-gray-900 mb-2">This job repeats</h3>
                        <p className="text-sm text-gray-600 mb-4">
                            Do you want to delete just this instance, or stop it repeating altogether?
                        </p>
                        <div className="space-y-2">
                            <button
                                onClick={() => destroyWithChoice(false)}
                                className="w-full py-2 border border-gray-300 text-gray-700 rounded-lg text-sm"
                            >
                                Just delete this job
                            </button>
                            <button
                                onClick={() => destroyWithChoice(true)}
                                className="w-full py-2 bg-red-600 text-white rounded-lg text-sm"
                            >
                                Delete this and stop repeating
                            </button>
                            <button
                                onClick={() => setShowDeleteOptions(false)}
                                className="w-full py-2 text-gray-500 text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}