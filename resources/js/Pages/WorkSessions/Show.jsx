import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WaypointTrail from '@/Components/WaypointTrail';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { compressImageFiles } from '@/imageCompression';
import { formatDate as formatDateDayFirst } from '@/dateInput';

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

export default function Show({ session, durationInHours, billingAmount, waypoints }) {
    const cameraInput = useRef(null);
    const galleryInput = useRef(null);
    const { flash } = usePage().props;
    const [uploading, setUploading] = useState(false);

    useEffect(() => {
        if (flash?.addPhoto && cameraInput.current) {
            cameraInput.current.click();
        }
    }, [flash]);

    const stop = () => {
        router.post(route('work-sessions.stop', session.id));
    };

    const finalise = () => {
        if (confirm('Finalise this work session?')) {
            router.post(route('work-sessions.finalise', session.id));
        }
    };

    const revertToDraft = () => {
        if (confirm('Revert this session back to draft? It will become editable again.')) {
            router.post(route('work-sessions.revert-to-draft', session.id));
        }
    };

    const destroy = () => {
        if (confirm('Are you sure you want to delete this work session?')) {
            router.delete(route('work-sessions.destroy', session.id));
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

    return (
        <AuthenticatedLayout>
            <Head title="Work Session" />

            <div className="max-w-lg mx-auto space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <Link href={route('work-sessions.index')} className="text-green-600 text-sm">
                        ← Work
                    </Link>
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
                        <p className="text-sm font-medium mb-3">⏱ Session in progress...</p>
                        <button
                            onClick={stop}
                            className="w-full py-3 bg-white text-green-600 rounded-lg font-medium text-base"
                        >
                            Stop Work
                        </button>
                    </div>
                )}

                {/* Session details */}
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
                                <span className="text-sm font-medium text-gray-900">{durationInHours}h</span>
                            </div>
                        )}
                        {billingAmount && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Billing Amount</span>
                                <span className="text-sm font-medium text-green-700">${billingAmount}</span>
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
                        {session.description && (
                            <div>
                                <p className="text-sm text-gray-500 mb-1">Description</p>
                                <p className="text-sm text-gray-900">{session.description}</p>
                            </div>
                        )}
                    </div>
                </div>

                <WaypointTrail waypoints={waypoints} />

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
                                className="text-sm px-3 py-1 bg-green-600 text-white rounded-lg disabled:opacity-50"
                            >
                                {uploading ? 'Uploading…' : 'Take Photo'}
                            </button>
                            <button
                                onClick={() => galleryInput.current.click()}
                                disabled={uploading}
                                className="text-sm px-3 py-1 bg-white border border-gray-300 text-gray-700 rounded-lg disabled:opacity-50"
                            >
                                Gallery
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
                            {session.photos.map((photo) => (
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

                {/* Delete */}
                <button
                    onClick={destroy}
                    className="w-full py-3 text-red-600 border border-red-300 rounded-lg text-sm"
                >
                    Delete Session
                </button>
            </div>
        </AuthenticatedLayout>
    );
}