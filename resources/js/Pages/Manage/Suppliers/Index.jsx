import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, Link } from '@inertiajs/react';

export default function Index({ suppliers }) {
    return (
        <AuthenticatedLayout title="Suppliers">
            <Head title="Suppliers" />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <BackLink href={route('manage.index')}>Manage</BackLink>
                </div>

                <h1 className="text-lg font-semibold text-gray-900">Suppliers</h1>

                {suppliers.length === 0 ? (
                    <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                        <h2 className="text-base font-semibold text-gray-900 mb-1">Add your first supplier</h2>
                        <p className="text-sm text-gray-600 mb-4">
                            Suppliers hold billing and contact details for the people and
                            businesses you buy from, or that a worker bills through.
                        </p>
                        <Link
                            href={route('manage.suppliers.create')}
                            className="inline-block px-6 py-3 bg-green-600 text-white rounded-lg font-medium"
                        >
                            + Add Supplier
                        </Link>
                    </div>
                ) : (
                    <>
                        <Link
                            href={route('manage.suppliers.create')}
                            className="block w-full py-2 text-center text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
                        >
                            + Add Supplier
                        </Link>
                        <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                            {suppliers.map((supplier) => (
                                <div key={supplier.id} className="flex items-center justify-between gap-2 px-4 py-3">
                                    <div className="min-w-0">
                                        <p className="text-sm text-gray-900 truncate">{supplier.name}</p>
                                        {(supplier.phone || supplier.email) && (
                                            <p className="text-xs text-gray-500 truncate">
                                                {[supplier.phone, supplier.email].filter(Boolean).join(' · ')}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-3 flex-shrink-0 text-sm">
                                        <Link href={route('manage.suppliers.show', supplier.id)} className="text-green-600 font-medium">
                                            View
                                        </Link>
                                        <Link href={route('manage.suppliers.edit', supplier.id)} className="text-green-600 font-medium">
                                            Edit
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
