import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { formatDate } from '@/dateInput';

function currentMonthRange() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const from = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const to = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(lastDay)}`;
    return { from, to };
}

export default function FinaliseAndShare({ sessions, currentDateFrom, currentDateTo }) {
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);
    const [selectedIds, setSelectedIds] = useState(sessions.map((s) => s.id));

    // Whenever the filtered list changes (date range edited, or after a submit
    // reloads with fewer draft sessions), default back to everything checked.
    useEffect(() => {
        setSelectedIds(sessions.map((s) => s.id));
    }, [sessions]);

    const goTo = (overrides = {}) => {
        router.get(route('work-sessions.finalise-and-share'), {
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

    const toggleSession = (id) => {
        setSelectedIds((current) =>
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id]
        );
    };

    const selectAll = () => setSelectedIds(sessions.map((s) => s.id));
    const selectNone = () => setSelectedIds([]);

    const formatTime = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const formatSessionDate = (datetime) => {
        if (!datetime) return '—';
        return formatDate(datetime);
    };

    const submit = () => {
        router.post(route('work-sessions.finalise-and-share.store'), {
            session_ids: selectedIds,
            date_from: currentDateFrom,
            date_to: currentDateTo,
        });
    };

    return (
        <AuthenticatedLayout title="Finalise & Share">
            <Head title="Finalise & Share" />

            <div className="max-w-lg mx-auto pb-24">
                <div className="flex items-center justify-between mb-4">
                    <Link href={route('work-sessions.index')} className="text-green-600 text-sm">
                        ← Work
                    </Link>
                </div>

                {/* Filters toggle */}
                <div className="flex items-center justify-between mb-3">
                    <button
                        onClick={() => setShowFilters((v) => !v)}
                        className="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-gray-700"
                    >
                        <span>Date range</span>
                        <span className="text-xs text-gray-500 font-normal">
                            {isThisMonth ? 'This month' : `${currentDateFrom} → ${currentDateTo}`}
                        </span>
                        <span className="text-gray-400">{showFilters ? '▲' : '▼'}</span>
                    </button>
                </div>

                {showFilters && (
                    <div className="bg-white rounded-lg shadow p-4 mb-4">
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
                                <DateRangeCalendar from={currentDateFrom} to={currentDateTo} onChange={changeRange} />
                            </div>
                        )}
                    </div>
                )}

                {/* Sessions list */}
                {sessions.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No draft work sessions in this date range.
                    </div>
                ) : (
                    <>
                        <div className="flex items-center justify-between mb-2 px-1">
                            <span className="text-sm text-gray-500">
                                {selectedIds.length} of {sessions.length} selected
                            </span>
                            <div className="flex gap-2 text-xs">
                                <button onClick={selectAll} className="text-green-600">All</button>
                                <button onClick={selectNone} className="text-green-600">None</button>
                            </div>
                        </div>
                        <div className="space-y-2">
                            {sessions.map((session) => (
                                <label
                                    key={session.id}
                                    className="flex items-start gap-3 bg-white rounded-lg shadow p-4 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedIds.includes(session.id)}
                                        onChange={() => toggleSession(session.id)}
                                        className="mt-1 rounded text-green-600 focus:ring-green-500"
                                    />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="font-medium text-gray-900">
                                                {session.farm_job?.name ?? 'Ad-hoc work'}
                                            </p>
                                            {session.billing_amount && (
                                                <p className="text-sm text-green-700 flex-shrink-0">
                                                    ${session.billing_amount}
                                                </p>
                                            )}
                                        </div>
                                        <p className="text-sm text-gray-500 mt-1">
                                            {formatSessionDate(session.started_at)} · {formatTime(session.started_at)} — {formatTime(session.ended_at)}
                                            {session.duration_in_hours && ` · ${session.duration_in_hours}h`}
                                        </p>
                                    </div>
                                </label>
                            ))}
                        </div>
                    </>
                )}
            </div>

            {sessions.length > 0 && (
                <div className="fixed bottom-16 left-0 right-0 bg-white border-t border-gray-200 p-4 z-10">
                    <div className="max-w-lg mx-auto">
                        <button
                            onClick={submit}
                            disabled={selectedIds.length === 0}
                            className="w-full py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
                        >
                            Finalise and Share{selectedIds.length > 0 ? ` (${selectedIds.length})` : ''}
                        </button>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
