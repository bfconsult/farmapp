import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WaypointTrail from '@/Components/WaypointTrail';
import { Head, Link, useForm } from '@inertiajs/react';
import { toLocalInputValue, fromLocalInputValue } from '@/dateInput';

export default function Edit({ session, plannedJobs, waypoints }) {
    const { data, setData, patch, processing, errors, transform } = useForm({
        description: session.description ?? '',
        farm_job_id: session.farm_job_id ?? '',
        started_at: toLocalInputValue(session.started_at),
        ended_at: toLocalInputValue(session.ended_at),
    });

    const submit = (e) => {
        e.preventDefault();
        transform((data) => ({
            ...data,
            started_at: fromLocalInputValue(data.started_at),
            ended_at: fromLocalInputValue(data.ended_at),
        }));
        patch(route('work-sessions.update', session.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Edit Work Session" />

            <div className="max-w-lg mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <Link href={route('work-sessions.show', session.id)} className="text-green-600 text-sm">
                        ← Back
                    </Link>
                    <h1 className="text-xl font-semibold text-gray-900">Edit Session</h1>
                </div>

                <WaypointTrail waypoints={waypoints} />

                <form onSubmit={submit} className="space-y-4">
                    <div className="bg-white rounded-lg shadow p-4 space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Started At</label>
                            <input
                                type="datetime-local"
                                value={data.started_at}
                                onChange={(e) => setData('started_at', e.target.value)}
                                className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                            />
                            {errors.started_at && <p className="mt-1 text-sm text-red-600">{errors.started_at}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Ended At</label>
                            <input
                                type="datetime-local"
                                value={data.ended_at}
                                onChange={(e) => setData('ended_at', e.target.value)}
                                className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                            />
                            {errors.ended_at && <p className="mt-1 text-sm text-red-600">{errors.ended_at}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Link to Planned Job (optional)</label>
                            <select
                                value={data.farm_job_id}
                                onChange={(e) => setData('farm_job_id', e.target.value)}
                                className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                            >
                                <option value="">
                                    {session.source === 'auto_tracked'
                                        ? 'Auto-tracked visit (no planned job)'
                                        : 'Ad-hoc work (no planned job)'}
                                </option>
                                {plannedJobs.map((job) => (
                                    <option key={job.id} value={job.id}>{job.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                            />
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <Link
                            href={route('work-sessions.show', session.id)}
                            className="flex-1 py-4 border border-gray-300 text-gray-700 rounded-lg text-base text-center"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex-1 py-4 bg-green-600 text-white rounded-lg text-base font-medium disabled:opacity-50"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}