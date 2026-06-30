import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        address: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('properties.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Add Property" />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-semibold text-gray-900 mb-6">
                        Add Property
                    </h1>

                    <div className="bg-white rounded-lg shadow p-6">
                        <form onSubmit={submit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Name
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                )}
                            </div>

                            <div className="mb-6">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Address
                                </label>
                                <input
                                    type="text"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.address && (
                                    <p className="mt-1 text-sm text-red-600">{errors.address}</p>
                                )}
                            </div>

                            <div className="flex justify-end gap-4">
                                
                                    <a href={route('properties.index')}
                                    className="px-4 py-2 text-gray-700 hover:text-gray-900"
                                >
                                    Cancel
                                </a>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                >
                                    Save Property
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}