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

                <Link
                    href={route('manage.suppliers.create')}
                    className="block w-full py-2 text-center text-sm text-green-600 border border-dashed border-green-300 rounded-lg"
                >
                    + Add Supplier
                </Link>

                {suppliers.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No suppliers set up yet.
                    </div>
                ) : (
                    <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                        {suppliers.map((supplier) => (
                            <Link
                                key={supplier.id}
                                href={route('manage.suppliers.edit', supplier.id)}
                                className="flex items-center justify-between gap-2 px-4 py-3 hover:bg-gray-50"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm text-gray-900 truncate">{supplier.name}</p>
                                    {(supplier.phone || supplier.email) && (
                                        <p className="text-xs text-gray-500 truncate">
                                            {[supplier.phone, supplier.email].filter(Boolean).join(' · ')}
                                        </p>
                                    )}
                                </div>
                                <span className="text-gray-400 flex-shrink-0">›</span>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
