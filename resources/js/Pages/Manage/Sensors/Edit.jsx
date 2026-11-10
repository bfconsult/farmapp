import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import LocationMap from '@/Components/LocationMap';
import Modal from '@/Components/Modal';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

/** Rough centroid of the property boundary, as a starting pin. */
function boundaryCenter(coordinates) {
    if (!coordinates || coordinates.length === 0) return null;
    const lat = coordinates.reduce((sum, [pointLat]) => sum + pointLat, 0) / coordinates.length;
    const lng = coordinates.reduce((sum, [, pointLng]) => sum + pointLng, 0) / coordinates.length;
    return { lat, lng };
}

export default function Edit({ device, models, kinds, prefill, propertyBoundary, zones = [] }) {
    const isNew = !device;

    const kindsFor = (model) => kinds.filter((kind) => kind.models.includes(model));
    const initialModel = device?.model ?? models[0]?.value ?? '';

    const { data, setData, post, put, processing, errors } = useForm({
        name: device?.name ?? prefill?.name ?? '',
        model: initialModel,
        kind: isNew ? (kindsFor(initialModel)[0]?.value ?? '') : (device.kind ?? ''),
        dev_eui: device?.dev_eui ?? prefill?.dev_eui ?? '',
        latitude: device?.latitude ?? null,
        longitude: device?.longitude ?? null,
    });

    const hasPin = data.latitude !== null && data.longitude !== null;
    const [showLocationModal, setShowLocationModal] = useState(false);
    const [pendingLocation, setPendingLocation] = useState(null);
    const [locating, setLocating] = useState(false);
    const [locateError, setLocateError] = useState(null);
    // Zones show the fences, which is what a gate sits on.
    const [showZones, setShowZones] = useState(true);
    const shownZones = showZones ? zones : [];

    const openLocationModal = () => {
        setPendingLocation(hasPin ? { lat: data.latitude, lng: data.longitude } : boundaryCenter(propertyBoundary));
        setShowLocationModal(true);
    };

    const saveLocation = () => {
        if (pendingLocation) {
            setData((current) => ({ ...current, latitude: pendingLocation.lat, longitude: pendingLocation.lng }));
        }
        setShowLocationModal(false);
    };

    // For setting it up standing next to the sensor.
    const useMyLocation = () => {
        if (!navigator.geolocation) {
            setLocateError('This device can\'t share its location.');
            return;
        }
        setLocating(true);
        setLocateError(null);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setData((current) => ({ ...current, latitude: position.coords.latitude, longitude: position.coords.longitude }));
                setLocating(false);
            },
            () => {
                setLocateError('Couldn\'t get your location.');
                setLocating(false);
            },
            { timeout: 10000, enableHighAccuracy: true }
        );
    };

    const submit = (e) => {
        e.preventDefault();
        if (isNew) {
            post(route('manage.sensors.store'));
        } else {
            put(route('manage.sensors.update', device.id));
        }
    };

    const destroy = () => {
        if (confirm(`Remove "${device.name}"? Its readings are deleted too.`)) {
            router.delete(route('manage.sensors.destroy', device.id));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={isNew ? 'Add Sensor' : `Edit ${device.name}`} />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">
                            {isNew ? 'Add Sensor' : 'Edit Sensor'}
                        </h1>
                        <BackLink href={isNew ? route('manage.sensors.index') : route('manage.sensors.show', device.id)}>
                            {isNew ? 'Sensors' : device.name}
                        </BackLink>
                    </div>

                    <div className="bg-white rounded-lg shadow p-6">
                        <form onSubmit={submit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Front gate"
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                            </div>

                            {isNew ? (
                                <>
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Sensor type</label>
                                        <select
                                            value={data.model}
                                            onChange={(e) => setData((current) => ({
                                                ...current,
                                                model: e.target.value,
                                                kind: kindsFor(e.target.value)[0]?.value ?? '',
                                            }))}
                                            className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                        >
                                            {models.map((model) => (
                                                <option key={model.value} value={model.value}>{model.label}</option>
                                            ))}
                                        </select>
                                        {errors.model && <p className="mt-1 text-sm text-red-600">{errors.model}</p>}
                                    </div>

                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">DevEUI</label>
                                        <input
                                            type="text"
                                            value={data.dev_eui}
                                            onChange={(e) => setData('dev_eui', e.target.value.replace(/[\s:-]/g, ''))}
                                            placeholder="16 characters, e.g. A84041210862A0CE"
                                            maxLength={16}
                                            className="w-full font-mono border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                        />
                                        {errors.dev_eui && <p className="mt-1 text-sm text-red-600">{errors.dev_eui}</p>}
                                    </div>
                                </>
                            ) : (
                                <p className="mb-4 text-sm text-gray-500">
                                    {device.model_label} · <span className="font-mono">{device.dev_eui}</span>
                                    <br />
                                    To change the type or DevEUI, remove the sensor and add it again. Its stored uplinks are decoded again when you do.
                                </p>
                            )}

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Used as</label>
                                <select
                                    value={data.kind}
                                    onChange={(e) => setData('kind', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                >
                                    {kindsFor(data.model).map((kind) => (
                                        <option key={kind.value} value={kind.value}>{kind.label}</option>
                                    ))}
                                    <option value="">Other (raw readings only)</option>
                                </select>
                                {errors.kind && <p className="mt-1 text-sm text-red-600">{errors.kind}</p>}
                            </div>

                            <div className="mb-6">
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-sm font-medium text-gray-700">Location</label>
                                    {zones.length > 0 && (
                                    <label className="flex items-center gap-1.5 text-xs text-gray-600">
                                        <input
                                            type="checkbox"
                                            checked={showZones}
                                            onChange={(e) => setShowZones(e.target.checked)}
                                            className="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                        />
                                        Show zones
                                    </label>
                                )}
                                </div>
                                <div className="rounded-lg border border-gray-200 overflow-hidden">
                                    {hasPin || propertyBoundary || shownZones.length > 0 ? (
                                        <LocationMap
                                            key={`${data.latitude}-${data.longitude}-${showZones}`}
                                            latitude={data.latitude}
                                            longitude={data.longitude}
                                            propertyBoundary={propertyBoundary}
                                            zones={shownZones}
                                            interactive={false}
                                            height="160px"
                                        />
                                    ) : (
                                        <div className="h-24 flex items-center justify-center text-sm text-gray-400">No location set</div>
                                    )}
                                    <div className="flex items-center justify-between gap-3 px-3 py-2 bg-white border-t border-gray-200 text-sm">
                                        <span className="text-xs text-gray-500">{locating && 'Finding your location…'}</span>
                                        <span className="flex items-center gap-4">
                                            {hasPin && (
                                                <button
                                                    type="button"
                                                    onClick={() => setData((current) => ({ ...current, latitude: null, longitude: null }))}
                                                    className="text-gray-500"
                                                >
                                                    Remove
                                                </button>
                                            )}
                                            <button type="button" onClick={useMyLocation} className="text-green-600 font-medium">
                                                Use my location
                                            </button>
                                            <button type="button" onClick={openLocationModal} className="text-green-600 font-medium">
                                                {hasPin ? 'Move' : 'Set on map'}
                                            </button>
                                        </span>
                                    </div>
                                </div>
                                {locateError && <p className="mt-1 text-sm text-red-600">{locateError}</p>}
                                {(errors.latitude || errors.longitude) && (
                                    <p className="mt-1 text-sm text-red-600">{errors.latitude || errors.longitude}</p>
                                )}
                            </div>

                            <div className="flex items-center justify-between">
                                {!isNew ? (
                                    <button type="button" onClick={destroy} className="text-sm text-red-600">
                                        Remove sensor
                                    </button>
                                ) : <span />}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md font-medium disabled:opacity-50"
                                >
                                    {isNew ? 'Add Sensor' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <Modal show={showLocationModal} onClose={() => setShowLocationModal(false)} maxWidth="lg">
                <div className="p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h3 className="text-sm font-medium text-gray-700">Sensor location</h3>
                        <div className="flex items-center gap-3">
                            <button type="button" onClick={saveLocation} className="text-sm text-green-600 font-medium">Done</button>
                            <button type="button" onClick={() => setShowLocationModal(false)} className="text-sm text-gray-500">Cancel</button>
                        </div>
                    </div>
                    {showLocationModal && (
                        pendingLocation ? (
                            <LocationMap
                                key={showZones}
                                latitude={pendingLocation.lat}
                                longitude={pendingLocation.lng}
                                propertyBoundary={propertyBoundary}
                                zones={shownZones}
                                editable
                                onDragEnd={(lat, lng) => setPendingLocation({ lat, lng })}
                            />
                        ) : (
                            <p className="text-sm text-gray-500 py-8 text-center">
                                Draw the property boundary first, or use "Use my location" while you're at the sensor.
                            </p>
                        )
                    )}
                    {pendingLocation && (
                        <div className="flex items-center justify-between gap-3 mt-2">
                            <p className="text-xs text-gray-400">Tap the map or drag the pin to where the sensor is.</p>
                            {zones.length > 0 && (
                                <label className="flex items-center gap-1.5 text-xs text-gray-600">
                                    <input
                                        type="checkbox"
                                        checked={showZones}
                                        onChange={(e) => setShowZones(e.target.checked)}
                                        className="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                    />
                                    Show zones
                                </label>
                            )}
                        </div>
                    )}
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
