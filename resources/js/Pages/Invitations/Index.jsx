import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
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

export default function Index({ property, roles, pendingInvitations, currentUserRole }) {
    const [showInvite, setShowInvite] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'worker',
    });

    const availableRoles = currentUserRole === 'admin'
        ? ['manager', 'worker', 'approver']
        : ['worker'];

    const invite = (e) => {
        e.preventDefault();
        post(route('invitations.store'), {
            onSuccess: () => {
                reset();
                setShowInvite(false);
            },
        });
    };

    const removeRole = (roleId) => {
        if (confirm('Remove this person from the property?')) {
            router.delete(route('invitations.destroy-role', roleId));
        }
    };

    const cancelInvitation = (invitationId) => {
        if (confirm('Cancel this invitation?')) {
            router.delete(route('invitations.destroy-invitation', invitationId));
        }
    };

    return (
        <AuthenticatedLayout title="Team">
            <Head title="Team" />

            <div className="max-w-lg mx-auto mt-2 space-y-4">
                <p className="text-sm text-gray-500">{property.name}</p>

                {/* Current team */}
                <div className="bg-white rounded-lg shadow">
                    <div className="px-4 py-3 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Team Members</h2>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {roles.map((role) => (
                            <div key={role.id} className="flex items-center justify-between px-4 py-3">
                                <div>
                                    <p className="text-sm text-gray-900">{role.user.name}</p>
                                    <p className="text-xs text-gray-500">{role.user.email}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${ROLE_COLORS[role.type]}`}>
                                        {ROLE_LABELS[role.type]}
                                    </span>
                                    {(currentUserRole === 'admin' || (currentUserRole === 'manager' && role.type === 'worker')) && (
                                        <button
                                            onClick={() => removeRole(role.id)}
                                            className="text-xs text-red-500"
                                        >
                                            Remove
                                        </button>
                                    )}
                                </div>
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
                                <div key={invitation.id} className="flex items-center justify-between px-4 py-3">
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
                            <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select
                                value={data.role}
                                onChange={(e) => setData('role', e.target.value)}
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