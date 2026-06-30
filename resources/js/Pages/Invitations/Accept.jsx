import { Head, useForm } from '@inertiajs/react';

export default function Accept({ invitation }) {
    const { post, processing } = useForm();

    const accept = (e) => {
        e.preventDefault();
        post(route('invitations.process', invitation.token));
    };

    return (
        <div className="min-h-screen bg-gray-100 flex items-center justify-center px-4">
            <Head title="Join Property" />

            <div className="max-w-sm w-full bg-white rounded-lg shadow p-6 text-center">
                <p className="text-2xl mb-2">🌱</p>
                <h1 className="text-lg font-semibold text-gray-900 mb-2">
                    You've been invited!
                </h1>
                <p className="text-sm text-gray-600 mb-6">
                    Join <strong>{invitation.property.name}</strong> as a{' '}
                    <strong className="capitalize">{invitation.role}</strong>
                </p>

                <form onSubmit={accept}>
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full py-3 bg-green-600 text-white rounded-lg font-medium disabled:opacity-50"
                    >
                        Accept Invitation
                    </button>
                </form>
            </div>
        </div>
    );
}