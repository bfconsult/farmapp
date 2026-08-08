import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import LocationMap from '@/Components/LocationMap';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const todayIso = () => new Date().toISOString().slice(0, 10);

/** Rough centroid of a property boundary, used to give the location picker
 * a sensible starting pin position when GPS hasn't provided one. */
function boundaryCenter(coordinates) {
    if (!coordinates || coordinates.length === 0) return null;
    const lat = coordinates.reduce((sum, [pointLat]) => sum + pointLat, 0) / coordinates.length;
    const lng = coordinates.reduce((sum, [, pointLng]) => sum + pointLng, 0) / coordinates.length;
    return { lat, lng };
}

export default function Create({ priorities, jobTypes, jobStatuses, currentProperty, checklistTemplates, assets, selectedAssetId }) {
    const defaultStatus = jobStatuses.find((status) => status.is_default);

    const { data, setData, post, transform, processing, errors } = useForm({
        name: '',
        description: '',
        estimated_hours: '',
        budget: '',
        hourly_rate: '',
        priority_id: '',
        job_type_id: '',
        job_status_id: defaultStatus ? String(defaultStatus.id) : '',
        zone_id: '',
        asset_id: selectedAssetId ? String(selectedAssetId) : '',
        latitude: '',
        longitude: '',
        repeats: false,
        interval: 'monthly',
        starts_on: todayIso(),
        scheduled_date: '',
        checklist_template_ids: [],
    });

    const toggleChecklistTemplate = (templateId) => {
        setData('checklist_template_ids',
            data.checklist_template_ids.includes(templateId)
                ? data.checklist_template_ids.filter((id) => id !== templateId)
                : [...data.checklist_template_ids, templateId]
        );
    };

    const [locationStatus, setLocationStatus] = useState('getting');
    const [showOptional, setShowOptional] = useState(false);
    const [showLocationModal, setShowLocationModal] = useState(false);
    const [pendingLocation, setPendingLocation] = useState(null);

    const propertyBoundary = currentProperty.shape?.coordinates;

    const openLocationModal = () => {
        setPendingLocation(
            data.latitude && data.longitude
                ? { lat: data.latitude, lng: data.longitude }
                : boundaryCenter(propertyBoundary)
        );
        setShowLocationModal(true);
    };

    const saveLocation = () => {
        if (pendingLocation) {
            setData((current) => ({ ...current, latitude: pendingLocation.lat, longitude: pendingLocation.lng }));
        }
        setShowLocationModal(false);
    };

    const hasPin = Boolean(data.latitude && data.longitude);
    const locationCaption = hasPin
        ? 'Location set'
        : locationStatus === 'getting'
            ? 'Finding your location…'
            : 'No location set';

    useEffect(() => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    setData(data => ({
                        ...data,
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                    }));
                    setLocationStatus('got');
                },
                () => {
                    setLocationStatus('failed');
                },
                { timeout: 10000, enableHighAccuracy: true }
            );
        } else {
            setLocationStatus('failed');
        }
    }, []);

    const submitWithIntent = (intent) => {
        transform((data) => ({ ...data, intent }));
        post(route('jobs.store'));
    };

    const submit = (e) => {
        e.preventDefault();
        submitWithIntent(data.repeats ? 'plan' : 'camera');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Add Job" />

            <div className="max-w-lg mx-auto">
                <div className="mb-6">
                    <h1 className="text-xl font-semibold text-gray-900">Add Job</h1>
                </div>

                {currentProperty.zones && currentProperty.zones.length > 0 && (
                    <div className="mb-4">
                        <label className="block text-xs text-gray-500 mb-1">
                            Zone <span className="text-gray-400">optional</span>
                        </label>
                        <select
                            value={data.zone_id}
                            onChange={(e) => setData('zone_id', e.target.value)}
                            className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                        >
                            <option value="">No specific zone</option>
                            {currentProperty.zones.map((zone) => (
                                <option key={zone.id} value={zone.id}>{zone.name}</option>
                            ))}
                        </select>
                        {errors.zone_id && <p className="mt-1 text-sm text-red-600">{errors.zone_id}</p>}
                    </div>
                )}

                {assets.length > 0 && (
                    <div className="mb-4">
                        <label className="block text-xs text-gray-500 mb-1">
                            Asset <span className="text-gray-400">optional</span>
                        </label>
                        <select
                            value={data.asset_id}
                            onChange={(e) => setData('asset_id', e.target.value)}
                            className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                        >
                            <option value="">Not related to an asset</option>
                            {assets.map((asset) => (
                                <option key={asset.id} value={asset.id}>{asset.name}</option>
                            ))}
                        </select>
                        {errors.asset_id && <p className="mt-1 text-sm text-red-600">{errors.asset_id}</p>}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <input
                            type="text"
                            placeholder="Job name *"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoFocus
                            className="w-full text-lg border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-4"
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div className="rounded-lg border border-gray-200 overflow-hidden">
                        {hasPin || propertyBoundary ? (
                            <LocationMap
                                key={`${data.latitude}-${data.longitude}`}
                                latitude={data.latitude || null}
                                longitude={data.longitude || null}
                                propertyBoundary={propertyBoundary}
                                interactive={false}
                                height="128px"
                            />
                        ) : (
                            <div className="h-32 flex items-center justify-center text-sm text-gray-400">
                                {locationStatus === 'getting' ? 'Finding your location…' : 'Location unavailable'}
                            </div>
                        )}
                        <div className="flex items-center justify-between px-3 py-2 bg-white border-t border-gray-200">
                            <span className="text-xs text-gray-500">{locationCaption}</span>
                            <button
                                type="button"
                                onClick={openLocationModal}
                                className="text-sm text-green-600 font-medium"
                            >
                                Change
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={() => setShowOptional(!showOptional)}
                        className="text-sm text-green-600 hover:text-green-800"
                    >
                        {showOptional ? '▲ Hide details' : '▼ Add details (optional)'}
                    </button>

                    {showOptional && (
                        <div className="space-y-4">
                            <textarea
                                placeholder="Description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                            />

                            {!data.repeats && (
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">
                                        Scheduled Date <span className="text-gray-400">optional</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={data.scheduled_date}
                                        onChange={(e) => setData('scheduled_date', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    />
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Priority</label>
                                    <select
                                        value={data.priority_id}
                                        onChange={(e) => setData('priority_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    >
                                        <option value="">Select</option>
                                        {priorities.map((priority) => (
                                            <option key={priority.id} value={priority.id}>{priority.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Type</label>
                                    <select
                                        value={data.job_type_id}
                                        onChange={(e) => setData('job_type_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    >
                                        <option value="">Select</option>
                                        {jobTypes.map((type) => (
                                            <option key={type.id} value={type.id}>{type.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Status</label>
                                    <select
                                        value={data.job_status_id}
                                        onChange={(e) => setData('job_status_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    >
                                        <option value="">Select</option>
                                        {jobStatuses.map((status) => (
                                            <option key={status.id} value={status.id}>{status.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Est. Hours</label>
                                    <input
                                        type="number"
                                        step="0.5"
                                        placeholder="0"
                                        value={data.estimated_hours}
                                        onChange={(e) => setData('estimated_hours', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Budget ($)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        value={data.budget}
                                        onChange={(e) => setData('budget', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">
                                        Hourly Rate ($) <span className="text-gray-400">optional</span>
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        placeholder="Uses worker's rate"
                                        value={data.hourly_rate}
                                        onChange={(e) => setData('hourly_rate', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    />
                                </div>
                            </div>

                            {checklistTemplates.length > 0 && (
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">
                                        Checklists <span className="text-gray-400">optional</span>
                                    </label>
                                    <div className="space-y-1 border border-gray-200 rounded-lg divide-y divide-gray-100">
                                        {checklistTemplates.map((template) => (
                                            <label
                                                key={template.id}
                                                className="flex items-center gap-2 px-3 py-2 cursor-pointer"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={data.checklist_template_ids.includes(template.id)}
                                                    onChange={() => toggleChecklistTemplate(template.id)}
                                                    className="rounded text-green-600 focus:ring-green-500"
                                                />
                                                <span className="text-sm text-gray-900">{template.name}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="border-t border-gray-200 pt-4">
                        <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input
                                type="checkbox"
                                checked={data.repeats}
                                onChange={(e) => setData((current) => ({
                                    ...current,
                                    repeats: e.target.checked,
                                    scheduled_date: e.target.checked ? '' : current.scheduled_date,
                                }))}
                                className="rounded"
                            />
                            Make this job repeat
                        </label>

                        {data.repeats && (
                            <div className="grid grid-cols-2 gap-3 mt-3">
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Repeats</label>
                                    <select
                                        value={data.interval}
                                        onChange={(e) => setData('interval', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    >
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Starting</label>
                                    <input
                                        type="date"
                                        value={data.starts_on}
                                        onChange={(e) => setData('starts_on', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex gap-2 pt-2">
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="flex-1 flex items-center justify-center py-3 border border-gray-300 text-gray-700 rounded-lg text-sm"
                        >
                            Cancel
                        </button>
                        {data.repeats ? (
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 flex items-center justify-center py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
                            >
                                Create Repeating Job
                            </button>
                        ) : (
                            <>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 flex flex-col items-center justify-center gap-1 py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
                                >
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Report
                                </button>
                                <button
                                    type="button"
                                    disabled={processing}
                                    onClick={() => submitWithIntent('plan')}
                                    className="flex-1 flex flex-col items-center justify-center gap-1 py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
                                >
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Plan
                                </button>
                            </>
                        )}
                    </div>
                </form>

                <Modal show={showLocationModal} onClose={() => setShowLocationModal(false)} maxWidth="lg">
                    <div className="p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-sm font-medium text-gray-700">Location</h3>
                            <div className="flex items-center gap-3">
                                <button onClick={saveLocation} className="text-sm text-green-600 font-medium">Save</button>
                                <button onClick={() => setShowLocationModal(false)} className="text-sm text-gray-500">Cancel</button>
                            </div>
                        </div>
                        {showLocationModal && (
                            pendingLocation ? (
                                <LocationMap
                                    latitude={pendingLocation.lat}
                                    longitude={pendingLocation.lng}
                                    propertyBoundary={propertyBoundary}
                                    editable
                                    onDragEnd={(lat, lng) => setPendingLocation({ lat, lng })}
                                />
                            ) : (
                                <p className="text-sm text-gray-500 py-8 text-center">
                                    Couldn't determine a starting location - try again once GPS is available.
                                </p>
                            )
                        )}
                        {pendingLocation && (
                            <p className="text-xs text-gray-400 mt-2">Drag the pin to set the location.</p>
                        )}
                    </div>
                </Modal>
            </div>
        </AuthenticatedLayout>
    );
}