import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { compressImageFiles } from '@/imageCompression';
import { formatDate } from '@/dateInput';

const STATUS_LABELS = {
    incomplete: 'Incomplete',
    complete: 'Complete',
};

const STATUS_COLORS = {
    incomplete: 'bg-gray-100 text-gray-600',
    complete: 'bg-green-100 text-green-700',
};

export default function Show({ measurement }) {
    const fileInput = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [value, setValue] = useState(
        measurement.answer_type === 'number'
            ? measurement.value_number ?? ''
            : measurement.value_text ?? ''
    );

    const valueField = measurement.answer_type === 'number' ? 'value_number' : 'value_text';

    const addMeasurement = () => {
        setSubmitting(true);
        router.patch(route('metric-measurements.update', measurement.id), {
            [valueField]: value === '' ? null : value,
            status: 'complete',
        }, { preserveScroll: true, onFinish: () => setSubmitting(false) });
    };

    // Reopens the period for editing without touching the recorded value -
    // it stays in place (pre-filled) until Add Measurement is pressed again.
    const remeasure = () => {
        router.patch(route('metric-measurements.update', measurement.id), {
            status: 'incomplete',
        }, { preserveScroll: true });
    };

    const destroyPhoto = (photoId) => {
        if (confirm('Delete this photo?')) {
            router.delete(route('photos.destroy', photoId));
        }
    };

    const uploadPhotos = async (e) => {
        const files = e.target.files;
        if (!files.length) return;

        setUploading(true);
        const compressed = await compressImageFiles(files);

        const formData = new FormData();
        compressed.forEach(file => formData.append('photos[]', file));

        router.post(route('photos.store-metric-measurement', measurement.id), formData, {
            forceFormData: true,
            onFinish: () => setUploading(false),
            onError: () => alert('Photo upload failed. Please try again with a smaller photo.'),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={measurement.name} />

            <div className="max-w-lg mx-auto space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <Link href={route('metrics.index')} className="text-green-600 text-sm">
                        ← Metrics
                    </Link>
                </div>

                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-start justify-between mb-3">
                        <h1 className="text-lg font-semibold text-gray-900">{measurement.name}</h1>
                        <span className={`text-xs px-2 py-1 rounded-full font-medium flex-shrink-0 ${STATUS_COLORS[measurement.status]}`}>
                            {STATUS_LABELS[measurement.status]}
                        </span>
                    </div>
                    <p className="text-sm text-gray-500">
                        {formatDate(measurement.period_start.slice(0, 10), { year: 'numeric' })} – {formatDate(measurement.period_end.slice(0, 10), { year: 'numeric' })}
                    </p>
                </div>

                {/* Value entry */}
                <div className="bg-white rounded-lg shadow p-4 space-y-3">
                    <label className="block text-sm font-medium text-gray-700">
                        {measurement.answer_type === 'number' ? 'Value' : 'Notes'}
                    </label>
                    {measurement.status === 'incomplete' ? (
                        <>
                            {measurement.answer_type === 'number' ? (
                                <input
                                    type="number"
                                    step="any"
                                    value={value}
                                    onChange={(e) => setValue(e.target.value)}
                                    className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                />
                            ) : (
                                <textarea
                                    value={value}
                                    onChange={(e) => setValue(e.target.value)}
                                    rows={4}
                                    className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                />
                            )}
                            <button
                                onClick={addMeasurement}
                                disabled={submitting}
                                className="w-full py-3 bg-green-600 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                            >
                                {submitting ? 'Saving…' : 'Add Measurement'}
                            </button>
                        </>
                    ) : (
                        <>
                            <p className="text-base text-gray-900 whitespace-pre-wrap">
                                {measurement.answer_type === 'number' ? measurement.value_number : measurement.value_text}
                            </p>
                            <button
                                onClick={remeasure}
                                className="w-full py-3 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50"
                            >
                                Re-measure
                            </button>
                        </>
                    )}
                </div>

                {/* Photos */}
                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Photos</h2>
                        <button
                            onClick={() => fileInput.current.click()}
                            disabled={uploading}
                            className="text-sm px-3 py-1 bg-green-600 text-white rounded-lg disabled:opacity-50"
                        >
                            {uploading ? 'Uploading…' : '+ Add Photo'}
                        </button>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/*"
                            multiple
                            onChange={uploadPhotos}
                            className="hidden"
                        />
                    </div>

                    {measurement.photos && measurement.photos.length === 0 ? (
                        <button
                            onClick={() => fileInput.current.click()}
                            className="w-full border-2 border-dashed border-gray-300 rounded-lg p-8 text-center text-gray-400"
                        >
                            <svg className="w-8 h-8 mx-auto mb-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <p className="text-sm">Tap to add a photo</p>
                        </button>
                    ) : (
                        <div className="grid grid-cols-3 gap-2">
                            {measurement.photos.map((photo) => (
                                <div key={photo.id} className="relative">
                                    <img
                                        src={photo.url}
                                        className="w-full h-24 object-cover rounded-lg"
                                    />
                                    <button
                                        onClick={() => destroyPhoto(photo.id)}
                                        className="absolute top-1 right-1 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                    >
                                        ×
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
