import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PropertyBoundaryPicker from '@/Components/PropertyBoundaryPicker';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Edit({ property }) {
    const { flash } = usePage().props;
    const isNewProperty = !!flash?.isNewProperty;
    const title = isNewProperty ? 'Create New Property' : 'Edit Property';

    const { data, setData, patch, processing, errors } = useForm({
        name: property.name,
        address: property.address,
        billing_company_name: property.billing_company_name ?? '',
        billing_abn: property.billing_abn ?? '',
        billing_address: property.billing_address ?? '',
        billing_phone: property.billing_phone ?? '',
        billing_contact_name: property.billing_contact_name ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('properties.update', property.id));
    };

    // The boundary picker's "refine it on the Boundary page" link navigates
    // straight to shape.edit - without this, whatever name/address the user
    // had typed but not yet hit "Update Property" for was silently lost.
    const goToBoundaryEditor = () => {
        patch(route('properties.update', property.id), {
            preserveScroll: true,
            onSuccess: () => router.visit(route('shape.edit', property.id)),
        });
    };

    const discardNewProperty = () => {
        if (confirm("Discard this new property? It hasn't been saved with any real details yet.")) {
            router.delete(route('properties.destroy', property.id));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={isNewProperty ? title : `Edit ${property.name}`} />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-semibold text-gray-900 mb-6">
                        {title}
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

                            <div className="mb-6 border-t border-gray-200 pt-6">
                                <h2 className="text-sm font-medium text-gray-700 mb-1">Billing Details</h2>
                                <p className="text-xs text-gray-500 mb-4">
                                    Shown at the top of Excel/PDF timesheet exports. All optional.
                                </p>

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

                            <PropertyBoundaryPicker property={property} onRefineClick={goToBoundaryEditor} />

                            <div className="flex justify-end gap-4">
                                {isNewProperty ? (
                                    <button
                                        type="button"
                                        onClick={discardNewProperty}
                                        className="px-4 py-2 text-gray-700 hover:text-gray-900"
                                    >
                                        Cancel
                                    </button>
                                ) : (
                                    <Link
                                        href={route('properties.show', property.id)}
                                        className="px-4 py-2 text-gray-700 hover:text-gray-900"
                                    >
                                        Cancel
                                    </Link>
                                )}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                >
                                    Update Property
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}