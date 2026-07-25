import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { formatDate } from '@/dateInput';
import { compressImageFiles } from '@/imageCompression';

export default function NoteRow({ note, canManage, canCreate }) {
    const cameraInput = useRef(null);
    const galleryInput = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [editing, setEditing] = useState(false);
    const [body, setBody] = useState(note.body);
    const [seen, setSeen] = useState(!note.is_unread);

    // Marked seen only on an explicit open (tapping the note itself), not
    // just because the page it's on happened to load.
    const markSeen = () => {
        if (seen) return;
        setSeen(true);
        router.post(route('notes.mark-seen', note.id), {}, { preserveScroll: true, preserveState: true });
    };

    const save = () => {
        router.patch(route('notes.update', note.id), { body }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm('Delete this note?')) {
            router.delete(route('notes.destroy', note.id), { preserveScroll: true, preserveState: true });
        }
    };

    const destroyPhoto = (photoId) => {
        if (confirm('Delete this photo?')) {
            router.delete(route('photos.destroy', photoId), { preserveScroll: true, preserveState: true });
        }
    };

    const uploadPhotos = async (e) => {
        const files = e.target.files;
        if (!files.length) return;

        setUploading(true);
        const compressed = await compressImageFiles(files);

        const formData = new FormData();
        compressed.forEach(file => formData.append('photos[]', file));

        router.post(route('photos.store-note', note.id), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
            onError: () => alert('Photo upload failed. Please try again with a smaller photo.'),
        });
    };

    if (editing) {
        return (
            <div className="p-3 bg-green-50 space-y-2">
                <textarea
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    rows={3}
                />
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-1.5 bg-green-600 text-white rounded-lg text-xs">Save</button>
                    <button onClick={() => { setEditing(false); setBody(note.body); }} className="flex-1 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div className="px-3 py-3">
            <div onClick={markSeen} className="cursor-pointer">
                <p className="text-sm text-gray-900 whitespace-pre-wrap flex items-start gap-1.5">
                    {!seen && (
                        <span className="mt-1.5 w-2 h-2 rounded-full bg-red-500 flex-shrink-0" aria-label="Unread" />
                    )}
                    <span>{note.body}</span>
                </p>
                <p className="text-xs text-gray-400 mt-1">
                    {note.created_by?.name} · {formatDate(note.created_at)}
                </p>
            </div>

            {note.photos && note.photos.length > 0 && (
                <div className="grid grid-cols-3 gap-2 mt-2">
                    {note.photos.map((photo) => (
                        <div key={photo.id} className="relative">
                            <img src={photo.url} className="w-full h-20 object-cover rounded-lg" />
                            {canManage && (
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

            <div className="flex gap-3 mt-2 text-xs">
                {canCreate && (
                    <>
                        <button onClick={() => cameraInput.current.click()} disabled={uploading} aria-label="Take photo" className="text-green-600 disabled:opacity-50">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                        <button onClick={() => galleryInput.current.click()} disabled={uploading} aria-label="Choose from gallery" className="text-green-600 disabled:opacity-50">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" />
                            </svg>
                        </button>
                    </>
                )}
                {canManage && (
                    <>
                        <button onClick={() => setEditing(true)} className="text-green-600">Edit</button>
                        <button onClick={destroy} className="text-red-500">Delete</button>
                    </>
                )}
            </div>
            <input
                ref={cameraInput}
                type="file"
                accept="image/*"
                capture="environment"
                onChange={uploadPhotos}
                className="hidden"
            />
            <input
                ref={galleryInput}
                type="file"
                accept="image/*"
                multiple
                onChange={uploadPhotos}
                className="hidden"
            />
        </div>
    );
}
