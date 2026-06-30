import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function LookupRow({ item, onSave, onDelete, extraFields }) {
    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState(item);

    const save = () => {
        onSave(item.id, values);
        setEditing(false);
    };

    if (editing) {
        return (
            <div className="p-3 bg-green-50 rounded-lg space-y-2">
                <input
                    type="text"
                    value={values.name}
                    onChange={(e) => setValues({ ...values, name: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Name"
                />
                {extraFields && extraFields(values, setValues)}
                <div className="flex gap-2">
                    <button
                        onClick={save}
                        className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm"
                    >
                        Save
                    </button>
                    <button
                        onClick={() => setEditing(false)}
                        className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between py-3 px-1 border-b border-gray-100 last:border-0">
            <div>
                <span className="text-sm text-gray-900">{item.name}</span>
                {item.order !== undefined && (
                    <span className="text-xs text-gray-400 ml-2">#{item.order}</span>
                )}
                {item.can_book_time !== undefined && (
                    <span className={`text-xs ml-2 px-1.5 py-0.5 rounded ${item.can_book_time ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                        {item.can_book_time ? 'bookable' : 'not bookable'}
                    </span>
                )}
            </div>
            <div className="flex gap-3">
                <button
                    onClick={() => setEditing(true)}
                    className="text-sm text-green-600"
                >
                    Edit
                </button>
                <button
                    onClick={() => onDelete(item.id)}
                    className="text-sm text-red-500"
                >
                    Delete
                </button>
            </div>
        </div>
    );
}

function AddRow({ onAdd, extraFields }) {
    const [adding, setAdding] = useState(false);
    const [values, setValues] = useState({ name: '', order: 0, can_book_time: true });

    const save = () => {
        onAdd(values);
        setValues({ name: '', order: 0, can_book_time: true });
        setAdding(false);
    };

    if (!adding) {
        return (
            <button
                onClick={() => setAdding(true)}
                className="w-full py-2 text-sm text-green-600 border border-dashed border-green-300 rounded-lg mt-2"
            >
                + Add New
            </button>
        );
    }

    return (
        <div className="p-3 bg-green-50 rounded-lg space-y-2 mt-2">
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Name"
                autoFocus
            />
            {extraFields && extraFields(values, setValues)}
            <div className="flex gap-2">
                <button
                    onClick={save}
                    className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm"
                >
                    Add
                </button>
                <button
                    onClick={() => setAdding(false)}
                    className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm"
                >
                    Cancel
                </button>
            </div>
        </div>
    );
}

export default function Index({ priorities, jobTypes, jobStatuses }) {
    const [activeTab, setActiveTab] = useState('priorities');

    const tabs = [
        { key: 'priorities', label: 'Priorities' },
        { key: 'jobTypes', label: 'Types' },
        { key: 'jobStatuses', label: 'Statuses' },
    ];

    // Priorities
    const addPriority = (values) => router.post(route('settings.priorities.store'), values);
    const savePriority = (id, values) => router.patch(route('settings.priorities.update', id), values);
    const deletePriority = (id) => {
        if (confirm('Delete this priority?')) router.delete(route('settings.priorities.destroy', id));
    };

    // Job Types
    const addJobType = (values) => router.post(route('settings.job-types.store'), { name: values.name });
    const saveJobType = (id, values) => router.patch(route('settings.job-types.update', id), { name: values.name });
    const deleteJobType = (id) => {
        if (confirm('Delete this job type?')) router.delete(route('settings.job-types.destroy', id));
    };

    // Job Statuses
    const addJobStatus = (values) => router.post(route('settings.job-statuses.store'), values);
    const saveJobStatus = (id, values) => router.patch(route('settings.job-statuses.update', id), values);
    const deleteJobStatus = (id) => {
        if (confirm('Delete this status?')) router.delete(route('settings.job-statuses.destroy', id));
    };

    const orderField = (values, setValues) => (
        <input
            type="number"
            value={values.order}
            onChange={(e) => setValues({ ...values, order: parseInt(e.target.value) })}
            className="w-full border-gray-300 rounded-lg p-2 text-sm"
            placeholder="Order"
        />
    );

    const statusFields = (values, setValues) => (
        <>
            <input
                type="number"
                value={values.order}
                onChange={(e) => setValues({ ...values, order: parseInt(e.target.value) })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Order"
            />
            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={values.can_book_time}
                    onChange={(e) => setValues({ ...values, can_book_time: e.target.checked })}
                    className="rounded"
                />
                Can book time against this status
            </label>
        </>
    );

    return (
        <AuthenticatedLayout title="Settings">
            <Head title="Settings" />

            <div className="max-w-lg mx-auto mt-2">
                {/* Tabs */}
                <div className="flex gap-1 bg-gray-100 rounded-lg p-1 mb-4">
                    {tabs.map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            className={`flex-1 py-2 rounded-md text-sm font-medium transition-all ${
                                activeTab === tab.key
                                    ? 'bg-white text-gray-900 shadow'
                                    : 'text-gray-500'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                <div className="bg-white rounded-lg shadow p-4">
                    {activeTab === 'priorities' && (
                        <>
                            {priorities.map((item) => (
                                <LookupRow
                                    key={item.id}
                                    item={item}
                                    onSave={savePriority}
                                    onDelete={deletePriority}
                                    extraFields={orderField}
                                />
                            ))}
                            <AddRow onAdd={addPriority} extraFields={orderField} />
                        </>
                    )}

                    {activeTab === 'jobTypes' && (
                        <>
                            {jobTypes.map((item) => (
                                <LookupRow
                                    key={item.id}
                                    item={item}
                                    onSave={saveJobType}
                                    onDelete={deleteJobType}
                                />
                            ))}
                            <AddRow onAdd={addJobType} />
                        </>
                    )}

                    {activeTab === 'jobStatuses' && (
                        <>
                            {jobStatuses.map((item) => (
                                <LookupRow
                                    key={item.id}
                                    item={item}
                                    onSave={saveJobStatus}
                                    onDelete={deleteJobStatus}
                                    extraFields={statusFields}
                                />
                            ))}
                            <AddRow onAdd={addJobStatus} extraFields={statusFields} />
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}