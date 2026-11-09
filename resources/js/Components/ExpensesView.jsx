import { formatDate } from '@/dateInput';
import { formatNumber } from '@/numberFormat';

/**
 * The Expenses section of a Diary report - one card per job with its
 * expenses in the period and a subtotal. Used identically by Reports/
 * Index.jsx and Diary/SharedView.jsx, alongside JobsView/MetricsView - see
 * Expense::forDiaryPeriod() for how the data is built. No links into the
 * app, since the public share link renders this too.
 */
export default function ExpensesView({ expensesByJob = [] }) {
    if (expensesByJob.length === 0) {
        return null;
    }

    const total = expensesByJob.reduce((sum, group) => sum + group.totalAmount, 0);

    return (
        <div>
            <div className="flex items-baseline justify-between mb-3">
                <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Expenses</h2>
                <span className="text-sm font-medium text-gray-900">${formatNumber(total)}</span>
            </div>
            <div className="space-y-3">
                {expensesByJob.map((group) => (
                    <div key={group.job.id} className="bg-white rounded-lg shadow overflow-hidden">
                        <div className="flex items-center justify-between gap-2 px-4 py-3 bg-gray-50 border-b border-gray-100">
                            <p className="text-sm font-medium text-gray-900 min-w-0 truncate">{group.job.name}</p>
                            <p className="text-sm font-medium text-gray-900 flex-shrink-0">${formatNumber(group.totalAmount)}</p>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {group.expenses.map((expense) => (
                                <div key={expense.id} className="flex items-start justify-between gap-2 px-4 py-3">
                                    <div className="min-w-0">
                                        <p className="text-sm text-gray-900 truncate">{expense.name}</p>
                                        <p className="text-xs text-gray-500 mt-1">{formatDate(expense.date)}</p>
                                    </div>
                                    <p className="text-sm text-gray-900 flex-shrink-0">
                                        {expense.amount != null ? `$${formatNumber(expense.amount)}` : 'Awaiting amount'}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
