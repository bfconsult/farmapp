import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MetricsView from '@/Components/MetricsView';

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

/** Measure tab row - data entry only, no metric-definition controls. */
function MeasureRow({ metric }) {
    return (
        <div className="flex items-center justify-between gap-2 px-4 py-3">
            <div className="min-w-0">
                <p className="text-sm text-gray-900">{metric.name}</p>
                <p className="text-xs text-gray-500 mt-1">{REPORTING_PERIOD_LABELS[metric.reporting_period]}</p>
            </div>
            {metric.latest_measurement && (
                <div className="flex items-center gap-2 flex-shrink-0">
                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${STATUS_COLORS[metric.latest_measurement.status]}`}>
                        {STATUS_LABELS[metric.latest_measurement.status]}
                    </span>
                    <Link
                        href={route('metric-measurements.show', metric.latest_measurement.id)}
                        className="text-xs px-3 py-1.5 bg-green-600 text-white rounded-lg font-medium"
                    >
                        Measure
                    </Link>
                </div>
            )}
        </div>
    );
}

/** Manage tab row (admin/manager only) - the metric definition itself. */
function ManageRow({ metric }) {
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

    return (
        <div className="flex items-center justify-between gap-2 px-4 py-3">
            <div className="min-w-0">
                <p className="text-sm text-gray-900">{metric.name}</p>
                <p className="text-xs text-gray-500 mt-1">
                    {REPORTING_PERIOD_LABELS[metric.reporting_period]} · {ANSWER_TYPE_LABELS[metric.answer_type]}
                </p>
                <div className="flex gap-3 mt-1 text-xs">
                    <button onClick={() => setEditing(true)} className="text-green-600">Edit</button>
                    <button onClick={toggleActive} className="text-blue-600">{metric.is_active ? 'Pause' : 'Resume'}</button>
                    <button onClick={destroy} className="text-red-500">Delete</button>
                </div>
            </div>
            {!metric.is_active && (
                <span className="text-xs px-2 py-1 rounded-full font-medium bg-gray-100 text-gray-500 flex-shrink-0">
                    Paused
                </span>
            )}
        </div>
    );
}

export default function MetricsManager({ metrics }) {
    const { currentUserRole } = usePage().props;
    const canManage = currentUserRole === 'admin' || currentUserRole === 'manager';
    const canMeasure = canManage || currentUserRole === 'worker';

    // Every role reaches this page, but only sees the tabs relevant to it -
    // approver only ever gets View, worker gets View + Measure, admin/manager get all three.
    const tabs = [
        { id: 'view', label: 'View' },
        ...(canMeasure ? [{ id: 'measure', label: 'Measure' }] : []),
        ...(canManage ? [{ id: 'manage', label: 'Manage' }] : []),
    ];
    const [activeTab, setActiveTab] = useState(tabs[0].id);

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

    return (
        <div className="space-y-4">
            {tabs.length > 1 && (
                <div className="flex items-center gap-2">
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`rounded-full px-3 py-1 text-sm font-medium ${
                                activeTab === tab.id ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            )}

            {activeTab === 'measure' && (
                <>
                    <p className="text-sm text-gray-500">
                        Add a measurement for the current period of each metric.
                    </p>

                    {metrics.length === 0 ? (
                        <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                            No metrics set up yet.
                        </div>
                    ) : (
                        <div className="bg-white rounded-lg shadow divide-y divide-gray-100">
                            {metrics.map((metric) => (
                                <MeasureRow key={metric.id} metric={metric} />
                            ))}
                        </div>
                    )}
                </>
            )}

            {activeTab === 'manage' && (
                <>
                    <p className="text-sm text-gray-500">
                        Metrics track property-level figures over time - tractor hours, water storage, hay bales on hand. Each metric opens a new measurement automatically at the start of every period.
                    </p>

                    {metrics.length === 0 ? (
                        <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                            No metrics set up yet.
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {REPORTING_PERIOD_ORDER
                                .map((period) => ({
                                    period,
                                    metrics: metrics.filter((m) => m.reporting_period === period),
                                }))
                                .filter((group) => group.metrics.length > 0)
                                .map((group) => (
                                    <div key={group.period} className="bg-white rounded-lg shadow overflow-hidden">
                                        <div className="px-4 py-2 bg-gray-50 border-b border-gray-100">
                                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                {REPORTING_PERIOD_LABELS[group.period]}
                                            </p>
                                        </div>
                                        <div className="divide-y divide-gray-100">
                                            {group.metrics.map((metric) => (
                                                <ManageRow key={metric.id} metric={metric} />
                                            ))}
                                        </div>
                                    </div>
                                ))}
                        </div>
                    )}

                    {adding ? (
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
                    )}
                </>
            )}

            {activeTab === 'view' && (
                <>
                    <p className="text-sm text-gray-500">
                        The most recent measurement for each metric, grouped by how often it's measured.
                    </p>

                    <MetricsView metrics={metrics} />
                </>
            )}
        </div>
    );
}
