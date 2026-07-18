import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

function currentMonthRange() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const from = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const to = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(lastDay)}`;
    return { from, to };
}

function JobCard({ job }) {
    return (
        <Link
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
                {job.incomplete_checklists_count > 0 && (
                    <span className="text-xs px-2 py-1 rounded-full font-medium bg-red-100 text-red-700">
                        Checklist incomplete
                    </span>
                )}
            </div>

            {job.description && (
                <p className="text-sm text-gray-500 mt-2 line-clamp-1">
                    {job.description}
                </p>
            )}

            {(job.zone || job.user) && (
                <div className="flex items-center justify-between mt-2">
                    <span className="text-xs text-gray-400">{job.zone?.name}</span>
                    {job.user && (
                        <span className="text-xs text-gray-400">Created by {job.user.name}</span>
                    )}
                </div>
            )}
        </Link>
    );
}

function JobsCalendar({ month, jobs, onMonthChange }) {
    const todayIso = new Date().toISOString().slice(0, 10);
    const [selectedDate, setSelectedDate] = useState(() => (todayIso.slice(0, 7) === month ? todayIso : null));

    const [year, monthNum] = month.split('-').map(Number);
    const pad = (n) => String(n).padStart(2, '0');
    const toISO = (day) => `${year}-${pad(monthNum)}-${pad(day)}`;

    const firstWeekday = (new Date(year, monthNum - 1, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(year, monthNum, 0).getDate();
    const monthLabel = new Date(year, monthNum - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const cells = [...Array(firstWeekday).fill(null), ...[...Array(daysInMonth).keys()].map((d) => d + 1)];

    const jobsByDate = useMemo(() => {
        const map = {};
        jobs.forEach((job) => {
            (map[job.effective_date] ??= []).push(job);
        });
        return map;
    }, [jobs]);

    const changeMonth = (delta) => {
        const d = new Date(year, monthNum - 1 + delta, 1);
        setSelectedDate(null);
        onMonthChange(`${d.getFullYear()}-${pad(d.getMonth() + 1)}`);
    };

    const selectedJobs = selectedDate ? (jobsByDate[selectedDate] ?? []) : [];

    return (
        <div>
            <div className="bg-white rounded-lg shadow p-3 mb-4">
                <div className="flex items-center justify-between mb-2">
                    <button
                        type="button"
                        onClick={() => changeMonth(-1)}
                        className="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-full"
                        aria-label="Previous month"
                    >
                        ‹
                    </button>
                    <span className="text-sm font-semibold text-gray-900">{monthLabel}</span>
                    <button
                        type="button"
                        onClick={() => changeMonth(1)}
                        className="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-full"
                        aria-label="Next month"
                    >
                        ›
                    </button>
                </div>

                <div className="grid grid-cols-7 gap-y-1 text-center">
                    {WEEKDAYS.map((wd) => (
                        <div key={wd} className="text-xs text-gray-400 font-medium py-1">
                            {wd}
                        </div>
                    ))}

                    {cells.map((day, i) => {
                        if (day === null) return <div key={`empty-${i}`} />;
                        const iso = toISO(day);
                        const dayJobs = jobsByDate[iso] ?? [];
                        const isToday = iso === todayIso;
                        const isSelected = iso === selectedDate;
                        return (
                            <button
                                type="button"
                                key={iso}
                                onClick={() => setSelectedDate(iso)}
                                className={`aspect-square flex flex-col items-center justify-center rounded-lg text-sm mx-auto w-full transition-colors ${
                                    isSelected
                                        ? 'bg-green-600 text-white font-semibold'
                                        : isToday
                                        ? 'bg-green-50 text-green-700 font-semibold'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                            >
                                <span>{day}</span>
                                {dayJobs.length > 0 && (
                                    <span className="flex gap-0.5 mt-0.5">
                                        {Array.from({ length: Math.min(dayJobs.length, 3) }).map((_, dotIndex) => (
                                            <span
                                                key={dotIndex}
                                                className={`w-1 h-1 rounded-full ${isSelected ? 'bg-white' : 'bg-green-500'}`}
                                            />
                                        ))}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>

            {selectedDate && (
                <div>
                    <p className="text-xs text-gray-500 mb-2">
                        {selectedJobs.length} job{selectedJobs.length === 1 ? '' : 's'} on{' '}
                        {formatDate(selectedDate, { weekday: 'long', month: 'long', year: false })}
                    </p>
                    {selectedJobs.length === 0 ? (
                        <p className="text-sm text-gray-400 bg-white rounded-lg shadow p-4 text-center">
                            No jobs this day.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {selectedJobs.map((job) => (
                                <JobCard key={job.id} job={job} />
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function Index({ jobs, counts, currentStatusIds, currentOrder, currentDateFrom, currentDateTo, jobStatuses, calendarJobs, calendarMonth }) {
    const { currentUserRole } = usePage().props;
    const canManageRecurring = currentUserRole === 'admin' || currentUserRole === 'manager';
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);
    const [view, setView] = useState('list');

    const goTo = (overrides = {}) => {
        router.get(route('jobs.index'), {
            statuses: overrides.statuses ?? currentStatusIds,
            date_from: overrides.dateFrom ?? currentDateFrom,
            date_to: overrides.dateTo ?? currentDateTo,
            order: overrides.order ?? currentOrder,
            calendar_month: overrides.calendarMonth ?? calendarMonth,
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
    const changeCalendarMonth = (newMonth) => goTo({ calendarMonth: newMonth });

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

                {/* Filters + view toggle + order */}
                <div className="flex items-center justify-between mb-3 gap-2">
                    {view === 'list' ? (
                        <button
                            onClick={() => setShowFilters((v) => !v)}
                            className="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-gray-700"
                        >
                            <span>Filters</span>
                            <span className="text-xs text-gray-500 font-normal">{filterSummary}</span>
                            <span className="text-gray-400">{showFilters ? '▲' : '▼'}</span>
                        </button>
                    ) : <span />}

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setView('list')}
                            aria-label="Card view"
                            className={`p-2 rounded-lg border ${
                                view === 'list'
                                    ? 'bg-green-600 border-green-600 text-white'
                                    : 'bg-white border-gray-300 text-gray-500'
                            }`}
                        >
                            <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
                                <rect x="3" y="3" width="7" height="7" rx="1" />
                                <rect x="14" y="3" width="7" height="7" rx="1" />
                                <rect x="3" y="14" width="7" height="7" rx="1" />
                                <rect x="14" y="14" width="7" height="7" rx="1" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            onClick={() => setView('calendar')}
                            aria-label="Calendar view"
                            className={`p-2 rounded-lg border ${
                                view === 'calendar'
                                    ? 'bg-green-600 border-green-600 text-white'
                                    : 'bg-white border-gray-300 text-gray-500'
                            }`}
                        >
                            <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                        </button>

                        {view === 'list' && (
                            <select
                                value={currentOrder}
                                onChange={changeOrder}
                                className="text-sm border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500"
                            >
                                <option value="latest">Latest first</option>
                                <option value="priority">By priority</option>
                                <option value="status">By status</option>
                            </select>
                        )}
                    </div>
                </div>

                {view === 'list' && (
                <>

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

                        {canManageRecurring && (
                            <div className="border-t border-gray-100 pt-3">
                                <Link
                                    href={route('recurring-jobs.index')}
                                    className="text-sm text-green-600"
                                >
                                    Recurring Jobs →
                                </Link>
                            </div>
                        )}
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
                            <JobCard key={job.id} job={job} />
                        ))}
                    </div>
                )}
                </>
                )}

                {view === 'calendar' && (
                    <JobsCalendar
                        month={calendarMonth}
                        jobs={calendarJobs}
                        onMonthChange={changeCalendarMonth}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
