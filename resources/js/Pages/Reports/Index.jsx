import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

function currentMonthRange() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const from = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const to = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(lastDay)}`;
    return { from, to };
}

export default function Index({ workers, grandTotal, currentDateFrom, currentDateTo }) {
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);

    const goTo = (overrides = {}) => {
        router.get(route('reports.index'), {
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

    const formatDate = (iso) =>
        new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', { day: 'numeric', month: 'short' });

    const formatTime = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const formatSessionDate = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleDateString();
    };

    return (
        <AuthenticatedLayout title="Reports">
            <Head title="Reports" />

            <div className="max-w-lg mx-auto pb-6">
                {/* Grand total */}
                <div className="flex items-center justify-between px-4 py-3 bg-white rounded-lg shadow mb-3">
                    <p className="text-sm font-medium text-gray-500 uppercase tracking-wide">All Workers</p>
                    <p className="text-base font-semibold text-gray-900">
                        {grandTotal.hours}h
                        {grandTotal.billing > 0 && (
                            <span className="text-green-700"> · ${grandTotal.billing}</span>
                        )}
                    </p>
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

                {/* Per-worker breakdown */}
                {workers.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No finalised work sessions in this date range.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {workers.map((worker) => (
                            <div key={worker.user.id} className="bg-white rounded-lg shadow overflow-hidden">
                                <div className="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-100">
                                    <p className="font-medium text-gray-900">{worker.user.name}</p>
                                    <div className="text-right">
                                        <p className="text-xs text-gray-500 uppercase tracking-wide">Subtotal</p>
                                        <p className="text-sm font-medium text-gray-900">
                                            {worker.totalHours}h
                                            {worker.totalBilling > 0 && (
                                                <span className="text-green-700"> · ${worker.totalBilling}</span>
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <div className="divide-y divide-gray-100">
                                    {worker.sessions.map((session) => (
                                        <div key={session.id} className="flex items-start justify-between gap-2 px-4 py-3">
                                            <div className="min-w-0">
                                                <p className="text-sm text-gray-900 truncate">
                                                    {session.farm_job?.name ?? 'Ad-hoc work'}
                                                </p>
                                                <p className="text-xs text-gray-500 mt-1">
                                                    {formatSessionDate(session.started_at)} · {formatTime(session.started_at)} — {formatTime(session.ended_at)}
                                                </p>
                                            </div>
                                            <div className="text-right flex-shrink-0">
                                                <p className="text-sm text-gray-900">{session.duration_in_hours}h</p>
                                                {session.billing_amount && (
                                                    <p className="text-xs text-green-700">${session.billing_amount}</p>
                                                )}
                                            </div>
                                        </div>
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
