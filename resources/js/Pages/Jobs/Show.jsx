import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

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

export default function Show({ job }) {
    const fileInput = useRef(null);
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.addPhoto && fileInput.current) {
            fileInput.current.click();
        }
    }, [flash]);
    
    const destroy = () => {
        if (confirm('Are you sure you want to delete this job?')) {
            router.delete(route('jobs.destroy', job.id));
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

        router.post(route('photos.store', job.id), formData, {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={job.name} />

            <div className="max-w-lg mx-auto space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <Link href={route('jobs.index')} className="text-green-600 text-sm">
                        ← Jobs
                    </Link>
                    <Link
                        href={route('jobs.edit', job.id)}
                        className="text-sm px-3 py-1 border border-green-600 text-green-600 rounded-lg"
                    >
                        Edit
                    </Link>
                </div>

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

                    {job.photos && job.photos.length === 0 ? (
                        <button
                            onClick={() => fileInput.current.click()}
                            className="w-full border-2 border-dashed border-gray-300 rounded-lg p-8 text-center text-gray-400"
                        >
                            <p className="text-2xl mb-2">📷</p>
                            <p className="text-sm">Tap to add a photo</p>
                        </button>
                    ) : (
                        <div className="grid grid-cols-3 gap-2">
                            {job.photos.map((photo) => (
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

                {/* Delete job */}
                <button
                    onClick={destroy}
                    className="w-full py-3 text-red-600 border border-red-300 rounded-lg text-sm"
                >
                    Delete Job
                </button>
            </div>
        </AuthenticatedLayout>
    );
}