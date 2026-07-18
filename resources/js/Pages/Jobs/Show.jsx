import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { compressImageFiles } from '@/imageCompression';
import { formatDate } from '@/dateInput';

const STATUS_COLORS = {
    'Pending': 'bg-yellow-100 text-yellow-800',
    'In Progress': 'bg-blue-100 text-blue-800',
    'Completed': 'bg-green-100 text-green-800',
    'Cancelled': 'bg-gray-100 text-gray-800',
};

const PRIORITY_COLORS = {
    'Low': 'bg-gray-100 text-gray-600',
    'Medium': 'bg-yellow-100 text-yellow-700',
    'High': 'bg-orange-100 text-orange-700',
    'Critical': 'bg-red-100 text-red-700',
};

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

export default function Show({ job, checklistTemplates, seenBy }) {
    const fileInput = useRef(null);
    const { flash, currentUserRole } = usePage().props;
    const [uploading, setUploading] = useState(false);
    const [showDeleteOptions, setShowDeleteOptions] = useState(false);
    const [showShare, setShowShare] = useState(false);
    const [copied, setCopied] = useState(false);
    const [selectedTemplateId, setSelectedTemplateId] = useState('');

    const canAttachChecklist = currentUserRole !== 'approver';
    const hasIncompleteChecklists = (job.checklists ?? []).some((c) => c.status === 'incomplete');

    const attachChecklist = () => {
        router.post(route('checklists.store'), {
            farm_job_id: job.id,
            checklist_template_id: selectedTemplateId,
        }, { preserveScroll: true, onSuccess: () => setSelectedTemplateId('') });
    };

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
                            <span className={`text-xs px-2 py-1 rounded-full font-medium ${STATUS_COLORS[job.job_status.name] ?? 'bg-gray-100 text-gray-600'}`}>
                                {job.job_status.name}
                            </span>
                        )}
                        {job.priority && (
                            <span className={`text-xs px-2 py-1 rounded-full font-medium ${PRIORITY_COLORS[job.priority.name] ?? 'bg-gray-100 text-gray-600'}`}>
                                {job.priority.name}
                            </span>
                        )}
                        {job.job_type && (
                            <span className="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
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
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-500">Property</span>
                            <span className="text-sm text-gray-900">{job.property?.name}</span>
                        </div>
                        {job.user && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Created by</span>
                                <span className="text-sm text-gray-900">{job.user.name}</span>
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
                                    {parseFloat(job.latitude).toFixed(5)}, {parseFloat(job.longitude).toFixed(5)}
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
                            capture="environment"
                            multiple
                            onChange={uploadPhotos}
                            className="hidden"
                        />

                    </div>

                    {job.photos && job.photos.length === 0 ? (
                        <button
                            onClick={() => fileInput.current.click()}
                            disabled={uploading}
                            className="w-full border-2 border-dashed border-gray-300 rounded-lg p-8 text-center text-gray-400 disabled:opacity-50"
                        >
                            <svg className="w-8 h-8 mx-auto mb-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <p className="text-sm">Tap to add a photo</p>
                        </button>
                    ) : (
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
                    <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Checklists</h2>

                    {job.checklists && job.checklists.length > 0 && (
                        <div className="space-y-2 mb-3">
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

                    {canAttachChecklist && checklistTemplates?.length > 0 && (
                        <div className="flex gap-2">
                            <select
                                value={selectedTemplateId}
                                onChange={(e) => setSelectedTemplateId(e.target.value)}
                                className="flex-1 border-gray-300 rounded-lg text-sm"
                            >
                                <option value="">Select a checklist…</option>
                                {checklistTemplates.map((template) => (
                                    <option key={template.id} value={template.id}>{template.name}</option>
                                ))}
                            </select>
                            <button
                                onClick={attachChecklist}
                                disabled={!selectedTemplateId}
                                className="px-3 py-1 bg-green-600 text-white rounded-lg text-sm disabled:opacity-50"
                            >
                                Attach
                            </button>
                        </div>
                    )}

                    {(!job.checklists || job.checklists.length === 0) && (!canAttachChecklist || !checklistTemplates?.length) && (
                        <p className="text-sm text-gray-400">No checklists attached.</p>
                    )}
                </div>

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

                {/* Seen by */}
                {seenBy && seenBy.length > 0 && (
                    <div className="bg-white rounded-lg shadow p-4">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Seen by</h2>
                        <div className="space-y-2">
                            {seenBy.map((view) => (
                                <div key={view.user_id} className="flex justify-between">
                                    <span className="text-sm text-gray-900">{view.user_name}</span>
                                    <span className="text-xs text-gray-500">{formatViewedAt(view.viewed_at)}</span>
                                </div>
                            ))}
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