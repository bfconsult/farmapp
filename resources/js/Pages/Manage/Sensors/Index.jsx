import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { formatDateTime, formatReading, timeAgo } from '@/Utils/sensorFormat';

function UnrecognisedDevice({ device }) {
    const [showRaw, setShowRaw] = useState(false);

    return (
        <div className="px-4 py-3">
            <div className="flex items-center justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-sm text-gray-900 truncate">
                        {device.device_name || 'Unnamed'} <span className="font-mono text-xs text-gray-500">{device.dev_eui}</span>
                    </p>
                    <p className="text-xs text-gray-500">
                        {device.uplinks} uplink{device.uplinks === 1 ? '' : 's'} · last {timeAgo(device.received_at)}
                    </p>
                </div>
                <div className="flex items-center gap-3 flex-shrink-0 text-sm">
                    <button type="button" onClick={() => setShowRaw(!showRaw)} className="text-gray-500">
                        {showRaw ? 'Hide' : 'Raw'}
                    </button>
                    <Link
                        href={route('manage.sensors.create', { dev_eui: device.dev_eui, name: device.device_name ?? '' })}
                        className="text-green-600 font-medium"
                    >
                        Add
                    </Link>
                </div>
            </div>
            {showRaw && (
                <div className="mt-2">
                    <p className="text-xs text-gray-500 mb-1">Newest uplink, received {formatDateTime(device.received_at)}</p>
                    <pre className="text-xs bg-gray-50 border border-gray-200 rounded p-2 overflow-x-auto">
                        {JSON.stringify(device.body, null, 2)}
                    </pre>
                </div>
            )}
        </div>
    );
}

export default function Index({ devices, unrecognised }) {
    return (
        <AuthenticatedLayout title="Sensors">
            <Head title="Sensors" />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <BackLink href={route('manage.index')}>Manage</BackLink>
                </div>

                <h1 className="text-lg font-semibold text-gray-900">Sensors</h1>

                {devices.length === 0 ? (
                    <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                        <h2 className="text-base font-semibold text-gray-900 mb-1">Add your first sensor</h2>
                        <p className="text-sm text-gray-600 mb-4">
                            Sensors report through your LoRaWAN gateway. Add one by its DevEUI,
                            printed on its label, and anything it has already sent shows up straight away.
                        </p>
                        <Link
                            href={route('manage.sensors.create')}
                            className="inline-block px-6 py-3 bg-green-600 text-white rounded-lg font-medium"
                        >
                            + Add Sensor
                        </Link>
                    </div>
                ) : (
                    <>
                        <Link
                            href={route('manage.sensors.create')}
                            className="block w-full py-2 text-center text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
                        >
                            + Add Sensor
                        </Link>
                        <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                            {devices.map((device) => (
                                <Link
                                    key={device.id}
                                    href={route('manage.sensors.show', device.id)}
                                    className="flex items-center justify-between gap-2 px-4 py-3 hover:bg-gray-50"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm text-gray-900 truncate">{device.name}</p>
                                        <p className="text-xs text-gray-500 truncate">
                                            {device.latest.length > 0
                                                ? device.latest.map((reading) => (reading.states || reading.unit ? '' : `${reading.label} `) + formatReading(reading.value, reading)).join(' · ')
                                                : 'No readings yet'}
                                        </p>
                                    </div>
                                    <span className="text-xs text-gray-400 flex-shrink-0">{timeAgo(device.last_seen_at)}</span>
                                </Link>
                            ))}
                        </div>
                    </>
                )}

                {unrecognised.length > 0 && (
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900">Unrecognised devices</h2>
                        <p className="text-xs text-gray-500 mb-2">
                            The gateway has heard these, but no property has added them. Only app admins see this list.
                        </p>
                        <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                            {unrecognised.map((device) => (
                                <UnrecognisedDevice key={device.dev_eui} device={device} />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
