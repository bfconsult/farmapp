import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ColorPicker from '@/Components/ColorPicker';
import { pillBadgeClass } from '@/Utils/pillColors';
import { typeGroups } from '@/Components/AssetDetailsFields';

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
                <span className={`text-sm px-2 py-0.5 rounded-full ${pillBadgeClass(item.color)}`}>{item.name}</span>
                {item.order !== undefined && (
                    <span className="text-xs text-gray-400 ml-2">#{item.order}</span>
                )}
                {item.can_book_time !== undefined && (
                    <span className={`text-xs ml-2 px-1.5 py-0.5 rounded ${item.can_book_time ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                        {item.can_book_time ? 'bookable' : 'not bookable'}
                    </span>
                )}
                {item.is_in_progress_default && (
                    <span className="text-xs ml-2 px-1.5 py-0.5 rounded bg-blue-100 text-blue-700">
                        in-progress default
                    </span>
                )}
                {item.is_recurring_closed_default && (
                    <span className="text-xs ml-2 px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">
                        repeating-closed default
                    </span>
                )}
                {item.kind && (
                    <span className="text-xs ml-2 px-1.5 py-0.5 rounded bg-sky-100 text-sky-700" title="The farm view and sensors use this type, so it can be renamed or moved but not deleted">
                        farm view
                    </span>
                )}
                {item.is_finished_default && (
                    <span className="text-xs ml-2 px-1.5 py-0.5 rounded bg-green-100 text-green-700">
                        finished default
                    </span>
                )}
            </div>
            {item.is_protected ? (
                <span className="text-xs text-gray-400">Default</span>
            ) : (
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
            )}
        </div>
    );
}

function AddRow({ onAdd, extraFields }) {
    const [adding, setAdding] = useState(false);
    const [values, setValues] = useState({
        name: '', order: 0, color: null, can_book_time: true, is_in_progress_default: false, is_recurring_closed_default: false, is_finished_default: false,
    });

    const save = () => {
        onAdd(values);
        setValues({
            name: '', order: 0, color: null, can_book_time: true, is_in_progress_default: false, is_recurring_closed_default: false, is_finished_default: false,
        });
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

const BILLING_BLOCK_LABELS = {
    1: '1 minute',
    15: '15 minutes',
    30: '30 minutes',
    60: '1 hour',
};

export default function Index({ priorities, jobTypes, jobStatuses, assetTypes, livestockTypes, billingBlockMinutes, billingBlockOptions }) {
    const [activeTab, setActiveTab] = useState('priorities');

    const tabs = [
        { key: 'priorities', label: 'Priorities' },
        { key: 'jobTypes', label: 'Types' },
        { key: 'jobStatuses', label: 'Statuses' },
        { key: 'assetTypes', label: 'Asset Types' },
        { key: 'livestockTypes', label: 'Livestock Types' },
        { key: 'billing', label: 'Billing' },
    ];

    // Every action below stays on the current tab (preserveState: true) -
    // without it, Laravel's back() redirect remounts the whole page and
    // resets activeTab to its default ('priorities').
    const preserve = { preserveState: true, preserveScroll: true };

    // Priorities
    const addPriority = (values) => router.post(route('settings.priorities.store'), values, preserve);
    const savePriority = (id, values) => router.patch(route('settings.priorities.update', id), values, preserve);
    const deletePriority = (id) => {
        if (confirm('Delete this priority?')) router.delete(route('settings.priorities.destroy', id), preserve);
    };

    // Job Types
    const addJobType = (values) => router.post(route('settings.job-types.store'), { name: values.name, color: values.color }, preserve);
    const saveJobType = (id, values) => router.patch(route('settings.job-types.update', id), { name: values.name, color: values.color }, preserve);
    const deleteJobType = (id) => {
        if (confirm('Delete this job type?')) router.delete(route('settings.job-types.destroy', id), preserve);
    };

    // Asset Types
    const addAssetType = (values) => router.post(route('settings.asset-types.store'), { name: values.name, parent_id: values.parent_id || null }, preserve);
    const saveAssetType = (id, values) => router.patch(route('settings.asset-types.update', id), { name: values.name, parent_id: values.parent_id || null }, preserve);
    // Two levels: a category, or a sub-type of one. A category with
    // sub-types of its own has to stay a category.
    const assetTypeGroups = typeGroups(assetTypes);
    const categoryField = (values, setValues) => {
        if (values.id && assetTypes.some((type) => type.parent_id === values.id)) return null;
        return (
            <select
                value={values.parent_id ?? ''}
                onChange={(e) => setValues({ ...values, parent_id: e.target.value ? Number(e.target.value) : null })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
            >
                <option value="">A category of its own</option>
                {assetTypeGroups
                    .filter(({ category }) => category.id !== values.id)
                    .map(({ category }) => (
                        <option key={category.id} value={category.id}>Sub-type of {category.name}</option>
                    ))}
            </select>
        );
    };
    const deleteAssetType = (id) => {
        if (confirm('Delete this asset type?')) router.delete(route('settings.asset-types.destroy', id), preserve);
    };

    // Livestock Types
    const addLivestockType = (values) => router.post(route('settings.livestock-types.store'), { name: values.name }, preserve);
    const saveLivestockType = (id, values) => router.patch(route('settings.livestock-types.update', id), { name: values.name }, preserve);
    const deleteLivestockType = (id) => {
        if (confirm('Delete this livestock type?')) router.delete(route('settings.livestock-types.destroy', id), preserve);
    };

    // Job Statuses
    const addJobStatus = (values) => router.post(route('settings.job-statuses.store'), values, preserve);
    const saveJobStatus = (id, values) => router.patch(route('settings.job-statuses.update', id), values, preserve);
    const deleteJobStatus = (id) => {
        if (confirm('Delete this status?')) router.delete(route('settings.job-statuses.destroy', id), preserve);
    };

    // Billing block
    const setBillingBlock = (minutes) => {
        router.patch(route('settings.billing-block.update'), { billing_block_minutes: minutes }, preserve);
    };

    const colorField = (values, setValues) => (
        <ColorPicker value={values.color} onChange={(color) => setValues({ ...values, color })} />
    );

    const orderField = (values, setValues) => (
        <>
            <input
                type="number"
                value={values.order}
                onChange={(e) => setValues({ ...values, order: parseInt(e.target.value) })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Order"
            />
            {colorField(values, setValues)}
        </>
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
            {colorField(values, setValues)}
            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={values.can_book_time}
                    onChange={(e) => setValues({ ...values, can_book_time: e.target.checked })}
                    className="rounded"
                />
                Can book time against this status
            </label>
            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={values.is_in_progress_default ?? false}
                    onChange={(e) => setValues({ ...values, is_in_progress_default: e.target.checked })}
                    className="rounded"
                />
                Switch a job to this status when time is first booked against it
            </label>
            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={values.is_recurring_closed_default ?? false}
                    onChange={(e) => setValues({ ...values, is_recurring_closed_default: e.target.checked })}
                    className="rounded"
                />
                Switch a repeating job's instance to this status once its period ends
            </label>
            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={values.is_finished_default ?? false}
                    onChange={(e) => setValues({ ...values, is_finished_default: e.target.checked })}
                    className="rounded"
                />
                Switch a job to this status when "Finish Job" is tapped
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
                                    extraFields={colorField}
                                />
                            ))}
                            <AddRow onAdd={addJobType} extraFields={colorField} />
                        </>
                    )}

                    {activeTab === 'assetTypes' && (
                        <>
                            <p className="text-xs text-gray-500 mb-2">
                                Categories, each with its sub-types - e.g. Fence and Trough under Fixed. An asset can be in a category or one of its sub-types.
                            </p>
                            {assetTypeGroups.map(({ category, subTypes }) => (
                                <div key={category.id}>
                                    <LookupRow item={category} onSave={saveAssetType} onDelete={deleteAssetType} extraFields={categoryField} />
                                    {subTypes.length > 0 && (
                                        <div className="ml-6 border-l border-gray-100 pl-3">
                                            {subTypes.map((item) => (
                                                <LookupRow key={item.id} item={item} onSave={saveAssetType} onDelete={deleteAssetType} extraFields={categoryField} />
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                            <AddRow onAdd={addAssetType} extraFields={categoryField} />
                        </>
                    )}

                    {activeTab === 'livestockTypes' && (
                        <>
                            {livestockTypes.map((item) => (
                                <LookupRow
                                    key={item.id}
                                    item={item}
                                    onSave={saveLivestockType}
                                    onDelete={deleteLivestockType}
                                />
                            ))}
                            <AddRow onAdd={addLivestockType} />
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

                    {activeTab === 'billing' && (
                        <div>
                            <p className="text-sm text-gray-500 mb-3">
                                Round work session durations up to the nearest:
                            </p>
                            <div className="space-y-1">
                                <label className="flex items-center gap-2 py-2 px-1 text-sm text-gray-900">
                                    <input
                                        type="radio"
                                        name="billing_block"
                                        checked={!billingBlockMinutes}
                                        onChange={() => setBillingBlock(null)}
                                    />
                                    No rounding (exact time)
                                </label>
                                {billingBlockOptions.map((minutes) => (
                                    <label key={minutes} className="flex items-center gap-2 py-2 px-1 text-sm text-gray-900">
                                        <input
                                            type="radio"
                                            name="billing_block"
                                            checked={billingBlockMinutes === minutes}
                                            onChange={() => setBillingBlock(minutes)}
                                        />
                                        {BILLING_BLOCK_LABELS[minutes] ?? `${minutes} minutes`}
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}