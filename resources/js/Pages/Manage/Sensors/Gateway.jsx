import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { timeAgo } from '@/Utils/sensorFormat';

function CopyRow({ label, value }) {
    const [copied, setCopied] = useState(false);

    const copy = () => {
        navigator.clipboard?.writeText(value).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <div className="px-4 py-2.5">
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs text-gray-500">{label}</p>
                <button type="button" onClick={copy} className="text-xs text-green-600 font-medium flex-shrink-0">
                    {copied ? 'Copied' : 'Copy'}
                </button>
            </div>
            <p className="text-sm font-mono text-gray-900 break-all">{value}</p>
        </div>
    );
}

export default function Gateway({ gateway, issuedKey, eventUrls, header }) {
    const regenerate = () => {
        if (confirm('Issue a new key? The current key stops working straight away, so the gateway stops reporting until you enter the new one.')) {
            router.post(route('manage.gateways.regenerate-key', gateway.id));
        }
    };

    const destroy = () => {
        if (confirm(`Remove "${gateway.name}"? Its key stops working. Readings already received are kept.`)) {
            router.delete(route('manage.gateways.destroy', gateway.id));
        }
    };

    return (
        <AuthenticatedLayout title={gateway.name}>
            <Head title={gateway.name} />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <BackLink href={route('manage.sensors.index')}>Sensors</BackLink>
                </div>

                <div>
                    <h1 className="text-lg font-semibold text-gray-900">{gateway.name}</h1>
                    <p className="text-xs text-gray-500">
                        Gateway EUI <span className="font-mono">{gateway.gateway_eui}</span> · last message {timeAgo(gateway.last_seen_at)}
                    </p>
                </div>

                {issuedKey ? (
                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <p className="text-sm font-semibold text-gray-900">Copy this key now</p>
                        <p className="text-xs text-gray-600 mb-2">
                            It won't be shown again. If you lose it, issue a new one below.
                        </p>
                        <div className="bg-white rounded border border-amber-200">
                            <CopyRow label={`Header value (${header})`} value={issuedKey} />
                        </div>
                    </div>
                ) : null}

                <div>
                    <h2 className="text-sm font-semibold text-gray-900">Gateway setup</h2>
                    <p className="text-xs text-gray-500 mb-2">
                        In the gateway's HTTP integration, add one custom header named <span className="font-mono">{header}</span> with
                        the key as its value, and enter these addresses for each event type.
                    </p>
                    <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                        <CopyRow label="Header name" value={header} />
                        {Object.entries(eventUrls).map(([event, url]) => (
                            <CopyRow key={event} label={`${event.charAt(0).toUpperCase()}${event.slice(1)} URL`} value={url} />
                        ))}
                    </div>
                </div>

                <div className="flex items-center justify-between pt-2">
                    <button type="button" onClick={destroy} className="text-sm text-red-600">
                        Remove gateway
                    </button>
                    <button type="button" onClick={regenerate} className="text-sm text-green-600 font-medium">
                        Issue a new key
                    </button>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
