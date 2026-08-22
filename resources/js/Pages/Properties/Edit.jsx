import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PropertyBoundaryPicker from '@/Components/PropertyBoundaryPicker';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Edit({ property, isNewProperty }) {
    const title = isNewProperty ? 'Create New Property' : 'Edit Property';

    const { data, setData, patch, transform, processing, errors } = useForm({
        name: property.name,
        address: property.address,
        email: property.email ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        transform((formData) => formData);
        patch(route('properties.update', property.id));
    };

    // If the rest of the form is already fillable-out (required fields
    // non-blank), save the boundary and the form together in one request
    // instead of making the user separately hit Update Property afterwards.
    // Otherwise just save the boundary on its own, same as before - the
    // form isn't ready to submit yet.
    const saveBoundary = (coordinates, onFinish) => {
        const readyToSubmit = data.name.trim() !== '' && data.address.trim() !== '';

        if (readyToSubmit) {
            // No preserveScroll here - this navigates away to a different
            // page (Map, or the property page), so the scroll position
            // should reset to top same as a normal submit. Keeping it would
            // leave the browser scrolled to wherever the boundary picker
            // sat on this (long) form, pushing the new page's flash banner
            // off the top of the screen.
            transform((formData) => ({ ...formData, coordinates }));
            patch(route('properties.update', property.id), {
                onFinish,
            });
        } else {
            router.put(route('shape.update', property.id), { coordinates }, {
                preserveScroll: true,
                preserveState: true,
                onFinish,
            });
        }
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

                            {!isNewProperty && (
                                <div className="mb-6">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Email
                                    </label>
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                        placeholder="office@yourfarm.com"
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Used as the reply-to address when contacting suppliers - required before you can invite one to a job.
                                    </p>
                                    {errors.email && (
                                        <p className="mt-1 text-sm text-red-600">{errors.email}</p>
                                    )}
                                </div>
                            )}

                            <PropertyBoundaryPicker property={property} onSave={saveBoundary} />

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
                                    {isNewProperty ? 'Create Property' : 'Update Property'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}