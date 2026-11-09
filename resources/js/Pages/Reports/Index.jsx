import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import DiaryDays from '@/Components/DiaryDays';
import MetricsView from '@/Components/MetricsView';
import JobsView from '@/Components/JobsView';
import ExpensesView from '@/Components/ExpensesView';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { formatDate, currentMonthRange, previousMonthRange } from '@/dateInput';

/**
 * The full activity diary for every role - the same sections as the shared
 * link (Diary/SharedView), plus a date filter. Only admin/manager can
 * share it (see routes/web.php), so the Share button is hidden otherwise.
 */
export default function Index({ days, metrics, completedJobs, openJobs, expensesByJob, currentDateFrom, currentDateTo, justSharedUrl }) {
    const { currentUserRole } = usePage().props;
    const canShare = currentUserRole === 'admin' || currentUserRole === 'manager';
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);
    const [copied, setCopied] = useState(false);
    const [confirmingShare, setConfirmingShare] = useState(false);

    const goTo = (overrides = {}) => {
        router.get(route('reports.index'), {
            date_from: overrides.dateFrom ?? currentDateFrom,
            date_to: overrides.dateTo ?? currentDateTo,
        }, { preserveState: true, preserveScroll: true });
    };

    const changeRange = (dateFrom, dateTo) => goTo({ dateFrom, dateTo });

    const previewUrl = route('reports.diary-preview', {
        date_from: currentDateFrom,
        date_to: currentDateTo,
    });

    const confirmShare = () => {
        router.post(route('reports.diary-share.store'), {
            date_from: currentDateFrom,
            date_to: currentDateTo,
        });
        setConfirmingShare(false);
    };

    const copyLink = () => {
        navigator.clipboard.writeText(justSharedUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const resetToThisMonth = () => {
        const { from, to } = currentMonthRange();
        goTo({ dateFrom: from, dateTo: to });
    };

    const resetToPreviousMonth = () => {
        const { from, to } = previousMonthRange();
        goTo({ dateFrom: from, dateTo: to });
    };

    const isThisMonth = (() => {
        const { from, to } = currentMonthRange();
        return currentDateFrom === from && currentDateTo === to;
    })();

    const isPreviousMonth = (() => {
        const { from, to } = previousMonthRange();
        return currentDateFrom === from && currentDateTo === to;
    })();

    return (
        <AuthenticatedLayout title="Reports">
            <Head title="Reports" />

            <div className="max-w-lg mx-auto pb-6">
                {/* Filters toggle */}
                <div className="flex items-center justify-between mb-3 gap-2">
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
                    {canShare && (
                        <button
                            onClick={() => setConfirmingShare((v) => !v)}
                            className="px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-green-600 flex-shrink-0"
                        >
                            Share diary
                        </button>
                    )}
                </div>

                {confirmingShare && (
                    <div className="bg-white border border-gray-200 rounded-lg p-3 mb-4">
                        <p className="text-sm text-gray-700 mb-3">
                            Create a link for {formatDate(currentDateFrom)} → {formatDate(currentDateTo)}?
                            Anyone with the link will be able to view it, no account needed.
                        </p>
                        <div className="flex items-center gap-2">
                            <a
                                href={previewUrl}
                                className="px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700"
                            >
                                Preview
                            </a>
                            <button
                                onClick={confirmShare}
                                className="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium"
                            >
                                Create link
                            </button>
                            <button
                                onClick={() => setConfirmingShare(false)}
                                className="px-3 py-1.5 text-sm text-gray-500"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                )}

                {justSharedUrl && (
                    <div className="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                        <p className="text-sm text-green-800 mb-2">
                            Diary link created for this date range — anyone with this link can view it, no account needed.
                        </p>
                        <div className="flex items-center gap-2">
                            <input
                                readOnly
                                value={justSharedUrl}
                                onFocus={(e) => e.target.select()}
                                className="flex-1 min-w-0 text-xs border border-green-300 rounded-lg px-2 py-1.5 bg-white text-gray-700"
                            />
                            <button
                                onClick={copyLink}
                                className="flex-shrink-0 px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-medium"
                            >
                                {copied ? 'Copied!' : 'Copy'}
                            </button>
                        </div>
                    </div>
                )}

                {showFilters && (
                    <div className="bg-white rounded-lg shadow p-4 mb-4">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm font-medium text-gray-700">Date range</span>
                            <div className="flex gap-3 text-xs">
                                {!isPreviousMonth && (
                                    <button onClick={resetToPreviousMonth} className="text-green-600">
                                        Previous month
                                    </button>
                                )}
                                {!isThisMonth && (
                                    <button onClick={resetToThisMonth} className="text-green-600">
                                        This month
                                    </button>
                                )}
                            </div>
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

                {metrics && metrics.length > 0 && (
                    <>
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mt-6 mb-3">
                            Metrics
                        </h2>
                        <MetricsView metrics={metrics} showStatusBadge={false} showHistoryLinks={false} showPhotos />
                    </>
                )}

                <div className="mt-6">
                    <JobsView completedJobs={completedJobs} openJobs={openJobs} />
                </div>

                <div className="mt-6">
                    <ExpensesView expensesByJob={expensesByJob} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
