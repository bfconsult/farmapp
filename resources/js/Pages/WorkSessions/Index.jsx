import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import HelpTip from '@/Components/HelpTip';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import { Head, Link, router, usePage } from '@inertiajs/react';
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
    // Only flagged as "Auto-tracked" until it's been reviewed once (see
    // WorkSessionController::update()) - after that it's treated as a
    // normal entry everywhere, same as Edit.jsx's isAutoTracked.
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

export default function Index({ sessions, activeSession, currentDateFrom, currentDateTo, currentStatusDraft, currentStatusFinalised }) {
    const { currentProperty } = usePage().props;
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);

    const stop = () => {
        router.post(route('work-sessions.stop', activeSession.id));
    };

    const goTo = (overrides = {}) => {
        router.get(route('work-sessions.index'), {
            date_from: overrides.dateFrom ?? currentDateFrom,
            date_to: overrides.dateTo ?? currentDateTo,
            status_draft: overrides.statusDraft ?? currentStatusDraft,
            status_finalised: overrides.statusFinalised ?? currentStatusFinalised,
        }, { preserveState: true, preserveScroll: true });
    };

    const changeRange = (dateFrom, dateTo) => goTo({ dateFrom, dateTo });

    const toggleStatusDraft = () => goTo({ statusDraft: !currentStatusDraft });

    const toggleStatusFinalised = () => goTo({ statusFinalised: !currentStatusFinalised });

    const resetToThisMonth = () => {
        const { from, to } = currentMonthRange();
        goTo({ dateFrom: from, dateTo: to });
    };

    const isThisMonth = (() => {
        const { from, to } = currentMonthRange();
        return currentDateFrom === from && currentDateTo === to;
    })();

    const formatTime = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const formatDate = (datetime) => {
        if (!datetime) return '—';
        return formatDateDayFirst(datetime);
    };

    return (
        <AuthenticatedLayout title="Jobs">
            <Head title="Work" />

           
            <div className="max-w-lg mx-auto mt-2">
 

                {/* Active session banner */}
                {activeSession && (
                    <div className="bg-green-600 rounded-lg p-4 text-white mb-4">
                        <p className="text-sm font-medium mb-1">⏱ Session in progress</p>
                        <p className="text-base font-semibold mb-3">
                            {sessionLabel(activeSession)}
                        </p>
                        <p className="text-sm mb-3 opacity-90">
                            Started at {formatTime(activeSession.started_at)}
                        </p>
                        <div className="flex gap-2">
                            <button
                                onClick={stop}
                                className="flex-1 py-2 bg-white text-green-600 rounded-lg font-medium text-sm"
                            >
                                Stop Work
                            </button>
                            <Link
                                href={route('work-sessions.show', activeSession.id)}
                                className="flex-1 py-2 border border-white text-white rounded-lg font-medium text-sm text-center"
                            >
                                View
                            </Link>
                        </div>
                    </div>
                )}

                {/* Start new session button */}
                {!activeSession && (
                    <Link
                        href={route('work-sessions.create')}
                        className="block w-full py-4 bg-green-600 text-white rounded-lg text-base font-medium text-center mb-4"
                    >
                        Start Work Session
                    </Link>
                )}

                {/* Filters / Finalise & share / export */}
                <div className="flex items-center justify-between gap-2 mb-3">
                    <button
                        onClick={() => setShowFilters((v) => !v)}
                        className="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-gray-700"
                    >
                        <span>Filter</span>
                        {(!isThisMonth || !currentStatusDraft || !currentStatusFinalised) && (
                            <span className="w-1.5 h-1.5 rounded-full bg-green-600" />
                        )}
                        <span className="text-gray-400">{showFilters ? '▲' : '▼'}</span>
                    </button>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-1 px-3 py-2 bg-white rounded-lg shadow">
                            <Link
                                href={route('work-sessions.finalise-and-share')}
                                className="text-sm font-medium text-green-600"
                            >
                                Finalise & Share →
                            </Link>
                            <HelpTip messageKey="work-sessions.finalise-and-share" />
                        </div>
                        <Link
                            href={route('work-sessions.export')}
                            className="px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-green-600"
                        >
                            Export →
                        </Link>
                    </div>
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
                                <span>{formatDateDayFirst(currentDateFrom)} → {formatDateDayFirst(currentDateTo)}</span>
                                <span className="text-gray-400">{showCalendar ? '▲' : '▼'}</span>
                            </button>
                            {showCalendar && (
                                <div className="mt-2 border border-gray-200 rounded-lg p-3">
                                    <DateRangeCalendar from={currentDateFrom} to={currentDateTo} onChange={changeRange} />
                                </div>
                            )}
                        </div>

                        <div>
                            <span className="text-sm font-medium text-gray-700 block mb-2">Status</span>
                            <div className="flex gap-4">
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input
                                        type="checkbox"
                                        checked={currentStatusDraft}
                                        onChange={toggleStatusDraft}
                                        className="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                                    />
                                    Draft
                                </label>
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input
                                        type="checkbox"
                                        checked={currentStatusFinalised}
                                        onChange={toggleStatusFinalised}
                                        className="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                                    />
                                    Finalised
                                </label>
                            </div>
                        </div>
                    </div>
                )}

                {/* Sessions list */}
                {sessions.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No work sessions in this date range.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {sessions.map((session) => (
                            <Link
                                key={session.id}
                                href={route('work-sessions.show', session.id)}
                                className="block bg-white rounded-lg shadow p-4 hover:bg-gray-50"
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="font-medium text-gray-900">
                                            {sessionLabel(session)}
                                        </p>
                                        <p className={`text-sm mt-1 ${session.has_conflict ? 'text-red-600 font-medium' : 'text-gray-500'}`}>
                                            {formatDate(session.started_at)} · {formatTime(session.started_at)} — {formatTime(session.ended_at)}
                                            {session.has_conflict && ' (conflict)'}
                                        </p>
                                        {session.description && (
                                            <p className="text-sm text-gray-500 mt-1 line-clamp-1">
                                                {session.description}
                                            </p>
                                        )}
                                    </div>
                                    <div className="text-right flex-shrink-0 ml-2">
                                        {session.duration_in_hours && (
                                            <p className="text-sm font-medium text-gray-900">
                                                {session.duration_in_hours}h
                                            </p>
                                        )}
                                        {session.billing_amount && (
                                            <p className="text-sm text-green-700">
                                                ${session.billing_amount}
                                            </p>
                                        )}
                                        {!session.ended_at ? (
                                            <span className="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                                Active
                                            </span>
                                        ) : (
                                            <span className={`text-xs px-2 py-1 rounded-full font-medium ${STATUS_COLORS[session.status]}`}>
                                                {STATUS_LABELS[session.status]}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}