import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ properties }) {
    return (
        <AuthenticatedLayout>
            <Head title="Properties" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex justify-between items-center mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">
                            Properties
                        </h1>
                        <Link
                            href={route('properties.create')}
                            className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                        >
                            Add Property
                        </Link>
                    </div>

                    {properties.length === 0 ? (
                        <div className="bg-white rounded-lg shadow p-6 text-center text-gray-500">
                            No properties yet. Add your first property to get started.
                        </div>
                    ) : (
                        <div className="grid gap-4">
                            {properties.map((property) => (
                                <div
                                    key={property.id}
                                    className="bg-white rounded-lg shadow p-6 flex justify-between items-center"
                                >
                                    <div>
                                        <h2 className="text-lg font-medium text-gray-900">
                                            {property.name}
                                        </h2>
                                        <p className="text-sm text-gray-500">
                                            {property.address}
                                        </p>
                                    </div>
                                    <Link
                                        href={route('properties.show', property.id)}
                                        className="text-green-600 hover:text-green-800"
                                    >
                                        View →
                                    </Link>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}