import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { formatDate } from '@/dateInput';

const ASSET_COLOR = '#2563eb';

function AssetFields({ values, setValues }) {
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
            <input
                type="number"
                step="0.01"
                value={values.value}
                onChange={(e) => setValues({ ...values, value: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Value ($, optional)"
            />
        </div>
    );
}

function MaintenanceItemRow({ item, canManage, canConvert }) {
    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({
        name: item.name,
        description: item.description ?? '',
        start_date: item.start_date.slice(0, 10),
        repeat_period_days: item.repeat_period_days,
        auto_generate: item.auto_generate,
    });

    const save = () => {
        router.patch(route('maintenance-items.update', item.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm(`Delete maintenance item "${item.name}"?`)) {
            router.delete(route('maintenance-items.destroy', item.id), { preserveScroll: true, preserveState: true });
        }
    };

    const convert = () => {
        if (confirm(`Turn "${item.name}" into a job now?`)) {
            router.post(route('maintenance-items.convert', item.id));
        }
    };

    const isOverdue = item.next_due_date.slice(0, 10) < new Date().toISOString().slice(0, 10);

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
                <textarea
                    value={values.description}
                    onChange={(e) => setValues({ ...values, description: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Description (optional)"
                    rows={2}
                />
                <div className="grid grid-cols-2 gap-2">
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Start date</label>
                        <input
                            type="date"
                            value={values.start_date}
                            onChange={(e) => setValues({ ...values, start_date: e.target.value })}
                            className="w-full border-gray-300 rounded-lg p-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Repeat (days)</label>
                        <input
                            type="number"
                            min="1"
                            value={values.repeat_period_days}
                            onChange={(e) => setValues({ ...values, repeat_period_days: e.target.value })}
                            className="w-full border-gray-300 rounded-lg p-2 text-sm"
                        />
                    </div>
                </div>
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={values.auto_generate}
                        onChange={(e) => setValues({ ...values, auto_generate: e.target.checked })}
                        className="rounded text-green-600 focus:ring-green-500"
                    />
                    Auto-generate the job when due
                </label>
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-1.5 bg-green-600 text-white rounded-lg text-xs">Save</button>
                    <button onClick={() => setEditing(false)} className="flex-1 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between gap-2 px-3 py-3">
            <div className="min-w-0 flex-1">
                <p className="text-sm text-gray-900">{item.name}</p>
                {item.description && (
                    <p className="text-xs text-gray-500 mt-0.5">{item.description}</p>
                )}
                <div className="flex items-center gap-2 mt-1">
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${isOverdue ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'}`}>
                        {isOverdue ? 'Overdue' : 'Due'} {formatDate(item.next_due_date.slice(0, 10))}
                    </span>
                    <span className="text-xs text-gray-400">every {item.repeat_period_days}d</span>
                    {item.auto_generate && (
                        <span className="text-xs px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-700">
                            Auto
                        </span>
                    )}
                </div>
                <div className="flex gap-3 mt-1 text-xs">
                    {canConvert && (
                        <button onClick={convert} className="text-green-600 font-medium">Turn into Job</button>
                    )}
                    {canManage && (
                        <>
                            <button onClick={() => setEditing(true)} className="text-green-600">Edit</button>
                            <button onClick={destroy} className="text-red-500">Delete</button>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

function AddMaintenanceItemForm({ asset }) {
    const todayIso = () => new Date().toISOString().slice(0, 10);
    const [adding, setAdding] = useState(false);
    const [values, setValues] = useState({ name: '', description: '', start_date: todayIso(), repeat_period_days: 90, auto_generate: false });

    const create = () => {
        router.post(route('maintenance-items.store', asset.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setAdding(false);
                setValues({ name: '', description: '', start_date: todayIso(), repeat_period_days: 90, auto_generate: false });
            },
        });
    };

    if (!adding) {
        return (
            <button
                onClick={() => setAdding(true)}
                className="block w-full py-2 text-center text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
            >
                + Add Maintenance Item
            </button>
        );
    }

    return (
        <div className="bg-white rounded-lg shadow p-4 space-y-3">
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Name (e.g. Service pump)"
            />
            <textarea
                value={values.description}
                onChange={(e) => setValues({ ...values, description: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Description (optional)"
                rows={2}
            />
            <div className="grid grid-cols-2 gap-2">
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Start date</label>
                    <input
                        type="date"
                        value={values.start_date}
                        onChange={(e) => setValues({ ...values, start_date: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                </div>
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Repeat (days)</label>
                    <input
                        type="number"
                        min="1"
                        value={values.repeat_period_days}
                        onChange={(e) => setValues({ ...values, repeat_period_days: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                </div>
            </div>
            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={values.auto_generate}
                    onChange={(e) => setValues({ ...values, auto_generate: e.target.checked })}
                    className="rounded text-green-600 focus:ring-green-500"
                />
                Auto-generate the job when due
            </label>
            <div className="flex gap-2">
                <button onClick={create} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Item</button>
                <button onClick={() => setAdding(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
            </div>
        </div>
    );
}

export default function Show({ asset }) {
    const { currentUserRole } = usePage().props;
    const canManage = currentUserRole === 'admin' || currentUserRole === 'manager';
    const canConvert = canManage || currentUserRole === 'worker';

    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({
        name: asset.name,
        description: asset.description ?? '',
        value: asset.value ?? '',
    });

    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const locationLayer = useRef(null);

    const save = () => {
        router.patch(route('assets.update', asset.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const clearLocation = () => {
        if (!confirm('Clear this asset\'s location?')) return;
        router.put(route('assets.update-location', asset.id), {}, { preserveScroll: true });
    };

    useEffect(() => {
        if (mapInstance.current) return;

        Promise.all([
            import('leaflet'),
            import('@geoman-io/leaflet-geoman-free'),
            import('leaflet/dist/leaflet.css'),
            import('@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css'),
        ]).then(([L]) => {
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            });

            const map = L.map(mapRef.current).setView([-37.8136, 144.9631], 15);
            mapInstance.current = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            if (asset.shape) {
                locationLayer.current = L.polygon(asset.shape, {
                    color: ASSET_COLOR,
                    fillColor: ASSET_COLOR,
                    fillOpacity: 0.15,
                }).addTo(map);
                map.fitBounds(locationLayer.current.getBounds(), { padding: [40, 40] });
            } else if (asset.latitude && asset.longitude) {
                locationLayer.current = L.marker([asset.latitude, asset.longitude]).addTo(map);
                map.setView([asset.latitude, asset.longitude], 17);
            } else {
                map.locate({ setView: true, maxZoom: 16 });
            }

            if (canManage) {
                map.pm.addControls({
                    position: 'topleft',
                    drawMarker: true,
                    drawCircleMarker: false,
                    drawPolyline: false,
                    drawRectangle: false,
                    drawCircle: false,
                    drawText: false,
                    editMode: true,
                    dragMode: false,
                    cutPolygon: false,
                    removalMode: false,
                });

                map.on('pm:create', (e) => {
                    if (locationLayer.current) locationLayer.current.remove();
                    locationLayer.current = e.layer;
                    map.pm.disableDraw();

                    if (e.shape === 'Marker') {
                        const { lat, lng } = e.layer.getLatLng();
                        router.put(route('assets.update-location', asset.id), { latitude: lat, longitude: lng }, { preserveScroll: true });
                    } else {
                        const coordinates = e.layer.getLatLngs()[0].map((ll) => [ll.lat, ll.lng]);
                        router.put(route('assets.update-location', asset.id), { shape: coordinates }, { preserveScroll: true });
                    }
                });
            }
        });

        return () => {
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
            }
        };
    }, []);

    return (
        <AuthenticatedLayout>
            <Head title={asset.name} />

            <div className="max-w-lg mx-auto space-y-4">
                <div className="flex items-center justify-between">
                    <Link href={route('manage.index')} className="text-green-600 text-sm">
                        ← Manage
                    </Link>
                </div>

                <div className="bg-white rounded-lg shadow p-4">
                    {editing ? (
                        <div className="space-y-3">
                            <AssetFields values={values} setValues={setValues} />
                            <div className="flex gap-2">
                                <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                                <button onClick={() => setEditing(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="flex items-start justify-between mb-2">
                                <h1 className="text-lg font-semibold text-gray-900">{asset.name}</h1>
                                {asset.asset_type && (
                                    <span className="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600 flex-shrink-0">
                                        {asset.asset_type.name}
                                    </span>
                                )}
                            </div>
                            {asset.description && (
                                <p className="text-sm text-gray-500 mb-2">{asset.description}</p>
                            )}
                            {asset.value && (
                                <p className="text-sm text-gray-900">${asset.value}</p>
                            )}
                            {canManage && (
                                <button onClick={() => setEditing(true)} className="text-xs text-green-600 mt-2">Edit</button>
                            )}
                        </>
                    )}
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Location</h2>
                        {canManage && (asset.shape || (asset.latitude && asset.longitude)) && (
                            <button onClick={clearLocation} className="text-xs text-red-500">Clear location</button>
                        )}
                    </div>
                    <div ref={mapRef} style={{ height: '300px' }} />
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide px-4 py-2 border-b border-gray-100">
                        Maintenance Items
                    </h2>
                    {asset.maintenance_items.length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No maintenance items yet.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {asset.maintenance_items.map((item) => (
                                <MaintenanceItemRow key={item.id} item={item} canManage={canManage} canConvert={canConvert} />
                            ))}
                        </div>
                    )}
                </div>

                {canManage && <AddMaintenanceItemForm asset={asset} />}
            </div>
        </AuthenticatedLayout>
    );
}
