import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WaypointTrail from '@/Components/WaypointTrail';
import BackLink from '@/Components/BackLink';
import NoteRow from '@/Components/NoteRow';
import AddNoteForm from '@/Components/AddNoteForm';
import PhotoLightbox from '@/Components/PhotoLightbox';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { compressImageFiles } from '@/imageCompression';
import {
    toLocalInputValue,
    fromLocalInputValue,
    splitLocalValue,
    joinLocalValue,
    timeOptionsForBlock,
    floorToBillingBlock,
    formatDate as formatDateDayFirst,
} from '@/dateInput';
import { formatNumber } from '@/numberFormat';

const STATUS_LABELS = {
    draft: 'Draft',
    finalised: 'Finalised',
    approved: 'Approved',
};

const STATUS_COLORS = {
    draft: 'bg-gray-100 text-gray-600',
    finalised: 'bg-blue-100 text-blue-700',
    approved: 'bg-green-100 text-green-700',
};

export default function Show({ session, durationInHours, billingAmount, waypoints, zones, from, plannedJobs, billingBlockMinutes }) {
    const cameraInput = useRef(null);
    const galleryInput = useRef(null);
    const { currentUserRole } = usePage().props;
    const canManage = currentUserRole === 'admin' || currentUserRole === 'manager';
    const canCreateNote = canManage || currentUserRole === 'worker';
    const [uploading, setUploading] = useState(false);
    const [addingNote, setAddingNote] = useState(false);
    const [lightboxIndex, setLightboxIndex] = useState(null);

    // Only relevant while the session is still active - see the "Start
    // Time" / "Pick a Job" cards below, which replace Session Details for
    // that case (it's mostly empty fields until the session has ended).
    const [editingStart, setEditingStart] = useState(false);
    const [startDate, setStartDate] = useState('');
    const [startTime, setStartTime] = useState('');
    const [jobId, setJobId] = useState(session.farm_job_id ?? '');
    const startTimeOptions = useMemo(() => timeOptionsForBlock(billingBlockMinutes), [billingBlockMinutes]);
    const hasLocation = session.latitude && session.longitude;
    const [locating, setLocating] = useState(!hasLocation);

    // The session may have started without a location fix (denied/slow GPS
    // at the time) - keep watching in the background while active and save
    // one the moment it comes in, rather than leaving it unavailable for
    // good just because the first attempt on the Start page missed it.
    useEffect(() => {
        if (session.ended_at || hasLocation || !navigator.geolocation) {
            setLocating(false);
            return;
        }

        setLocating(true);
        const watchId = navigator.geolocation.watchPosition(
            (position) => {
                navigator.geolocation.clearWatch(watchId);
                setLocating(false);
                router.patch(route('work-sessions.update', session.id), {
                    started_at: session.started_at,
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                }, { preserveScroll: true, preserveState: true });
            },
            (error) => {
                // Permission denial won't ever resolve on its own - stop
                // asking. A timeout or momentary fix failure might still
                // succeed on a later attempt, so keep the watch running.
                if (error.code === error.PERMISSION_DENIED) {
                    navigator.geolocation.clearWatch(watchId);
                    setLocating(false);
                }
            },
            { enableHighAccuracy: true, timeout: 20000 }
        );

        return () => navigator.geolocation.clearWatch(watchId);
    }, [session.id, session.ended_at, hasLocation]);

    // Reached via Manage -> Work Sessions rather than the self-service Work
    // tab (see WorkSessionController::cameFromManage()) - Back has to know
    // this explicitly since the two entry points share this same page.
    const backHref = from === 'manage' ? route('manage.work-sessions') : route('work-sessions.index');
    const backLabel = from === 'manage' ? 'Manage' : 'Work';

    const stop = () => {
        router.post(route('work-sessions.stop', session.id));
    };

    const beginEditStart = () => {
        const local = splitLocalValue(toLocalInputValue(floorToBillingBlock(new Date(session.started_at), billingBlockMinutes).toISOString()));
        setStartDate(local.date);
        setStartTime(local.time);
        setEditingStart(true);
    };

    const saveStart = () => {
        router.patch(route('work-sessions.update', session.id), {
            started_at: fromLocalInputValue(joinLocalValue(startDate, startTime)),
        }, {
            preserveScroll: true,
            onSuccess: () => setEditingStart(false),
        });
    };

    const saveJob = (newJobId) => {
        setJobId(newJobId);
        router.patch(route('work-sessions.update', session.id), {
            started_at: session.started_at,
            farm_job_id: newJobId || null,
        }, { preserveScroll: true });
    };

    const finalise = () => {
        if (confirm('Finalise this work session?')) {
            router.post(route('work-sessions.finalise', session.id));
        }
    };

    const revertToDraft = () => {
        if (confirm('Revert this session back to draft? It will become editable again.')) {
            router.post(route('work-sessions.revert-to-draft', from ? { workSession: session.id, from } : session.id));
        }
    };

    const destroy = () => {
        if (confirm('Are you sure you want to delete this work session?')) {
            router.delete(route('work-sessions.destroy', from ? { work_session: session.id, from } : session.id));
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
        formData.append('work_session_id', session.id);

        router.post(route('photos.store-session', session.id), formData, {
            forceFormData: true,
            onFinish: () => setUploading(false),
            onError: () => alert('Photo upload failed. Please try again with a smaller photo.'),
        });
    };

    const formatTime = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const formatDate = (datetime) => {
        if (!datetime) return '—';
        return formatDateDayFirst(datetime);
    };

    const locationBadge = (
        <span className="inline-flex items-center gap-1 font-normal text-gray-400">
            <svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
            {hasLocation ? 'Location saved' : locating ? 'Locating…' : 'Location unavailable'}
        </span>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Work Session" />

            <div className="max-w-lg mx-auto mt-2 space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <BackLink href={backHref}>{backLabel}</BackLink>
                    {session.status === 'draft' && (
                        <Link
                            href={route('work-sessions.edit', session.id)}
                            className="text-sm px-3 py-1 border border-green-600 text-green-600 rounded-lg"
                        >
                            Edit
                        </Link>
                    )}
                </div>

                {/* Active session banner */}
                {!session.ended_at && (
                    <div className="bg-green-600 rounded-lg p-4 text-white">
                        <p className="text-sm font-medium mb-3 flex items-center gap-1.5">
                            <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Session in progress...
                        </p>
                        <button
                            onClick={stop}
                            className="w-full py-3 bg-white text-green-600 rounded-lg font-medium text-base"
                        >
                            Stop Work
                        </button>
                    </div>
                )}

                {/* Session details */}
                {session.ended_at ? (
                    <div className="bg-white rounded-lg shadow p-4">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">
                            Session Details
                        </h2>
                        <div className="space-y-3">
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Status</span>
                                <span className={`text-xs px-2 py-1 rounded-full font-medium ${STATUS_COLORS[session.status]}`}>
                                    {STATUS_LABELS[session.status]}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Date</span>
                                <span className="text-sm text-gray-900">{formatDate(session.started_at)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Started</span>
                                <span className="text-sm text-gray-900">{formatTime(session.started_at)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Ended</span>
                                <span className="text-sm text-gray-900">{formatTime(session.ended_at)}</span>
                            </div>
                            {durationInHours && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Duration</span>
                                    <span className="text-sm font-medium text-gray-900">{formatNumber(durationInHours)}h</span>
                                </div>
                            )}
                            {billingAmount && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Billing Amount</span>
                                    <span className="text-sm font-medium text-green-700">${formatNumber(billingAmount)}</span>
                                </div>
                            )}
                            {session.farm_job && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Planned Job</span>
                                    <Link
                                        href={route('jobs.show', session.farm_job.id)}
                                        className="text-sm text-green-600"
                                    >
                                        {session.farm_job.name}
                                    </Link>
                                </div>
                            )}
                            {session.asset && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Asset</span>
                                    <Link
                                        href={route('assets.show', session.asset.id)}
                                        className="text-sm text-green-600"
                                    >
                                        {session.asset.name}
                                    </Link>
                                </div>
                            )}
                            {session.description && (
                                <div>
                                    <p className="text-sm text-gray-500 mb-1">Description</p>
                                    <p className="text-sm text-gray-900">{session.description}</p>
                                </div>
                            )}
                        </div>
                    </div>
                ) : (
                    <>
                        {/* Start time */}
                        <div className="bg-white rounded-lg shadow p-4">
                            {!editingStart ? (
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-xs text-gray-500 mb-1 flex items-center gap-2">
                                            <span>Start Time</span>
                                            {locationBadge}
                                        </p>
                                        <p className="text-lg font-medium text-gray-900">{formatTime(session.started_at)}</p>
                                    </div>
                                    <button type="button" onClick={beginEditStart} className="text-sm text-green-600 font-medium">
                                        Change
                                    </button>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <div className="grid grid-cols-2 gap-2">
                                        <input
                                            type="date"
                                            value={startDate}
                                            onChange={(e) => setStartDate(e.target.value)}
                                            className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                        />
                                        <select
                                            value={startTime}
                                            onChange={(e) => setStartTime(e.target.value)}
                                            className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                        >
                                            {startTimeOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setEditingStart(false)}
                                            className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            onClick={saveStart}
                                            className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm font-medium"
                                        >
                                            Save
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Pick a job */}
                        <div className="bg-white rounded-lg shadow p-4">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Link to Planned Job (optional)
                            </label>
                            <select
                                value={jobId}
                                onChange={(e) => saveJob(e.target.value)}
                                className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                            >
                                <option value="">Ad-hoc work (no planned job)</option>
                                {plannedJobs.map((job) => (
                                    <option key={job.id} value={job.id}>{job.name}</option>
                                ))}
                            </select>
                        </div>
                    </>
                )}

                <WaypointTrail waypoints={waypoints} zones={zones} workSessionId={session.id} />

                {/* Finalise */}
                {session.status === 'draft' && session.ended_at && (
                    <>
                        {session.has_conflict && (
                            <p className="text-sm text-red-600 mb-2 font-medium text-center">
                                Session time conflicts with an existing session - please resolve before finalising
                            </p>
                        )}
                        <button
                            onClick={finalise}
                            disabled={session.has_conflict}
                            className="w-full py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 disabled:hover:bg-green-600 disabled:cursor-not-allowed"
                        >
                            Finalise Session
                        </button>
                    </>
                )}

                {/* Revert to draft */}
                {session.status === 'finalised' && (
                    <button
                        onClick={revertToDraft}
                        className="w-full py-3 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50"
                    >
                        Revert to Draft
                    </button>
                )}

                {/* Photos */}
                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Photos</h2>
                        <div className="flex gap-2">
                            <button
                                onClick={() => cameraInput.current.click()}
                                disabled={uploading}
                                aria-label="Take photo"
                                className="p-2 bg-green-600 text-white rounded-lg disabled:opacity-50"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                            <button
                                onClick={() => galleryInput.current.click()}
                                disabled={uploading}
                                aria-label="Choose from gallery"
                                className="p-2 bg-white border border-gray-300 text-gray-700 rounded-lg disabled:opacity-50"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" />
                                </svg>
                            </button>
                        </div>
                        <input
                            ref={cameraInput}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            onChange={uploadPhotos}
                            className="hidden"
                        />
                        <input
                            ref={galleryInput}
                            type="file"
                            accept="image/*"
                            multiple
                            onChange={uploadPhotos}
                            className="hidden"
                        />
                    </div>

                    {session.photos && session.photos.length === 0 ? (
                        <button
                            onClick={() => cameraInput.current.click()}
                            className="w-full border-2 border-dashed border-gray-300 rounded-lg p-8 text-center text-gray-400"
                        >
                            <svg className="w-8 h-8 mx-auto mb-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <p className="text-sm">Tap to add a photo</p>
                        </button>
                    ) : (
                        <div className="grid grid-cols-3 gap-2">
                            {session.photos.map((photo, i) => (
                                <div key={photo.id} className="relative">
                                    <img
                                        src={photo.url}
                                        onClick={() => setLightboxIndex(i)}
                                        className="w-full h-24 object-cover rounded-lg cursor-pointer"
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

                {/* Notes */}
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Notes</h2>
                        {canCreateNote && !addingNote && (
                            <button onClick={() => setAddingNote(true)} className="text-xs text-green-600 font-medium">
                                + Add
                            </button>
                        )}
                    </div>
                    {session.notes && session.notes.length > 0 ? (
                        <div className="divide-y divide-gray-100">
                            {session.notes.map((note) => (
                                <NoteRow key={note.id} note={note} canManage={canManage} canCreate={canCreateNote} />
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-gray-400 p-4">No notes yet.</p>
                    )}
                    {addingNote && (
                        <AddNoteForm parentField="work_session_id" parentId={session.id} onClose={() => setAddingNote(false)} />
                    )}
                </div>

                {/* Delete */}
                <button
                    onClick={destroy}
                    className="w-full py-3 text-red-600 border border-red-300 rounded-lg text-sm"
                >
                    Delete Session
                </button>
            </div>

            {session.photos && session.photos.length > 0 && (
                <PhotoLightbox
                    photos={session.photos}
                    index={lightboxIndex}
                    onClose={() => setLightboxIndex(null)}
                    onIndexChange={setLightboxIndex}
                />
            )}
        </AuthenticatedLayout>
    );
}