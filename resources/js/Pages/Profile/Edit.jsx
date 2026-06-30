import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status, currentRole }) {
    const isAdmin = currentRole === 'admin';
    const isAdminOrManager = currentRole === 'admin' || currentRole === 'manager';
    const logout = () => {
        router.post(route('logout'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Profile" />

            <div className="max-w-lg mx-auto space-y-4">
                <h1 className="text-xl font-semibold text-gray-900 mb-6">Profile</h1>

                {/* Properties section */}
                <div className="bg-white rounded-lg shadow">
                    <div className="px-4 py-3 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Properties</h2>
                    </div>
                    <div className="divide-y divide-gray-100">
                        <Link
                            href={route('properties.index')}
                            className="flex items-center justify-between px-4 py-4 hover:bg-gray-50"
                        >
                            <span className="text-gray-900">Manage Properties</span>
                            <span className="text-gray-400">›</span>
                        </Link>
                        {isAdmin && (
                            <Link
                                href={route('properties.create')}
                                className="flex items-center justify-between px-4 py-4 hover:bg-gray-50"
                            >
                                <span className="text-gray-900">Add Property</span>
                                <span className="text-gray-400">›</span>
                            </Link>
                        )}
                        {isAdminOrManager && (
                            <Link
                                href={route('invitations.index')}
                                className="flex items-center justify-between px-4 py-4 hover:bg-gray-50"
                            >
                                <span className="text-gray-900">Team</span>
                                <span className="text-gray-400">›</span>
                            </Link>
                        )}
                        {isAdminOrManager && (
                            <Link
                                href={route('settings.index')}
                                className="flex items-center justify-between px-4 py-4 hover:bg-gray-50"
                            >
                                <span className="text-gray-900">Custom settings</span>
                                <span className="text-gray-400">›</span>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Account section */}
                <div className="bg-white rounded-lg shadow">
                    <div className="px-4 py-3 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Account</h2>
                    </div>
                    <div className="divide-y divide-gray-100">
                        <div className="px-4 py-4">
                            <UpdateProfileInformationForm
                                mustVerifyEmail={mustVerifyEmail}
                                status={status}
                            />
                        </div>
                        <div className="px-4 py-4">
                            <UpdatePasswordForm />
                        </div>
                    </div>
                </div>

                {/* Danger zone */}
                <div className="bg-white rounded-lg shadow">
                    <div className="px-4 py-3 border-b border-gray-100">
                        <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide">Danger Zone</h2>
                    </div>
                    <div className="divide-y divide-gray-100">
                        <div className="px-4 py-4">
                            <DeleteUserForm />
                        </div>
                        <button
                            onClick={logout}
                            className="w-full text-left px-4 py-4 text-red-600 hover:bg-gray-50"
                        >
                            Log Out
                        </button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}