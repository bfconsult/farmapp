import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const ROLE_LABELS = {
    admin: 'Admin',
    manager: 'Manager',
    worker: 'Worker',
    approver: 'Approver',
};

const ROLE_COLORS = {
    admin: 'bg-purple-100 text-purple-700',
    manager: 'bg-blue-100 text-blue-700',
    worker: 'bg-green-100 text-green-700',
    approver: 'bg-yellow-100 text-yellow-700',
};

const ALL_ROLE_TYPES = ['admin', 'manager', 'worker', 'approver'];

export default function Index({ property, roles, suppliers, pendingInvitations, currentUserRole }) {
    const { auth } = usePage().props;
    // null | 'add' | 'invite' - only one of the two "bring someone onto the
    // team" forms is ever open at a time, in the same slot below.
    const [mode, setMode] = useState(null);
    const [copiedId, setCopiedId] = useState(null);
    const [messageEdited, setMessageEdited] = useState(false);
    const [invitingRoleId, setInvitingRoleId] = useState(null);

    const defaultMessage = (role) =>
        `${auth.user.name} has invited you to join ${property.name} on FieldWerkz as a ${ROLE_LABELS[role]}.`;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'worker',
        message: defaultMessage('worker'),
    });

    const addForm = useForm({
        name: '',
        email: '',
        role: 'worker',
        hourly_rate: '',
    });

    const memberInviteForm = useForm({
        email: '',
        message: '',
    });

    const adminCount = roles.filter((r) => r.type === 'admin').length;

    const canEditRate = (role) =>
        currentUserRole === 'admin' ||
        (currentUserRole === 'manager' && (role.type === 'worker' || role.user.id === auth.user.id));

    // Managers can only add/invite workers; admins can do any role - the
    // same gate both the "Add" and "Invite by Email" forms use.
    const availableRoles = currentUserRole === 'admin'
        ? ['manager', 'worker', 'approver']
        : ['worker'];

    const changeRole = (e) => {
        const role = e.target.value;
        setData((prev) => ({
            ...prev,
            role,
            message: messageEdited ? prev.message : defaultMessage(role),
        }));
    };

    const changeMessage = (e) => {
        setMessageEdited(true);
        setData('message', e.target.value);
    };

    const invite = (e) => {
        e.preventDefault();
        post(route('invitations.store'), {
            onSuccess: () => {
                reset();
                setMessageEdited(false);
                setMode(null);
            },
        });
    };

    const addMember = (e) => {
        e.preventDefault();
        addForm.post(route('invitations.store-member'), {
            onSuccess: () => {
                addForm.reset();
                setMode(null);
            },
        });
    };

    const openMemberInvite = (role) => {
        memberInviteForm.reset();
        memberInviteForm.setData({
            email: role.user.email ?? '',
            message: defaultMessage(role.type),
        });
        setInvitingRoleId(role.id);
    };

    const sendMemberInvite = (e, roleId) => {
        e.preventDefault();
        memberInviteForm.post(route('invitations.invite-member', roleId), {
            preserveScroll: true,
            onSuccess: () => setInvitingRoleId(null),
        });
    };

    const removeRole = (roleId) => {
        if (confirm('Remove this person from the property?')) {
            router.delete(route('invitations.destroy-role', roleId));
        }
    };

    const changeRoleType = (roleId, type) => {
        router.patch(route('invitations.update-role', roleId), { type }, { preserveScroll: true });
    };

    const updateRate = (roleId, value) => {
        router.patch(route('invitations.update-member-rate', roleId), { hourly_rate: value || null }, { preserveScroll: true });
    };

    const updateSupplier = (roleId, value) => {
        router.patch(route('invitations.update-member-supplier', roleId), { supplier_id: value || null }, { preserveScroll: true });
    };

    const cancelInvitation = (invitationId) => {
        if (confirm('Cancel this invitation?')) {
            router.delete(route('invitations.destroy-invitation', invitationId));
        }
    };

    const copyInviteLink = async (invitation) => {
        const link = route('invitations.accept', invitation.token);
        try {
            await navigator.clipboard.writeText(link);
            setCopiedId(invitation.id);
            setTimeout(() => setCopiedId((current) => (current === invitation.id ? null : current)), 2000);
        } catch {
            // Clipboard API unavailable; the link is still visible and selectable to copy manually.
        }
    };

    const canManageRole = (role) =>
        currentUserRole === 'admin' || (currentUserRole === 'manager' && role.type === 'worker');

    const pendingInvitationForUser = (userId) =>
        pendingInvitations.find((invitation) => invitation.user_id === userId);

    return (
        <AuthenticatedLayout title="Team">
            <Head title="Team" />

            <div className="max-w-lg mx-auto mt-2 space-y-4">
                {/* Current team */}
                <div className="bg-white rounded-lg shadow">
                    <div className="px-4 py-3 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Team Members</h2>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {roles.map((role) => {
                            const unclaimed = !role.user.claimed_at;
                            const pendingInvite = pendingInvitationForUser(role.user.id);

                            return (
                                <div key={role.id} className="px-4 py-3">
                                    <div className="flex items-center justify-between">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <p className="text-sm text-gray-900 truncate">{role.user.name}</p>
                                                {unclaimed && (
                                                    <span className="text-xs px-1.5 py-0.5 rounded-full font-medium bg-amber-100 text-amber-700 flex-shrink-0">
                                                        Unclaimed
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-xs text-gray-500 truncate">
                                                {role.user.email ?? <span className="italic text-gray-400">No email</span>}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2 flex-shrink-0">
                                            {currentUserRole === 'admin' ? (
                                                <select
                                                    value={role.type}
                                                    onChange={(e) => changeRoleType(role.id, e.target.value)}
                                                    disabled={role.type === 'admin' && adminCount <= 1}
                                                    className={`text-xs rounded-full font-medium border-0 py-1 pl-2 pr-6 focus:ring-2 focus:ring-green-500 disabled:opacity-60 ${ROLE_COLORS[role.type]}`}
                                                >
                                                    {ALL_ROLE_TYPES.map((type) => (
                                                        <option key={type} value={type}>{ROLE_LABELS[type]}</option>
                                                    ))}
                                                </select>
                                            ) : (
                                                <span className={`text-xs px-2 py-1 rounded-full font-medium ${ROLE_COLORS[role.type]}`}>
                                                    {ROLE_LABELS[role.type]}
                                                </span>
                                            )}
                                            {canManageRole(role) && !(role.type === 'admin' && adminCount <= 1) && (
                                                <button
                                                    onClick={() => removeRole(role.id)}
                                                    className="text-xs text-red-500"
                                                >
                                                    Remove
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {unclaimed && role.type !== 'admin' && canManageRole(role) && (
                                        pendingInvite ? (
                                            <p className="text-xs text-gray-400 mt-1.5">Invite sent</p>
                                        ) : invitingRoleId === role.id ? (
                                            <form onSubmit={(e) => sendMemberInvite(e, role.id)} className="mt-2 p-3 bg-green-50 rounded-lg space-y-2">
                                                <input
                                                    type="email"
                                                    value={memberInviteForm.data.email}
                                                    onChange={(e) => memberInviteForm.setData('email', e.target.value)}
                                                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                                                    placeholder="email@example.com"
                                                    autoFocus
                                                />
                                                {memberInviteForm.errors.email && (
                                                    <p className="text-xs text-red-600">{memberInviteForm.errors.email}</p>
                                                )}
                                                <textarea
                                                    value={memberInviteForm.data.message}
                                                    onChange={(e) => memberInviteForm.setData('message', e.target.value)}
                                                    rows={2}
                                                    maxLength={2000}
                                                    className="w-full border-gray-300 rounded-lg p-2 text-sm"
                                                />
                                                <div className="flex gap-2">
                                                    <button
                                                        type="submit"
                                                        disabled={memberInviteForm.processing}
                                                        className="flex-1 py-1.5 bg-green-600 text-white rounded-lg text-xs font-medium disabled:opacity-50"
                                                    >
                                                        Send Invite
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setInvitingRoleId(null)}
                                                        className="flex-1 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        ) : (
                                            <button
                                                onClick={() => openMemberInvite(role)}
                                                className="text-xs text-green-600 mt-1.5"
                                            >
                                                Send invite
                                            </button>
                                        )
                                    )}

                                    {(currentUserRole === 'admin' || currentUserRole === 'manager') && (
                                        <div className="mt-2 pt-2 border-t border-gray-50 space-y-1.5">
                                            <div className="flex items-center gap-2">
                                                <label className="text-xs text-gray-500 w-16 flex-shrink-0">Rate ($/hr)</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="Not set"
                                                    defaultValue={role.user.hourly_rate ?? ''}
                                                    onBlur={(e) => updateRate(role.id, e.target.value)}
                                                    disabled={!canEditRate(role)}
                                                    className="w-24 text-sm border-gray-300 rounded-lg px-2 py-1 disabled:bg-gray-50 disabled:text-gray-400"
                                                />
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <label
                                                    className="text-xs text-gray-500 w-16 flex-shrink-0"
                                                    title="Bills through this supplier's company details (name, ABN, invoice address) instead of their own."
                                                >
                                                    Billing via
                                                </label>
                                                <select
                                                    value={role.supplier_id ?? ''}
                                                    onChange={(e) => updateSupplier(role.id, e.target.value)}
                                                    disabled={!canEditRate(role)}
                                                    className="flex-1 min-w-0 text-sm border-gray-300 rounded-lg px-2 py-1 disabled:bg-gray-50 disabled:text-gray-400"
                                                >
                                                    <option value="">Bills as an individual</option>
                                                    {suppliers.map((s) => (
                                                        <option key={s.id} value={s.id}>{s.name}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            {role.supplier && (
                                                <p className="text-xs text-gray-400 pl-[4.5rem]">
                                                    Company/ABN/invoice details come from{' '}
                                                    <a
                                                        href={route('manage.suppliers.edit', role.supplier.id)}
                                                        target="_blank"
                                                        rel="noopener"
                                                        className="text-green-600 underline"
                                                    >
                                                        {role.supplier.name}
                                                    </a>.
                                                </p>
                                            )}
                                            {canEditRate(role) && (
                                                <p className="text-xs pl-[4.5rem]">
                                                    <a
                                                        href={route('manage.suppliers.create')}
                                                        target="_blank"
                                                        rel="noopener"
                                                        className="text-green-600"
                                                    >
                                                        + New supplier
                                                    </a>
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Pending invitations */}
                {pendingInvitations.length > 0 && (
                    <div className="bg-white rounded-lg shadow">
                        <div className="px-4 py-3 border-b border-gray-100">
                            <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Pending Invitations</h2>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {pendingInvitations.map((invitation) => (
                                <div key={invitation.id} className="px-4 py-3">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm text-gray-900">{invitation.email}</p>
                                            <p className="text-xs text-gray-500">Invited as {ROLE_LABELS[invitation.role]}</p>
                                        </div>
                                        <button
                                            onClick={() => cancelInvitation(invitation.id)}
                                            className="text-xs text-red-500"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                    <div className="flex items-center gap-2 mt-2">
                                        <p className="flex-1 min-w-0 text-xs text-gray-500 bg-gray-50 rounded-lg px-2 py-1.5 truncate">
                                            {route('invitations.accept', invitation.token)}
                                        </p>
                                        <button
                                            onClick={() => copyInviteLink(invitation)}
                                            className="text-xs text-green-600 whitespace-nowrap flex-shrink-0"
                                        >
                                            {copiedId === invitation.id ? 'Copied!' : 'Copy link'}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Add / invite forms */}
                {mode === null && (
                    <div className="flex gap-2">
                        <button
                            onClick={() => setMode('add')}
                            className="flex-1 py-3 bg-green-600 text-white rounded-lg text-sm font-medium"
                        >
                            + Add Team Member
                        </button>
                        <button
                            onClick={() => setMode('invite')}
                            className="flex-1 py-3 border border-green-600 text-green-600 rounded-lg text-sm font-medium"
                        >
                            + Invite by Email
                        </button>
                    </div>
                )}

                {mode === 'add' && (
                    <form onSubmit={addMember} className="bg-white rounded-lg shadow p-4 space-y-3">
                        <p className="text-xs text-gray-500">
                            No email needed - useful for someone who won't use the app themselves but whose
                            time still needs to be logged. You can send them an invite later, any time.
                        </p>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input
                                type="text"
                                value={addForm.data.name}
                                onChange={(e) => addForm.setData('name', e.target.value)}
                                className="w-full border-gray-300 rounded-lg p-3 text-sm"
                                autoFocus
                            />
                            {addForm.errors.name && <p className="mt-1 text-sm text-red-600">{addForm.errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Email <span className="text-gray-400">optional</span>
                            </label>
                            <input
                                type="email"
                                value={addForm.data.email}
                                onChange={(e) => addForm.setData('email', e.target.value)}
                                className="w-full border-gray-300 rounded-lg p-3 text-sm"
                                placeholder="email@example.com"
                            />
                            {addForm.errors.email && <p className="mt-1 text-sm text-red-600">{addForm.errors.email}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select
                                value={addForm.data.role}
                                onChange={(e) => addForm.setData('role', e.target.value)}
                                className="w-full border-gray-300 rounded-lg p-3 text-sm"
                            >
                                {availableRoles.map((role) => (
                                    <option key={role} value={role}>{ROLE_LABELS[role]}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Default rate ($/hr) <span className="text-gray-400">optional</span>
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={addForm.data.hourly_rate}
                                onChange={(e) => addForm.setData('hourly_rate', e.target.value)}
                                className="w-full border-gray-300 rounded-lg p-3 text-sm"
                            />
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="submit"
                                disabled={addForm.processing}
                                className="flex-1 py-3 bg-green-600 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                            >
                                Add Team Member
                            </button>
                            <button
                                type="button"
                                onClick={() => setMode(null)}
                                className="flex-1 py-3 border border-gray-300 text-gray-700 rounded-lg text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                )}

                {mode === 'invite' && (
                    <form onSubmit={invite} className="bg-white rounded-lg shadow p-4 space-y-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full border-gray-300 rounded-lg p-3 text-sm"
                                placeholder="email@example.com"
                                autoFocus
                            />
                            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Message</label>
                            <textarea
                                value={data.message}
                                onChange={changeMessage}
                                rows={3}
                                maxLength={2000}
                                className="w-full border-gray-300 rounded-lg p-3 text-sm"
                            />
                            {errors.message && <p className="mt-1 text-sm text-red-600">{errors.message}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select
                                value={data.role}
                                onChange={changeRole}
                                className="w-full border-gray-300 rounded-lg p-3 text-sm"
                            >
                                {availableRoles.map((role) => (
                                    <option key={role} value={role}>{ROLE_LABELS[role]}</option>
                                ))}
                            </select>
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 py-3 bg-green-600 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                            >
                                Send Invite
                            </button>
                            <button
                                type="button"
                                onClick={() => setMode(null)}
                                className="flex-1 py-3 border border-gray-300 text-gray-700 rounded-lg text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
