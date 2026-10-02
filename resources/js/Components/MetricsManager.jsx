import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { formatMeasurementValue } from '@/Components/MetricsView';

const REPORTING_PERIOD_ORDER = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

const REPORTING_PERIOD_LABELS = {
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
};

const ANSWER_TYPE_LABELS = {
    number: 'Number',
    text: 'Text',
};

const STATUS_LABELS = {
    incomplete: 'Incomplete',
    complete: 'Complete',
};

const STATUS_COLORS = {
    incomplete: 'bg-gray-100 text-gray-600',
    complete: 'bg-green-100 text-green-700',
};

function MetricFields({ values, setValues }) {
    return (
        <div className="space-y-3">
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Name (e.g. Tractor hours)"
            />
            <textarea
                value={values.description}
                onChange={(e) => setValues({ ...values, description: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Description (optional)"
                rows={2}
            />
            <div className="grid grid-cols-2 gap-2">
                <select
                    value={values.reporting_period}
                    onChange={(e) => setValues({ ...values, reporting_period: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                >
                    {Object.entries(REPORTING_PERIOD_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
                <select
                    value={values.answer_type}
                    onChange={(e) => setValues({ ...values, answer_type: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                >
                    {Object.entries(ANSWER_TYPE_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
            </div>
        </div>
    );
}

/**
 * One metric's row - what used to be split across the View/Measure/Manage
 * tabs (MeasureRow/ManageRow/MetricsView's own rendering) now all live
 * together: the latest value and status for everyone, a Measure link for
 * whoever can log one, and inline edit/pause/delete for admin/manager.
 * History stays available to every role, same as the old View tab default.
 */
function MetricRow({ metric, canMeasure, canManage }) {
    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({
        name: metric.name,
        description: metric.description ?? '',
        reporting_period: metric.reporting_period,
        answer_type: metric.answer_type,
    });

    const save = () => {
        router.patch(route('metrics.update', metric.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const toggleActive = () => {
        router.patch(route('metrics.update', metric.id), {
            ...values,
            is_active: !metric.is_active,
        }, { preserveScroll: true, preserveState: true });
    };

    const destroy = () => {
        if (confirm(`Delete "${metric.name}"? Measurements it already created will be left as-is.`)) {
            router.delete(route('metrics.destroy', metric.id), { preserveScroll: true, preserveState: true });
        }
    };

    if (editing) {
        return (
            <div className="p-4 bg-green-50 space-y-3">
                <MetricFields values={values} setValues={setValues} />
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                    <button onClick={() => setEditing(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                </div>
            </div>
        );
    }

    const measurement = metric.latest_measurement;

    return (
        <div className="px-4 py-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-sm text-gray-900">{metric.name}</p>
                    {measurement && (
                        <p className="text-xs text-gray-500 mt-1">{formatMeasurementValue(measurement)}</p>
                    )}
                    {!metric.is_active && (
                        <span className="inline-block text-xs px-2 py-0.5 rounded-full font-medium bg-gray-100 text-gray-500 mt-1">
                            Paused
                        </span>
                    )}
                </div>
                {measurement && (
                    <span className={`text-xs px-2 py-1 rounded-full font-medium flex-shrink-0 ${STATUS_COLORS[measurement.status]}`}>
                        {STATUS_LABELS[measurement.status]}
                    </span>
                )}
            </div>
            <div className="flex flex-wrap gap-3 mt-2 text-xs">
                {canMeasure && measurement && (
                    <Link href={route('metric-measurements.show', measurement.id)} className="text-green-600 font-medium">
                        Measure
                    </Link>
                )}
                <Link href={route('metrics.history', metric.id)} className="text-green-600">History</Link>
                {canManage && (
                    <>
                        <button onClick={() => setEditing(true)} className="text-green-600">Edit</button>
                        <button onClick={toggleActive} className="text-blue-600">{metric.is_active ? 'Pause' : 'Resume'}</button>
                        <button onClick={destroy} className="text-red-500">Delete</button>
                    </>
                )}
            </div>
        </div>
    );
}

export default function MetricsManager({ metrics }) {
    const { currentUserRole } = usePage().props;
    const canManage = currentUserRole === 'admin' || currentUserRole === 'manager';
    const canMeasure = canManage || currentUserRole === 'worker';

    const [adding, setAdding] = useState(false);
    const [values, setValues] = useState({
        name: '',
        description: '',
        reporting_period: 'monthly',
        answer_type: 'number',
    });

    const create = () => {
        router.post(route('metrics.store'), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setAdding(false);
                setValues({ name: '', description: '', reporting_period: 'monthly', answer_type: 'number' });
            },
        });
    };

    const groups = REPORTING_PERIOD_ORDER
        .map((period) => ({ period, metrics: metrics.filter((m) => m.reporting_period === period) }))
        .filter((group) => group.metrics.length > 0);

    return (
        <div className="space-y-4">
            {metrics.length === 0 && !adding ? (
                canManage ? (
                    <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                        <h2 className="text-base font-semibold text-gray-900 mb-1">Create your first metric</h2>
                        <p className="text-sm text-gray-600 mb-4">
                            Metrics are used to track important data over time — tractor hours, water
                            storage, hay bales on hand. Each metric creates a new reminder to record a
                            measurement at the interval you define.
                        </p>
                        <button
                            onClick={() => setAdding(true)}
                            className="inline-block px-6 py-3 bg-green-600 text-white rounded-lg font-medium"
                        >
                            + Add Metric
                        </button>
                    </div>
                ) : (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No metrics set up yet.
                    </div>
                )
            ) : (
                canManage && (
                    adding ? (
                        <div className="bg-white rounded-lg shadow p-4 space-y-3">
                            <MetricFields values={values} setValues={setValues} />
                            <div className="flex gap-2">
                                <button onClick={create} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Metric</button>
                                <button onClick={() => setAdding(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                            </div>
                        </div>
                    ) : (
                        <button
                            onClick={() => setAdding(true)}
                            className="block w-full py-2 text-center text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
                        >
                            + Add Metric
                        </button>
                    )
                )
            )}

            {groups.map((group) => (
                <div key={group.period} className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="px-4 py-2 bg-gray-50 border-b border-gray-100">
                        <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            {REPORTING_PERIOD_LABELS[group.period]}
                        </p>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {group.metrics.map((metric) => (
                            <MetricRow key={metric.id} metric={metric} canMeasure={canMeasure} canManage={canManage} />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
