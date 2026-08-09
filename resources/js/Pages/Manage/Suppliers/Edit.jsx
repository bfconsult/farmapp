import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, router, useForm } from '@inertiajs/react';

export default function Edit({ supplier }) {
    const isNew = !supplier;

    const { data, setData, post, put, processing, errors } = useForm({
        name: supplier?.name ?? '',
        description: supplier?.description ?? '',
        street_address: supplier?.street_address ?? '',
        phone: supplier?.phone ?? '',
        email: supplier?.email ?? '',
        billing_company_name: supplier?.billing_company_name ?? '',
        billing_abn: supplier?.billing_abn ?? '',
        billing_address: supplier?.billing_address ?? '',
        billing_phone: supplier?.billing_phone ?? '',
        billing_contact_name: supplier?.billing_contact_name ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isNew) {
            post(route('manage.suppliers.store'));
        } else {
            put(route('manage.suppliers.update', supplier.id));
        }
    };

    const destroy = () => {
        if (confirm(`Delete "${supplier.name}"?`)) {
            router.delete(route('manage.suppliers.destroy', supplier.id));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={isNew ? 'Add Supplier' : `Edit ${supplier.name}`} />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">
                            {isNew ? 'Add Supplier' : 'Edit Supplier'}
                        </h1>
                        <BackLink href={route('manage.suppliers.index')}>Suppliers</BackLink>
                    </div>

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

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Description <span className="text-gray-400">optional</span>
                                </label>
                                <textarea
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={2}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.description && (
                                    <p className="mt-1 text-sm text-red-600">{errors.description}</p>
                                )}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Street Address
                                </label>
                                <input
                                    type="text"
                                    value={data.street_address}
                                    onChange={(e) => setData('street_address', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.street_address && (
                                    <p className="mt-1 text-sm text-red-600">{errors.street_address}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Phone
                                    </label>
                                    <input
                                        type="text"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    />
                                    {errors.phone && (
                                        <p className="mt-1 text-sm text-red-600">{errors.phone}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Email
                                    </label>
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    />
                                    {errors.email && (
                                        <p className="mt-1 text-sm text-red-600">{errors.email}</p>
                                    )}
                                </div>
                            </div>

                            <div className="mb-6 border-t border-gray-200 pt-6">
                                <h2 className="text-sm font-medium text-gray-700 mb-4">Billing Details</h2>

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

                                <div>
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
                            </div>

                            <div className="flex items-center justify-between">
                                {!isNew ? (
                                    <button
                                        type="button"
                                        onClick={destroy}
                                        className="text-sm text-red-600 hover:text-red-800"
                                    >
                                        Delete
                                    </button>
                                ) : <span />}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                >
                                    {isNew ? 'Add Supplier' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
