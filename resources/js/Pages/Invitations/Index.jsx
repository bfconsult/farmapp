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

export default function Index({ property, roles, pendingInvitations, currentUserRole }) {
    const { auth } = usePage().props;
    const [showInvite, setShowInvite] = useState(false);
    const [copiedId, setCopiedId] = useState(null);
    const [messageEdited, setMessageEdited] = useState(false);

    const defaultMessage = (role) =>
        `${auth.user.name} has invited you to join ${property.name} on FieldWerkz as a ${ROLE_LABELS[role]}.`;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'worker',
        message: defaultMessage('worker'),
    });

    const adminCount = roles.filter((r) => r.type === 'admin').length;

    const canEditRate = (role) =>
        currentUserRole === 'admin' ||
        (currentUserRole === 'manager' && (role.type === 'worker' || role.user.id === auth.user.id));

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
                setShowInvite(false);
            },
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
                        {roles.map((role) => (
                            <div key={role.id} className="px-4 py-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-gray-900">{role.user.name}</p>
                                        <p className="text-xs text-gray-500">{role.user.email}</p>
                                    </div>
                                    <div className="flex items-center gap-2">
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
                                        {(currentUserRole === 'admin' || (currentUserRole === 'manager' && role.type === 'worker')) &&
                                            !(role.type === 'admin' && adminCount <= 1) && (
                                            <button
                                                onClick={() => removeRole(role.id)}
                                                className="text-xs text-red-500"
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>
                                </div>
                                {(currentUserRole === 'admin' || currentUserRole === 'manager') && (
                                    <div className="flex items-center gap-2 mt-2">
                                        <label className="text-xs text-gray-500">Default rate ($/hr)</label>
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
                                )}
                            </div>
                        ))}
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

                {/* Invite form */}
                {!showInvite ? (
                    <button
                        onClick={() => setShowInvite(true)}
                        className="w-full py-3 bg-green-600 text-white rounded-lg text-sm font-medium"
                    >
                        + Invite Someone
                    </button>
                ) : (
                    <form onSubmit={invite} className="bg-white rounded-lg shadow p-4 space-y-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full border-gray-300 rounded-lg p-3 text-sm"
                                placeholder="email@example.com"
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
                                onClick={() => setShowInvite(false)}
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