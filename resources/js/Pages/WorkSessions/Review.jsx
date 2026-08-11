import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import Modal from '@/Components/Modal';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    formatDate as formatDateDayFirst,
    fromLocalInputValue,
    joinLocalValue,
} from '@/dateInput';
import { formatNumber } from '@/numberFormat';

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

function sessionLabel(session) {
    if (session.farm_job) return session.farm_job.name;
    return session.source === 'auto_tracked' && !session.reviewed_at ? 'Auto-tracked visit' : 'Ad-hoc work';
}

function currentMonthRange() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const from = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const to = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(lastDay)}`;
    return { from, to };
}

function SessionRow({ session }) {
    const unfinalise = () => {
        if (confirm(`Unfinalise this session? It will become editable again, so you'll want to add a note explaining why.`)) {
            router.post(route('work-sessions.revert-to-draft', { workSession: session.id, from: 'manage' }));
        }
    };

    const formatTime = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <div className="flex items-start justify-between gap-2 px-4 py-3">
            <Link href={route('work-sessions.show', { work_session: session.id, from: 'manage' })} className="min-w-0 flex-1">
                <p className="text-sm font-medium text-gray-900">{sessionLabel(session)}</p>
                <p className="text-xs text-gray-500 mt-1">
                    {formatDateDayFirst(session.started_at)} · {formatTime(session.started_at)} — {formatTime(session.ended_at)}
                    {session.duration_in_hours && ` · ${formatNumber(session.duration_in_hours)}h`}
                </p>
                {session.created_by && (
                    <p className="text-xs text-gray-400 mt-0.5">Added by {session.created_by.name}</p>
                )}
            </Link>
            <div className="flex flex-col items-end gap-1.5 flex-shrink-0">
                <span className={`text-xs px-2 py-1 rounded-full font-medium whitespace-nowrap ${STATUS_COLORS[session.status]}`}>
                    {STATUS_LABELS[session.status]}
                </span>
                {session.status === 'finalised' && (
                    <button onClick={unfinalise} className="text-xs text-green-600 font-medium whitespace-nowrap">
                        Unfinalise
                    </button>
                )}
            </div>
        </div>
    );
}

// A manager retroactively logging someone else's time almost never knows
// the exact clock-off time - they know when a job started and roughly how
// long it took. Asking for start + duration instead of start + end matches
// that, and as a side effect handles a shift that crosses midnight without
// needing a second date picker.
function addHours(localValue, hours) {
    if (!localValue || !hours) return '';
    const start = new Date(localValue);
    if (Number.isNaN(start.getTime())) return '';
    return new Date(start.getTime() + hours * 60 * 60 * 1000);
}

