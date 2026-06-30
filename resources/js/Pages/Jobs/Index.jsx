import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

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
export default function Index({ jobs, counts, currentStatus, currentOrder, currentView, jobStatuses }) {
    const { currentProperty } = usePage().props;

    const filterByStatus = (status) => {
        router.get(route('jobs.index'), {
            status: currentStatus === status ? null : status,
            order: currentOrder,
            view: currentView,
        }, { preserveState: true });
    };

    const changeOrder = (e) => {
        router.get(route('jobs.index'), {
            status: currentStatus,
            order: e.target.value,
            view: currentView,
        }, { preserveState: true });
    };

    const changeView = (view) => {
        router.get(route('jobs.index'), {
            view,
            order: currentOrder,
        }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout title="Jobs">
            <Head title="Jobs" />

            <div className="max-w-lg mx-auto mt-2">


                {/* View toggle */}
                <div className="flex gap-1 bg-gray-100 rounded-lg p-1 mb-4">
                    {['active', 'completed', 'all'].map((view) => (
                        <button
                            key={view}
                            onClick={() => changeView(view)}
                            className={`flex-1 py-2 rounded-md text-sm font-medium capitalize transition-all ${
                                currentView === view
                                    ? 'bg-white text-gray-900 shadow'
                                    : 'text-gray-500'
                            }`}
                        >
                            {view}
                        </button>
                    ))}
                </div>

                {/* Status counts */}
                <div className="grid gap-1 mb-3" style={{ gridTemplateColumns: `repeat(${jobStatuses.length}, 1fr)` }}>
                {jobStatuses.map((status) => (
                    <button
                        key={status.id}
                        onClick={() => filterByStatus(status.name)}
                        className={`rounded-lg p-1.5 text-center border-2 transition-all ${
                            currentStatus === status.name
                                ? 'border-green-500 bg-green-50'
                                : 'border-transparent bg-white'
                        }`}
                    >
                        <div className="text-base font-bold text-gray-900">
                            {counts[status.name] ?? 0}
                        </div>
                        <div className="text-xs text-gray-500 leading-tight">
                            {status.name}
                        </div>
                    </button>
                ))}
            </div>

                {/* Order selector */}
                <div className="flex items-center justify-between mb-4">
                    <span className="text-sm text-gray-500">
                        {currentStatus ? `Filtered: ${currentStatus}` : 'All jobs'}
                        {currentStatus && (
                            <button
                                onClick={() => filterByStatus(currentStatus)}
                                className="ml-2 text-green-600"
                            >
                                ✕ Clear
                            </button>
                        )}
                    </span>
                    <select
                        value={currentOrder}
                        onChange={changeOrder}
                        className="text-sm border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500"
                    >
                        <option value="latest">Latest first</option>
                        <option value="priority">By priority</option>
                        <option value="status">By status</option>
                    </select>
                </div>

                {/* Jobs list */}
                {jobs.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center">
                        <p className="text-gray-500 mb-4">
                            {currentStatus ? `No ${currentStatus} jobs.` : `No ${currentView} jobs.`}
                        </p>
                        {currentView === 'active' && !currentStatus && (
                            <Link
                                href={route('jobs.create')}
                                className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm"
                            >
                                Add your first job
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="space-y-3">
                        {jobs.map((job) => (
                            <Link
                                key={job.id}
                                href={route('jobs.show', job.id)}
                                className="block bg-white rounded-lg shadow p-4 hover:bg-gray-50 active:bg-gray-100"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <h2 className="text-base font-medium text-gray-900">
                                        {job.name}
                                    </h2>
                                    <span className="text-gray-400 text-lg flex-shrink-0">›</span>
                                </div>

                                <div className="flex flex-wrap gap-2 mt-2">
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

                                {job.description && (
                                    <p className="text-sm text-gray-500 mt-2 line-clamp-1">
                                        {job.description}
                                    </p>
                                )}
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}