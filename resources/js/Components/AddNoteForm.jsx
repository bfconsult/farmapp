import { useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * `parentField`/`parentId` name which single-parent column this note attaches
 * to (e.g. "asset_id"/asset.id or "job_id"/job.id) - NoteController::store()
 * requires a note to be linked to exactly one of asset/job/map-location.
 */
export default function AddNoteForm({ parentField, parentId, onClose }) {
    const [body, setBody] = useState('');

    const create = () => {
        router.post(route('notes.store'), { [parentField]: parentId, body }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                onClose();
                setBody('');
            },
        });
    };

    return (
        <div className="p-4 space-y-3 border-t border-gray-100">
            <textarea
                value={body}
                onChange={(e) => setBody(e.target.value)}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Note..."
                rows={3}
            />
            <div className="flex gap-2">
                <button onClick={create} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Note</button>
                <button onClick={onClose} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
            </div>
        </div>
    );
}
