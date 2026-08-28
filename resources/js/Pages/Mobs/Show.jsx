import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import NoteRow from '@/Components/NoteRow';
import AddNoteForm from '@/Components/AddNoteForm';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { formatDate } from '@/dateInput';

function MobFields({ values, setValues }) {
    return (
        <input
            type="text"
            value={values.name}
            onChange={(e) => setValues({ ...values, name: e.target.value })}
            className="w-full border-gray-300 rounded-lg p-2 text-sm"
            placeholder="Mob name"
        />
    );
}

export default function Show({ mob }) {
    const { currentUserRole } = usePage().props;
    const canManage = currentUserRole === 'admin' || currentUserRole === 'manager';
    const canCreateNote = canManage || currentUserRole === 'worker';

    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({ name: mob.name });

    const [zoneId, setZoneId] = useState(mob.current_zone?.zone_id ?? '');
    const [addingNote, setAddingNote] = useState(false);
    const [showHistory, setShowHistory] = useState(false);

    const pastZones = (mob.zone_history ?? []).slice(1); // [0] is current_zone itself

    const save = () => {
        router.patch(route('mobs.update', mob.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const moveZone = () => {
        router.put(route('mobs.update-zone', mob.id), { zone_id: zoneId || null }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={mob.name} />

            <div className="max-w-lg mx-auto mt-2 space-y-4">
                <BackLink href={route('manage.livestock')}>Livestock</BackLink>

                <div className="bg-white rounded-lg shadow p-4">
                    {editing ? (
                        <div className="space-y-3">
                            <MobFields values={values} setValues={setValues} />
                            <div className="flex gap-2">
                                <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                                <button onClick={() => setEditing(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                            </div>
                        </div>
                    ) : (
                        <>
                            <h1 className="text-lg font-semibold text-gray-900">{mob.name}</h1>
                            <p className="text-sm text-gray-500 mt-1">
                                {mob.livestock?.length ?? 0} animal{(mob.livestock?.length ?? 0) === 1 ? '' : 's'}
                            </p>
                            {canManage && (
                                <button onClick={() => setEditing(true)} className="text-xs text-green-600 mt-2">Edit</button>
                            )}
                        </>
                    )}
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Paddock</h2>
                    </div>
                    <div className="p-4">
                        <p className="text-sm text-gray-900 mb-2">
                            {mob.current_zone?.zone?.name ?? 'No paddock assigned'}
                        </p>
                        {canManage && (
                            <div className="flex gap-2">
                                <select
                                    value={zoneId}
                                    onChange={(e) => setZoneId(e.target.value)}
                                    className="flex-1 border-gray-300 rounded-lg p-2 text-sm"
                                >
                                    <option value="">No paddock</option>
                                    {(mob.property?.zones ?? []).map((zone) => (
                                        <option key={zone.id} value={zone.id}>{zone.name}</option>
                                    ))}
                                </select>
                                <button onClick={moveZone} className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">
                                    Move
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                {pastZones.length > 0 && (
                    <div className="bg-white rounded-lg shadow overflow-hidden">
                        <button
                            onClick={() => setShowHistory((v) => !v)}
                            className="w-full flex items-center justify-between px-4 py-2 border-b border-gray-100"
                        >
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">
                                {showHistory ? '▾' : '▸'} Paddock History ({pastZones.length})
                            </h2>
                        </button>
                        {showHistory && (
                            <div className="divide-y divide-gray-100">
                                {pastZones.map((entry) => (
                                    <div key={entry.id} className="px-4 py-2 text-sm text-gray-700 flex justify-between">
                                        <span>{entry.zone?.name ?? 'No paddock'}</span>
                                        <span className="text-xs text-gray-400">{formatDate(entry.created_at)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Animals</h2>
                    </div>
                    {(mob.livestock ?? []).length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No animals in this mob yet.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {mob.livestock.map((animal) => (
                                <Link key={animal.id} href={route('livestock.show', animal.id)} className="block px-4 py-3">
                                    <p className="text-sm text-gray-900">{animal.tag_number}{animal.name ? ` · ${animal.name}` : ''}</p>
                                    {animal.livestock_type && (
                                        <p className="text-xs text-gray-500 mt-0.5">{animal.livestock_type.name}</p>
                                    )}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Notes</h2>
                        {canCreateNote && !addingNote && (
                            <button onClick={() => setAddingNote(true)} className="text-xs text-green-600 font-medium">
                                + Add
                            </button>
                        )}
                    </div>
                    {mob.notes.length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No notes yet.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {mob.notes.map((note) => (
                                <NoteRow key={note.id} note={note} canManage={canManage} canCreate={canCreateNote} />
                            ))}
                        </div>
                    )}
                    {addingNote && (
                        <AddNoteForm parentField="mob_id" parentId={mob.id} onClose={() => setAddingNote(false)} />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
