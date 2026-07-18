import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { compressImageFiles } from '@/imageCompression';

const TYPE_LABELS = {
    before_start: 'Before Start',
    at_completion: 'At Completion',
};

const STATUS_LABELS = {
    incomplete: 'Incomplete',
    complete: 'Complete',
};

const STATUS_COLORS = {
    incomplete: 'bg-gray-100 text-gray-600',
    complete: 'bg-green-100 text-green-700',
};

function ChecklistItemRow({ item, canEdit }) {
    const fileInput = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [evidenceValue, setEvidenceValue] = useState(item.evidence_value ?? '');
    const [error, setError] = useState(null);

    const toggleChecked = () => {
        setError(null);
        router.patch(route('checklist-items.update', item.id), {
            is_checked: !item.is_checked,
        }, {
            preserveScroll: true,
            onError: (errors) => setError(errors.is_checked ?? 'Could not update this item.'),
        });
    };

    const saveEvidence = () => {
        if (evidenceValue === (item.evidence_value ?? '')) return;
        router.patch(route('checklist-items.update', item.id), {
            evidence_value: evidenceValue,
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

        router.post(route('photos.store-checklist-item', item.id), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
            onError: () => alert('Photo upload failed. Please try again with a smaller photo.'),
        });
    };

    return (
        <div className="p-4 space-y-3">
            <div className="flex items-start gap-3">
                <input
                    type="checkbox"
                    checked={item.is_checked}
                    onChange={canEdit ? toggleChecked : undefined}
                    disabled={!canEdit}
                    className="mt-1 w-5 h-5 rounded text-green-600 focus:ring-green-500 flex-shrink-0"
                />
                <div className="min-w-0 flex-1">
                    <p className={`text-sm ${item.is_checked ? 'text-gray-500 line-through' : 'text-gray-900'}`}>
                        {item.name}
                    </p>
                    {item.photo_required && (
                        <span className={`inline-block text-xs px-2 py-0.5 rounded-full font-medium mt-1 ${
                            item.photos.length > 0 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'
                        }`}>
                            {item.photos.length > 0 ? 'Photo attached' : 'Photo required'}
                        </span>
                    )}
                    {error && (
                        <p className="text-xs text-red-600 mt-1">{error}</p>
                    )}
                </div>
            </div>

            {item.evidence_prompt && (
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Evidence: {item.evidence_prompt}</label>
                    <input
                        type="text"
                        value={evidenceValue}
                        onChange={(e) => setEvidenceValue(e.target.value)}
                        onBlur={saveEvidence}
                        disabled={!canEdit}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm disabled:bg-gray-50"
                    />
                </div>
            )}

            {(item.photo_required || item.photos.length > 0) && (
                <div>
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs text-gray-500">Photos</span>
                        {canEdit && (
                            <button
                                onClick={() => fileInput.current.click()}
                                disabled={uploading}
                                className="text-xs px-2 py-1 bg-green-600 text-white rounded-lg disabled:opacity-50"
                            >
                                {uploading ? 'Uploading…' : '+ Add Photo'}
                            </button>
                        )}
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            multiple
                            onChange={uploadPhotos}
                            className="hidden"
                        />
                    </div>
                    {item.photos.length === 0 ? (
                        canEdit && (
                            <button
                                onClick={() => fileInput.current.click()}
                                className="w-full border-2 border-dashed border-gray-300 rounded-lg p-4 text-center text-gray-400 text-xs"
                            >
                                Tap to add a photo
                            </button>
                        )
                    ) : (
                        <div className="grid grid-cols-3 gap-2">
                            {item.photos.map((photo) => (
                                <div key={photo.id} className="relative">
                                    <img src={photo.url} className="w-full h-20 object-cover rounded-lg" />
                                    {canEdit && (
                                        <button
                                            onClick={() => destroyPhoto(photo.id)}
                                            className="absolute top-1 right-1 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                        >
                                            ×
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function Show({ checklist }) {
    const { currentUserRole } = usePage().props;
    const canEdit = currentUserRole === 'admin' || currentUserRole === 'manager' || currentUserRole === 'worker';
    const checkedCount = checklist.items.filter((item) => item.is_checked).length;

    return (
        <AuthenticatedLayout>
            <Head title={checklist.name} />

            <div className="max-w-lg mx-auto space-y-4">
                <div className="flex items-center justify-between">
                    <Link href={route('jobs.show', checklist.farm_job_id)} className="text-green-600 text-sm">
                        ← {checklist.farm_job?.name ?? 'Job'}
                    </Link>
                </div>

                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-start justify-between mb-2">
                        <h1 className="text-lg font-semibold text-gray-900">{checklist.name}</h1>
                        <span className={`text-xs px-2 py-1 rounded-full font-medium flex-shrink-0 ${STATUS_COLORS[checklist.status]}`}>
                            {STATUS_LABELS[checklist.status]}
                        </span>
                    </div>
                    <p className="text-sm text-gray-500">
                        {TYPE_LABELS[checklist.type]} · {checkedCount}/{checklist.items.length} checked
                    </p>
                </div>

                <div className="bg-white rounded-lg shadow divide-y divide-gray-100">
                    {checklist.items.map((item) => (
                        <ChecklistItemRow key={item.id} item={item} canEdit={canEdit} />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
