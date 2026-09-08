import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import NoteRow from '@/Components/NoteRow';
import AddNoteForm from '@/Components/AddNoteForm';
import PhotoLightbox from '@/Components/PhotoLightbox';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { compressImageFiles } from '@/imageCompression';
import { formatDate } from '@/dateInput';

const STATUS_BADGE = {
    active: 'bg-green-100 text-green-700',
    sold: 'bg-blue-100 text-blue-700',
    deceased: 'bg-gray-100 text-gray-600',
    culled: 'bg-orange-100 text-orange-700',
};

function formatPrice(price, type) {
    if (!price) return null;
    const amount = `$${Number(price).toFixed(2)}`;
    if (type === 'per_kg') return `${amount}/kg`;
    if (type === 'per_unit') return `${amount}/hd`;
    return amount;
}

function PurchaseFields({ values, setValues }) {
    return (
        <>
            <div className="grid grid-cols-2 gap-2">
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Birth weight (kg)</label>
                    <input
                        type="number"
                        step="0.1"
                        min="0"
                        value={values.birth_weight}
                        onChange={(e) => setValues({ ...values, birth_weight: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                </div>
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Purchase weight (kg)</label>
                    <input
                        type="number"
                        step="0.1"
                        min="0"
                        value={values.purchase_weight}
                        onChange={(e) => setValues({ ...values, purchase_weight: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                </div>
            </div>
            <div className="grid grid-cols-2 gap-2">
                <select
                    value={values.purchase_price_type}
                    onChange={(e) => setValues({ ...values, purchase_price_type: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                >
                    <option value="">Purchase price type</option>
                    <option value="per_kg">Per kg live weight</option>
                    <option value="per_unit">Per head</option>
                </select>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={values.purchase_price}
                    onChange={(e) => setValues({ ...values, purchase_price: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Purchase price"
                />
            </div>
        </>
    );
}

function AnimalFields({ values, setValues, mobs, errors }) {
    return (
        <div className="space-y-3">
            <div>
                <input
                    type="text"
                    value={values.tag_number}
                    onChange={(e) => setValues({ ...values, tag_number: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    placeholder="Tag number"
                />
                {errors?.tag_number && <p className="mt-1 text-xs text-red-600">{errors.tag_number}</p>}
            </div>
            <input
                type="text"
                value={values.name}
                onChange={(e) => setValues({ ...values, name: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Name (optional)"
            />
            <select
                value={values.sex}
                onChange={(e) => setValues({ ...values, sex: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
            >
                <option value="">Sex unknown</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>
            <div className="grid grid-cols-2 gap-2">
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Birth date</label>
                    <input
                        type="date"
                        value={values.date_of_birth}
                        onChange={(e) => setValues({ ...values, date_of_birth: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                </div>
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Purchase date</label>
                    <input
                        type="date"
                        value={values.purchase_date}
                        onChange={(e) => setValues({ ...values, purchase_date: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                </div>
            </div>
            <PurchaseFields values={values} setValues={setValues} />
            <select
                value={values.mob_id}
                onChange={(e) => setValues({ ...values, mob_id: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
            >
                <option value="">No mob</option>
                {mobs.map((mob) => (
                    <option key={mob.id} value={mob.id}>{mob.name}</option>
                ))}
            </select>
        </div>
    );
}

function parentLabel(parent) {
    if (!parent) return null;
    return parent.name ? `${parent.tag_number} · ${parent.name}` : parent.tag_number;
}

export default function Show({ livestock, offspring, potentialParents, mobs }) {
    const { currentUserRole } = usePage().props;
    const canManage = currentUserRole === 'admin' || currentUserRole === 'manager';
    const canCreateNote = canManage || currentUserRole === 'worker';

    const [editing, setEditing] = useState(false);
    const [errors, setErrors] = useState({});
    const [values, setValues] = useState({
        tag_number: livestock.tag_number,
        name: livestock.name ?? '',
        sex: livestock.sex ?? '',
        date_of_birth: livestock.date_of_birth?.slice(0, 10) ?? '',
        purchase_date: livestock.purchase_date?.slice(0, 10) ?? '',
        birth_weight: livestock.birth_weight ?? '',
        purchase_weight: livestock.purchase_weight ?? '',
        purchase_price_type: livestock.purchase_price_type ?? '',
        purchase_price: livestock.purchase_price ?? '',
        mob_id: livestock.mob_id ?? '',
    });

    const [status, setStatus] = useState(livestock.status);
    const [sireId, setSireId] = useState(livestock.sire_id ?? '');
    const [damId, setDamId] = useState(livestock.dam_id ?? '');
    const [addingNote, setAddingNote] = useState(false);

    const cameraInput = useRef(null);
    const galleryInput = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [lightboxIndex, setLightboxIndex] = useState(null);

    const [editingSale, setEditingSale] = useState(false);
    const [saleValues, setSaleValues] = useState({
        sale_weight: livestock.sale_weight ?? '',
        sale_price_type: livestock.sale_price_type ?? '',
        sale_price: livestock.sale_price ?? '',
    });

    const save = () => {
        router.patch(route('livestock.update', livestock.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { setEditing(false); setErrors({}); },
            onError: setErrors,
        });
    };

    const updateStatus = (newStatus) => {
        setStatus(newStatus);
        router.patch(route('livestock.update', livestock.id), {
            tag_number: livestock.tag_number,
            status: newStatus,
        }, { preserveScroll: true, preserveState: true });
    };

    const updateSire = () => {
        router.patch(route('livestock.update', livestock.id), {
            tag_number: livestock.tag_number,
            sire_id: sireId || null,
        }, { preserveScroll: true, preserveState: true });
    };

    const updateDam = () => {
        router.patch(route('livestock.update', livestock.id), {
            tag_number: livestock.tag_number,
            dam_id: damId || null,
        }, { preserveScroll: true, preserveState: true });
    };

    const updateSale = () => {
        router.patch(route('livestock.update', livestock.id), {
            tag_number: livestock.tag_number,
            ...saleValues,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditingSale(false),
        });
    };

    const uploadPhotos = async (e) => {
        const files = e.target.files;
        if (!files.length) return;

        setUploading(true);
        const compressed = await compressImageFiles(files);

        const formData = new FormData();
        compressed.forEach(file => formData.append('photos[]', file));

        router.post(route('photos.store-livestock', livestock.id), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
            onError: () => alert('Photo upload failed. Please try again with a smaller photo.'),
        });
    };

    const destroyPhoto = (photoId) => {
        if (confirm('Delete this photo?')) {
            router.delete(route('photos.destroy', photoId), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={livestock.tag_number} />

            <div className="max-w-lg mx-auto mt-2 space-y-4">
                <BackLink href={route('manage.livestock')}>Livestock</BackLink>

                <div className="bg-white rounded-lg shadow p-4">
                    {editing ? (
                        <div className="space-y-3">
                            <AnimalFields values={values} setValues={setValues} mobs={mobs} errors={errors} />
                            <div className="flex gap-2">
                                <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                                <button onClick={() => { setEditing(false); setErrors({}); }} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="flex items-start justify-between mb-2">
                                <h1 className="text-lg font-semibold text-gray-900">
                                    {livestock.tag_number}{livestock.name ? ` · ${livestock.name}` : ''}
                                </h1>
                                {livestock.livestock_type && (
                                    <span className="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600 flex-shrink-0">
                                        {livestock.livestock_type.name}
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center gap-2 text-sm text-gray-500 flex-wrap">
                                {livestock.sex && <span className="capitalize">{livestock.sex}</span>}
                                {livestock.date_of_birth && <span>Born {formatDate(livestock.date_of_birth.slice(0, 10))}</span>}
                                {livestock.purchase_date && <span>Purchased {formatDate(livestock.purchase_date.slice(0, 10))}</span>}
                            </div>
                            {(livestock.birth_weight || livestock.purchase_weight || livestock.purchase_price) && (
                                <div className="flex items-center gap-2 text-sm text-gray-500 flex-wrap mt-1">
                                    {livestock.birth_weight && <span>Birth weight {livestock.birth_weight}kg</span>}
                                    {livestock.purchase_weight && <span>Purchase weight {livestock.purchase_weight}kg</span>}
                                    {livestock.purchase_price && <span>{formatPrice(livestock.purchase_price, livestock.purchase_price_type)}</span>}
                                </div>
                            )}
                            {livestock.mob && (
                                <Link href={route('mobs.show', livestock.mob.id)} className="text-sm text-green-600 mt-1 inline-block">
                                    {livestock.mob.name}
                                </Link>
                            )}
                            {canManage && (
                                <button onClick={() => setEditing(true)} className="text-xs text-green-600 mt-2 block">Edit</button>
                            )}
                        </>
                    )}
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Status</h2>
                    </div>
                    <div className="p-4">
                        {canManage ? (
                            <select
                                value={status}
                                onChange={(e) => updateStatus(e.target.value)}
                                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                            >
                                <option value="active">Active</option>
                                <option value="sold">Sold</option>
                                <option value="deceased">Deceased</option>
                                <option value="culled">Culled</option>
                            </select>
                        ) : (
                            <span className={`text-xs px-2 py-1 rounded ${STATUS_BADGE[status]}`}>{status}</span>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Sale</h2>
                    </div>
                    <div className="p-4">
                        {editingSale ? (
                            <div className="space-y-3">
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="block text-xs text-gray-500 mb-1">Sale weight (kg)</label>
                                        <input
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            value={saleValues.sale_weight}
                                            onChange={(e) => setSaleValues({ ...saleValues, sale_weight: e.target.value })}
                                            className="w-full border-gray-300 rounded-lg p-2 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs text-gray-500 mb-1">Sale price</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={saleValues.sale_price}
                                            onChange={(e) => setSaleValues({ ...saleValues, sale_price: e.target.value })}
                                            className="w-full border-gray-300 rounded-lg p-2 text-sm"
                                        />
                                    </div>
                                </div>
                                <select
                                    value={saleValues.sale_price_type}
                                    onChange={(e) => setSaleValues({ ...saleValues, sale_price_type: e.target.value })}
                                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                                >
                                    <option value="">Sale price type</option>
                                    <option value="per_kg">Per kg live weight</option>
                                    <option value="per_unit">Per head</option>
                                </select>
                                <div className="flex gap-2">
                                    <button onClick={updateSale} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                                    <button onClick={() => setEditingSale(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                                </div>
                            </div>
                        ) : (
                            <>
                                {livestock.sale_weight || livestock.sale_price ? (
                                    <div className="flex items-center gap-2 text-sm text-gray-900 flex-wrap">
                                        {livestock.sale_weight && <span>{livestock.sale_weight}kg</span>}
                                        {livestock.sale_price && <span>{formatPrice(livestock.sale_price, livestock.sale_price_type)}</span>}
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-400">Not recorded</p>
                                )}
                                {canManage && (
                                    <button onClick={() => setEditingSale(true)} className="text-xs text-green-600 mt-2 block">Edit</button>
                                )}
                            </>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Parentage</h2>
                    </div>
                    <div className="p-4 space-y-3">
                        <div>
                            <p className="text-xs text-gray-500 mb-1">Sire</p>
                            {canManage ? (
                                <div className="flex gap-2">
                                    <select
                                        value={sireId}
                                        onChange={(e) => setSireId(e.target.value)}
                                        className="flex-1 border-gray-300 rounded-lg p-2 text-sm"
                                    >
                                        <option value="">Unknown</option>
                                        {potentialParents.map((parent) => (
                                            <option key={parent.id} value={parent.id}>{parentLabel(parent)}</option>
                                        ))}
                                    </select>
                                    <button onClick={updateSire} className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                                </div>
                            ) : livestock.sire ? (
                                <Link href={route('livestock.show', livestock.sire.id)} className="text-sm text-green-600">
                                    {parentLabel(livestock.sire)}
                                </Link>
                            ) : (
                                <p className="text-sm text-gray-400">Unknown</p>
                            )}
                        </div>
                        <div>
                            <p className="text-xs text-gray-500 mb-1">Dam</p>
                            {canManage ? (
                                <div className="flex gap-2">
                                    <select
                                        value={damId}
                                        onChange={(e) => setDamId(e.target.value)}
                                        className="flex-1 border-gray-300 rounded-lg p-2 text-sm"
                                    >
                                        <option value="">Unknown</option>
                                        {potentialParents.map((parent) => (
                                            <option key={parent.id} value={parent.id}>{parentLabel(parent)}</option>
                                        ))}
                                    </select>
                                    <button onClick={updateDam} className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                                </div>
                            ) : livestock.dam ? (
                                <Link href={route('livestock.show', livestock.dam.id)} className="text-sm text-green-600">
                                    {parentLabel(livestock.dam)}
                                </Link>
                            ) : (
                                <p className="text-sm text-gray-400">Unknown</p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Offspring</h2>
                    </div>
                    {offspring.length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No recorded offspring.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {offspring.map((child) => (
                                <Link key={child.id} href={route('livestock.show', child.id)} className="block px-4 py-3 text-sm text-gray-900">
                                    {parentLabel(child)}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Photos</h2>
                        {canCreateNote && (
                            <div className="flex gap-2">
                                <button
                                    onClick={() => cameraInput.current.click()}
                                    disabled={uploading}
                                    aria-label="Take photo"
                                    className="p-2 bg-green-600 text-white rounded-lg disabled:opacity-50"
                                >
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </button>
                                <button
                                    onClick={() => galleryInput.current.click()}
                                    disabled={uploading}
                                    aria-label="Choose from gallery"
                                    className="p-2 bg-white border border-gray-300 text-gray-700 rounded-lg disabled:opacity-50"
                                >
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" />
                                    </svg>
                                </button>
                            </div>
                        )}
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

                    {livestock.photos.length === 0 ? (
                        canCreateNote ? (
                            <button
                                onClick={() => cameraInput.current.click()}
                                className="w-full border-2 border-dashed border-gray-300 rounded-lg p-8 text-center text-gray-400"
                            >
                                <svg className="w-8 h-8 mx-auto mb-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <p className="text-sm">Tap to add a photo</p>
                            </button>
                        ) : (
                            <p className="text-sm text-gray-400">No photos yet.</p>
                        )
                    ) : (
                        <div className="grid grid-cols-3 gap-2">
                            {livestock.photos.map((photo, i) => (
                                <div key={photo.id} className="relative">
                                    <img
                                        src={photo.url}
                                        onClick={() => setLightboxIndex(i)}
                                        className="w-full h-24 object-cover rounded-lg cursor-pointer"
                                    />
                                    {canCreateNote && (
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

                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Notes</h2>
                        {canCreateNote && !addingNote && (
                            <button onClick={() => setAddingNote(true)} className="text-xs text-green-600 font-medium">
                                + Add
                            </button>
                        )}
                    </div>
                    {livestock.notes.length === 0 ? (
                        <p className="text-sm text-gray-400 p-4">No notes yet.</p>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {livestock.notes.map((note) => (
                                <NoteRow key={note.id} note={note} canManage={canManage} canCreate={canCreateNote} />
                            ))}
                        </div>
                    )}
                    {addingNote && (
                        <AddNoteForm parentField="livestock_id" parentId={livestock.id} onClose={() => setAddingNote(false)} />
                    )}
                </div>
            </div>

            {livestock.photos.length > 0 && (
                <PhotoLightbox
                    photos={livestock.photos}
                    index={lightboxIndex}
                    onClose={() => setLightboxIndex(null)}
                    onIndexChange={setLightboxIndex}
                />
            )}
        </AuthenticatedLayout>
    );
}
