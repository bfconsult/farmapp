import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import DateRangeCalendar from '@/Components/DateRangeCalendar';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDate as formatDateDayFirst } from '@/dateInput';
import { formatNumber } from '@/numberFormat';

const TYPE_LABELS = { expense: 'Expense', labour: 'Labour' };

const STATUS_LABELS = { draft: 'Draft', finalised: 'Finalised', approved: 'Approved' };

const STATUS_COLORS = {
    draft: 'bg-gray-100 text-gray-600',
    finalised: 'bg-blue-100 text-blue-700',
    approved: 'bg-green-100 text-green-700',
};

function defaultRange() {
    const pad = (n) => String(n).padStart(2, '0');
    const toISO = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const to = new Date();
    const from = new Date();
    from.setMonth(from.getMonth() - 3);
    return { from: toISO(from), to: toISO(to) };
}

// Two kinds of transaction feed this list: Expenses logged directly against
// the supplier, and work sessions belonging to a worker who bills through it
// (see Role::supplier()) - both are shown, clearly labelled, in one list
// rather than as separate sections, so the total actually reflects
// everything billed through this supplier.
function TransactionRow({ transaction }) {
    const isLabour = transaction.type === 'labour';

    return (
        <div className="px-4 py-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex items-center gap-1.5">
                        <span className="text-[10px] uppercase tracking-wide font-medium text-gray-400">
                            {TYPE_LABELS[transaction.type]}
                        </span>
                        {isLabour && (
                            <span className={`text-xs px-1.5 py-0.5 rounded-full font-medium ${STATUS_COLORS[transaction.status]}`}>
                                {STATUS_LABELS[transaction.status]}
                            </span>
                        )}
                    </div>
                    <p className="text-sm text-gray-900">{transaction.name}</p>
                    <p className="text-xs text-gray-500 mt-0.5">
                        {formatDateDayFirst(transaction.date)}
                        {isLabour ? (
                            <>
                                {transaction.duration_in_hours && ` · ${formatNumber(transaction.duration_in_hours)}h`}
                                {' · '}
                                {transaction.amount != null ? `$${formatNumber(transaction.amount)}` : 'Rate not set'}
                            </>
                        ) : (
                            <> · ${formatNumber(transaction.amount)} ({transaction.gst_inclusive ? 'GST inc' : 'GST ex'})</>
                        )}
                    </p>
                    {transaction.farm_job && (
                        <Link href={route('jobs.show', transaction.farm_job.id)} className="text-xs text-green-600">
                            {transaction.farm_job.name}
                        </Link>
                    )}
                    {transaction.description && (
                        <p className="text-sm text-gray-500 mt-1">{transaction.description}</p>
                    )}
                </div>
                {transaction.reimburse && (
                    <span className="text-xs px-2 py-1 rounded-full font-medium bg-amber-100 text-amber-700 flex-shrink-0">
                        Reimburse
                    </span>
                )}
            </div>
        </div>
    );
}

export default function Show({ supplier, transactions, total, currentDateFrom, currentDateTo }) {
    const [showFilters, setShowFilters] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);

    const goTo = (overrides = {}) => {
        router.get(route('manage.suppliers.show', supplier.id), {
            date_from: overrides.dateFrom ?? currentDateFrom,
            date_to: overrides.dateTo ?? currentDateTo,
        }, { preserveState: true, preserveScroll: true });
    };

    const changeRange = (dateFrom, dateTo) => goTo({ dateFrom, dateTo });

    const resetToDefaultRange = () => {
        const { from, to } = defaultRange();
        goTo({ dateFrom: from, dateTo: to });
    };

    const isDefaultRange = (() => {
        const { from, to } = defaultRange();
        return currentDateFrom === from && currentDateTo === to;
    })();

    return (
        <AuthenticatedLayout title={supplier.name}>
            <Head title={supplier.name} />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <BackLink href={route('manage.suppliers.index')}>Suppliers</BackLink>
                    <Link
                        href={route('manage.suppliers.edit', supplier.id)}
                        className="text-sm px-3 py-1 border border-green-600 text-green-600 rounded-lg"
                    >
                        Edit
                    </Link>
                </div>

                <div>
                    <h1 className="text-lg font-semibold text-gray-900">{supplier.name}</h1>
                    {(supplier.phone || supplier.email) && (
                        <p className="text-sm text-gray-500">
                            {[supplier.phone, supplier.email].filter(Boolean).join(' · ')}
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setShowFilters((v) => !v)}
                        className="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-gray-700"
                    >
                        <span>Filter</span>
                        {!isDefaultRange && <span className="w-1.5 h-1.5 rounded-full bg-green-600" />}
                        <span className="text-gray-400">{showFilters ? '▲' : '▼'}</span>
                    </button>
                </div>

                {showFilters && (
                    <div className="bg-white rounded-lg shadow p-4 space-y-2">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm font-medium text-gray-700">Date range</span>
                            {!isDefaultRange && (
                                <button onClick={resetToDefaultRange} className="text-xs text-green-600">
                                    Reset to last 3 months
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

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="px-4 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Transactions</h2>
                        <span className="text-sm font-medium text-gray-900">${formatNumber(total)}</span>
                    </div>
                    {transactions.length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No transactions in this date range.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {transactions.map((transaction) => (
                                <TransactionRow key={`${transaction.type}-${transaction.id}`} transaction={transaction} />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
