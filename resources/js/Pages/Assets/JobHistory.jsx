import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { formatDate } from '@/dateInput';
import { pillBadgeClass } from '@/Utils/pillColors';

export default function JobHistory({ asset, jobs }) {
    return (
        <AuthenticatedLayout>
            <Head title={`${asset.name} - Job History`} />

            <div className="max-w-lg mx-auto space-y-4">
                <div className="flex items-center justify-between">
                    <Link href={route('assets.show', asset.id)} className="text-green-600 text-sm">
                        ← {asset.name}
                    </Link>
                </div>

                <h1 className="text-lg font-semibold text-gray-900">Job History</h1>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    {jobs.length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No jobs from this asset's maintenance items yet.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {jobs.map((job) => (
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
