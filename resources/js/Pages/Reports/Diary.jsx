import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import DiaryDays from '@/Components/DiaryDays';
import MetricsView from '@/Components/MetricsView';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDate } from '@/dateInput';

function currentMonthRange() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const from = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const to = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(lastDay)}`;
    return { from, to };
}

export default function Diary({ days, currentDateFrom, currentDateTo, metrics }) {
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

    return (
        <AuthenticatedLayout title="Diary">
            <Head title="Diary" />

            <div className="max-w-lg mx-auto pb-6">
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

                <DiaryDays days={days} />

                <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mt-6 mb-3">
                    Metrics
                </h2>
                <MetricsView metrics={metrics} showStatusBadge={false} showHistoryLinks={false} showPhotos />
            </div>
        </AuthenticatedLayout>
    );
}
