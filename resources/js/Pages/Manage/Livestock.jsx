import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_BADGE = {
    active: 'bg-green-100 text-green-700',
    sold: 'bg-blue-100 text-blue-700',
    deceased: 'bg-gray-100 text-gray-600',
    culled: 'bg-orange-100 text-orange-700',
};

function MobFields({ values, setValues }) {
    return (
        <input
            type="text"
            value={values.name}
            onChange={(e) => setValues({ ...values, name: e.target.value })}
            className="w-full border-gray-300 rounded-lg p-2 text-sm"
            placeholder="Mob name (e.g. Breeding Mob)"
        />
    );
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

function BulkAnimalFields({ values, setValues, livestockTypes, errors }) {
    return (
        <div className="space-y-3">
            <div className="grid grid-cols-2 gap-2">
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Starting tag number</label>
                    <input
                        type="text"
                        value={values.tag_number}
                        onChange={(e) => setValues({ ...values, tag_number: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                        placeholder="NLIS001"
                    />
                    {errors?.tag_number && <p className="mt-1 text-xs text-red-600">{errors.tag_number}</p>}
                </div>
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Number of animals</label>
                    <input
                        type="number"
                        min="1"
                        max="100"
                        value={values.count}
                        onChange={(e) => setValues({ ...values, count: e.target.value })}
                        className="w-full border-gray-300 rounded-lg p-2 text-sm"
                    />
                </div>
            </div>
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
                value={values.livestock_type_id}
                onChange={(e) => setValues({ ...values, livestock_type_id: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
            >
                <option value="">No type</option>
                {livestockTypes.map((type) => (
                    <option key={type.id} value={type.id}>{type.name}</option>
                ))}
            </select>
        </div>
    );
}

function MobRow({ mob, canManage, livestockTypes }) {
    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({ name: mob.name });

    const [addingBulk, setAddingBulk] = useState(false);
    const emptyBulkValues = {
        tag_number: '', count: 2, sex: '', date_of_birth: '', purchase_date: '',
        birth_weight: '', purchase_weight: '', purchase_price_type: '', purchase_price: '',
        livestock_type_id: '',
    };
    const [bulkValues, setBulkValues] = useState(emptyBulkValues);
    const [bulkErrors, setBulkErrors] = useState({});

    const save = () => {
        router.patch(route('mobs.update', mob.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm(`Delete "${mob.name}"? Its animals will be kept but unassigned from this mob.`)) {
            router.delete(route('mobs.destroy', mob.id), { preserveScroll: true, preserveState: true });
        }
    };

    const createBulk = () => {
        router.post(route('mobs.animals.store', mob.id), bulkValues, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setAddingBulk(false);
                setBulkValues(emptyBulkValues);
                setBulkErrors({});
            },
            onError: (errors) => setBulkErrors(errors),
        });
    };

    if (editing) {
        return (
            <div className="p-4 bg-green-50 space-y-3">
                <MobFields values={values} setValues={setValues} />
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                    <button onClick={() => setEditing(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                </div>
            </div>
        );
    }

    if (addingBulk) {
        return (
            <div className="p-4 bg-green-50 space-y-3">
                <p className="text-sm font-medium text-gray-900">{mob.name}</p>
                <BulkAnimalFields values={bulkValues} setValues={setBulkValues} livestockTypes={livestockTypes} errors={bulkErrors} />
                <div className="flex gap-2">
                    <button onClick={createBulk} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Animals</button>
                    <button onClick={() => { setAddingBulk(false); setBulkErrors({}); }} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between gap-2 px-4 py-3">
            <Link href={route('mobs.show', mob.id)} className="min-w-0 flex-1">
                <p className="text-sm text-gray-900">{mob.name}</p>
                <div className="flex items-center gap-2 mt-1">
                    <span className="text-xs text-gray-500">
                        {mob.current_zone?.zone?.name ?? 'No paddock'}
                    </span>
                    <span className="text-xs text-gray-400">
                        {mob.livestock_count} animal{mob.livestock_count === 1 ? '' : 's'}
                    </span>
                </div>
                {canManage && (
                    <div className="flex gap-3 mt-1 text-xs">
                        <button type="button" onClick={(e) => { e.preventDefault(); setEditing(true); }} className="text-green-600">Edit</button>
                        {mob.livestock_count === 0 && (
                            <button type="button" onClick={(e) => { e.preventDefault(); setAddingBulk(true); }} className="text-green-600">Add multiple animals</button>
                        )}
                        <button type="button" onClick={(e) => { e.preventDefault(); destroy(); }} className="text-red-500">Delete</button>
                    </div>
                )}
            </Link>
        </div>
    );
}

function AnimalFields({ values, setValues, livestockTypes, mobs }) {
    return (
        <div className="space-y-3">
            <input
                type="text"
                value={values.tag_number}
                onChange={(e) => setValues({ ...values, tag_number: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Tag number"
            />
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
            <div className="grid grid-cols-2 gap-2">
                <select
                    value={values.livestock_type_id}
                    onChange={(e) => setValues({ ...values, livestock_type_id: e.target.value })}
                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                >
                    <option value="">No type</option>
                    {livestockTypes.map((type) => (
                        <option key={type.id} value={type.id}>{type.name}</option>
                    ))}
                </select>
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
        </div>
    );
}

function AnimalRow({ animal, canManage, livestockTypes, mobs }) {
    const [editing, setEditing] = useState(false);
    const [values, setValues] = useState({
        tag_number: animal.tag_number,
        name: animal.name ?? '',
        sex: animal.sex ?? '',
        date_of_birth: animal.date_of_birth?.slice(0, 10) ?? '',
        purchase_date: animal.purchase_date?.slice(0, 10) ?? '',
        birth_weight: animal.birth_weight ?? '',
        purchase_weight: animal.purchase_weight ?? '',
        purchase_price_type: animal.purchase_price_type ?? '',
        purchase_price: animal.purchase_price ?? '',
        livestock_type_id: animal.livestock_type_id ?? '',
        mob_id: animal.mob_id ?? '',
    });

    const save = () => {
        router.patch(route('livestock.update', animal.id), values, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    const destroy = () => {
        if (confirm(`Delete "${animal.tag_number}"? This can't be undone.`)) {
            router.delete(route('livestock.destroy', animal.id), { preserveScroll: true, preserveState: true });
        }
    };

    if (editing) {
        return (
            <div className="p-4 bg-green-50 space-y-3">
                <AnimalFields values={values} setValues={setValues} livestockTypes={livestockTypes} mobs={mobs} />
                <div className="flex gap-2">
                    <button onClick={save} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Save</button>
                    <button onClick={() => setEditing(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between gap-2 px-4 py-3">
            <Link href={route('livestock.show', animal.id)} className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="text-sm text-gray-900">{animal.tag_number}{animal.name ? ` · ${animal.name}` : ''}</p>
                    <span className={`text-xs px-1.5 py-0.5 rounded ${STATUS_BADGE[animal.status]}`}>
                        {animal.status}
                    </span>
                </div>
                <div className="flex items-center gap-2 mt-1">
                    {animal.livestock_type && <span className="text-xs text-gray-500">{animal.livestock_type.name}</span>}
                    {animal.mob && <span className="text-xs text-gray-400">{animal.mob.name}</span>}
                </div>
                {canManage && (
                    <div className="flex gap-3 mt-1 text-xs">
                        <button type="button" onClick={(e) => { e.preventDefault(); setEditing(true); }} className="text-green-600">Edit</button>
                        <button type="button" onClick={(e) => { e.preventDefault(); destroy(); }} className="text-red-500">Delete</button>
                    </div>
                )}
            </Link>
        </div>
    );
}

export default function Livestock({ mobs, livestock, livestockTypes, zones, canManage }) {
    const [activeTab, setActiveTab] = useState('mobs');

    const [addingMob, setAddingMob] = useState(false);
    const [mobValues, setMobValues] = useState({ name: '' });

    const createMob = () => {
        router.post(route('mobs.store'), mobValues, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setAddingMob(false);
                setMobValues({ name: '' });
            },
        });
    };

    const [addingAnimal, setAddingAnimal] = useState(false);
    const emptyAnimalValues = {
        tag_number: '', name: '', sex: '', date_of_birth: '', purchase_date: '',
        birth_weight: '', purchase_weight: '', purchase_price_type: '', purchase_price: '',
        livestock_type_id: '', mob_id: '',
    };
    const [animalValues, setAnimalValues] = useState(emptyAnimalValues);

    const createAnimal = () => {
        router.post(route('livestock.store'), animalValues, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setAddingAnimal(false);
                setAnimalValues(emptyAnimalValues);
            },
        });
    };

    return (
        <AuthenticatedLayout title="Livestock">
            <Head title="Livestock" />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <BackLink href={route('manage.index')}>Manage</BackLink>

                <h1 className="text-lg font-semibold text-gray-900">Livestock</h1>

                <div className="flex gap-1 bg-gray-100 rounded-lg p-1">
                    <button
                        onClick={() => setActiveTab('mobs')}
                        className={`flex-1 py-2 rounded-md text-sm font-medium transition-all ${activeTab === 'mobs' ? 'bg-white text-gray-900 shadow' : 'text-gray-500'}`}
                    >
                        Mobs
                    </button>
                    <button
                        onClick={() => setActiveTab('animals')}
                        className={`flex-1 py-2 rounded-md text-sm font-medium transition-all ${activeTab === 'animals' ? 'bg-white text-gray-900 shadow' : 'text-gray-500'}`}
                    >
                        Animals
                    </button>
                </div>

                {activeTab === 'mobs' && (
                    <>
                        {canManage && mobs.length === 0 && !addingMob ? (
                            <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                                <h2 className="text-base font-semibold text-gray-900 mb-1">Add your first mob</h2>
                                <p className="text-sm text-gray-600 mb-4">
                                    A mob is a group of animals - assign a paddock to it from its own page once created.
                                </p>
                                <button
                                    onClick={() => setAddingMob(true)}
                                    className="inline-block px-6 py-3 bg-green-600 text-white rounded-lg font-medium"
                                >
                                    + Add Mob
                                </button>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">
                                Mobs group animals together and can be assigned a current paddock.
                            </p>
                        )}

                        {canManage && (mobs.length > 0 || addingMob) && (
                            addingMob ? (
                                <div className="bg-white rounded-lg shadow p-4 space-y-3">
                                    <MobFields values={mobValues} setValues={setMobValues} />
                                    <div className="flex gap-2">
                                        <button onClick={createMob} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Mob</button>
                                        <button onClick={() => setAddingMob(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                                    </div>
                                </div>
                            ) : (
                                <button
                                    onClick={() => setAddingMob(true)}
                                    className="block w-full py-2 text-center text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
                                >
                                    + Add Mob
                                </button>
                            )
                        )}

                        {mobs.length === 0 ? (
                            canManage ? null : (
                                <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                                    No mobs set up yet.
                                </div>
                            )
                        ) : (
                            <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                                {mobs.map((mob) => (
                                    <MobRow key={mob.id} mob={mob} canManage={canManage} livestockTypes={livestockTypes} />
                                ))}
                            </div>
                        )}
                    </>
                )}

                {activeTab === 'animals' && (
                    <>
                        {canManage && livestock.length === 0 && !addingAnimal ? (
                            <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                                <h2 className="text-base font-semibold text-gray-900 mb-1">Add your first animal</h2>
                                <p className="text-sm text-gray-600 mb-4">
                                    Track individual animals with a tag number, type, mob, and parentage.
                                </p>
                                <button
                                    onClick={() => setAddingAnimal(true)}
                                    className="inline-block px-6 py-3 bg-green-600 text-white rounded-lg font-medium"
                                >
                                    + Add Animal
                                </button>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">
                                Individual animals, with type, mob, and parentage tracked on each one's own page.
                            </p>
                        )}

                        {canManage && (livestock.length > 0 || addingAnimal) && (
                            addingAnimal ? (
                                <div className="bg-white rounded-lg shadow p-4 space-y-3">
                                    <AnimalFields values={animalValues} setValues={setAnimalValues} livestockTypes={livestockTypes} mobs={mobs} />
                                    <div className="flex gap-2">
                                        <button onClick={createAnimal} className="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm">Add Animal</button>
                                        <button onClick={() => setAddingAnimal(false)} className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Cancel</button>
                                    </div>
                                </div>
                            ) : (
                                <button
                                    onClick={() => setAddingAnimal(true)}
                                    className="block w-full py-2 text-center text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
                                >
                                    + Add Animal
                                </button>
                            )
                        )}

                        {livestock.length === 0 ? (
                            canManage ? null : (
                                <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                                    No animals set up yet.
                                </div>
                            )
                        ) : (
                            <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                                {livestock.map((animal) => (
                                    <AnimalRow key={animal.id} animal={animal} canManage={canManage} livestockTypes={livestockTypes} mobs={mobs} />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
