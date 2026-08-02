import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDate as formatDateDayFirst } from '@/dateInput';

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
            router.post(route('work-sessions.revert-to-draft', session.id));
        }
    };

    const formatTime = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <div className="flex items-start justify-between gap-2 px-4 py-3">
            <Link href={route('work-sessions.show', session.id)} className="min-w-0 flex-1">
                <p className="text-sm font-medium text-gray-900">{sessionLabel(session)}</p>
                <p className="text-xs text-gray-500 mt-1">
                    {formatDateDayFirst(session.started_at)} · {formatTime(session.started_at)} — {formatTime(session.ended_at)}
                    {session.duration_in_hours && ` · ${session.duration_in_hours}h`}
                </p>
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

export default function Review({ workers, currentDateFrom, currentDateTo }) {
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);

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

                <h1 className="text-lg font-semibold text-gray-900">Work Sessions</h1>

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
                                <div className="px-4 py-2 bg-gray-50 border-b border-gray-100">
                                    <p className="text-sm font-medium text-gray-900">{worker.user.name}</p>
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
        </AuthenticatedLayout>
    );
}
