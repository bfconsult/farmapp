import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';



export default function Show({ property }) {

    const destroy = () => {
        if (confirm('Are you sure you want to delete this property?')) {
            router.delete(route('properties.destroy', property.id));
        }
    };
    return (
        <AuthenticatedLayout>
            <Head title={property.name} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex justify-between items-center mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">
                            {property.name}
                        </h1>
                        <div className="flex gap-4">
                            <Link
                                href={route('properties.edit', property.id)}
                                className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                            >
                                Edit
                            </Link>
                            <button
                            onClick={destroy}
                            className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                            >
                            Delete
                            </button>
                            <Link
                                href={route('properties.index')}
                                className="px-4 py-2 text-gray-700 hover:text-gray-900"
                            >
                                ← Back
                            </Link>
   
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 className="text-lg font-medium text-gray-900 mb-4">
                            Details
                        </h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-sm text-gray-500">Name</p>
                                <p className="text-gray-900">{property.name}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Address</p>
                                <p className="text-gray-900">{property.address}</p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow p-6">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-lg font-medium text-gray-900">
                                Jobs
                            </h2>
                        </div>
                        {property.farm_jobs && property.farm_jobs.length === 0 ? (
                            <p className="text-gray-500 text-sm">No jobs yet.</p>
                        ) : (
                            <div className="grid gap-4">
                                {property.farm_jobs && property.farm_jobs.map((job) => (
                                    <div key={job.id} className="border rounded p-4">
                                        <p className="font-medium">{job.name}</p>
                                        <p className="text-sm text-gray-500">{job.description}</p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}