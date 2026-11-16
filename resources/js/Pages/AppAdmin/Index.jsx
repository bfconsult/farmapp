import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const COLUMNS = [
    { key: 'farm_jobs_count', label: 'Jobs' },
    { key: 'work_sessions_count', label: 'Work Sessions' },
    { key: 'assets_count', label: 'Assets' },
    { key: 'admin_properties_count', label: 'Properties' },
    { key: 'checklists_count', label: 'Checklists' },
    { key: 'metrics_count', label: 'Metrics' },
];

function DeactivateModal({ user, onClose }) {
    const [processing, setProcessing] = useState(false);

    const deactivate = () => {
        router.post(route('app-admin.users.deactivate', user.id), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
        });
    };

    return (
        <div className="p-6">
            <h2 className="text-lg font-medium text-gray-900">Deactivate {user.name}?</h2>
            <p className="mt-2 text-sm text-gray-600">
                They're logged out and can't log in again, and their email address is freed up to sign up with.
                Everything they've done is kept.
            </p>
            <div className="mt-6 flex justify-end gap-3">
                <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                <DangerButton onClick={deactivate} disabled={processing}>Deactivate</DangerButton>
            </div>
        </div>
    );
}

function DeleteModal({ user, onClose }) {
    const [preview, setPreview] = useState(null);
    const [loadError, setLoadError] = useState(false);
    const { data, setData, delete: destroy, processing, errors } = useForm({ confirm_name: '' });

    useEffect(() => {
        window.axios.get(route('app-admin.users.delete-preview', user.id))
            .then((response) => setPreview(response.data))
            .catch(() => setLoadError(true));
    }, [user.id]);

    const submit = (e) => {
        e.preventDefault();
        destroy(route('app-admin.users.destroy', user.id), { preserveScroll: true, onSuccess: onClose });
    };

    const records = Object.entries(preview?.records ?? {});
    const blocked = preview?.blocked_by?.length > 0;

    return (
        <form onSubmit={submit} className="p-6">
            <h2 className="text-lg font-medium text-gray-900">Delete {user.name} permanently?</h2>

            {loadError && <p className="mt-3 text-sm text-red-600">Couldn't work out what would be deleted. Try again.</p>}
            {!preview && !loadError && <p className="mt-3 text-sm text-gray-500">Checking what would be deleted…</p>}

            {preview && blocked && (
                <div className="mt-3 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                    <p className="font-medium">Can't be deleted</p>
                    <p className="mt-1">
                        They have records on properties other people belong to, and deleting them would take those
                        records with them. Deactivate them instead.
                    </p>
                    <ul className="mt-2 space-y-1">
                        {preview.blocked_by.map((property) => (
                            <li key={property.id}>
                                <span className="font-medium">{property.name}</span>:{' '}
                                {Object.entries(property.records).map(([label, count]) => `${count} ${label.toLowerCase()}`).join(', ')}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {preview && !blocked && (
                <div className="mt-3 space-y-3 text-sm text-gray-700">
                    <p>This can't be undone. Along with the account, it deletes:</p>
                    <ul className="list-disc pl-5 space-y-1">
                        {preview.properties.map((property) => (
                            <li key={property.id}>
                                Property <span className="font-medium">{property.name}</span> and everything on it (no one else belongs to it)
                            </li>
                        ))}
                        {records.map(([label, count]) => (
                            <li key={label}>{count} {label.toLowerCase()}</li>
                        ))}
                        {preview.properties.length === 0 && records.length === 0 && <li>Nothing else - they haven't added anything.</li>}
                    </ul>
                    <div>
                        <label htmlFor="confirm_name" className="block text-sm text-gray-700">
                            Type <span className="font-medium">{user.name}</span> to confirm
                        </label>
                        <TextInput
                            id="confirm_name"
                            value={data.confirm_name}
                            onChange={(e) => setData('confirm_name', e.target.value)}
                            className="mt-1 block w-full"
                            autoComplete="off"
                        />
                        <InputError message={errors.confirm_name} className="mt-2" />
                    </div>
                </div>
            )}

            <div className="mt-6 flex justify-end gap-3">
                <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                {preview && !blocked && (
                    <DangerButton disabled={processing || data.confirm_name.trim() !== user.name.trim()}>
                        Delete permanently
                    </DangerButton>
                )}
            </div>
        </form>
    );
}

export default function Index({ users, deactivated, deactivatedCount }) {
    const { auth } = usePage().props;
    // {user, action: 'deactivate' | 'delete'} while a dialog is open.
    const [pending, setPending] = useState(null);
    const close = () => setPending(null);

    return (
        <AuthenticatedLayout title="App Admin">
            <Head title="App Admin" />

            <div className="max-w-4xl mx-auto mt-2 space-y-4 pb-24 px-4">
                <div className="flex items-center justify-between">
                    <BackLink href={route('profile.edit')}>Account</BackLink>
                </div>

                <div>
                    <div className="flex items-baseline justify-between gap-4">
                        <h1 className="text-lg font-semibold text-gray-900">
                            {deactivated ? 'Deactivated Accounts' : 'User Activity Report'}
                        </h1>
                        <Link
                            href={deactivated ? route('app-admin.report') : route('app-admin.report', { deactivated: 1 })}
                            className="text-sm font-medium text-green-700 hover:underline whitespace-nowrap"
                        >
                            {deactivated ? 'Active accounts' : `Deactivated accounts (${deactivatedCount})`}
                        </Link>
                    </div>
                    <p className="text-sm text-gray-500 mt-1">
                        {deactivated
                            ? `Accounts that were deactivated, by the user or an app admin. They can't log in, and their email has ".deleted" added so the address can be used again. Their records are kept.`
                            : <>Counts of what each user has added across the whole app, not just the currently-selected property.
                                "Properties" counts properties they currently hold the admin role on, as an approximation of who created it.</>}
                    </p>
                </div>

                <div className="bg-white rounded-lg shadow overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100">
                                <th className="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase tracking-wide">User</th>
                                {COLUMNS.map((col) => (
                                    <th key={col.key} className="text-right px-4 py-2 text-xs font-medium text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        {col.label}
                                    </th>
                                ))}
                                <th className="px-4 py-2"><span className="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <td className="px-4 py-3">
                                        <p className="text-gray-900">{user.name}</p>
                                        <p className="text-xs text-gray-400">{user.email ?? '—'}</p>
                                    </td>
                                    {COLUMNS.map((col) => (
                                        <td key={col.key} className="text-right px-4 py-3 text-gray-700 tabular-nums">
                                            {user[col.key]}
                                        </td>
                                    ))}
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        {!deactivated && user.id !== auth.user.id && (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={() => setPending({ user, action: 'deactivate' })}
                                                    className="text-xs font-medium text-gray-600 hover:text-gray-900"
                                                >
                                                    Deactivate
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setPending({ user, action: 'delete' })}
                                                    className="ml-3 text-xs font-medium text-red-600 hover:text-red-800"
                                                >
                                                    Delete
                                                </button>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {users.length === 0 && (
                                <tr>
                                    <td colSpan={COLUMNS.length + 2} className="px-4 py-6 text-center text-gray-500">
                                        {deactivated ? 'No deactivated accounts.' : 'No users.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal show={pending !== null} onClose={close} maxWidth="lg">
                {pending?.action === 'deactivate' && <DeactivateModal user={pending.user} onClose={close} />}
                {pending?.action === 'delete' && <DeleteModal key={pending.user.id} user={pending.user} onClose={close} />}
            </Modal>
        </AuthenticatedLayout>
    );
}
