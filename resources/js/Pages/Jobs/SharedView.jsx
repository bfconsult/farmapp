import { Head } from '@inertiajs/react';
import { formatDate } from '@/dateInput';
import { pillBadgeClass } from '@/Utils/pillColors';

export default function SharedView({ job, logoUrl }) {
    return (
        <>
            <Head title={job.name} />

            <div className="min-h-screen bg-gray-100 flex justify-center p-4">
                <div className="max-w-lg w-full space-y-4 mt-8">
                    <div className="flex items-center justify-center gap-2 text-gray-400 text-sm mb-2">
                        <img src={logoUrl} className="w-5 h-5" alt="" />
                        <span>FieldWerkz</span>
                    </div>

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
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow p-4 space-y-3">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Details</h2>
                        {job.property && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Property</span>
                                <span className="text-sm text-gray-900">{job.property.name}</span>
                            </div>
                        )}
                        {job.scheduled_date && (
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Scheduled</span>
                                <span className="text-sm text-gray-900">
                                    {formatDate(job.scheduled_date.slice(0, 10), { weekday: 'short', year: false })}
                                </span>
                            </div>
                        )}
                        {job.description && (
                            <div>
                                <p className="text-sm text-gray-500 mb-1">Description</p>
                                <p className="text-sm text-gray-900 whitespace-pre-line">{job.description}</p>
                            </div>
                        )}
                    </div>

                    {job.photos.length > 0 && (
                        <div className="bg-white rounded-lg shadow p-4">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Photos</h2>
                            <div className="grid grid-cols-3 gap-2">
                                {job.photos.map((photo) => (
                                    <img
                                        key={photo.id}
                                        src={photo.url}
                                        className="w-full h-24 object-cover rounded-lg"
                                        alt=""
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    <p className="text-center text-xs text-gray-400 pt-2 pb-8">
                        Shared view — sign in to your FieldWerkz account to see more.
                    </p>
                </div>
            </div>
        </>
    );
}
