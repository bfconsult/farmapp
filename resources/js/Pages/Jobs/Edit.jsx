import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ job, priorities, jobTypes, jobStatuses, properties }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: job.name,
        description: job.description ?? '',
        estimated_hours: job.estimated_hours ?? '',
        budget: job.budget ?? '',
        priority_id: job.priority_id ?? '',
        job_type_id: job.job_type_id ?? '',
        job_status_id: job.job_status_id ?? '',
        property_id: job.property_id,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('jobs.update', job.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${job.name}`} />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-semibold text-gray-900 mb-6">
                        Edit Job
                    </h1>

                    <div className="bg-white rounded-lg shadow p-6">
                        <form onSubmit={submit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Estimated Hours</label>
                                    <input
                                        type="number"
                                        step="0.5"
                                        value={data.estimated_hours}
                                        onChange={(e) => setData('estimated_hours', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    />
                                    {errors.estimated_hours && <p className="mt-1 text-sm text-red-600">{errors.estimated_hours}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Budget ($)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={data.budget}
                                        onChange={(e) => setData('budget', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    />
                                    {errors.budget && <p className="mt-1 text-sm text-red-600">{errors.budget}</p>}
                                </div>
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Property</label>
                                <select
                                    value={data.property_id}
                                    onChange={(e) => setData('property_id', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                >
                                    <option value="">Select a property</option>
                                    {properties.map((property) => (
                                        <option key={property.id} value={property.id}>{property.name}</option>
                                    ))}
                                </select>
                                {errors.property_id && <p className="mt-1 text-sm text-red-600">{errors.property_id}</p>}
                            </div>

                            <div className="grid grid-cols-3 gap-4 mb-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                    <select
                                        value={data.priority_id}
                                        onChange={(e) => setData('priority_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    >
                                        <option value="">Select</option>
                                        {priorities.map((priority) => (
                                            <option key={priority.id} value={priority.id}>{priority.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                    <select
                                        value={data.job_type_id}
                                        onChange={(e) => setData('job_type_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    >
                                        <option value="">Select</option>
                                        {jobTypes.map((type) => (
                                            <option key={type.id} value={type.id}>{type.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select
                                        value={data.job_status_id}
                                        onChange={(e) => setData('job_status_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    >
                                        <option value="">Select</option>
                                        {jobStatuses.map((status) => (
                                            <option key={status.id} value={status.id}>{status.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="flex justify-end gap-4">
                                <Link
                                    href={route('jobs.show', job.id)}
                                    className="px-4 py-2 text-gray-700 hover:text-gray-900"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                >
                                    Update Job
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}