function LogTimeModal({ show, onClose, teamMembers, bookableJobs, initialUserId }) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        user_id: initialUserId ?? '',
        farm_job_id: '',
        description: '',
        date: '',
        started_time: '',
        duration_hours: '',
    });

    const startedLocal = joinLocalValue(data.date, data.started_time);
    const computedEnd = addHours(startedLocal, parseFloat(data.duration_hours));

    const submit = (e) => {
        e.preventDefault();
        transform((data) => ({
            ...data,
            farm_job_id: data.farm_job_id || null,
            started_at: fromLocalInputValue(startedLocal),
            ended_at: computedEnd ? computedEnd.toISOString() : null,
        }));
        post(route('manage.work-sessions.store'), {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const close = () => {
        clearErrors();
        reset();
        onClose();
    };

    return (
        <Modal show={show} onClose={close} maxWidth="md">
            <form onSubmit={submit} className="p-6 space-y-4">
                <h2 className="text-lg font-medium text-gray-900">Log Time for a Worker</h2>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Worker</label>
                    <select
                        value={data.user_id}
                        onChange={(e) => setData('user_id', e.target.value)}
                        className="w-full border-gray-300 rounded-lg p-3 text-sm"
                        required
                    >
                        <option value="">Select a worker</option>
                        {teamMembers.map((member) => (
                            <option key={member.id} value={member.id}>{member.name}</option>
                        ))}
                    </select>
                    {errors.user_id && <p className="mt-1 text-sm text-red-600">{errors.user_id}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input
                        type="date"
                        value={data.date}
                        onChange={(e) => setData('date', e.target.value)}
                        className="w-full border-gray-300 rounded-lg p-3 text-sm"
                        required
                    />
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Start</label>
                        <input
                            type="time"
                            value={data.started_time}
                            onChange={(e) => setData('started_time', e.target.value)}
                            className="w-full border-gray-300 rounded-lg p-3 text-sm"
                            required
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Duration (hours)</label>
                        <input
                            type="number"
                            step="0.25"
                            min="0.25"
                            placeholder="e.g. 2.5"
                            value={data.duration_hours}
                            onChange={(e) => setData('duration_hours', e.target.value)}
                            className="w-full border-gray-300 rounded-lg p-3 text-sm"
                            required
                        />
                    </div>
                </div>
                {computedEnd && (
                    <p className="text-xs text-gray-500 -mt-2">
                        Finishes at {computedEnd.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        {computedEnd.toDateString() !== new Date(startedLocal).toDateString() && ' (next day)'}
                    </p>
                )}
                {errors.started_at && <p className="text-sm text-red-600">{errors.started_at}</p>}
                {errors.ended_at && <p className="text-sm text-red-600">{errors.ended_at}</p>}

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Job (optional)</label>
                    <select
                        value={data.farm_job_id}
                        onChange={(e) => setData('farm_job_id', e.target.value)}
                        className="w-full border-gray-300 rounded-lg p-3 text-sm"
                    >
                        <option value="">Ad-hoc work (no job)</option>
                        {bookableJobs.map((job) => (
                            <option key={job.id} value={job.id}>{job.name}</option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                    <textarea
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        rows={2}
                        className="w-full border-gray-300 rounded-lg p-3 text-sm"
                    />
                </div>

                <div className="flex gap-3">
                    <button
                        type="button"
                        onClick={close}
                        className="flex-1 py-3 border border-gray-300 text-gray-700 rounded-lg text-sm"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="flex-1 py-3 bg-green-600 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                    >
                        Log Time
                    </button>
                </div>
            </form>
        </Modal>
    );
}

export default function Review({ workers, teamMembers, bookableJobs, currentDateFrom, currentDateTo }) {
    const { currentUserRole } = usePage().props;
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);
    const [logTimeFor, setLogTimeFor] = useState(null); // null = closed, '' = open with no worker preselected, or a user id
    const canLogTime = ['admin', 'manager', 'approver'].includes(currentUserRole);

    const goTo = (overrides = {}) => {
        router.get(route('manage.work-sessions'), {
            date_from: overrides.dateFrom ?? currentDateFrom,
            date_to: overrides.dateTo ?? currentDateTo,
        }, { preserveState: true, preserveScroll: true });
    };

    const changeRange = (dateFrom, dateTo) => goTo({ dateFrom, dateTo });

    const resetToThisMonth = () => {
        const { from, to } = currentMonthRange();
        goTo({ dateFrom: from, dateTo: to });
    };

    const isThisMonth = (() => {
        const { from, to } = currentMonthRange();
        return currentDateFrom === from && currentDateTo === to;
    })();

    return (
        <AuthenticatedLayout title="Work Sessions">
            <Head title="Work Sessions" />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <BackLink href={route('manage.index')}>Manage</BackLink>
                </div>

                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-semibold text-gray-900">Work Sessions</h1>
                    {canLogTime && (
                        <button
                            onClick={() => setLogTimeFor('')}
                            className="text-sm text-green-600 font-medium"
                        >
                            + Log time for a worker
                        </button>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setShowFilters((v) => !v)}
                        className="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-gray-700"
                    >
                        <span>Filter</span>
                        {!isThisMonth && <span className="w-1.5 h-1.5 rounded-full bg-green-600" />}
                        <span className="text-gray-400">{showFilters ? '▲' : '▼'}</span>
                    </button>
                </div>

                {showFilters && (
                    <div className="bg-white rounded-lg shadow p-4 space-y-2">
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
                            <span>{formatDateDayFirst(currentDateFrom)} → {formatDateDayFirst(currentDateTo)}</span>
                            <span className="text-gray-400">{showCalendar ? '▲' : '▼'}</span>
                        </button>
                        {showCalendar && (
                            <div className="mt-2 border border-gray-200 rounded-lg p-3">
                                <DateRangeCalendar from={currentDateFrom} to={currentDateTo} onChange={changeRange} />
                            </div>
                        )}
                    </div>
                )}

                {workers.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No work sessions in this date range.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {workers.map((worker) => (
                            <div key={worker.user.id} className="bg-white rounded-lg shadow overflow-hidden">
                                <div className="px-4 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                                    <p className="text-sm font-medium text-gray-900">{worker.user.name}</p>
                                    {canLogTime && (
                                        <button
                                            onClick={() => setLogTimeFor(worker.user.id)}
                                            className="text-xs text-green-600 font-medium"
                                        >
                                            + Add entry
                                        </button>
                                    )}
                                </div>
                                <div className="divide-y divide-gray-100">
                                    {worker.sessions.map((session) => (
                                        <SessionRow key={session.id} session={session} />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {canLogTime && (
                <LogTimeModal
                    key={logTimeFor ?? 'closed'}
                    show={logTimeFor !== null}
                    onClose={() => setLogTimeFor(null)}
                    teamMembers={teamMembers}
                    bookableJobs={bookableJobs}
                    initialUserId={logTimeFor || ''}
                />
            )}
        </AuthenticatedLayout>
    );
}
