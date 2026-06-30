import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export default function Show({ session, durationInHours, billingAmount }) {
    const fileInput = useRef(null);
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.addPhoto && fileInput.current) {
            fileInput.current.click();
        }
    }, [flash]);

    const stop = () => {
        router.post(route('work-sessions.stop', session.id));
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

    const uploadPhotos = (e) => {
        const files = e.target.files;
        if (!files.length) return;

        const formData = new FormData();
        Array.from(files).forEach(file => formData.append('photos[]', file));
        formData.append('work_session_id', session.id);

        router.post(route('photos.store-session', session.id), formData, {
            forceFormData: true,
        });
    };

    const formatTime = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const formatDate = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleDateString();
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
                    <Link
                        href={route('work-sessions.edit', session.id)}
                        className="text-sm px-3 py-1 border border-green-600 text-green-600 rounded-lg"
                    >
                        Edit
                    </Link>
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

                {/* Photos */}
                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Photos</h2>
                        <button
                            onClick={() => fileInput.current.click()}
                            className="text-sm px-3 py-1 bg-green-600 text-white rounded-lg"
                        >
                            + Add Photo
                        </button>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            multiple
                            onChange={uploadPhotos}
                            className="hidden"
                        />
                    </div>

                    {session.photos && session.photos.length === 0 ? (
                        <button
                            onClick={() => fileInput.current.click()}
                            className="w-full border-2 border-dashed border-gray-300 rounded-lg p-8 text-center text-gray-400"
                        >
                            <p className="text-2xl mb-2">📷</p>
                            <p className="text-sm">Tap to add a photo</p>
                        </button>
                    ) : (
                        <div className="grid grid-cols-3 gap-2">
                            {session.photos.map((photo) => (
                                <div key={photo.id} className="relative">
                                    <img
                                        src={`/storage/${photo.file}`}
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