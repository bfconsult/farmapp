import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const INTERVAL_LABELS = {
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
    yearly: 'Yearly',
};

function emptyValues() {
    return {
        name: '',
        description: '',
        job_type_id: '',
        priority_id: '',
        estimated_hours: '',
        budget: '',
        hourly_rate: '',
        interval: 'monthly',
        starts_on: new Date().toISOString().slice(0, 10),
    };
}

function RecurringJobFields({ values, setValues, jobTypes, priorities, showStartsOn }) {
    return (
        <div className="space-y-3">
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Name"
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
                    value={values.job_type_id}
                    onChange={(e) => setValues({ ...values, job_type_id: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                >
                    <option value="">Type</option>
                    {jobTypes.map((type) => (
                        <option key={type.id} value={type.id}>{type.name}</option>
                    ))}
                </select>
                <select
                    value={values.priority_id}
                    onChange={(e) => setValues({ ...values, priority_id: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                >
                    <option value="">Priority</option>
                    {priorities.map((priority) => (
                        <option key={priority.id} value={priority.id}>{priority.name}</option>
                    ))}
                </select>
            </div>
            <div className="grid grid-cols-3 gap-2">
                <input
                    type="number"
                    step="0.5"
                    value={values.estimated_hours}
                    onChange={(e) => setValues({ ...values, estimated_hours: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Est. hours"
                />
                <input
                    type="number"
                    step="0.01"
                    value={values.budget}
                    onChange={(e) => setValues({ ...values, budget: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Budget ($)"
                />
                <input
                    type="number"
                    step="0.01"
                    value={values.hourly_rate}
                    onChange={(e) => setValues({ ...values, hourly_rate: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Rate ($/hr)"
                />
            </div>
            <div className="grid grid-cols-2 gap-2">
                <select
                    value={values.interval}
                    onChange={(e) => setValues({ ...values, interval: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                >
                    {Object.entries(INTERVAL_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
                {showStartsOn ? (
                    <input
                        type="date"
                        value={values.starts_on}
                        onChange={(e) => setValues({ ...values, starts_on: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                ) : (
                    <div className="flex items-center text-xs text-gray-400 px-1">
                        Start date can't be changed after creation
                    </div>
                )}
            </div>
        </div>
    );
}

function RecurringJobCard({ recurringJob, jobTypes, priorities }) {
    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({
        name: recurringJob.name,
        description: recurringJob.description ?? '',
        job_type_id: recurringJob.job_type_id ?? '',
        priority_id: recurringJob.priority_id ?? '',
        estimated_hours: recurringJob.estimated_hours ?? '',
        budget: recurringJob.budget ?? '',
        hourly_rate: recurringJob.hourly_rate ?? '',
        interval: recurringJob.interval,
    });

    const save = () => {
        router.patch(route('recurring-jobs.update', recurringJob.id), values, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const toggleActive = () => {
        router.patch(route('recurring-jobs.update', recurringJob.id), {
            ...values,
            is_active: !recurringJob.is_active,
        }, { preserveScroll: true });
    };

    const destroy = () => {
        if (confirm(`Delete "${recurringJob.name}"? Jobs it already created will be left as-is.`)) {
            router.delete(route('recurring-jobs.destroy', recurringJob.id), { preserveScroll: true });
        }
    };

    if (editing) {
        return (
            <div className="bg-green-50 rounded-lg shadow p-4 space-y-3">
                <RecurringJobFields values={values} setValues={setValues} jobTypes={jobTypes} priorities={priorities} showStartsOn={false} />
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                    <button onClick={() => setEditing(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-lg shadow p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-medium text-gray-900">{recurringJob.name}</p>
                    <p className="text-xs text-gray-500 mt-1">
                        {INTERVAL_LABELS[recurringJob.interval]} · {recurringJob.instances_count} instance{recurringJob.instances_count === 1 ? '' : 's'} created
                    </p>
                </div>
                <span className={`text-xs px-2 py-1 rounded-full font-medium flex-shrink-0 ${recurringJob.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                    {recurringJob.is_active ? 'Active' : 'Paused'}
                </span>
            </div>
            <div className="flex gap-3 mt-3 text-sm">
                <button onClick={() => setEditing(true)} className="text-green-600">Edit</button>
                <button onClick={toggleActive} className="text-blue-600">{recurringJob.is_active ? 'Pause' : 'Resume'}</button>
                <button onClick={destroy} className="text-red-500">Delete</button>
            </div>
        </div>
    );
}

export default function Index({ recurringJobs, jobTypes, priorities }) {
    const [adding, setAdding] = useState(false);
    const [values, setValues] = useState(emptyValues());

    const add = () => {
        router.post(route('recurring-jobs.store'), values, {
            preserveScroll: true,
            onSuccess: () => {
                setValues(emptyValues());
                setAdding(false);
            },
        });
    };

    return (
        <AuthenticatedLayout title="Recurring Jobs">
            <Head title="Recurring Jobs" />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <p className="text-sm text-gray-500">
                    Recurring jobs automatically create a new job instance each period (e.g. a monthly management-hours bucket), closing out the previous one once its period ends.
                </p>

                {recurringJobs.length === 0 && !adding ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No recurring jobs set up yet.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {recurringJobs.map((recurringJob) => (
                            <RecurringJobCard
                                key={recurringJob.id}
                                recurringJob={recurringJob}
                                jobTypes={jobTypes}
                                priorities={priorities}
                            />
                        ))}
                    </div>
                )}

                {adding ? (
                    <div className="bg-green-50 rounded-lg shadow p-4 space-y-3">
                        <RecurringJobFields values={values} setValues={setValues} jobTypes={jobTypes} priorities={priorities} showStartsOn />
                        <div className="flex gap-2">
                            <button onClick={add} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add</button>
                            <button onClick={() => setAdding(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                        </div>
                    </div>
                ) : (
                    <button
                        onClick={() => setAdding(true)}
                        className="w-full py-2 text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
                    >
                        + Add Recurring Job
                    </button>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
