import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, useForm } from '@inertiajs/react';

export default function Billing({ property }) {
    const { data, setData, put, processing, errors } = useForm({
        billing_company_name: property.billing_company_name ?? '',
        billing_abn: property.billing_abn ?? '',
        billing_address: property.billing_address ?? '',
        billing_phone: property.billing_phone ?? '',
        billing_contact_name: property.billing_contact_name ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('properties.billing.update', property.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`${property.name} — Billing Details`} />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">Billing Details</h1>
                        <BackLink href={route('properties.show', property.id)}>Back</BackLink>
                    </div>

                    <div className="bg-white rounded-lg shadow p-6">
                        <p className="text-sm text-gray-500 mb-6">
                            Shown at the top of Excel/PDF timesheet exports. All optional.
                        </p>

                        <form onSubmit={submit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Company/Business Name
                                </label>
                                <input
                                    type="text"
                                    value={data.billing_company_name}
                                    onChange={(e) => setData('billing_company_name', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.billing_company_name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.billing_company_name}</p>
                                )}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    ABN
                                </label>
                                <input
                                    type="text"
                                    value={data.billing_abn}
                                    onChange={(e) => setData('billing_abn', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.billing_abn && (
                                    <p className="mt-1 text-sm text-red-600">{errors.billing_abn}</p>
                                )}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Billing Address
                                </label>
                                <input
                                    type="text"
                                    value={data.billing_address}
                                    onChange={(e) => setData('billing_address', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.billing_address && (
                                    <p className="mt-1 text-sm text-red-600">{errors.billing_address}</p>
                                )}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Phone
                                </label>
                                <input
                                    type="text"
                                    value={data.billing_phone}
                                    onChange={(e) => setData('billing_phone', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.billing_phone && (
                                    <p className="mt-1 text-sm text-red-600">{errors.billing_phone}</p>
                                )}
                            </div>

                            <div className="mb-6">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Billing Contact Name
                                </label>
                                <input
                                    type="text"
                                    value={data.billing_contact_name}
                                    onChange={(e) => setData('billing_contact_name', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.billing_contact_name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.billing_contact_name}</p>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                >
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
