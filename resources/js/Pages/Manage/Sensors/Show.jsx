import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDateTime, formatReading, signalLabel, timeAgo } from '@/Utils/sensorFormat';

function UplinkRow({ event, metrics }) {
    const [showRaw, setShowRaw] = useState(false);
    const definitions = Object.fromEntries(metrics.map((m) => [m.metric, m]));
    const shown = metrics.filter((m) => m.shown && event.values[m.metric] !== undefined);

    let summary;
    if (event.error) {
        summary = <span className="text-red-600">Couldn't decode: {event.error}</span>;
    } else if (!event.processed_at) {
        summary = <span className="text-gray-500">Not decoded yet</span>;
    } else if (Object.keys(event.values).length === 0) {
        summary = <span className="text-gray-500">No readings (port {event.f_port})</span>;
    } else {
        summary = (shown.length > 0 ? shown : Object.keys(event.values).map((metric) => definitions[metric] ?? { metric, label: metric }))
            .map((m) => `${m.label}: ${formatReading(event.values[m.metric], m)}`)
            .join(' · ');
    }

    return (
        <div className="px-4 py-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-sm text-gray-900">{summary}</p>
                    <p className="text-xs text-gray-500">
                        {formatDateTime(event.recorded_at)}
                        {event.f_cnt !== null && ` · frame ${event.f_cnt}`}
                    </p>
                </div>
                <button type="button" onClick={() => setShowRaw(!showRaw)} className="text-sm text-gray-500 flex-shrink-0">
                    {showRaw ? 'Hide' : 'Raw'}
                </button>
            </div>
            {showRaw && (
                <div className="mt-2 space-y-2">
                    {Object.keys(event.values).length > 0 && (
                        <table className="w-full text-xs">
                            <tbody>
                                {Object.entries(event.values).map(([metric, value]) => (
                                    <tr key={metric} className="border-b border-gray-100 last:border-0">
                                        <td className="py-1 text-gray-500">{definitions[metric]?.label ?? metric}</td>
                                        <td className="py-1 font-mono text-gray-400">{metric}</td>
                                        <td className="py-1 text-right text-gray-900">{formatReading(value, definitions[metric])}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    <pre className="text-xs bg-gray-50 border border-gray-200 rounded p-2 overflow-x-auto">
                        {JSON.stringify(event.body, null, 2)}
                    </pre>
                </div>
            )}
        </div>
    );
}

export default function Show({ device, metrics, events }) {
    const toggleShown = (metric) => {
        const shownMetrics = metrics
            .filter((m) => (m.metric === metric ? !m.shown : m.shown))
            .map((m) => m.metric);

        router.patch(route('manage.sensors.shown-metrics', device.id), { shown_metrics: shownMetrics }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const signal = signalLabel(device.last_rssi, device.last_snr);

    // Fields the model can report but this sensor never has (e.g. an LDS02's
    // leak fields while it's in door mode) sit behind a link. A sensor with
    // no readings at all starts with them open, so the list isn't empty.
    const unreportedCount = metrics.filter((m) => !m.recorded_at).length;
    const [showUnreported, setShowUnreported] = useState(unreportedCount === metrics.length);
    const visibleMetrics = metrics.filter((m) => m.recorded_at || showUnreported);

    return (
        <AuthenticatedLayout title={device.name}>
            <Head title={device.name} />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <BackLink href={route('manage.sensors.index')}>Sensors</BackLink>
                    <Link href={route('manage.sensors.edit', device.id)} className="text-sm text-green-600 font-medium">
                        Edit
                    </Link>
                </div>

                <div>
                    <h1 className="text-lg font-semibold text-gray-900">{device.name}</h1>
                    <p className="text-xs text-gray-500">
                        {device.model_label} · <span className="font-mono">{device.dev_eui}</span>
                    </p>
                </div>

                <div className="bg-white rounded-lg shadow p-4 grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p className="text-xs text-gray-500">Last seen</p>
                        <p className="text-sm text-gray-900">{timeAgo(device.last_seen_at)}</p>
                    </div>
                    <div>
                        <p className="text-xs text-gray-500">Battery</p>
                        <p className="text-sm text-gray-900">{device.battery !== null ? `${device.battery.toFixed(2)} V` : '—'}</p>
                    </div>
                    <div>
                        <p className="text-xs text-gray-500">Signal</p>
                        <p className="text-sm text-gray-900">{signal ? signal.split(' (')[0] : '—'}</p>
                    </div>
                    {signal && <p className="col-span-3 text-xs text-gray-400">{signal}</p>}
                </div>

                <div>
                    <h2 className="text-sm font-semibold text-gray-900">Fields</h2>
                    <p className="text-xs text-gray-500 mb-2">
                        Every field is stored. Tick the ones to show for this sensor.
                    </p>
                    <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                        {visibleMetrics.map((m) => (
                            <label key={m.metric} className={`flex items-center gap-3 px-4 py-2.5 ${m.recorded_at ? '' : 'opacity-50'}`}>
                                <input
                                    type="checkbox"
                                    checked={m.shown}
                                    onChange={() => toggleShown(m.metric)}
                                    className="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                />
                                <span className="flex-1 min-w-0">
                                    <span className="block text-sm text-gray-900">{m.label}</span>
                                    <span className="block text-xs text-gray-400">
                                        {m.recorded_at ? `${timeAgo(m.recorded_at)}` : 'Not reported by this sensor yet'}
                                    </span>
                                </span>
                                <span className="text-sm text-gray-900">{formatReading(m.value, m)}</span>
                            </label>
                        ))}
                    </div>
                    {unreportedCount > 0 && unreportedCount < metrics.length && (
                        <button
                            type="button"
                            onClick={() => setShowUnreported(!showUnreported)}
                            className="mt-2 text-sm text-green-600 font-medium"
                        >
                            {showUnreported
                                ? 'Hide fields not reported yet'
                                : `Show ${unreportedCount} more field${unreportedCount === 1 ? '' : 's'} not reported yet`}
                        </button>
                    )}
                </div>

                <div>
                    <h2 className="text-sm font-semibold text-gray-900 mb-2">Recent uplinks</h2>
                    {events.length === 0 ? (
                        <p className="text-sm text-gray-500">Nothing received from this sensor yet.</p>
                    ) : (
                        <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                            {events.map((event) => (
                                <UplinkRow key={event.id} event={event} metrics={metrics} />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
