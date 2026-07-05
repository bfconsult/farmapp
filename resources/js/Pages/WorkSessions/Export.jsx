import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function currentMonthRange() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const from = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const to = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(lastDay)}`;
    return { from, to };
}

export default function Export({ currentDateFrom, currentDateTo, draftCount, exportSummary }) {
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);
    const [showBilling, setShowBilling] = useState(false);

    const goTo = (overrides = {}) => {
        router.get(route('work-sessions.export'), {
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

    const downloadUrl = (format) => route('work-sessions.export.download', {
        date_from: currentDateFrom,
        date_to: currentDateTo,
        format,
        rate: showBilling ? 'billing' : 'time',
    });

    return (
        <AuthenticatedLayout title="Export">
            <Head title="Export" />

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

                {/* Export */}
                <div className="bg-white rounded-lg shadow p-4">
                    {draftCount > 0 && (
                        <div className="mb-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
                            {draftCount} draft session{draftCount === 1 ? '' : 's'} in this range won't be exported. Finalise them first.
                        </div>
                    )}

                    <div className="flex justify-between text-sm mb-2">
                        <span className="text-gray-500">Sessions</span>
                        <span className="font-medium text-gray-900">{exportSummary.count}</span>
                    </div>
                    <div className="flex justify-between text-sm mb-2">
                        <span className="text-gray-500">Total hours</span>
                        <span className="font-medium text-gray-900">{exportSummary.hours}h</span>
                    </div>
                    <div className="flex justify-between text-sm mb-4">
                        <span className="text-gray-500">Total billing (Ex GST)</span>
                        <span className="font-medium text-green-700">${exportSummary.billing}</span>
                    </div>

                    <div className="flex items-center justify-between mb-4">
                        <span className="text-sm font-medium text-gray-700">Show billing amounts</span>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={showBilling}
                            onClick={() => setShowBilling((v) => !v)}
                            className={`relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors ${
                                showBilling ? 'bg-green-600' : 'bg-gray-300'
                            }`}
                        >
                            <span
                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                    showBilling ? 'translate-x-6' : 'translate-x-1'
                                }`}
                            />
                        </button>
                    </div>

                    <div className="flex gap-2">
                        <a
                            href={downloadUrl('excel')}
                            className="flex-1 py-3 bg-blue-600 text-white rounded-lg text-sm font-medium text-center hover:bg-blue-700"
                        >
                            Export as Excel
                        </a>
                        <a
                            href={downloadUrl('pdf')}
                            className="flex-1 py-3 border border-blue-600 text-blue-600 rounded-lg text-sm font-medium text-center hover:bg-blue-50"
                        >
                            Export as PDF
                        </a>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
