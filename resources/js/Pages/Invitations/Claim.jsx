import { Head, useForm } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import TextInput from '@/Components/TextInput';

export default function Claim({ invitation }) {
    const { data, setData, post, processing, errors } = useForm({
        name: invitation.user.name ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('invitations.claim', invitation.token));
    };

    return (
        <div className="min-h-screen bg-gray-100 flex items-center justify-center px-4">
            <Head title="Set Up Your Account" />

            <div className="max-w-sm w-full bg-white rounded-lg shadow p-6">
                <div className="text-center">
                    <ApplicationLogo className="h-10 w-10 mx-auto mb-2" />
                    <h1 className="text-lg font-semibold text-gray-900 mb-2">
                        Set up your account
                    </h1>
                    <p className="text-sm text-gray-600 mb-6">
                        You've already got time logged on <strong>{invitation.property.name}</strong> as a{' '}
                        <strong className="capitalize">{invitation.role}</strong>. Set a password to
                        start signing in yourself.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4 text-left">
                    <div>
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput id="email" value={invitation.email} className="mt-1 block w-full bg-gray-50" disabled />
                    </div>

                    <div>
                        <InputLabel htmlFor="name" value="Name" />
                        <TextInput
                            id="name"
                            value={data.name}
                            className="mt-1 block w-full"
                            autoComplete="name"
                            isFocused
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="password" value="Password" />
                        <PasswordInput
                            id="password"
                            value={data.password}
                            className="mt-1"
                            autoComplete="new-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirm Password" />
                        <PasswordInput
                            id="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-1"
                            autoComplete="new-password"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />
                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full py-3 bg-green-600 text-white rounded-lg font-medium disabled:opacity-50"
                    >
                        Set Password & Sign In
                    </button>
                </form>
            </div>
        </div>
    );
}
