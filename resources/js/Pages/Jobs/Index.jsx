import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

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

function currentMonthRange() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const from = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const to = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(lastDay)}`;
    return { from, to };
}

export default function Index({ jobs, counts, currentStatusIds, currentOrder, currentDateFrom, currentDateTo, jobStatuses }) {
    const { currentProperty, currentUserRole } = usePage().props;
    const canManageRecurring = currentUserRole === 'admin' || currentUserRole === 'manager';
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);

    const goTo = (overrides = {}) => {
        router.get(route('jobs.index'), {
            statuses: overrides.statuses ?? currentStatusIds,
            date_from: overrides.dateFrom ?? currentDateFrom,
            date_to: overrides.dateTo ?? currentDateTo,
            order: overrides.order ?? currentOrder,
        }, { preserveState: true, preserveScroll: true });
    };

    const toggleStatus = (statusId) => {
        const next = currentStatusIds.includes(statusId)
            ? currentStatusIds.filter((id) => id !== statusId)
            : [...currentStatusIds, statusId];
        goTo({ statuses: next });
    };

    const selectAllStatuses = () => goTo({ statuses: jobStatuses.map((s) => s.id) });
    const selectNoStatuses = () => goTo({ statuses: [] });

    const changeOrder = (e) => goTo({ order: e.target.value });
    const changeRange = (dateFrom, dateTo) => goTo({ dateFrom, dateTo });

    const formatDate = (iso) =>
        new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', { day: 'numeric', month: 'short' });

    const resetToThisMonth = () => {
        const { from, to } = currentMonthRange();
        goTo({ dateFrom: from, dateTo: to });
    };

    const isThisMonth = (() => {
        const { from, to } = currentMonthRange();
        return currentDateFrom === from && currentDateTo === to;
    })();

    const filterSummary = [
        isThisMonth ? 'This month' : `${currentDateFrom} → ${currentDateTo}`,
        currentStatusIds.length === jobStatuses.length
            ? 'All statuses'
            : `${currentStatusIds.length} status${currentStatusIds.length === 1 ? '' : 'es'}`,
    ].join(' · ');

    return (
        <AuthenticatedLayout title="Jobs">
            <Head title="Jobs" />

            <div className="max-w-lg mx-auto mt-2">

                {canManageRecurring && (
                    <div className="flex justify-end mb-3">
                        <Link
                            href={route('recurring-jobs.index')}
                            className="px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-green-600"
                        >
                            Recurring Jobs →
                        </Link>
                    </div>
                )}

                {/* Filters toggle */}
                <div className="flex items-center justify-between mb-3">
                    <button
                        onClick={() => setShowFilters((v) => !v)}
                        className="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-gray-700"
                    >
                        <span>Filters</span>
                        <span className="text-xs text-gray-500 font-normal">{filterSummary}</span>
                        <span className="text-gray-400">{showFilters ? '▲' : '▼'}</span>
                    </button>

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

                {showFilters && (
                    <div className="bg-white rounded-lg shadow p-4 mb-4 space-y-4">
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-sm font-medium text-gray-700">Date range</span>
                                {!isThisMonth && (
                                    <button onClick={resetToThisMonth} className="text-xs text-green-600">
                                        Reset to this month
                                    </button>
                                )}
                            </div>
                            <button
                                onClick={() => setShowCalendar((v) => !v)}
                                className="w-full flex items-center justify-between text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-700"
                            >
                                <span>{formatDate(currentDateFrom)} → {formatDate(currentDateTo)}</span>
                                <span className="text-gray-400">{showCalendar ? '▲' : '▼'}</span>
                            </button>
                            {showCalendar && (
                                <div className="mt-2 border border-gray-200 rounded-lg p-3">
                                    <DateRangeCalendar
                                        from={currentDateFrom}
                                        to={currentDateTo}
                                        onChange={changeRange}
                                    />
                                </div>
                            )}
                        </div>

                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-sm font-medium text-gray-700">Status</span>
                                <div className="flex gap-2 text-xs">
                                    <button onClick={selectAllStatuses} className="text-green-600">All</button>
                                    <button onClick={selectNoStatuses} className="text-green-600">None</button>
                                </div>
                            </div>
                            <div className="space-y-1">
                                {jobStatuses.map((status) => (
                                    <label
                                        key={status.id}
                                        className="flex items-center justify-between gap-2 py-1 cursor-pointer"
                                    >
                                        <span className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={currentStatusIds.includes(status.id)}
                                                onChange={() => toggleStatus(status.id)}
                                                className="rounded text-green-600 focus:ring-green-500"
                                            />
                                            <span className="text-sm text-gray-700">{status.name}</span>
                                        </span>
                                        <span className="text-xs text-gray-400">{counts[status.id] ?? 0}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* Jobs list */}
                {jobs.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center">
                        <p className="text-gray-500 mb-4">No jobs match the current filters.</p>
                        <Link
                            href={route('jobs.create')}
                            className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm"
                        >
                            Add your first job
                        </Link>
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
                                    <h2 className="text-base font-medium text-gray-900 flex items-center gap-1.5">
                                        {job.recurring_job_id && (
                                            <svg className="w-4 h-4 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-label="Repeating job">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                            </svg>
                                        )}
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
