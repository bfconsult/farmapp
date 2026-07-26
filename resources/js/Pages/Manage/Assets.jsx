import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDate } from '@/dateInput';

function AssetFields({ values, setValues, assetTypes }) {
    return (
        <div className="space-y-3">
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Name (e.g. Header tank)"
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
                    value={values.asset_type_id}
                    onChange={(e) => setValues({ ...values, asset_type_id: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                >
                    <option value="">No type</option>
                    {assetTypes.map((type) => (
                        <option key={type.id} value={type.id}>{type.name}</option>
                    ))}
                </select>
                <input
                    type="number"
                    step="0.01"
                    value={values.value}
                    onChange={(e) => setValues({ ...values, value: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Value ($, optional)"
                />
            </div>
        </div>
    );
}

function nearestDueDate(asset) {
    if (!asset.maintenance_items || asset.maintenance_items.length === 0) return null;
    return asset.maintenance_items.reduce((earliest, item) =>
        !earliest || item.next_due_date < earliest ? item.next_due_date : earliest,
        null
    );
}

function AssetRow({ asset, canManage, assetTypes }) {
    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({
        name: asset.name,
        description: asset.description ?? '',
        asset_type_id: asset.asset_type_id ?? '',
        value: asset.value ?? '',
    });

    const save = () => {
        router.patch(route('assets.update', asset.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm(`Delete "${asset.name}"? Its maintenance items will be deleted too.`)) {
            router.delete(route('assets.destroy', asset.id), { preserveScroll: true, preserveState: true });
        }
    };

    const dueDate = nearestDueDate(asset);
    const isOverdue = dueDate && dueDate < new Date().toISOString().slice(0, 10);

    if (editing) {
        return (
            <div className="p-4 bg-green-50 space-y-3">
                <AssetFields values={values} setValues={setValues} assetTypes={assetTypes} />
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                    <button onClick={() => setEditing(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between gap-2 px-4 py-3">
            <Link href={route('assets.show', asset.id)} className="min-w-0 flex-1">
                <p className="text-sm text-gray-900">{asset.name}</p>
                <div className="flex items-center gap-2 mt-1">
                    {asset.value && <span className="text-xs text-gray-500">${asset.value}</span>}
                    {dueDate && (
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${isOverdue ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'}`}>
                            {isOverdue ? 'Overdue' : 'Due'} {formatDate(dueDate)}
                        </span>
                    )}
                </div>
                {canManage && (
                    <div className="flex gap-3 mt-1 text-xs">
                        <button type="button" onClick={(e) => { e.preventDefault(); setEditing(true); }} className="text-green-600">Edit</button>
                        <button type="button" onClick={(e) => { e.preventDefault(); destroy(); }} className="text-red-500">Delete</button>
                    </div>
                )}
            </Link>
        </div>
    );
}

export default function Assets({ assets, assetTypes, canManage }) {
    const [adding, setAdding] = useState(false);
    const [values, setValues] = useState({ name: '', description: '', asset_type_id: '', value: '' });

    const create = () => {
        router.post(route('assets.store'), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setAdding(false);
                setValues({ name: '', description: '', asset_type_id: '', value: '' });
            },
        });
    };

    const grouped = [
        ...Object.values(
            assets.reduce((groups, asset) => {
                const key = asset.asset_type?.name ?? 'Other';
                (groups[key] ??= { label: key, assets: [] }).assets.push(asset);
                return groups;
            }, {})
        ),
    ];

    return (
        <AuthenticatedLayout title="Assets">
            <Head title="Assets" />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <BackLink href={route('manage.index')}>Manage</BackLink>
                </div>

                <h1 className="text-lg font-semibold text-gray-900">Assets</h1>

                <p className="text-sm text-gray-500">
                    Assets are property equipment, plant, and stock. Each can have maintenance items that turn into a job when due.
                </p>

                {canManage && (
                    adding ? (
                        <div className="bg-white rounded-lg shadow p-4 space-y-3">
                            <AssetFields values={values} setValues={setValues} assetTypes={assetTypes} />
                            <div className="flex gap-2">
                                <button onClick={create} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Asset</button>
                                <button onClick={() => setAdding(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                            </div>
                        </div>
                    ) : (
                        <button
                            onClick={() => setAdding(true)}
                            className="block w-full py-2 text-center text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
                        >
                            + Add Asset
                        </button>
                    )
                )}

                {assets.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No assets set up yet.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {grouped.map((group) => (
                            <div key={group.label} className="bg-white rounded-lg shadow overflow-hidden">
                                <div className="px-4 py-2 bg-gray-50 border-b border-gray-100">
                                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                        {group.label}
                                    </p>
                                </div>
                                <div className="divide-y divide-gray-100">
                                    {group.assets.map((asset) => (
                                        <AssetRow key={asset.id} asset={asset} canManage={canManage} assetTypes={assetTypes} />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
