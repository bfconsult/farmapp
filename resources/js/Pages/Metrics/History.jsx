import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_LABELS = {
    incomplete: 'Incomplete',
    complete: 'Complete',
};

const STATUS_COLORS = {
    incomplete: 'bg-gray-100 text-gray-600',
    complete: 'bg-green-100 text-green-700',
};

function formatPeriod(measurement) {
    const format = (dateStr) =>
        new Date(`${dateStr.slice(0, 10)}T00:00:00`).toLocaleDateString('en-US', { day: 'numeric', month: 'short' });

    return `${format(measurement.period_start)} – ${format(measurement.period_end)}`;
}

function PhotoGrid({ photos }) {
    return (
        <div className="grid grid-cols-3 gap-2">
            {photos.map((photo) => (
                <img key={photo.id} src={photo.url} className="w-full h-24 object-cover rounded-lg" />
            ))}
        </div>
    );
}

/** Numeric metrics: a compact table, with a link that pops open a modal of
 * that row's photos rather than showing them inline (a table row has no
 * room for thumbnails). */
function NumberHistoryTable({ measurements }) {
    const [photosFor, setPhotosFor] = useState(null);
    const photoMeasurement = measurements.find((m) => m.id === photosFor);

    return (
        <>
            <div className="bg-white rounded-lg shadow overflow-x-auto">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="bg-gray-50 border-b border-gray-100 text-left text-gray-500 uppercase tracking-wide">
                            <th className="px-2 py-2 font-medium">Period</th>
                            <th className="px-2 py-2 font-medium">Value</th>
                            <th className="px-2 py-2 font-medium">Status</th>
                            <th className="px-2 py-2 font-medium">Photos</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {measurements.map((measurement) => (
                            <tr key={measurement.id}>
                                <td className="px-2 py-2 whitespace-nowrap text-gray-500">{formatPeriod(measurement)}</td>
                                <td className="px-2 py-2 text-gray-900 font-medium">
                                    {measurement.value_number ?? '—'}
                                </td>
                                <td className="px-2 py-2">
                                    <span className={`px-1.5 py-0.5 rounded-full font-medium whitespace-nowrap ${STATUS_COLORS[measurement.status]}`}>
                                        {STATUS_LABELS[measurement.status]}
                                    </span>
                                </td>
                                <td className="px-2 py-2">
                                    {measurement.photos.length > 0 ? (
                                        <button onClick={() => setPhotosFor(measurement.id)} className="flex items-center gap-1 text-green-600 whitespace-nowrap">
                                            <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            {measurement.photos.length}
                                        </button>
                                    ) : (
                                        <span className="text-gray-400">—</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Modal show={!!photoMeasurement} onClose={() => setPhotosFor(null)} maxWidth="md">
                <div className="p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-medium text-gray-900">
                            {photoMeasurement && formatPeriod(photoMeasurement)}
                        </h2>
                        <button onClick={() => setPhotosFor(null)} className="text-gray-400 hover:text-gray-600">✕</button>
                    </div>
                    {photoMeasurement && <PhotoGrid photos={photoMeasurement.photos} />}
                </div>
            </Modal>
        </>
    );
}

/** Text metrics: a card per measurement, with photos shown directly on the
 * card - a table row can't hold free-text plus thumbnails legibly. */
function TextHistoryCards({ measurements }) {
    return (
        <div className="space-y-3">
            {measurements.map((measurement) => (
                <div key={measurement.id} className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-center justify-between mb-2">
                        <p className="text-xs text-gray-500">{formatPeriod(measurement)}</p>
                        <span className={`text-xs px-2 py-1 rounded-full font-medium ${STATUS_COLORS[measurement.status]}`}>
                            {STATUS_LABELS[measurement.status]}
                        </span>
                    </div>
                    <p className="text-sm text-gray-900 whitespace-pre-wrap">
                        {measurement.value_text || 'No value yet'}
                    </p>
                    {measurement.photos.length > 0 && (
                        <div className="mt-3">
                            <PhotoGrid photos={measurement.photos} />
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

export default function History({ metric, measurements }) {
    return (
        <AuthenticatedLayout title="Metric History">
            <Head title={`${metric.name} — History`} />

            <div className="max-w-lg mx-auto space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <Link href={route('metrics.index')} className="text-green-600 text-sm">
                        ← Metrics
                    </Link>
                </div>

                <div>
                    <h1 className="text-lg font-semibold text-gray-900">{metric.name}</h1>
                    {metric.description && (
                        <p className="text-sm text-gray-500 mt-1">{metric.description}</p>
                    )}
                </div>

                {measurements.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No measurements yet.
                    </div>
                ) : metric.answer_type === 'number' ? (
                    <NumberHistoryTable measurements={measurements} />
                ) : (
                    <TextHistoryCards measurements={measurements} />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
