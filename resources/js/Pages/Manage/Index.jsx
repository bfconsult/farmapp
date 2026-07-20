import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MetricsManager from '@/Components/MetricsManager';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDate } from '@/dateInput';

const TYPE_LABELS = {
    before_start: 'Before Start',
    at_completion: 'At Completion',
};

const TYPE_ORDER = ['before_start', 'at_completion'];

function ChecklistTemplateFields({ values, setValues }) {
    return (
        <div className="space-y-3">
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Name (e.g. Vehicle pre-start check)"
            />
            <select
                value={values.type}
                onChange={(e) => setValues({ ...values, type: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
            >
                {Object.entries(TYPE_LABELS).map(([value, label]) => (
                    <option key={value} value={value}>{label}</option>
                ))}
            </select>
        </div>
    );
}

function TemplateItemRow({ item }) {
    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({
        name: item.name,
        evidence: item.evidence ?? '',
        photo_required: item.photo_required,
    });

    const save = () => {
        router.patch(route('checklist-template-items.update', item.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm(`Delete item "${item.name}"?`)) {
            router.delete(route('checklist-template-items.destroy', item.id), { preserveScroll: true, preserveState: true });
        }
    };

    if (editing) {
        return (
            <div className="p-3 bg-green-50 space-y-2">
                <input
                    type="text"
                    value={values.name}
                    onChange={(e) => setValues({ ...values, name: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Item name"
                />
                <input
                    type="text"
                    value={values.evidence}
                    onChange={(e) => setValues({ ...values, evidence: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Evidence prompt (optional, e.g. fence voltage)"
                />
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={values.photo_required}
                        onChange={(e) => setValues({ ...values, photo_required: e.target.checked })}
                        className="rounded text-green-600 focus:ring-green-500"
                    />
                    Photo required
                </label>
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-1.5 bg-green-600 text-white rounded-lg text-xs">Save</button>
                    <button onClick={() => setEditing(false)} className="flex-1 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between gap-2 px-3 py-2">
            <div className="min-w-0">
                <p className="text-sm text-gray-900">{item.name}</p>
                {item.evidence && (
                    <p className="text-xs text-gray-500 mt-0.5">Evidence: {item.evidence}</p>
                )}
                <div className="flex gap-3 mt-1 text-xs">
                    <button onClick={() => setEditing(true)} className="text-green-600">Edit</button>
                    <button onClick={destroy} className="text-red-500">Delete</button>
                </div>
            </div>
            {item.photo_required && (
                <span className="text-xs px-2 py-1 rounded-full font-medium bg-amber-100 text-amber-700 flex-shrink-0">
                    Photo required
                </span>
            )}
        </div>
    );
}

function AddItemForm({ template, autoOpen = false }) {
    const [adding, setAdding] = useState(autoOpen);
    const [values, setValues] = useState({ name: '', evidence: '', photo_required: false });

    const create = () => {
        router.post(route('checklist-template-items.store', template.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setAdding(false);
                setValues({ name: '', evidence: '', photo_required: false });
            },
        });
    };

    if (!adding) {
        return (
            <button
                onClick={() => setAdding(true)}
                className="block w-full py-1.5 text-center text-xs text-green-600 border border-dashed border-green-300 rounded-lg"
            >
                + Add Item
            </button>
        );
    }

    return (
        <div className="p-3 bg-green-50 space-y-2 rounded-lg">
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Item name"
            />
            <input
                type="text"
                value={values.evidence}
                onChange={(e) => setValues({ ...values, evidence: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Evidence prompt (optional, e.g. fence voltage)"
            />
            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={values.photo_required}
                    onChange={(e) => setValues({ ...values, photo_required: e.target.checked })}
                    className="rounded text-green-600 focus:ring-green-500"
                />
                Photo required
            </label>
            <div className="flex gap-2">
                <button onClick={create} className="flex-1 py-1.5 bg-green-600 text-white rounded-lg text-xs">Add Item</button>
                <button onClick={() => setAdding(false)} className="flex-1 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs">Cancel</button>
            </div>
        </div>
    );
}

function TemplateRow({ template }) {
    const [editing, setEditing] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const [values, setValues] = useState({ name: template.name, type: template.type });

    const save = () => {
        router.patch(route('checklist-templates.update', template.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const toggleActive = () => {
        router.patch(route('checklist-templates.update', template.id), {
            ...values,
            is_active: !template.is_active,
        }, { preserveScroll: true, preserveState: true });
    };

    const destroy = () => {
        if (confirm(`Delete "${template.name}"? Checklists it already created will be left as-is.`)) {
            router.delete(route('checklist-templates.destroy', template.id), { preserveScroll: true, preserveState: true });
        }
    };

    if (editing) {
        return (
            <div className="p-4 bg-green-50 space-y-3">
                <ChecklistTemplateFields values={values} setValues={setValues} />
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                    <button onClick={() => setEditing(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div>
            <div className="flex items-center justify-between gap-2 px-4 py-3">
                <div className="min-w-0">
                    <p className="text-sm text-gray-900">{template.name}</p>
                    {template.items.length === 0 ? (
                        <button
                            onClick={() => setExpanded(true)}
                            className="mt-1 text-xs px-3 py-1.5 bg-green-600 text-white rounded-lg font-medium"
                        >
                            Add checkpoints
                        </button>
                    ) : (
                        <p className="text-xs text-gray-500 mt-1">{template.items.length} item{template.items.length === 1 ? '' : 's'}</p>
                    )}
                    <div className="flex gap-3 mt-1 text-xs">
                        {template.items.length > 0 && (
                            <button onClick={() => setExpanded((v) => !v)} className="text-gray-600">{expanded ? 'Hide items' : 'Show items'}</button>
                        )}
                        <button onClick={() => setEditing(true)} className="text-green-600">Edit</button>
                        <button onClick={toggleActive} className="text-blue-600">{template.is_active ? 'Pause' : 'Resume'}</button>
                        <button onClick={destroy} className="text-red-500">Delete</button>
                    </div>
                </div>
                {!template.is_active && (
                    <span className="text-xs px-2 py-1 rounded-full font-medium bg-gray-100 text-gray-500 flex-shrink-0">
                        Paused
                    </span>
                )}
            </div>
            {expanded && (
                <div className="px-4 pb-3 space-y-2 border-t border-gray-100 pt-2">
                    {template.items.length > 0 && (
                        <div className="bg-gray-50 rounded-lg divide-y divide-gray-100">
                            {template.items.map((item) => (
                                <TemplateItemRow key={item.id} item={item} />
                            ))}
                        </div>
                    )}
                    <AddItemForm template={template} autoOpen={template.items.length === 0} />
                </div>
            )}
        </div>
    );
}

function ChecklistsManager({ checklistTemplates }) {
    const [adding, setAdding] = useState(false);
    const [values, setValues] = useState({ name: '', type: 'before_start' });

    const create = () => {
        router.post(route('checklist-templates.store'), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setAdding(false);
                setValues({ name: '', type: 'before_start' });
            },
        });
    };

    return (
        <div className="space-y-4">
            <p className="text-sm text-gray-500">
                Checklist templates get attached to a job - their items are copied onto a fresh checklist each time, so editing a template later doesn't change checklists already in progress.
            </p>

            {checklistTemplates.length === 0 ? (
                <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                    No checklist templates set up yet.
                </div>
            ) : (
                <div className="space-y-4">
                    {TYPE_ORDER
                        .map((type) => ({
                            type,
                            templates: checklistTemplates.filter((t) => t.type === type),
                        }))
                        .filter((group) => group.templates.length > 0)
                        .map((group) => (
                            <div key={group.type} className="bg-white rounded-lg shadow overflow-hidden">
                                <div className="px-4 py-2 bg-gray-50 border-b border-gray-100">
                                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                        {TYPE_LABELS[group.type]}
                                    </p>
                                </div>
                                <div className="divide-y divide-gray-100">
                                    {group.templates.map((template) => (
                                        <TemplateRow key={template.id} template={template} />
                                    ))}
                                </div>
                            </div>
                        ))}
                </div>
            )}

            {adding ? (
                <div className="bg-white rounded-lg shadow p-4 space-y-3">
                    <ChecklistTemplateFields values={values} setValues={setValues} />
                    <div className="flex gap-2">
                        <button onClick={create} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Template</button>
                        <button onClick={() => setAdding(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                    </div>
                </div>
            ) : (
                <button
                    onClick={() => setAdding(true)}
                    className="block w-full py-2 text-center text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
                >
                    + Add Checklist Template
                </button>
            )}
        </div>
    );
}

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

function AssetsManager({ assets, assetTypes, canManage }) {
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
        <div className="space-y-4">
            <p className="text-sm text-gray-500">
                Assets are property equipment, plant, and stock. Each can have maintenance items that turn into a job when due.
            </p>

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
        </div>
    );
}

const ACTIVE_SECTION_STORAGE_KEY = 'manage-active-section';

export default function Index({ metrics, checklistTemplates, assets, assetTypes, canManage }) {
    const sections = [
        { id: 'metrics', label: 'Metrics' },
        ...(canManage ? [{ id: 'checklists', label: 'Checklists' }] : []),
        { id: 'assets', label: 'Assets' },
    ];

    // Remembered across full page navigations (e.g. drilling into an asset
    // then returning via its "← Manage" link, or the main nav) - a fresh
    // page visit always remounts this component, so plain useState alone
    // would silently reset to Metrics every time.
    const [activeSection, setActiveSection] = useState(() => {
        const stored = window.localStorage.getItem(ACTIVE_SECTION_STORAGE_KEY);
        return sections.some((section) => section.id === stored) ? stored : sections[0].id;
    });

    const selectSection = (id) => {
        setActiveSection(id);
        window.localStorage.setItem(ACTIVE_SECTION_STORAGE_KEY, id);
    };

    return (
        <AuthenticatedLayout title="Manage">
            <Head title="Manage" />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                {sections.length > 1 && (
                    <div className="flex items-center gap-2">
                        {sections.map((section) => (
                            <button
                                key={section.id}
                                onClick={() => selectSection(section.id)}
                                className={`rounded-full px-3 py-1 text-sm font-medium ${
                                    activeSection === section.id ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-600'
                                }`}
                            >
                                {section.label}
                            </button>
                        ))}
                    </div>
                )}

                {activeSection === 'metrics' && <MetricsManager metrics={metrics} />}
                {activeSection === 'checklists' && <ChecklistsManager checklistTemplates={checklistTemplates} />}
                {activeSection === 'assets' && <AssetsManager assets={assets} assetTypes={assetTypes} canManage={canManage} />}
            </div>
        </AuthenticatedLayout>
    );
}
